<?php

namespace App\Http\Controllers;
use App\Models\StockMovement;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Database\QueryException;

class MaterialLotController extends Controller
{
    /** @return \Illuminate\Support\Collection<int, Supplier> Empty when the Suppliers module is switched off. */
    private function suppliersForLots(): \Illuminate\Support\Collection
    {
        return app(ModuleRegistry::class)->isEnabled('purchasing')
            ? Supplier::whereNotIn('approval_status', Supplier::BLOCKED)->orderedByName()->get()
            : collect();
    }

    public function create(Material $material): View
    {
        return view('material-lots.create', [
            'material' => $material,
            'lot' => new MaterialLot(),
            'suppliers' => $this->suppliersForLots(),
        ]);
    }

    public function store(Request $request, Material $material): RedirectResponse
    {
        $validated = $request->validate([
            'lot_number' => [
                'required', 'string', 'max:80',
                Rule::unique('material_lots', 'lot_number'),
            ],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'supplier_lot_number' => ['nullable', 'string', 'max:100'],
            'supplier_name_snapshot' => ['nullable', 'string', 'max:150'],
            'received_date' => ['nullable', 'date'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'status' => [
                'required',
                Rule::in(['QUARANTINE', 'ACCEPTED', 'REJECTED', 'CLOSED']),
            ],
            'received_qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'accepted_qty' => ['required', 'numeric', 'min:0', 'lte:received_qty', 'max:999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency_code' => ['required', 'string', 'size:3'],
            'heat_or_batch_reference' => ['nullable', 'string', 'max:100'],
            'certificate_reference' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if ($validated['status'] === 'ACCEPTED' && (float) $validated['accepted_qty'] <= 0) {
            return back()
                ->withInput()
                ->withErrors(['accepted_qty' => 'An accepted lot must have a positive accepted quantity.']);
        }

        if (filled($validated['supplier_id'] ?? null)) {
            $supplier = Supplier::findOrFail($validated['supplier_id']);
            if ($supplier->isBlocked()) {
                return back()->withInput()->withErrors(['supplier_id' => "{$supplier->name} is ".strtolower(Supplier::STATUSES[$supplier->approval_status]).' and cannot be received from.']);
            }
            $validated['supplier_name_snapshot'] = $supplier->name;
        }

        if ($validated['status'] === 'ACCEPTED' && app(ModuleRegistry::class)->isEnabled('purchasing')
            && $blockers = app(\App\Domains\Purchasing\Services\IncomingGate::class)->blockers(new MaterialLot($validated))) {
            return back()->withInput()->withErrors(['status' => implode(' ', $blockers).' Record the lot in quarantine and accept it from the lot page.']);
        }

        $validated['material_id'] = $material->id;

        $lot = $material->lots()->create($validated);

        return redirect()
            ->route('materials.show', $material)
            ->with('success', "Lot {$lot->lot_number} recorded. This does not post stock movements.");
    }
    public function postReceipt(Request $request, Material $material, MaterialLot $lot): RedirectResponse
{
    $validated = $request->validate([
        'confirmed_physical_stock' => ['required', 'accepted'],
        'recorded_by' => ['required', 'string', 'max:100'],
        'stock_location_id' => ['nullable', 'uuid', Rule::exists('stock_locations', 'id')->where('is_active', true)],
        'notes' => ['nullable', 'string', 'max:2000'],
    ]);

    if ($lot->material_id !== $material->id) {
        abort(404);
    }
    if (! $material->is_stock_tracked) {
            throw ValidationException::withMessages([
            'confirmed_physical_stock' => 'This material is not configured for stock tracking.',
        ]);
    }

try {
    $posted = DB::transaction(function () use ($lot, $material, $validated) {
        $lockedLot = MaterialLot::query()
            ->whereKey($lot->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedLot->status !== 'ACCEPTED') {
            throw ValidationException::withMessages([
                'confirmed_physical_stock' => 'Only accepted lots can be posted.',
            ]);
        }

        if ((float) $lockedLot->accepted_qty <= 0) {
            throw ValidationException::withMessages([
                'confirmed_physical_stock' => 'The accepted quantity must be positive.',
            ]);
        }

        if ($lockedLot->unit_cost === null
            || strtoupper((string) $lockedLot->currency_code) !== 'PKR') {
            throw ValidationException::withMessages([
                'confirmed_physical_stock' => 'A valid PKR unit cost is required.',
            ]);
        }

        $existing = StockMovement::query()
            ->where('receipt_lot_id', $lockedLot->id)
            ->exists();

        if ($existing) {
            return false;
        }


        $quantity = (float) $lockedLot->accepted_qty;
        $unitCost = (float) $lockedLot->unit_cost;
        $baseUom = trim((string) $material->base_uom);

        if ($baseUom === '') {
            throw ValidationException::withMessages([
                'confirmed_physical_stock' =>
                    'The material must have a base unit of measure before receipt posting.',
            ]);
        }

        app(StockLedger::class)->receive($lockedLot, $quantity, $unitCost, $baseUom, [
            'movement_number' => 'RCV-'.strtoupper(str_replace('-', '', $lockedLot->id)),
            'source_type' => 'MATERIAL_LOT_RECEIPT',
            'source_id' => $lockedLot->id,
            'receipt_lot_id' => $lockedLot->id,
            'to_location_id' => $validated['stock_location_id'] ?? null,
            'recorded_by' => $validated['recorded_by'],
            'reason' => $validated['notes'] ?? 'Accepted lot receipt',
        ]);

        return true;
    });
    } catch (QueryException $e) {
    $sqlState = $e->errorInfo[0] ?? null;
    $constraint = $e->errorInfo[2] ?? '';

    $isReceiptDuplicate = $sqlState === '23505'
        && (
            str_contains($constraint, 'stock_movements_receipt_lot_id_unique')
            || str_contains($constraint, 'stock_movements_movement_number_unique')
        );

    if (! $isReceiptDuplicate) {
        throw $e;
    }

    $alreadyPosted = StockMovement::query()
        ->where('receipt_lot_id', $lot->id)
        ->exists();

    if (! $alreadyPosted) {
        throw $e;
    }

    $posted = false;
}

    return redirect()
        ->route('materials.show', $material)
        ->with(
            $posted ? 'success' : 'info',
            $posted
                ? "Receipt posted for lot {$lot->lot_number}."
                : "Lot {$lot->lot_number} already has a posted receipt."
        );
}
}
