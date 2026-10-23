<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AttachmentController;
use App\Models\Customer;
use App\Models\IronGrade;
use App\Domains\Enquiry\Models\Enquiry;
use App\Models\Product;
use App\Support\AttachableEntities;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Guided entry for a customer request: who, what they are giving us, the questions that kind of request needs, then an enquiry. */
class RequestWizardController extends Controller
{
    public function create(Request $request)
    {
        return view('enquiry::enquiries.wizard', [
            'customers' => Customer::active()->orderedByName()->get(),
            'products' => Product::with('revisions:id,product_id,revision,status')->orderBy('code')->get(['id', 'code', 'name', 'nominal_weight_kg']),
            'grades' => IronGrade::listed(),
            'scenarios' => config('enquiry_scenarios'),
            'categories' => AttachableEntities::CATEGORIES,
            'preset' => $request->only(['customer', 'scenario']),
        ]);
    }

    public function store(Request $request)
    {
        $sc = config('enquiry_scenarios');
        $maxKb = config('foundry.attachments.max_kb');
        $exts = config('foundry.attachments.extensions');
        $src = $request->input('source_type');
        $needs = $sc[$src]['needs'] ?? [];
        $has = fn (string $n) => in_array($n, $needs, true);
        $filesGiven = collect($request->file('files', []))->filter()->isNotEmpty();

        $rules = [
            'customer_mode' => ['required', Rule::in(['EXISTING', 'NEW'])],
            'customer_id' => ['required_if:customer_mode,EXISTING', 'nullable', 'exists:customers,id'],
            'new_name' => ['required_if:customer_mode,NEW', 'nullable', 'string', 'max:200'],
            'new_contact' => ['nullable', 'string', 'max:120'], 'new_phone' => ['nullable', 'string', 'max:40'],
            'new_email' => ['nullable', 'email', 'max:160'], 'new_terms' => ['nullable', 'string', 'max:100'],
            'source_type' => ['required', Rule::in(array_keys($sc))],
            'description' => ['required', 'string', 'max:200'],
            'product_id' => [$has('product') ? 'required' : 'nullable', 'exists:products,id'],
            'drawing_ref' => ['nullable', 'string', 'max:100'], 'drawing_rev' => ['nullable', 'string', 'max:20'],
            'material_text' => ['nullable', 'string', 'max:200'], 'material_new' => ['required_if:material_text,__new__', 'nullable', 'string', 'max:200'], 'family' => ['nullable', Rule::in(['GREY', 'DUCTILE'])],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'change_note' => [$has('change') ? 'required' : 'nullable', 'string', 'max:3000'],
            'tool_note' => [$has('tool') ? 'required' : 'nullable', 'string', 'max:3000'],
            'concept_note' => [$has('concept') ? 'required' : 'nullable', 'string', 'max:3000'],
            'transfer_note' => [$has('transfer') ? 'required' : 'nullable', 'string', 'max:3000'],
            'sample_note' => [$has('sample') ? 'required' : 'nullable', 'string', 'max:3000'],
            'first_batch_qty' => ['nullable', 'integer', 'min:0'], 'annual_qty' => ['nullable', 'integer', 'min:0'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'due_on' => ['nullable', 'date'], 'customer_reference' => ['nullable', 'string', 'max:100'], 'contact_name' => ['nullable', 'string', 'max:120'],
            'heat_treatment' => ['nullable', 'boolean'], 'ndt' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.$maxKb, 'extensions:'.implode(',', $exts)],
            'file_category' => ['nullable', Rule::in(array_keys(AttachableEntities::CATEGORIES))],
        ];
        $d = $request->validate($rules, [
            'product_id.required' => 'Choose the part from the list of parts we already make.',
            'change_note.required' => 'Say what has changed.', 'tool_note.required' => 'Describe the tooling the customer will send.',
            'concept_note.required' => 'Write down what the part has to do.', 'transfer_note.required' => 'Say where the part is made today and why it is moving.',
            'sample_note.required' => 'Describe the sample: how many, and what condition.',
            'files.*.max' => 'Each file may be at most '.Uploads::megabytes($maxKb).'.', 'files.*.uploaded' => Uploads::failedMessage(), 'files.*.extensions' => 'That file type is not allowed. Allowed: '.implode(', ', $exts).'.',
        ]);

        // A part we have never seen needs something to work from: a drawing number or at least one file.
        if ($has('drawing') && $src !== 'CHANGE' && blank($d['drawing_ref'] ?? null) && ! $filesGiven) {
            throw ValidationException::withMessages(['drawing_ref' => 'Give the drawing number, or attach the drawing or model.']);
        }
        if ($d['customer_mode'] === 'NEW' && Customer::whereHas('party', fn ($q) => $q->whereRaw('lower(name) = ?', [Str::lower(trim($d['new_name']))]))->exists()) {
            throw ValidationException::withMessages(['new_name' => 'A customer with this name already exists. Choose it from the list instead.']);
        }

        $enquiry = DB::transaction(function () use ($d, $request, $sc, $src, $filesGiven) {
            if ($d['customer_mode'] === 'NEW') {
                $customer = Customer::create([
                    'code' => $this->newCustomerCode($d['new_name']), 'name' => trim($d['new_name']), 'contact_name' => $d['new_contact'] ?? null,
                    'phone' => $d['new_phone'] ?? null, 'email' => $d['new_email'] ?? null, 'payment_terms' => $d['new_terms'] ?? null, 'active' => true,
                ]);
            } else {
                $customer = Customer::findOrFail($d['customer_id']);
            }
            $product = ! empty($d['product_id']) ? Product::find($d['product_id']) : null;

            $notes = collect([
                'What changed' => $d['change_note'] ?? null, 'Tooling the customer supplies' => $d['tool_note'] ?? null,
                'What the part has to do' => $d['concept_note'] ?? null, 'Current supplier and reason for moving' => $d['transfer_note'] ?? null,
                'Sample' => $d['sample_note'] ?? null,
            ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

            $e = Enquiry::create([
                'customer_id' => $customer->id, 'title' => Str::limit($d['description'], 150, '').' ('.$sc[$src]['short'].')', 'source_type' => $src, 'status' => 'FEASIBILITY',
                'received_on' => now()->toDateString(), 'due_on' => $d['due_on'] ?? null, 'customer_reference' => $d['customer_reference'] ?? null,
                'contact_name' => ($d['contact_name'] ?? null) ?: $customer->contact_name, 'summary' => $notes ?: null,
                'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
            ]);
            $e->items()->create([
                'line_no' => 1, 'description' => $d['description'], 'product_id' => $product?->id,
                'drawing_ref' => $d['drawing_ref'] ?? null, 'drawing_rev' => $d['drawing_rev'] ?? null, 'material_text' => ($d['material_text'] ?? null) === '__new__' ? $d['material_new'] : ($d['material_text'] ?? null),
                'family' => $d['family'] ?? null, 'weight_kg' => ($d['weight_kg'] ?? null) ?: $product?->nominal_weight_kg,
                'first_batch_qty' => $d['first_batch_qty'] ?? null, 'annual_qty' => $d['annual_qty'] ?? null, 'target_price' => $d['target_price'] ?? null,
                'heat_treatment' => $request->boolean('heat_treatment'), 'ndt' => $request->boolean('ndt'),
                'machining_cost' => 0, 'other_cost' => 0, 'tooling_cost' => 0, 'feasibility_status' => 'PENDING',
                'notes' => $src === 'CUSTOMER_TOOL' ? 'Customer-owned tooling: '.($d['tool_note'] ?? '') : null,
            ]);
            if ($filesGiven) {
                $saved = 0;
                $err = AttachmentController::saveFiles(array_values(array_filter($request->file('files'))), 'enquiry', $e->id, $d['file_category'] ?? $sc[$src]['file_category'], null, $saved);
                if ($err) {
                    throw ValidationException::withMessages(['files' => $err]);
                }
            }

            return $e;
        });

        return redirect()->route('enquiries.show', $enquiry)->with('success', "Request {$enquiry->code} created for {$enquiry->customer->name}. The checklist on the Summary tab shows what usually comes next.");
    }

    private function newCustomerCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)), 0, 4)) ?: 'CUST';
        $code = $base;
        for ($n = 1; Customer::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
