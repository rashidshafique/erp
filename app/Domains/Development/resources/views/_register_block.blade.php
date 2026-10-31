{{-- The developments that made, changed or replaced a pattern or core box (design 18h). Needs $record. --}}
@php
    $developments = \App\Domains\Development\Models\PatternDevelopment::touching($record);
    $isBox = $record instanceof \App\Models\CoreBox;
    $role = function ($d) use ($record, $isBox) {
        $tool = $d->tools->first(fn ($t) => ($isBox ? $t->core_box_id : $t->pattern_id) === $record->id);

        return match (true) {
            $tool === null => 'Being replaced',
            $tool->route === 'MODIFY' => 'Changed here',
            $tool->route === 'EXISTING' => 'Used here',
            default => 'Made here',
        };
    };
@endphp
<div class="panel"><div class="panel-header"><h2>Developments</h2></div>
    @if ($developments->isEmpty())<div class="panel-body muted">No developments have made or changed it.</div>
    @else
    <div class="table-wrap"><table><thead><tr><th>Code</th><th>Type</th><th>Stage</th><th>Role</th></tr></thead><tbody>
    @foreach ($developments as $d)
        <tr><td><a href="{{ route('developments.show', $d) }}">{{ $d->code }}</a></td><td>{{ $d->typeInfo()['name'] }}</td>
            <td>{{ $d->status === 'OPEN' ? $d->stageName() : \App\Domains\Development\Models\PatternDevelopment::STATUSES[$d->status] }}</td><td>{{ $role($d) }}</td></tr>
    @endforeach
    </tbody></table></div>
    @endif
</div>
