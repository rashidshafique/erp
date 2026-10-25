<?php

/**
 * What people may do. Roles hold permissions (editable on the Roles screen); routes map to permissions here.
 * Reading any page needs only a login, except where a read permission is listed. Changing anything needs the
 * mapped permission; a change route that is not mapped is allowed for administrators only.
 */
return [
    'catalog' => [
        'sales.manage' => 'Customers and sales orders',
        'planning.manage' => 'Production orders, melting plan, charge recipes',
        'melting.operate' => 'Heats, furnaces, chemistry, thermal analysis, treatment',
        'pouring.operate' => 'Pours',
        'moulding.operate' => 'Moulding runs, work lists, teams, sand lab',
        'shopfloor.record' => 'Record stage output and hold/release on lots',
        'quality.inspect' => 'Inspections and test results',
        'quality.manage' => 'NCRs, test limits, defect list, iron grades',
        'stores.manage' => 'Materials and stock',
        'master.manage' => 'Products and patterns',
        'tooling.manage' => 'Tooling maintenance and life',
        'attachments.manage' => 'Attach and remove files',
        'documents.sensitive' => 'See and attach ID documents and contracts',
        'approvals.decide' => 'Decide approval requests',
        'approvals.configure' => 'Set which actions need approval',
        'settings.manage' => 'Settings and custom fields',
        'users.manage' => 'Users and roles',
        'audit.view' => 'View the audit trail',
    ],

    // Open to every signed-in user; the screens check who may do what (own leave, a team's leave).
    'open_routes' => ['help.*'],

    // First match wins. Patterns use * as a wildcard.
    'routes' => [
        ['pattern' => 'users.*', 'permission' => 'users.manage', 'reads' => true],
        ['pattern' => 'roles.*', 'permission' => 'users.manage', 'reads' => true],
        ['pattern' => 'audit.*', 'permission' => 'audit.view', 'reads' => true],
        ['pattern' => 'settings.*', 'permission' => 'settings.manage', 'reads' => true],
        ['pattern' => 'custom-fields.index', 'permission' => 'settings.manage', 'reads' => true],
        ['pattern' => 'custom-fields.store', 'permission' => 'settings.manage'],
        ['pattern' => 'custom-fields.update', 'permission' => 'settings.manage'],
        ['pattern' => 'custom-fields.values', 'permission' => 'attachments.manage'],
        ['pattern' => 'process-controls.*', 'permission' => 'settings.manage'],
        ['pattern' => 'approval-policies.*', 'permission' => 'approvals.configure', 'reads' => true],
        ['pattern' => 'approvals.decide', 'permission' => 'approvals.decide'],
        ['pattern' => 'approvals.*', 'permission' => 'attachments.manage'],
        ['pattern' => 'attachments.*', 'permission' => 'attachments.manage'],
        ['pattern' => 'imports.*', 'permission' => 'master.manage', 'reads' => true],
        ['pattern' => 'customers.*', 'permission' => 'sales.manage'],
        ['pattern' => 'calendar.*', 'permission' => 'planning.manage', 'reads' => true],
        ['pattern' => 'heat-plan.*', 'permission' => 'planning.manage'],
        ['pattern' => 'planner.*', 'permission' => 'planning.manage'],
        ['pattern' => 'production-orders.*', 'permission' => 'planning.manage'],
        ['pattern' => 'charge-batches.*', 'permission' => 'planning.manage'],
        ['pattern' => 'heats.*', 'permission' => 'melting.operate'],
        ['pattern' => 'thermal.*', 'permission' => 'melting.operate'],
        ['pattern' => 'furnaces.*', 'permission' => 'melting.operate'],
        ['pattern' => 'pours.*', 'permission' => 'pouring.operate'],
        ['pattern' => 'moulding-runs.*', 'permission' => 'moulding.operate'],
        ['pattern' => 'work-lists.*', 'permission' => 'moulding.operate'],
        ['pattern' => 'moulding-teams.*', 'permission' => 'moulding.operate'],
        ['pattern' => 'sand-systems.*', 'permission' => 'settings.manage', 'reads' => true],
        ['pattern' => 'sand-tests.*', 'permission' => 'moulding.operate'],
        ['pattern' => 'lots.*', 'permission' => 'shopfloor.record'],
        ['pattern' => 'inspections.*', 'permission' => 'quality.inspect'],
        ['pattern' => 'ncrs.*', 'permission' => 'quality.manage'],
        ['pattern' => 'limits.*', 'permission' => 'quality.manage'],
        ['pattern' => 'defects.*', 'permission' => 'quality.manage'],
        ['pattern' => 'iron-grades.*', 'permission' => 'quality.manage'],
        ['pattern' => 'materials.*', 'permission' => 'stores.manage'],
        ['pattern' => 'stock-*', 'permission' => 'stores.manage'],
        ['pattern' => 'casting-analyses.*', 'permission' => 'master.manage'],
        ['pattern' => 'products.*', 'permission' => 'master.manage'],
        ['pattern' => 'patterns.*', 'permission' => 'master.manage'],
        ['pattern' => 'pattern-ownership.*', 'permission' => 'master.manage'],
        ['pattern' => 'pattern-revisions.*', 'permission' => 'master.manage'],
        ['pattern' => 'flasks.*', 'permission' => 'tooling.manage'],
        ['pattern' => 'flask-sizes.*', 'permission' => 'tooling.manage'],
    ],

    // Where someone lands after signing in, by permission. First entry they qualify for wins; everyone else gets the
    // dashboard. An entry with a module is skipped while that module is switched off or unlicensed.
    'home' => [
        ['permission' => 'melting.operate', 'route' => 'heats.index'],
        ['permission' => 'moulding.operate', 'route' => 'quick.floor.index', 'module' => 'quickflow'],
        ['permission' => 'moulding.operate', 'route' => 'moulding-runs.index'],
        ['permission' => 'shopfloor.record', 'route' => 'lots.queue'],
        ['permission' => 'quality.inspect', 'route' => 'lots.queue'],
        ['permission' => 'sales.manage', 'route' => 'sales-orders.index', 'module' => 'sales'],
        ['permission' => 'stores.manage', 'route' => 'stock-movements.index'],
    ],

    // Starting permissions per role. ADMIN always has everything.
    'roles' => [
        'ADMIN' => ['name' => 'Administrator', 'permissions' => ['*']],
        'SALES' => ['name' => 'Sales', 'permissions' => ['sales.manage', 'attachments.manage']],
        'PRODUCTION_MANAGER' => ['name' => 'Production manager', 'permissions' => ['planning.manage', 'moulding.operate', 'pouring.operate', 'shopfloor.record', 'packing.operate', 'dispatch.operate', 'attachments.manage', 'approvals.decide']],
        'MELT_SUPERVISOR' => ['name' => 'Melt shop supervisor', 'permissions' => ['melting.operate', 'pouring.operate', 'shopfloor.record', 'attachments.manage']],
        'MOULDING_SUPERVISOR' => ['name' => 'Moulding supervisor', 'permissions' => ['moulding.operate', 'shopfloor.record', 'attachments.manage']],
        'QUALITY_INSPECTOR' => ['name' => 'Quality inspector', 'permissions' => ['quality.inspect', 'shopfloor.record', 'attachments.manage']],
        'QUALITY_MANAGER' => ['name' => 'Quality manager', 'permissions' => ['quality.inspect', 'quality.manage', 'shopfloor.record', 'attachments.manage', 'approvals.decide', 'approvals.configure', 'audit.view']],
        'STORES_DISPATCH' => ['name' => 'Stores and dispatch', 'permissions' => ['stores.manage', 'packing.operate', 'dispatch.operate', 'attachments.manage']],
        'VIEWER' => ['name' => 'View only', 'permissions' => []],
    ],
];
