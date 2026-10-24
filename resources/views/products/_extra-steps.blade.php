{{-- Steps only some products go through (e.g. grinding), ticked per revision. Shown only when the foundry has such steps. --}}
@php $extra = \App\Models\ProductionStageDefinition::where('is_active', true)->where('product_specific', true)->orderBy('sequence')->get(); @endphp
@if ($extra->isNotEmpty())
<div class="panel" id="extra-steps">
    <div class="panel-header"><h2>Extra steps</h2><span class="muted small">Besides <a href="{{ route('settings.process') }}">Our process</a>, tick the steps this revision goes through. A new revision starts with the ticks of the one before.</span></div>
    <div class="panel-body">
        @foreach ($product->revisions as $revision)
            @php $ticked = $revision->extraSteps->pluck('id'); @endphp
            <form method="POST" action="{{ route('products.revisions.steps', [$product, $revision]) }}" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
                @csrf @method('PUT')
                <strong style="min-width:110px">Revision {{ $revision->revision }}</strong>
                @foreach ($extra as $stage)<label><input type="checkbox" name="steps[]" value="{{ $stage->id }}" @checked($ticked->contains($stage->id)) @disabled($product->trashed())> {{ $stage->name }}</label>@endforeach
                @unless ($product->trashed())<button class="btn btn-small" type="submit">Save</button>@endunless
            </form>
        @endforeach
    </div>
</div>
@endif
