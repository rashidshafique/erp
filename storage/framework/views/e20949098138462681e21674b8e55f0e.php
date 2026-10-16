<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $__env->yieldContent('title', 'Foundry ERP'); ?> — <?php echo e(\App\Support\UiSettings::brand()['name']); ?></title>
    <?php ($uiFont = \App\Support\UiSettings::font()); ?>
    <?php if($uiFont['url']): ?><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="<?php echo e($uiFont['url']); ?>"><?php endif; ?>

    <style>
        :root {
            --sidebar: #19242d;
            --sidebar-muted: #aab6c0;
            --accent: #a84a22;
            --accent-hover: #873a1a;
            --page: #f3f5f6;
            --surface: #ffffff;
            --text: #24313a;
            --muted: #73808a;
            --border: #e0e5e8;
            --success: #18794e;
            --warning: #946200;
            --danger: #b42318;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--text);
            background: var(--page);
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI",
                  Roboto, Helvetica, Arial, sans-serif;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .app-shell { min-height: 100vh; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 20;
            display: flex;
            flex-direction: column;
            width: 230px;
            padding: 24px 14px;
            color: #fff;
            background: var(--sidebar);
            overflow-y: auto;
        }

        .brand {
            padding: 0 12px 26px;
            color: #fff;
            font-size: 18px;
            font-weight: 750;
            line-height: 1.25;
        }

        .brand small {
            display: block;
            margin-top: 6px;
            color: var(--sidebar-muted);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.2px;
        }

        .nav-label {
            padding: 15px 12px 7px;
            color: #81909b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-link {
            display: block;
            margin: 2px 0;
            padding: 10px 12px;
            border-radius: 7px;
            color: var(--sidebar-muted);
            font-weight: 550;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #fff;
            background: #2b3944;
            text-decoration: none;
        }

        .nav-link.active { box-shadow: inset 3px 0 var(--accent); }

        .sidebar-footer {
            margin-top: auto;
            padding: 20px 12px 0;
            color: #81909b;
            font-size: 11px;
        }

        .main-area { min-height: 100vh; margin-left: 230px; }

        .topbar {
            display: flex;
            min-height: 66px;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 30px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }

        .breadcrumb { color: var(--muted); font-size: 12px; }
        .breadcrumb strong { color: var(--text); }

        .topbar-date { color: var(--muted); font-size: 12px; }

        .page-content {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .page-heading h1 {
            margin: 0 0 5px;
            font-size: 25px;
            letter-spacing: -0.6px;
        }

        .page-heading p { margin: 0; color: var(--muted); }

        .actions { display: flex; flex-wrap: wrap; gap: 8px; }

        .btn {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: #fff;
            color: var(--text);
            font: inherit;
            font-weight: 650;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover { background: #f8f9fa; text-decoration: none; }

        .btn-primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            border-color: var(--accent-hover);
            background: var(--accent-hover);
            color: #fff;
        }

        .btn-danger { border-color: #efc6c3; color: var(--danger); }

        .metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .metric, .panel {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 1px 2px rgb(20 30 40 / 3%);
        }

        .metric { padding: 18px 20px; }
        .metric-label { color: var(--muted); font-size: 12px; font-weight: 600; }
        .metric-value { margin-top: 7px; font-size: 27px; font-weight: 750; }
        .metric-note { margin-top: 4px; color: var(--muted); font-size: 11px; }

        .panel { margin-bottom: 22px; overflow: hidden; }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 17px 20px;
            border-bottom: 1px solid var(--border);
        }

        .panel-header h2 { margin: 0; font-size: 15px; }
        .panel-body { padding: 20px; }

        .table-wrap { width: 100%; overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; white-space: nowrap; }

        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #edf0f2;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #fafbfc;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #fcfcfd; }

        .muted { color: var(--muted); }
        .small { font-size: 12px; }

        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            background: #eef1f4;
            color: #52606b;
            font-size: 10px;
            font-weight: 750;
        }

        .status-in-progress { background: #fff0dc; color: #8a4b08; }
        .status-completed { background: #e4f5eb; color: #176b43; }
        .status-on-hold { background: #fff3cd; color: #7a5b00; }
        .status-cancelled { background: #fce8e6; color: #a12820; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
        }

        .filters .field { flex: 1 1 150px; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field { min-width: 0; }
        .field-full { grid-column: 1 / -1; }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .required { color: var(--danger); }

        input, select, textarea {
            width: 100%;
            min-height: 39px;
            padding: 9px 11px;
            border: 1px solid #ccd4d9;
            border-radius: 6px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        textarea { min-height: 90px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid rgb(168 74 34 / 15%);
            border-color: var(--accent);
        }

        .help { margin-top: 5px; color: var(--muted); font-size: 11px; }
        .error-text { margin-top: 5px; color: var(--danger); font-size: 12px; }

        .alert {
            margin-bottom: 20px;
            padding: 12px 15px;
            border: 1px solid;
            border-radius: 8px;
        }

        .alert-success { border-color: #b7e2c9; background: #eaf7ef; color: #176b43; }
        .alert-error { border-color: #efc6c3; background: #fff0ef; color: #a12820; }

        .empty-state { padding: 38px 20px; color: var(--muted); text-align: center; }
        .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .detail-label { margin-bottom: 4px; color: var(--muted); font-size: 11px; }
        .detail-value { overflow-wrap: anywhere; font-weight: 650; }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 20px;
            border-top: 1px solid var(--border);
        }

        .pagination-links { display: flex; flex-wrap: wrap; gap: 6px; }
        .pagination-links a, .pagination-links span {
            display: inline-block;
            padding: 5px 9px;
            border: 1px solid var(--border);
            border-radius: 5px;
        }

        .pagination-links .current { background: var(--accent); color: #fff; }

        @media (max-width: 1100px) {
            .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 760px) {
            .sidebar { position: static; width: 100%; padding: 14px; }
            .brand { padding: 0 8px 12px; }
            .nav-label { padding-top: 10px; }
            .sidebar-footer { display: none; }
            .main-area { margin-left: 0; }
            .topbar { min-height: 54px; padding: 12px 18px; }
            .page-content { padding: 20px 14px; }
            .page-heading { flex-direction: column; }
            .page-heading h1 { font-size: 22px; }
            .metrics { gap: 10px; }
            .metric { padding: 14px; }
            .metric-value { font-size: 23px; }
            .form-grid, .detail-grid { grid-template-columns: 1fr; }
            .field-full { grid-column: auto; }
            .panel-body { padding: 15px; }
        }

        /* Foundry ERP nested sidebar navigation */
        .sidebar-nav { display: flex; flex-direction: column; gap: 5px; }
        .nav-group { border-radius: 8px; }
        .nav-group-toggle {
            display: flex; width: 100%; align-items: center; justify-content: space-between;
            gap: 10px; padding: 11px 12px; border: 0; border-radius: 7px;
            background: transparent; color: #e0e7ec; font: inherit; font-weight: 650;
            text-align: left; cursor: pointer;
        }
        .nav-group-toggle:hover, .nav-group-toggle:focus-visible {
            outline: none; background: #2b3944; color: #fff;
        }
        .nav-group.is-active > .nav-group-toggle { color: #fff; }
        .nav-chevron { display: inline-block; color: #aab6c0; font-size: 20px; line-height: 1; transition: transform .18s ease; transform-origin: center; transform: rotate(90deg);}
        .nav-group.is-open .nav-chevron {  transform: rotate(270deg);}
        .nav-submenu { padding: 2px 0 7px 9px; }
        .nav-submenu[hidden] { display: none !important; }
        .nav-submenu .nav-link { padding: 9px 10px 9px 14px; font-size: 13px; }
        .nav-submenu .nav-link.active { color: #fff; background: #2b3944; box-shadow: inset 3px 0 var(--accent); }
        .sidebar-primary-action { margin-top: 14px; }
        .sidebar-primary-action .btn { width: 100%; }
        @media (prefers-reduced-motion: reduce) { .nav-chevron { transition: none; } }


        /* ERPNext white theme override v1 */
        :root {
            --sidebar: #ffffff !important;
            --sidebar-muted: #5e6872 !important;
            --accent: <?php echo e(\App\Support\UiSettings::theme()['accent']); ?> !important;
            --accent-hover: <?php echo e(\App\Support\UiSettings::theme()['hover']); ?> !important;
            --accent-dark: <?php echo e(\App\Support\UiSettings::theme()['dark']); ?>;
            --accent-soft: <?php echo e(\App\Support\UiSettings::theme()['soft']); ?>;
            --page: #f7f8fa !important;
            --surface: #ffffff !important;
            --text: #232a31 !important;
            --muted: #68737d !important;
            --border: #e2e6e9 !important;
        }

        .sidebar {
            width: 245px !important;
            background: #ffffff !important;
            color: #232a31 !important;
            border-right: 1px solid #e2e6e9 !important;
        }

        .main-area { margin-left: 245px !important; }

        body {
            background: #f7f8fa !important;
            color: #232a31 !important;
        }

        .brand { color: #232a31 !important; }
        .brand small { color: #84909a !important; }

        .nav-group-toggle {
            color: #424c55 !important;
            border-radius: 6px !important;
        }

        .nav-group-toggle:hover,
        .nav-group-toggle:focus-visible {
            background: #f2f5f3 !important;
            color: #202a24 !important;
        }

        .nav-group.is-active > .nav-group-toggle {
            color: var(--accent-dark) !important;
            background: var(--accent-soft) !important;
        }

        .nav-submenu .nav-link,
        .nav-link { color: #5e6872 !important; }

        .nav-submenu .nav-link:hover,
        .nav-link:hover {
            color: #202a24 !important;
            background: #f2f5f3 !important;
        }

        .nav-submenu .nav-link.active,
        .nav-link.active {
            color: var(--accent-dark) !important;
            background: var(--accent-soft) !important;
            box-shadow: inset 3px 0 var(--accent) !important;
        }

        .topbar { background: #ffffff !important; }
        .metric, .panel {
            background: #ffffff !important;
            border-color: #e3e7ea !important;
            box-shadow: 0 1px 2px rgb(20 30 40 / 2%) !important;
        }

        .btn-primary {
            background: var(--accent) !important;
            border-color: var(--accent) !important;
        }

        .btn-primary:hover {
            background: var(--accent-hover) !important;
            border-color: var(--accent-hover) !important;
        }


        /* Sidebar in the Stitch style: white panel, small uppercase section labels, icon + label rows,
           solid accent pill on the current page, soft grey on hover. */
        .sidebar-nav { gap: 6px; }
        .nav-ico { flex: none; opacity: .85; }
        .nav-group-toggle {
            padding: 7px 10px !important; border-radius: 8px !important;
            color: #64748b !important; font-size: 10.5px !important; font-weight: 700 !important;
            letter-spacing: .035em; text-transform: uppercase; white-space: nowrap; background: transparent !important; box-shadow: none !important;
        }
        .nav-group-toggle:hover, .nav-group-toggle:focus-visible { background: #f1f5f9 !important; color: #0f172a !important; }
        .nav-group.is-active > .nav-group-toggle { color: var(--accent-dark) !important; background: transparent !important; }
        .nav-lab { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
        .nav-chevron { font-size: 17px !important; }
        .nav-lab .nav-ico { width: 15px; height: 15px; }
        .nav-group.is-open > .nav-group-toggle { font-weight: 900 !important; }
        .nav-submenu { margin: 1px 0 6px 16px; padding: 2px 0 2px 5px !important; border-left: 1px solid #e2e8f0; }
        .nav-top { margin: 0 0 4px !important; font-weight: 600 !important; }
        .nav-submenu .nav-link, .nav-link {
            display: flex; align-items: center; gap: 10px; margin: 1px 0;
            padding: 7px 8px !important; border-radius: 8px; font-size: 13px; font-weight: 400;
            color: #475569 !important; background: transparent !important; box-shadow: none !important; text-decoration: none !important;
        }
        .nav-submenu .nav-link > span { min-width: 0; line-height: 1.25; }
        .nav-submenu .nav-link:hover, .nav-link:hover { background: #f1f5f9 !important; color: #0f172a !important; }
        .nav-submenu .nav-link.active, .nav-link.active {
            background: var(--accent) !important; color: #fff !important; font-weight: 400;
            box-shadow: 0 1px 2px rgb(15 23 42 / 12%) !important;
        }
        .nav-submenu .nav-link.active .nav-ico { opacity: 1; }

        <?php if(\App\Support\UiSettings::all()['compact']): ?>
        /* Compact density: tighter spacing everywhere, same look. Remove this block to go back to roomy. */
        body { font-size: 13px; line-height: 1.4; }
        .sidebar { width: 215px !important; padding: 14px 10px; }
        .brand { padding: 0 10px 14px; font-size: 16px; }
        .nav-group-toggle { padding: 6px 10px !important; }
        .nav-submenu { padding: 0 0 0 5px !important; margin-bottom: 4px; }
        .nav-submenu .nav-link { padding: 5px 10px !important; font-size: 12.5px; }
        .sidebar-nav { gap: 1px; }
        .sidebar-primary-action { margin-top: 8px; }
        .sidebar-footer { padding-top: 10px; }
        .main-area { margin-left: 215px !important; }
        .topbar { min-height: 40px; padding: 7px 20px; }
        .page-content { padding: 16px 20px; }
        .page-heading { margin-bottom: 12px; gap: 12px; }
        .page-heading h1 { margin: 0 0 2px; font-size: 20px; letter-spacing: -.3px; }
        .page-heading p { font-size: 12.5px; }
        .btn { min-height: 28px; padding: 4px 10px; gap: 5px; border-radius: 6px; font-size: 12.5px; }
        .btn-small { min-height: 24px; padding: 2px 8px; font-size: 12px; }
        .actions { gap: 6px; }
        .metrics { gap: 10px; margin-bottom: 12px; }
        .metric { padding: 10px 14px; }
        .metric-value { margin-top: 3px; font-size: 21px; }
        .metric-note { margin-top: 2px; }
        .metric, .panel { border-radius: 8px; }
        .panel { margin-bottom: 12px; }
        .panel-header { padding: 8px 14px; }
        .panel-header h2 { font-size: 13.5px; }
        .panel-body { padding: 12px 14px; }
        th, td { padding: 6px 10px; }
        th { font-size: 10.5px; }
        td .btn, td button.btn { min-height: 24px; padding: 2px 8px; }
        td input, td select { min-height: 26px; padding: 3px 6px; }
        .filters { gap: 8px; }
        .form-grid { gap: 10px 14px; }
        .field label { margin-bottom: 3px; font-size: 11.5px; }
        input, select, textarea { min-height: 28px; padding: 4px 8px; border-radius: 5px; }
        input[type=checkbox], input[type=radio] { width: auto; min-height: 0; padding: 0; }
        textarea { min-height: 56px; }
        .help { margin-top: 3px; font-size: 10.5px; }
        .error-text { margin-top: 3px; font-size: 11.5px; }
        .alert { padding: 7px 12px; margin-bottom: 10px; }
        .status { padding: 2px 7px; }
        .tabbar { margin: 2px 0 10px; }
        .tabbar button { padding: 6px 12px; }
        h3 { font-size: 13.5px; margin: 10px 0 6px; }
        p { margin: 0 0 8px; }
        @media (max-width: 900px) { .main-area { margin-left: 0 !important; } .sidebar { width: 100% !important; } }
        <?php endif; ?>
        /* Selected menu item: accent text only, no background. Menu width is dragged by the handle on its edge. */
        :root { --side-w: <?php echo e(\App\Support\UiSettings::all()['compact'] ? 215 : 245); ?>px; }
        .nav-submenu .nav-link.active, .nav-link.active, .nav-link.active:hover {
            background: transparent !important; box-shadow: none !important; color: var(--accent-dark) !important;
        }
        .nav-submenu .nav-link.active .nav-ico, .nav-link.active .nav-ico { opacity: 1; }
        .sidebar { overflow: hidden !important; }
        .sidebar-nav { flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden; }
        .sidebar-profile { flex: none; display: flex; align-items: center; gap: 8px; margin: 8px -4px 0; padding: 10px 6px 0; border-top: 1px solid #e2e8f0; }
        .sp-link { display: flex; align-items: center; gap: 9px; min-width: 0; flex: 1; color: #0f172a; text-decoration: none !important; }
        .sp-link:hover .sp-text strong { color: var(--accent-dark); }
        .sp-avatar { flex: none; display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 50%; background: var(--accent); color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .3px; }
        .sp-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
        .sp-text strong { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sp-text small { font-size: 11px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sp-out { flex: none; display: inline-flex; width: 30px; height: 30px; align-items: center; justify-content: center; padding: 0; border: 0; border-radius: 7px; background: transparent; color: #64748b; cursor: pointer; }
        .sp-out:hover { background: #f1f5f9; color: #0f172a; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        .sidebar-resizer { display: none; }
        @media (min-width: 901px) {
            .sidebar { width: var(--side-w) !important; }
            .main-area { margin-left: var(--side-w) !important; }
            .sidebar-resizer { display: block; position: fixed; top: 0; bottom: 0; left: calc(var(--side-w) - 3px); width: 7px; z-index: 30; cursor: col-resize; touch-action: none; }
            .sidebar-resizer::after { content: ""; position: absolute; top: 0; bottom: 0; left: 3px; width: 2px; background: transparent; transition: background .12s; }
            .sidebar-resizer:hover::after, .sidebar-resizer:focus-visible::after, .sidebar-resizer.dragging::after { background: var(--accent); }
            body.resizing-menu { cursor: col-resize; user-select: none; }
        }
        /* Font chosen by the administrator under Administration → Display */
        :root { --ui-font: <?php echo $uiFont['stack']; ?>; }
        body, button, input, select, textarea { font-family: var(--ui-font); }
    </style>
    <script>try{var w=parseInt(localStorage.getItem('erp.menuWidth'),10);if(w>=200&&w<=420)document.documentElement.style.setProperty('--side-w',w+'px');}catch(e){}</script>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body data-row-click="<?php echo e(\App\Models\AppSetting::getValue('ui.row_click', '1') === '1' ? '1' : '0'); ?>" data-toolbar="<?php echo e(\App\Support\UiSettings::all()['toolbar'] ? '1' : '0'); ?>" data-tidy-forms="<?php echo e(\App\Support\UiSettings::all()['tidy_forms'] ? '1' : '0'); ?>">
<div class="app-shell">
    <aside class="sidebar">
        <?php ($brand = \App\Support\UiSettings::brand()); ?>
        <a href="<?php echo e(route('dashboard')); ?>" class="brand" style="text-decoration:none">
            <?php if($brand['logo'] && $brand['position'] === 'TOP_LEFT'): ?>
                <img src="<?php echo e($brand['logo']); ?>" alt="<?php echo e($brand['name']); ?>" style="display:block;max-width:100%;height:<?php echo e($brand['height']); ?>px;object-fit:contain;object-position:left;margin-bottom:6px">
            <?php endif; ?>
            <?php if (! ($brand['logo'])): ?><?php echo e($brand['name']); ?><?php endif; ?>
            <small>OPERATIONS PLATFORM</small>
        </a>

        <nav class="sidebar-nav" aria-label="Main navigation">
            <a href="<?php echo e(route('dashboard')); ?>" class="nav-link nav-top <?php echo e(request()->routeIs('dashboard') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('dashboard'); ?><span>Dashboard</span></a>

            <div class="nav-group <?php echo e(request()->routeIs('products.*', 'patterns.*', 'casting-analyses.*', 'production-orders.*', 'moulding-runs.*', 'pours.*', 'work-lists.*', 'moulding-teams.*', 'mould-readiness.*') ? 'is-active' : ''); ?>">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab"><?php echo \App\Support\NavIcons::get('factory'); ?><span>Production</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="<?php echo e(route('products.index')); ?>" class="nav-link <?php echo e(request()->routeIs('products.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('box'); ?><span>Products</span></a>
                    <a href="<?php echo e(route('patterns.index')); ?>" class="nav-link <?php echo e(request()->routeIs('patterns.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('layers'); ?><span>Patterns</span></a>
                    <a href="<?php echo e(route('casting-analyses.index')); ?>" class="nav-link <?php echo e(request()->routeIs('casting-analyses.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('chart-line'); ?><span>Casting analysis</span></a>
                    <a href="<?php echo e(route('production-orders.index')); ?>" class="nav-link <?php echo e(request()->routeIs('production-orders.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('factory'); ?><span>Production orders</span></a>
                    <a href="<?php echo e(route('moulding-runs.index')); ?>" class="nav-link <?php echo e(request()->routeIs('moulding-runs.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('grid'); ?><span>Moulding runs</span></a>
                    <a href="<?php echo e(route('pours.index')); ?>" class="nav-link <?php echo e(request()->routeIs('pours.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('flame'); ?><span>Pour register</span></a>
                    <a href="<?php echo e(route('work-lists.index')); ?>" class="nav-link <?php echo e(request()->routeIs('work-lists.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('clipboard'); ?><span>Moulding work lists</span></a>
                    <a href="<?php echo e(route('moulding-teams.index')); ?>" class="nav-link <?php echo e(request()->routeIs('moulding-teams.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('users'); ?><span>Teams &amp; stations</span></a>
                    <a href="<?php echo e(route('mould-readiness.index')); ?>" class="nav-link <?php echo e(request()->routeIs('mould-readiness.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('check-circle'); ?><span>Mould readiness</span></a>
                </div>
            </div>

            <div class="nav-group <?php echo e(request()->routeIs('heats.*', 'furnaces.*', 'materials.*', 'charge-batches.*') ? 'is-active' : ''); ?>">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab"><?php echo \App\Support\NavIcons::get('flame'); ?><span>Melting &amp; Materials</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="<?php echo e(route('heats.index')); ?>" class="nav-link <?php echo e(request()->routeIs('heats.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('flask'); ?><span>Melting heats</span></a>
                    <a href="<?php echo e(route('furnaces.index')); ?>" class="nav-link <?php echo e(request()->routeIs('furnaces.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('zap'); ?><span>Furnaces</span></a>
                    <a href="<?php echo e(route('materials.index')); ?>" class="nav-link <?php echo e(request()->routeIs('materials.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('package'); ?><span>Materials</span></a>
                    <a href="<?php echo e(route('charge-batches.index')); ?>" class="nav-link <?php echo e(request()->routeIs('charge-batches.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('list-check'); ?><span>Charge recipes</span></a>
                </div>
            </div>

            <div class="nav-group <?php echo e(request()->routeIs('customers.*', 'enquiries.*', 'quotations.*', 'sales-orders.*', 'heat-plan.*') ? 'is-active' : ''); ?>">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab"><?php echo \App\Support\NavIcons::get('briefcase'); ?><span>Sales &amp; Planning</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="<?php echo e(route('customers.index')); ?>" class="nav-link <?php echo e(request()->routeIs('customers.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('building'); ?><span>Customers</span></a>
                    <a href="<?php echo e(route('enquiries.index')); ?>" class="nav-link <?php echo e(request()->routeIs('enquiries.*', 'quotations.*') && ! request()->routeIs('enquiries.wizard') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('file-text'); ?><span>Enquiries &amp; quotes</span></a>
                    <a href="<?php echo e(route('sales-orders.index')); ?>" class="nav-link <?php echo e(request()->routeIs('sales-orders.index', 'sales-orders.show', 'sales-orders.trace') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('receipt'); ?><span>Sales orders</span></a>
                    <a href="<?php echo e(route('heat-plan.index')); ?>" class="nav-link <?php echo e(request()->routeIs('heat-plan.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('calendar'); ?><span>Melting plan</span></a>
                </div>
            </div>

            <div class="nav-group <?php echo e(request()->routeIs('lots.*', 'inspections.*', 'ncrs.*', 'packing.*', 'dispatches.*', 'finished-goods.*', 'reports.*', 'defects.*') ? 'is-active' : ''); ?>">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab"><?php echo \App\Support\NavIcons::get('check-circle'); ?><span>Quality &amp; Dispatch</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="<?php echo e(route('lots.queue')); ?>" class="nav-link <?php echo e(request()->routeIs('lots.queue') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('list'); ?><span>Production queue</span></a>
                    <a href="<?php echo e(route('lots.index')); ?>" class="nav-link <?php echo e(request()->routeIs('lots.index', 'lots.show', 'lots.certificate') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('tag'); ?><span>Casting lots</span></a>
                    <a href="<?php echo e(route('finished-goods.index')); ?>" class="nav-link <?php echo e(request()->routeIs('finished-goods.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('archive'); ?><span>Finished goods</span></a>
                    <a href="<?php echo e(route('inspections.index')); ?>" class="nav-link <?php echo e(request()->routeIs('inspections.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('search-check'); ?><span>Inspections</span></a>
                    <a href="<?php echo e(route('ncrs.index')); ?>" class="nav-link <?php echo e(request()->routeIs('ncrs.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('alert'); ?><span>Nonconformance</span></a>
                    <a href="<?php echo e(route('packing.index')); ?>" class="nav-link <?php echo e(request()->routeIs('packing.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('package'); ?><span>Packing</span></a>
                    <a href="<?php echo e(route('dispatches.index')); ?>" class="nav-link <?php echo e(request()->routeIs('dispatches.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('truck'); ?><span>Dispatch</span></a>
                    <a href="<?php echo e(route('reports.yield')); ?>" class="nav-link <?php echo e(request()->routeIs('reports.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('bars'); ?><span>Yield &amp; scrap</span></a>
                    <a href="<?php echo e(route('defects.index')); ?>" class="nav-link <?php echo e(request()->routeIs('defects.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('bug'); ?><span>Defect list</span></a>
                </div>
            </div>

            <div class="nav-group <?php echo e(request()->routeIs('iron-grades.*', 'thermal.*', 'sand-tests.*') ? 'is-active' : ''); ?>">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab"><?php echo \App\Support\NavIcons::get('flask'); ?><span>Metallurgy</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="<?php echo e(route('iron-grades.index')); ?>" class="nav-link <?php echo e(request()->routeIs('iron-grades.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('hexagon'); ?><span>Iron grades</span></a>
                    <a href="<?php echo e(route('thermal.index')); ?>" class="nav-link <?php echo e(request()->routeIs('thermal.index', 'thermal.show') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('thermometer'); ?><span>Thermal tests</span></a>
                    <a href="<?php echo e(route('sand-tests.index')); ?>" class="nav-link <?php echo e(request()->routeIs('sand-tests.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('mountain'); ?><span>Sand lab</span></a>
                </div>
            </div>

            <div class="nav-group <?php echo e(request()->routeIs('tooling-maintenance.*', 'tooling-life.*') ? 'is-active' : ''); ?>">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab"><?php echo \App\Support\NavIcons::get('wrench'); ?><span>Equipment &amp; Tooling</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="<?php echo e(route('tooling-maintenance.dashboard')); ?>" class="nav-link <?php echo e(request()->routeIs('tooling-maintenance.dashboard') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('dashboard'); ?><span>Maintenance dashboard</span></a>
                    <a href="<?php echo e(route('tooling-maintenance.index')); ?>" class="nav-link <?php echo e(request()->routeIs('tooling-maintenance.index', 'tooling-maintenance.show', 'tooling-maintenance.edit') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('wrench'); ?><span>Maintenance records</span></a>
                    <a href="<?php echo e(route('tooling-life.dashboard')); ?>" class="nav-link <?php echo e(request()->routeIs('tooling-life.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('gauge'); ?><span>Tooling life</span></a>
                </div>
            </div>
            <div class="nav-group <?php echo e(request()->routeIs('hr.*', 'directory.*', 'leave.*') ? 'is-active' : ''); ?>">
    <button type="button" class="nav-group-toggle" aria-expanded="false">
        <span class="nav-lab"><?php echo \App\Support\NavIcons::get('users'); ?><span>People (HR)</span></span><span class="nav-chevron">›</span>
    </button>
    <div class="nav-submenu" hidden>
        <a href="<?php echo e(route('hr.dashboard')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.dashboard') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('users'); ?><span>People overview</span></a>
        <a href="<?php echo e(route('hr.employees.index')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.employees.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('id-card'); ?><span>Employees</span></a>
        <a href="<?php echo e(route('hr.attendance')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.attendance*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('clock'); ?><span>Attendance &amp; overtime</span></a>
        <a href="<?php echo e(route('hr.roster')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.roster*', 'hr.shifts.*', 'hr.holidays.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('calendar'); ?><span>Roster, shifts &amp; holidays</span></a>
        <a href="<?php echo e(route('hr.leave')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.leave*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('calendar-minus'); ?><span>Leave (HR)</span></a>
        <?php if(\App\Support\HrAccess::isPayroll()): ?><a href="<?php echo e(route('hr.payroll.index')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.payroll.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('wallet'); ?><span>Payroll</span></a><?php endif; ?>
        <a href="<?php echo e(route('leave.my')); ?>" class="nav-link <?php echo e(request()->routeIs('leave.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('calendar-minus'); ?><span>My leave &amp; approvals</span></a>
        <a href="<?php echo e(route('payslips.my')); ?>" class="nav-link <?php echo e(request()->routeIs('payslips.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('receipt'); ?><span>My payslips</span></a>
        <a href="<?php echo e(route('hr.orgchart')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.orgchart*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('network'); ?><span>Org chart</span></a>
        <a href="<?php echo e(route('directory.index')); ?>" class="nav-link <?php echo e(request()->routeIs('directory.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('network'); ?><span>Directory &amp; reporting lines</span></a>
        <a href="<?php echo e(route('hr.exits.index')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.exits.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('log-out'); ?><span>Exits &amp; clearance</span></a>
        <a href="<?php echo e(route('hr.policies.index')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.policies.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('book'); ?><span>HR policies</span></a>
        <a href="<?php echo e(route('hr.organisation')); ?>" class="nav-link <?php echo e(request()->routeIs('hr.organisation*', 'hr.checklist.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('building'); ?><span>Organisation setup</span></a>
    </div>
</div>
<div class="nav-group <?php echo e(request()->routeIs('settings.*', 'custom-fields.*', 'audit.*', 'users.*', 'roles.*', 'approval-policies.*') ? 'is-active' : ''); ?>">
    <button type="button" class="nav-group-toggle" aria-expanded="false">
        <span class="nav-lab"><?php echo \App\Support\NavIcons::get('settings'); ?><span>Administration</span></span>
        <span class="nav-chevron">›</span>
    </button>
    <div class="nav-submenu" hidden>
        <a href="<?php echo e(route('settings.edit')); ?>"
           class="nav-link <?php echo e(request()->routeIs('settings.*') && ! request()->routeIs('settings.display*', 'settings.quotation*', 'settings.codes*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('settings'); ?><span>Foundry settings</span></a>
        <a href="<?php echo e(route('custom-fields.index')); ?>"
           class="nav-link <?php echo e(request()->routeIs('custom-fields.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('sliders'); ?><span>Custom fields</span></a>
        <a href="<?php echo e(route('audit.index')); ?>"
           class="nav-link <?php echo e(request()->routeIs('audit.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('history'); ?><span>Audit trail</span></a>
        <a href="<?php echo e(route('approvals.index')); ?>" class="nav-link <?php echo e(request()->routeIs('approvals.*', 'approval-policies.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('inbox'); ?><span>Approvals</span></a>
        <a href="<?php echo e(route('settings.display')); ?>" class="nav-link <?php echo e(request()->routeIs('settings.display*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('monitor'); ?><span>Display</span></a>
        <a href="<?php echo e(route('imports.index')); ?>" class="nav-link <?php echo e(request()->routeIs('imports.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('upload'); ?><span>Import data</span></a>
        <a href="<?php echo e(route('settings.quotation')); ?>" class="nav-link <?php echo e(request()->routeIs('settings.quotation*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('percent'); ?><span>Quotation rates</span></a>
        <a href="<?php echo e(route('settings.codes')); ?>" class="nav-link <?php echo e(request()->routeIs('settings.codes*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('hash'); ?><span>Codes and marking</span></a>
        <a href="<?php echo e(route('users.index')); ?>" class="nav-link <?php echo e(request()->routeIs('users.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('user'); ?><span>Users</span></a>
        <a href="<?php echo e(route('roles.index')); ?>" class="nav-link <?php echo e(request()->routeIs('roles.*') ? 'active' : ''); ?>"><?php echo \App\Support\NavIcons::get('lock'); ?><span>Roles and permissions</span></a>
    </div>
</div>
        </nav>

        <?php if($brand['logo'] && $brand['position'] === 'BOTTOM_LEFT'): ?>
            <div style="margin-top:auto;padding:12px 12px 0"><img src="<?php echo e($brand['logo']); ?>" alt="<?php echo e($brand['name']); ?>" style="display:block;max-width:100%;height:<?php echo e($brand['height']); ?>px;object-fit:contain;object-position:left"></div>
        <?php endif; ?>
        <?php if(auth()->guard()->check()): ?>
            <?php ($me = auth()->user()); ?>
            <?php ($roleNames = $me->roles->pluck('name')->implode(', ') ?: 'No role'); ?>
            <div class="sidebar-profile">
                <a href="<?php echo e(route('account.password')); ?>" class="sp-link" title="Account and password">
                    <span class="sp-avatar" aria-hidden="true"><?php echo e(strtoupper(mb_substr(collect(preg_split('/\s+/', trim($me->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''), 0, 2))); ?></span>
                    <span class="sp-text"><strong><?php echo e($me->name); ?></strong><small><?php echo e($roleNames); ?></small></span>
                </a>
                <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button type="submit" class="sp-out" title="Sign out"><?php echo \App\Support\NavIcons::get('log-out'); ?><span class="sr-only">Sign out</span></button></form>
            </div>
        <?php else: ?>
            <div class="sidebar-footer" <?php if($brand['logo'] && $brand['position'] === 'BOTTOM_LEFT'): ?> style="margin-top:0" <?php endif; ?>>
                <?php if (! ($brand['logo'])): ?><?php echo e($brand['name']); ?><br><?php endif; ?>
                Production & shop-floor control
            </div>
        <?php endif; ?>

    </aside>
    <div class="sidebar-resizer" id="sidebar-resizer" role="separator" aria-orientation="vertical" aria-label="Drag to resize the menu (double-click to reset)" tabindex="0"></div>

    <div class="main-area">
        <header class="topbar">
            <div class="breadcrumb">
                Operations / <strong><?php echo $__env->yieldContent('breadcrumb', 'Dashboard'); ?></strong>
            </div>
            <form method="GET" action="<?php echo e(route('scan')); ?>" class="scan-box" style="margin-left:auto;margin-right:12px"><input id="scan-input" name="code" placeholder="Scan or find a code…" autocomplete="off"><kbd id="scan-hint" class="scan-hint" title="Press to jump to the search box">Ctrl K</kbd></form>
            <style>
                .scan-box{position:relative;display:inline-block}
                .scan-box input:not(#_){width:230px;min-height:24px;padding:2px 52px 2px 8px}
                .scan-hint{position:absolute;right:6px;top:50%;transform:translateY(-50%);font:600 10px/1 system-ui,sans-serif;color:#6b7280;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;padding:3px 5px;pointer-events:none}
                .scan-box input:focus + .scan-hint{opacity:0}
            </style>
            <script>
                (function () {
                    var i = document.getElementById('scan-input'), h = document.getElementById('scan-hint');
                    if (!i) return;
                    var mac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
                    h.textContent = mac ? '\u2318 K' : 'Ctrl K';
                    document.addEventListener('keydown', function (e) {
                        var t = e.target, typing = t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable);
                        var combo = (e.key || '').toLowerCase() === 'k' && (mac ? e.metaKey : e.ctrlKey);
                        if (combo || (e.key === '/' && !typing && !e.metaKey && !e.ctrlKey && !e.altKey)) {
                            e.preventDefault(); i.focus(); i.select();
                        }
                    });
                })();
            </script>
            <div class="topbar-date"><?php echo e(now()->format('D, d M Y')); ?>

            </div>
        </header>

        <main class="page-content">
            <?php if(session('success')): ?>
                <div class="alert alert-success" role="status">
                    <?php echo e(session('success')); ?>

                </div>
            <?php endif; ?>

            <?php if($errors->any()): ?>
                <div class="alert alert-error" role="alert">
                    <strong>Please correct the following:</strong>
                    <ul style="margin:7px 0 0;padding-left:20px">
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>
</div>

<script data-erp-form-behavior>
(() => {
    'use strict';

    function initialiseForm(form) {
        if (form.dataset.erpInitialised === 'true') return;
        form.dataset.erpInitialised = 'true';

        // Default only explicitly marked transaction dates to today's local date.
        form.querySelectorAll('input[type="date"][data-default-today]').forEach(input => {
            if (!input.value) {
                const now = new Date();
                const localDate = [
                    now.getFullYear(),
                    String(now.getMonth() + 1).padStart(2, '0'),
                    String(now.getDate()).padStart(2, '0')
                ].join('-');

                input.value = localDate;
            }
        });

        // Restore old() values after validation; autofocus the first invalid field.
        const invalid = form.querySelector(
            'input:invalid:not([type="hidden"]):not([disabled]), ' +
            'select:invalid:not([disabled]), textarea:invalid:not([disabled])'
        );

        const explicit = form.querySelector(
            '[data-autofocus]:not([disabled]), [autofocus]:not([disabled])'
        );

        const firstUsable = form.querySelector(
            'input:not([type="hidden"]):not([disabled]):not([readonly]), ' +
            'select:not([disabled]), textarea:not([disabled])'
        );

        const target = invalid || explicit || firstUsable;

        if (target) {
            // Defer until the page is rendered; don't steal focus from a dialog.
            requestAnimationFrame(() => {
                if (!document.querySelector('dialog[open], [role="dialog"]')) {
                    target.focus({ preventScroll: true });
                    if (target.select && target.tagName === 'INPUT' &&
                        ['text', 'search', 'tel', 'url'].includes(target.type)) {
                        target.select();
                    }
                }
            });
        }
    }

    function initialise() {
        document.querySelectorAll('form').forEach(initialiseForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
</script>


<script>
/* Foundry ERP sidebar submenu behavior */
document.addEventListener('DOMContentLoaded', () => {
    const groups = [...document.querySelectorAll('.sidebar-nav .nav-group')];

    function setOpen(group, open) {
        const button = group.querySelector('.nav-group-toggle');
        const submenu = group.querySelector('.nav-submenu');
        if (!button || !submenu) return;

        group.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', String(open));
        submenu.hidden = !open;
    }

    groups.forEach(group => {
        const button = group.querySelector('.nav-group-toggle');
        if (!button) return;

        setOpen(group, group.classList.contains('is-active'));

        button.addEventListener('click', () => {
            setOpen(group, !group.classList.contains('is-open'));
        });

        // group.addEventListener('mouseenter', event => {
        //     if (event.pointerType === 'touch') return;
        //     setOpen(group, true);
        // });

        // group.addEventListener('mouseleave', event => {
        //     if (event.pointerType === 'touch') return;
        //     if (group.classList.contains('is-active')) {
        //         setOpen(group, true);
        //     } else {
        //         setOpen(group, false);
        //     }
        // });

        // group.addEventListener('focusout', event => {
        //     if (!group.contains(event.relatedTarget) &&
        //         !group.classList.contains('is-active')) {
        //         setOpen(group, false);
        //     }
        // });
    });
});
</script>

<script>
(function () {
    var h = document.getElementById('sidebar-resizer'); if (!h) return;
    var root = document.documentElement, MIN = 200, MAX = 420;
    function cur() { return Math.round(document.querySelector('.sidebar').getBoundingClientRect().width); }
    function set(w) { w = Math.max(MIN, Math.min(MAX, Math.round(w))); root.style.setProperty('--side-w', w + 'px'); return w; }
    function save(w) { try { localStorage.setItem('erp.menuWidth', String(w)); } catch (e) {} }
    h.addEventListener('pointerdown', function (e) {
        e.preventDefault(); h.setPointerCapture(e.pointerId); h.classList.add('dragging'); document.body.classList.add('resizing-menu');
    });
    h.addEventListener('pointermove', function (e) { if (h.classList.contains('dragging')) set(e.clientX); });
    function end(e) {
        if (!h.classList.contains('dragging')) return;
        h.classList.remove('dragging'); document.body.classList.remove('resizing-menu');
        try { h.releasePointerCapture(e.pointerId); } catch (x) {}
        save(cur());
    }
    h.addEventListener('pointerup', end); h.addEventListener('pointercancel', end);
    h.addEventListener('dblclick', function () { root.style.removeProperty('--side-w'); try { localStorage.removeItem('erp.menuWidth'); } catch (e) {} });
    h.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault(); save(set(cur() + (e.key === 'ArrowRight' ? 16 : -16)));
    });
})();
</script>

<?php echo $__env->make("partials.tabs", array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make("partials.table-ui", array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make("partials.form-ui", array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make("partials.rowlink", array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/layouts/app.blade.php ENDPATH**/ ?>