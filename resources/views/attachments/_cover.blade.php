{{-- Main photo for a record's summary. Usage: @include('attachments._cover', ['type' => 'product', 'id' => $product->id]) --}}
@php($cover = \App\Models\Attachment::coverFor($type, (string) $id))
<div style="float:right;margin:0 0 8px 14px;width:{{ $size ?? 150 }}px;text-align:center">
    @if ($cover)
        <a href="{{ route('attachments.download', $cover) }}" target="_blank" title="{{ $cover->title ?: $cover->original_name }}">
            <img src="{{ route('attachments.download', $cover) }}" alt="{{ $cover->title ?: 'Photo' }}"
                 style="width:100%;height:{{ $size ?? 150 }}px;object-fit:contain;background:#fff;border:1px solid var(--border);border-radius:8px;padding:2px;min-height:0">
        </a>
    @else
        <div class="muted small" style="height:{{ $size ?? 150 }}px;display:flex;align-items:center;justify-content:center;border:1px dashed var(--border);border-radius:8px;padding:8px">
            No photo yet.<br>Add one under Files.
        </div>
    @endif
</div>
