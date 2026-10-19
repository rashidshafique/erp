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
        'pattern.scrap_customer_pattern' => "Scrap a customer's pattern",
        'pattern.approve_revision' => 'Approve a pattern revision',
    ],
];
