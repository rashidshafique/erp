{{-- Tax and reporting details of a stock or sales item: inventory class, goods or service, tariff code, tax unit and country of origin.
     $item (a Material or Product, may be unsaved). --}}
@php $tariffLabel = \App\Support\TaxJurisdiction::tariffLabel(); @endphp
<div class="form-grid">
    <div class="field">
        <label for="inventory_class">Inventory class</label>
        <select id="inventory_class" name="inventory_class">
            @foreach (\App\Services\Stock\InventoryClass::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('inventory_class', $item->inventory_class ?: $item->defaultInventoryClass()->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="help">The line of the accounts its stock and cost are reported in. Goods bought to sell as they are are stock-in-trade.</div>
        @error('inventory_class')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="supply_type">Goods or service</label>
        <select id="supply_type" name="supply_type">
            @foreach (['GOODS' => 'Goods', 'SERVICE' => 'Service'] as $value => $label)
                <option value="{{ $value }}" @selected(old('supply_type', $item->supply_type ?: 'GOODS') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="help">Goods and services can be taxed by different authorities at different rates.</div>
        @error('supply_type')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="tariff_code">{{ $tariffLabel }}</label>
        <input id="tariff_code" name="tariff_code" maxlength="20" inputmode="decimal" value="{{ old('tariff_code', $item->tariff_code) }}" placeholder="{{ \App\Support\TaxJurisdiction::tariffExample() }}">
        <div class="help">The customs tariff heading printed on tax invoices.</div>
        @error('tariff_code')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="tax_uom">Unit on tax invoices</label>
        <input id="tax_uom" name="tax_uom" maxlength="40" value="{{ old('tax_uom', $item->tax_uom) }}" placeholder="e.g. KG">
        <div class="help">As the tax authority's unit list names it. Blank uses the base unit.</div>
        @error('tax_uom')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="origin_country">Country of origin</label>
        <input id="origin_country" name="origin_country" maxlength="2" value="{{ old('origin_country', $item->origin_country) }}" placeholder="PK" style="text-transform:uppercase;max-width:90px">
        <div class="help">Two-letter country code.</div>
        @error('origin_country')<div class="error">{{ $message }}</div>@enderror
    </div>
</div>
