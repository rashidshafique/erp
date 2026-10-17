<?php

/** Actions that can be put behind an approval. Policies (on/off, how many approvers) are edited on screen. */
return [
    'actions' => [
        'production_order.release' => 'Release a production order',
        'ncr.close' => 'Close a nonconformance report',
        'lot.release_hold' => 'Release a lot from hold',
        'dispatch.create' => 'Dispatch castings to a customer',
        'limits.change' => 'Add, change or remove a test limit',
        'quotation.issue_low_margin' => 'Issue a quotation below the minimum margin',
        'hr.confirm_employee' => 'Confirm an employee after probation',
        'hr.transfer_employee' => 'Transfer or promote an employee',
        'hr.finalise_exit' => 'Complete an employee exit',
        'hr.approve_payroll' => 'Approve a payroll run',
        'hr.approve_advance' => 'Approve an advance or loan',
        'hr.approve_settlement' => 'Approve a final settlement',
        'pattern.scrap_customer_pattern' => "Scrap a customer's pattern",
    ],
];
