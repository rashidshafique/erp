<div style="display:flex;gap:6px;margin:0 0 14px;flex-wrap:wrap">
@foreach (['hr.attendance' => 'Daily sheet', 'hr.attendance.exceptions' => 'Exceptions', 'hr.attendance.overtime' => 'Overtime', 'hr.attendance.import' => 'Import punches'] as $r => $l)
<a class="btn {{ request()->routeIs($r, $r.'.run') ? 'btn-primary' : '' }}" href="{{ route($r) }}">{{ $l }}</a>
@endforeach
</div>
