{{-- Photos for a record's summary: the main photo, the other photos as thumbnails, all opening in the lightbox, and a button to
     add photos at any time. Usage: @include('attachments._cover', ['type' => 'product', 'id' => $product->id])
     $size (px, default 150); $upload (default true) shows the "Add photos" button. --}}
@php
    $size ??= 150;
    $upload ??= true;
    $photos = \App\Models\Attachment::where('attachable_type', $type)->where('attachable_id', (string) $id)->whereNull('removed_at')
        ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])->orderByDesc('created_at')->orderByDesc('id')->get();
    $cover = \App\Models\Attachment::coverFor($type, (string) $id);
    $others = $cover ? $photos->reject(fn ($p) => $p->id === $cover->id)->values() : collect();
    $group = 'photos-'.$type.'-'.$id;
    $caption = fn ($p) => $p->title ?: $p->original_name;
@endphp
@once
<style>
    .cover-box { float: right; margin: 0 0 8px 14px; text-align: center; }
    .cover-box .cover-main { display: block; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); overflow: hidden; }
    .cover-box .cover-main img { display: block; width: 100%; object-fit: contain; padding: 2px; min-height: 0; }
    .cover-box .cover-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; border: 1px dashed var(--border); border-radius: 8px; padding: 8px; color: var(--muted); font-size: 12px; }
    .cover-box .cover-thumbs { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .cover-box .cover-thumbs a { display: block; width: 34px; height: 34px; border-radius: 5px; overflow: hidden; border: 1px solid var(--border); }
    .cover-box .cover-thumbs img { width: 100%; height: 100%; object-fit: cover; padding: 0; min-height: 0; display: block; }
    .cover-box .cover-more { font-size: 11px; color: var(--muted); align-self: center; }
    .cover-box .cover-add { margin-top: 6px; }
    .cover-box .cover-add label { display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--accent); }
    .cover-box .cover-add input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .cover-box .cover-add label:focus-within { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }
</style>
@endonce
<div class="cover-box" style="width:{{ $size }}px">
    @if ($cover)
        <a class="cover-main" href="{{ route('attachments.download', $cover) }}" data-lightbox="{{ $group }}" data-caption="{{ $caption($cover) }}" title="{{ $caption($cover) }}">
            <img src="{{ route('attachments.download', $cover) }}" alt="{{ $cover->title ?: 'Photo' }}" style="height:{{ $size }}px">
        </a>
        @if ($others->isNotEmpty())
            <div class="cover-thumbs">
                @foreach ($others->take(5) as $p)
                    <a href="{{ route('attachments.download', $p) }}" data-lightbox="{{ $group }}" data-caption="{{ $caption($p) }}" title="{{ $caption($p) }}"><img src="{{ route('attachments.download', $p) }}" alt="" loading="lazy"></a>
                @endforeach
                @foreach ($others->slice(5) as $p)<a href="{{ route('attachments.download', $p) }}" data-lightbox="{{ $group }}" data-caption="{{ $caption($p) }}" hidden></a>@endforeach
                @if ($others->count() > 5)<span class="cover-more">+{{ $others->count() - 5 }}</span>@endif
            </div>
        @endif
    @else
        <div class="cover-empty" style="height:{{ $size }}px">{!! \App\Support\NavIcons::get('box', 28) !!}<span>No photo yet</span></div>
    @endif
    @if ($upload)
        <form method="POST" action="{{ route('attachments.store', [$type, $id]) }}" enctype="multipart/form-data" class="cover-add">
            @csrf<input type="hidden" name="category" value="PHOTO">
            <label>{!! \App\Support\NavIcons::get('plus-circle', 14) !!}{{ $cover ? 'Add photos' : 'Add a photo' }}<input type="file" name="files[]" accept="image/jpeg,image/png,image/webp" multiple onchange="this.form.submit()"></label>
            <noscript><button class="btn btn-small" type="submit">Upload</button></noscript>
        </form>
    @endif
</div>
