{{-- The enquiry record once it is sent (design 14b): the stage bar, the tooling and pattern decision, where feasibility stands, questions to
     the customer, the quotes and the log; on the right the key facts, the drawing and everything linked to it. Edit details opens the
     intake page (1b), Close enquiry the closing dialog (14c), and Start pattern development the development dialog (12a, new part).
     A won, lost, declined or cancelled enquiry shows the same page without the actions. --}}
@extends('layouts.app')
@section('title', $e->code)
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / {{ $e->code }} @endsection
@section('content')
@include('partials.search-select')
@include('partials.quick-dialog-assets')
<style>
    .er-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .er-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .er-crumb { display: flex; align-items: center; gap: 16px; font-size: 13px; flex-wrap: wrap; } .er-crumb a { color: var(--muted); }
    .er-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .er-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .er-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #6b6e73; }
    .er-pill.er-blue { background: #eaf0fd; color: var(--accent); } .er-pill.er-ok { background: #e7f4ec; color: #1d7048; } .er-pill.er-warn { background: #fbf0dc; color: #94610a; } .er-pill.er-bad { background: #fbe7e5; color: #b3352c; }
    .er-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .er-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 22px; min-width: 0; }
    .er-side { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; font-size: 13px; min-width: 0; }
    .er-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; }
    .er-steps > div { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); } .er-steps > div::before { content: ''; height: 4px; border-radius: 2px; background: #e6e7e9; }
    .er-steps > div.done::before { background: var(--accent); } .er-steps > div.now { color: var(--text); font-weight: 600; }
    .er-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .er-box { border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; min-width: 0; }
    .er-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .er-opts { display: flex; flex-direction: column; gap: 6px; }
    .er-opt { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; cursor: pointer; }
    .er-opt input { flex: none; margin: 2px 0 0; accent-color: var(--accent); } .er-opt.on { border-color: var(--accent); background: #f5f8fe; } .er-opt.on strong { font-weight: 600; }
    .er-opt strong { display: block; font-weight: 400; } .er-opt span { font-size: 12px; color: var(--muted); }
    .er-kv { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; } .er-kv .k { font-size: 12px; color: var(--muted); }
    .er-table { margin: 0 -12px; overflow-x: auto; } .er-table table { width: 100%; } .er-table td { vertical-align: top; }
    .er-r { text-align: right; }
    .er-log > div { display: grid; grid-template-columns: 110px minmax(0, 1fr) 80px; gap: 12px; padding: 11px 0; border-bottom: 1px solid #eff0f2; }
    .er-log .who { font-size: 12px; color: var(--muted); } .er-log .when { font-size: 12px; color: var(--muted); text-align: right; }
    .er-add { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 4px; } .er-add input, .er-add select { width: auto; } .er-add .grow { flex: 1 1 260px; min-width: 0; }
    .er-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border); } .er-row:last-child { border-bottom: 0; } .er-row > span:last-child { color: var(--muted); text-align: right; }
    .er-preview { height: 140px; border-radius: 8px; border: 1px solid #e0e1e4; background: repeating-linear-gradient(135deg, #fff 0 8px, #f1f2f4 8px 16px); display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .er-preview img { max-width: 100%; max-height: 100%; object-fit: contain; } .er-preview iframe { width: 100%; height: 100%; border: 0; background: #fff; }
    .er-preview code { font-size: 11.5px; color: var(--muted); background: #fff; padding: 4px 8px; border-radius: 4px; }
    .er-muted { color: var(--muted); } .er-link { color: var(--accent); font-size: 12.5px; cursor: pointer; background: none; border: 0; padding: 0; }
    .er-answer { display: flex; gap: 6px; } .er-answer input { flex: 1; min-width: 0; }
    @media (max-width: 1100px) { .er-grid { grid-template-columns: 1fr; } .er-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .er-two, .er-kv { grid-template-columns: 1fr; } .er-bar, .er-main { padding-left: 16px; padding-right: 16px; } .er-log > div { grid-template-columns: 1fr; gap: 4px; } .er-log .when { text-align: left; } }

    dialog.ec-modal { width: min(640px, calc(100vw - 32px)); }
    .ec-modal .qd-head { padding: 16px 24px; } .ec-modal .qd-head h2 { font-size: 15px; font-weight: 600; }
    .ec-modal .qd-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; font-size: 13px; }
    .ec-modal .qd-foot { padding: 14px 24px; border-top: 1px solid var(--border); justify-content: space-between; align-items: center; }
    .ec-modal .qd-foot > div { display: flex; gap: 8px; }
    .ec-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .ec-card { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; cursor: pointer; }
    .ec-card input { flex: none; margin: 2px 0 0; accent-color: var(--accent); } .ec-card.on { border-color: var(--accent); background: #f5f8fe; } .ec-card.on strong { font-weight: 600; }
    .ec-card strong { display: block; font-weight: 400; } .ec-card span { font-size: 12px; color: var(--muted); }
    .ec-f { display: flex; flex-direction: column; gap: 5px; font-size: 12px; color: var(--muted); } .ec-f input, .ec-f textarea { font-size: 13px; color: var(--text); }
    .ec-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .ec-chip { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--border); cursor: pointer; color: var(--text); position: relative; font-size: 13px; }
    .ec-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; } .ec-chip input { position: absolute; opacity: 0; pointer-events: none; }
    .ec-chip:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 1px; }
    .ec-grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .ec-ro { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: #f8f8f9; color: var(--text); font-size: 13px; }
    .ec-won { padding: 10px 12px; border-radius: 7px; background: #f5f8fe; border: 1px solid color-mix(in srgb, var(--accent) 30%, #fff); color: var(--text); }
    .ec-modal [hidden] { display: none !important; }
    @media (max-width: 600px) { .ec-cards, .ec-grid2 { grid-template-columns: 1fr; } }
</style>

<div class="tab-marker" data-tab-start="Enquiry"></div>
<livewire:enquiry::record :enquiry="$e" />

<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'enquiry', 'id' => $e->id])
@include('audit._history', ['type' => 'Enquiry', 'id' => $e->id])
@endsection
