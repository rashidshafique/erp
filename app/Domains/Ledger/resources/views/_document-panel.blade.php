{{-- The journals a Finance document posted, shown on its page. Expects $document. --}}
@php
    $src = \App\Domains\Ledger\Services\PostingRules::source($document);
    $journals = \App\Domains\Ledger\Models\Journal::where('source_type', $src['type'])->where('source_id', $src['id'])->orderBy('created_at')->get();
@endphp
@if ($journals->isNotEmpty())
<div class="panel"><div class="panel-body small">Ledger:
@foreach ($journals as $j)<a href="{{ route('ledger.journals.show', $j) }}" class="mono">{{ $j->number }}</a>@if ($j->kind === 'REVERSAL') <span class="muted">reversal</span>@endif @if (! $loop->last) · @endif @endforeach
</div></div>
@endif
