@extends('layouts.app')

@section('title', 'Count moulds')
@section('breadcrumb', 'Moulding / Count')

{{-- design: F3·3 count before pouring (Full). A short pattern goes on to F3·4 for its reason. --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.moulding._frame')

    <h1 style="margin:0;font-size:22px">Count for {{ $heat ? 'heat '.$heat->heat_number : 'the next heat' }}</h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 12px">
        @if ($heat){{ $heat->furnace?->code }} · {{ $heat->status === 'PLANNED' ? 'planned'.($heat->planned_start_at ? ' '.$heat->planned_start_at->format('D H:i') : '') : 'running' }}@else No heat is running or planned; the count is kept without a heat.@endif
    </p>

    @if ($waiting->isEmpty())
        <div class="fl-rows"><div class="fl-row-empty">No moulds are waiting for metal, so there is nothing to count.</div></div>
    @else
        <form method="POST" action="{{ route('floor.moulding.count.review') }}">@csrf
            <input type="hidden" name="heat_id" value="{{ $heat?->id }}">
            <div class="fl-count-pad">
                <div>
                    @foreach ($waiting as $id => $w)
                        <x-floor.count-field :name="'counted['.$id.']'" :id="'count-'.$loop->index"
                            :label="$w['revision']->pattern->code.' '.$w['revision']->pattern->name"
                            :value="old('counted.'.$id)" :records="$w['expected']"
                            :hint="'Records '.$w['expected'].' · assembled '.$w['assembled'].($w['unassembled'] ? ' · not assembled '.$w['unassembled'] : '').($w['oldest'] ? ' · since '.\Illuminate\Support\Carbon::parse($w['oldest'])->format('D H:i') : '')"
                            :below="'Less than records say ('.$w['expected'].'). Next asks why.'" placeholder="Leave empty to skip" />
                    @endforeach
                    <p style="color:var(--fl-meta);font-size:13.5px">Records {{ $waiting->sum('expected') }} moulds. Leave a pattern empty to skip it. Extra moulds are kept as counted.</p>
                </div>
                <x-floor.keypad save="Save count · {total}" />
            </div>
        </form>
    @endif
</div>
@endsection
