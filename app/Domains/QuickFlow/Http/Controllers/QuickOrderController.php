<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\IronGrade;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Models\SalesOrder;
use App\Services\Orders\ProductionOrderFactory;
use App\Services\Planning\HeatPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** A customer order on one screen: no contract review, no separate production order step. Each line is released for planning straight away. */
class QuickOrderController extends Controller
{
    public function create(HeatPlanner $planner): View
    {
        $products = Product::where('active', true)->orderBy('code')->get()->map(function (Product $product) use ($planner) {
            $revision = $this->revisionFor($product);

            return [
                'product' => $product,
                'hasGrade' => $revision && $planner->gradeFor($revision),
                'hasWeight' => $revision && $planner->metalPerProduct($revision),
            ];
        });

        return view('quickflow::orders.create', [
            'customers' => Customer::active()->orderBy('code')->get(), 'products' => $products,
            'grades' => IronGrade::orderBy('family')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, HeatPlanner $planner, ProductionOrderFactory $factory): RedirectResponse
    {
        $request->merge(['lines' => array_values(array_filter((array) $request->input('lines', []), fn ($l) => ! empty($l['product_id'])))]);
        $data = $request->validate([
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at'), 'required_without:new_customer'],
            'new_customer' => ['nullable', 'string', 'max:150'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'uuid', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:999999'],
            'lines.*.due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'lines.*.iron_grade_id' => ['nullable', 'uuid', 'exists:iron_grades,id'],
            'lines.*.casting_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $order = DB::transaction(function () use ($data, $planner, $factory) {
            $customer = filled($data['customer_id'] ?? null) ? Customer::findOrFail($data['customer_id']) : $this->newCustomer($data['new_customer']);
            $order = SalesOrder::create([
                'order_number' => SalesOrder::nextNumber(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'IN_PRODUCTION',
                'requested_delivery_date' => $data['due_date'] ?? null, 'customer_reference' => $data['customer_reference'] ?? null,
            ]);
            foreach ($data['lines'] as $i => $row) {
                $revision = $this->revisionFor(Product::findOrFail($row['product_id']), create: true);
                $this->completeFacts($revision, $row, $i, $planner);
                $line = $order->lines()->create([
                    'product_id' => $revision->product_id, 'product_revision_id' => $revision->id, 'line_number' => $i + 1, 'ordered_qty' => $row['qty'],
                    'uom' => 'piece', 'requested_delivery_date' => $row['due_date'] ?? $data['due_date'] ?? null,
                ]);
                $production = $factory->forSalesLine($line, quick: true);
                $production->update(['status' => 'RELEASED', 'released_qty' => $production->planned_qty]);
            }

            return $order;
        });

        return redirect()->route('quick.heat.index')->with('success', "Order {$order->order_number} entered and ready for planning.");
    }

    private function newCustomer(string $name): Customer
    {
        $code = Str::upper(Str::limit(Str::slug($name, ''), 8, ''));
        if (Customer::withTrashed()->where('code', $code)->exists()) {
            $code .= Str::upper(Str::random(3));
        }

        return Customer::create(['code' => $code, 'name' => $name]);
    }

    /** The newest approved revision of the product, else the newest one; with $create, revision A is made when there is none. */
    private function revisionFor(Product $product, bool $create = false): ?ProductRevision
    {
        $revisions = ProductRevision::where('product_id', $product->id)->orderByDesc('created_at')->get();
        $revision = $revisions->first(fn ($r) => in_array($r->status, ['APPROVED', 'ACTIVE'], true)) ?? $revisions->first();

        return $revision ?? ($create ? ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']) : null);
    }

    /** The plan needs an iron grade and a weight. Take them from the line when the product does not have them yet. */
    private function completeFacts(ProductRevision $revision, array $row, int $index, HeatPlanner $planner): void
    {
        if (! $planner->gradeFor($revision)) {
            $grade = filled($row['iron_grade_id'] ?? null) ? IronGrade::find($row['iron_grade_id']) : null;
            if (! $grade) {
                throw ValidationException::withMessages(["lines.$index.iron_grade_id" => 'Choose the iron grade for this product. It is remembered for next time.']);
            }
            ProductSpecification::create([
                'product_revision_id' => $revision->id, 'specification_code' => 'QUICK-'.$revision->id, 'title' => 'Iron grade (quick order)',
                'status' => 'APPROVED', 'material_grade' => $grade->code,
            ]);
        }
        if (! $planner->metalPerProduct($revision)) {
            if (! filled($row['casting_weight_kg'] ?? null)) {
                throw ValidationException::withMessages(["lines.$index.casting_weight_kg" => 'Give the casting weight in kg so the melt can be sized. It is remembered for next time.']);
            }
            $revision->update(['net_weight_kg' => $row['casting_weight_kg']]);
        }
    }
}
