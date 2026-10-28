<?php

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Sales\Models\PriceList;
use App\Domains\Sales\Models\PriceListItem;
use App\Domains\Sales\Services\PriceLists;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class PriceListController extends Controller
{
    public function index(): View
    {
        return view('sales::price-lists.index', ['lists' => PriceList::withCount(['items', 'customers'])->orderByDesc('is_default')->orderBy('code')->get(), 'check' => PriceLists::check(), 'discounts' => PriceLists::discountsOn(), 'discountLimit' => PriceLists::discountLimit()]);
    }

    public function store(Request $request, PriceLists $service): RedirectResponse
    {
        $list = PriceList::create($this->validated($request));
        if ($request->boolean('is_default')) {
            $service->makeDefault($list);
        }

        return redirect()->route('price-lists.show', $list)->with('success', "Price list {$list->code} added. Now enter its prices.");
    }

    public function show(PriceList $priceList): View
    {
        return view('sales::price-lists.show', [
            'list' => $priceList->load(['items.product', 'customers']), 'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'customers' => Customer::orderedByName()->whereNull('price_list_id')->get(),
        ]);
    }

    public function update(Request $request, PriceList $priceList, PriceLists $service): RedirectResponse
    {
        $priceList->update($this->validated($request, $priceList));
        if ($request->boolean('is_default')) {
            $service->makeDefault($priceList);
        } elseif ($priceList->is_default && $request->has('is_default')) {
            $priceList->update(['is_default' => false]);
        }

        return back()->with('success', 'Price list saved.');
    }

    public function items(Request $request, PriceList $priceList, PriceLists $service): RedirectResponse
    {
        $d = $request->validate([
            'items' => ['nullable', 'array'], 'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'], 'items.*.basis' => ['nullable', Rule::in(array_keys(PriceListItem::BASES))],
            'items.*.min_quantity' => ['nullable', 'numeric', 'min:0'], 'items.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $service->saveItems($priceList, $d['items'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Prices saved.');
    }

    public function copy(Request $request, PriceList $priceList, PriceLists $service): RedirectResponse
    {
        $d = $request->validate(['code' => ['required', 'string', 'max:40', 'unique:price_lists,code'], 'name' => ['required', 'string', 'max:120'],
            'percent' => ['nullable', 'numeric', 'min:-90', 'max:500'], 'valid_from' => ['nullable', 'date']]);
        $copy = $service->copy($priceList->load('items'), strtoupper($d['code']), $d['name'], (float) ($d['percent'] ?? 0), $d['valid_from'] ?? null);

        return redirect()->route('price-lists.show', $copy)->with('success', "Copied to {$copy->code}. It is not active until you switch it on.");
    }

    public function destroy(PriceList $priceList): RedirectResponse
    {
        $priceList->delete();

        return redirect()->route('price-lists.index')->with('success', "Price list {$priceList->code} deleted; its customers fall back to the default list.");
    }

    /** Gives a customer this list, or takes it away; from the price list page or the business partner page. */
    public function assign(Request $request, Customer $customer): RedirectResponse
    {
        $d = $request->validate(['price_list_id' => ['nullable', 'uuid', 'exists:price_lists,id']]);
        $customer->forceFill(['price_list_id' => $d['price_list_id'] ?? null])->save();

        return back()->with('success', $customer->name.(($d['price_list_id'] ?? null) ? ' now buys from this price list.' : ' now buys from the default price list.'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $d = $request->validate(['check' => ['required', Rule::in(array_keys(PriceLists::CHECK_OPTIONS))], 'discount_limit' => ['nullable', 'numeric', 'min:0', 'max:100']]);
        AppSetting::setValue(PriceLists::CHECK, $d['check'], 'What happens when an order line is priced under its list price');
        AppSetting::setValue(PriceLists::DISCOUNTS, $request->boolean('line_discounts') ? '1' : '0', 'Whether order and invoice lines have a discount box');
        AppSetting::setValue(PriceLists::DISCOUNT_LIMIT, (string) ($d['discount_limit'] ?? 0), 'A line discount above this percent is flagged at contract review (0 = no limit)');

        return back()->with('success', 'Saved.');
    }

    /** The list price for an order line being typed: ?customer, product (a revision id or product:<id>), qty, uom. */
    public function lookup(Request $request, PriceLists $service): JsonResponse
    {
        $value = (string) $request->query('product');
        $revision = str_starts_with($value, 'product:') ? null : ProductRevision::with('product')->find($value);
        $product = $revision?->product ?? Product::find(substr($value, 8));
        if (! $product) {
            return response()->json(['price' => null]);
        }
        $kgEach = (float) ($revision?->net_weight_kg ?: $product->nominal_weight_kg);
        $found = $service->priceFor($request->query('customer') ?: null, $product->id, max(0, (float) $request->query('qty', 0)), $request->query('uom') === 'kg' ? 'kg' : 'piece', $kgEach, Carbon::parse($request->query('date', today()->toDateString())));

        return response()->json($found ? ['price' => $found['price'], 'list' => $found['list']->code, 'basis' => $found['item']->basis, 'from' => $found['item']->min_quantity] : ['price' => null]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PriceList $list = null): array
    {
        $d = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('price_lists', 'code')->ignore($list?->id)], 'name' => ['required', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'max:10'], 'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ['code' => strtoupper($d['code']), 'is_active' => $request->boolean('is_active', $list === null)] + $d;
    }
}
