{{-- A product: its photo on the left, the name on the right with the product code under it.
     $product (may be null), optional $revision and $href, and $photo (main photo, looked up when not given). --}}
@props(['product' => null, 'revision' => null, 'href' => null, 'photo' => null, 'fallbackName' => 'Product not set'])
@php
    $photo ??= $product ? \App\Models\Attachment::coverFor('product', (string) $product->id) : null;
    $name = $product->name ?? $fallbackName;
@endphp
<span {{ $attributes->class(['product-cell']) }}>
    @if ($photo)
        <a href="{{ route('attachments.download', $photo) }}" data-lightbox="product-cells" data-caption="{{ $name }} · {{ $product->code }}">
            <img src="{{ route('attachments.download', $photo) }}" alt="" loading="lazy">
        </a>
    @else
        <span class="product-cell-none" title="No photo of this product yet">{!! \App\Support\NavIcons::get('layers', 18) !!}</span>
    @endif
    <x-item :name="$name" :code="$product->code ?? null" :revision="$revision" :href="$href" />
</span>
@once
<style>
    .product-cell { display: flex; gap: 10px; align-items: center; }
    .product-cell > a, .product-cell-none { flex: none; width: 44px; height: 44px; border-radius: 5px; border: 1px solid var(--border); overflow: hidden; }
    .product-cell img { display: block; width: 100%; height: 100%; object-fit: cover; padding: 0; min-height: 0; }
    .product-cell-none { display: inline-flex; align-items: center; justify-content: center; border-style: dashed; color: var(--muted); }
    .product-cell .item { min-width: 0; line-height: 1.3; }
</style>
@endonce
