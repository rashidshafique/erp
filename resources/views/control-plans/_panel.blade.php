{{-- The control plan of each revision of a product: follows Our process, or its own rows. A pattern change can mark it for review. --}}
@php $service = app(\App\Services\Production\ControlPlans::class); @endphp
<div class="panel" id="control-plan">
    <div class="panel-header"><h2>Control plan</h2><span class="muted small">The process this product goes through, with what is controlled at each step. Every revision follows <a href="{{ route('settings.process') }}">Our process</a> until it is given its own plan.</span></div>
    <div class="panel-body">
        @foreach ($product->revisions as $revision)
            @php $plan = $service->forRevision($revision); $items = $service->effectiveItems($plan); $stages = app(\App\Services\Production\ProcessRoutes::class)->stepsFor($revision->id); @endphp
            <details {{ $loop->first || $plan->needs_review_at ? 'open' : '' }} style="margin-bottom:14px">
                <summary><strong>Revision {{ $revision->revision }}</strong>
                    <span class="status">{{ $plan->follows_default ? 'Follows Our process' : ($plan->isApproved() ? 'Own plan, approved' : 'Own plan, draft') }}</span>
                    @if ($plan->needs_review_at)<span class="status status-on-hold">Needs review</span>@endif</summary>
                @if ($plan->needs_review_at)
                    <div class="alert alert-warning" style="margin:10px 0">{{ $plan->review_reason }}.
                        <form method="POST" action="{{ route('products.control-plan.confirm', [$product, $revision]) }}" style="display:inline-flex;gap:8px;align-items:center">@csrf
                            <input name="name" placeholder="Your name" required maxlength="100" style="width:150px"><button class="btn btn-small" type="submit">Confirm it still holds</button></form></div>
                @endif
                @if ($plan->follows_default)
                    @include('control-plans._steps', ['items' => $items, 'stages' => $stages])
                    @unless ($product->trashed())<form method="POST" action="{{ route('products.control-plan.customise', [$product, $revision]) }}" style="margin-top:8px">@csrf<button class="btn" type="submit">Give this revision its own plan</button></form>@endunless
                @else
                    @include('control-plans._steps', ['items' => $items, 'stages' => $stages, 'action' => route('products.control-plan.save', [$product, $revision]), 'submit' => 'Save draft'])
                    @unless ($plan->isApproved())
                        <form method="POST" action="{{ route('products.control-plan.approve', [$product, $revision]) }}" style="display:flex;gap:8px;align-items:center;margin-top:8px">@csrf
                            <input name="name" placeholder="Approved by" required maxlength="100" style="width:170px"><button class="btn" type="submit">Approve plan</button></form>
                    @else
                        <p class="muted small">Approved by {{ $plan->approved_by }} on {{ $plan->approved_at?->displayDate() }}.</p>
                    @endunless
                @endif
            </details>
        @endforeach
    </div>
</div>
