<li><strong>{{ $node->full_name }}</strong> <span class="muted small">{{ $node->designation?->name }}{{ $node->department ? ' · '.$node->department->name : '' }}</span>
@if ($depth < 12 && ($kids = $byManager->get($node->id)))<ul>@foreach ($kids as $k)@include('hr._tree', ['node' => $k, 'byManager' => $byManager, 'depth' => $depth + 1])@endforeach</ul>@endif</li>
