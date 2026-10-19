@php $ph = \App\Models\Attachment::coverFor('employee', $node->id); $kids = $depth < 12 ? $byManager->get($node->id) : null; @endphp
<li>
    <div class="oc-card" id="n-{{ $node->id }}" data-name="{{ mb_strtolower($node->full_name.' '.$node->designation?->name.' '.$node->department?->name) }}">
        @if ($ph)<img class="oc-av" src="{{ route('attachments.download', $ph) }}" alt="">@else<span class="oc-av oc-ini">{{ strtoupper(mb_substr($node->first_name, 0, 1).mb_substr((string) $node->last_name, 0, 1)) }}</span>@endif
        <div class="oc-t"><a href="{{ route('hr.employees.show', $node) }}"><strong>{{ $node->full_name }}</strong></a><small>{{ $node->designation?->name ?? 'No title yet' }}</small>@if ($node->department)<small class="oc-dep">{{ $node->department->name }}</small>@endif</div>
        <div class="oc-acts">
            @if ($node->phone)<a href="tel:{{ $node->phone }}" title="{{ $node->phone }}">{!! \App\Support\NavIcons::get('phone', 14) !!}</a>@endif
            @if ($node->work_email)<a href="mailto:{{ $node->work_email }}" title="{{ $node->work_email }}">{!! \App\Support\NavIcons::get('mail', 14) !!}</a>@endif
            <span class="oc-count" title="Direct reports">{{ $kids ? $kids->count() : 0 }} report{{ ($kids ? $kids->count() : 0) === 1 ? '' : 's' }}</span>
        </div>
    </div>
    <button type="button" class="oc-add" data-add="{{ $node->id }}" data-who="{{ $node->full_name }}" title="Add a person under {{ $node->full_name }}" aria-label="Add a person under {{ $node->full_name }}">+</button>
    @if ($kids)<ul>@foreach ($kids as $k)@include('hr::_orgnode', ['node' => $k, 'byManager' => $byManager, 'depth' => $depth + 1])@endforeach</ul>@endif
</li>
