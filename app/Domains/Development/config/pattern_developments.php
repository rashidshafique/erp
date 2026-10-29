<?php

/**
 * The kinds of pattern development (design 12a): what each is for, the stages it runs, what is taken from the records it opens
 * from, and a one-line note. A development runs its stages in order and is done when the last one is reached. `enquiry_stages`
 * counts the first stages the enquiry itself covers, which are skipped when the development opens on a won enquiry.
 */
return [
    'types' => [
        'NEW_PART' => [
            'name' => 'New part', 'sub' => 'New customer or first enquiry', 'enquiry_stages' => 1,
            'stages' => ['Enquiry review', 'Design discussion', 'Pattern build', 'Trial & sample', 'Approved'],
            'copies' => ['Customer and contact', 'Drawing from enquiry', 'Metal and quantity'],
            'note' => 'Feasibility runs during enquiry review. Creates the part record on approval.',
        ],
        'NEW_PRODUCT' => [
            'name' => 'New product', 'sub' => 'Existing customer, different part',
            'stages' => ['Design discussion', 'Pattern build', 'Trial & sample', 'Approved'],
            'copies' => ['Terms and pricing rules', 'Usual pattern maker', 'Open points from similar part'],
            'note' => 'Skips enquiry review; feasibility is optional for repeat customers.',
        ],
        'REPLACEMENT' => [
            'name' => 'Replacement pattern', 'sub' => 'Worn, damaged or beyond repair',
            'stages' => ['Cost approval', 'Pattern build', 'Trial & first-off', 'Old pattern retired'],
            'copies' => ['Part and drawing', 'Pattern construction', 'Owner', 'Last pattern maker', 'Life and refurb history'],
            'note' => 'No design discussion; same drawing.',
        ],
        'MODIFICATION' => [
            'name' => 'Modification', 'sub' => 'Drawing change on a running part',
            'stages' => ['Change review', 'Pattern modification', 'Trial & first-off', 'Approved'],
            'copies' => ['Part and drawing revisions', 'Pattern and core boxes', 'PPAP level from customer'],
            'note' => 'The pattern stays in use until the modified one passes first-off.',
        ],
        'CUSTOMER_PATTERN' => [
            'name' => 'Customer pattern', 'sub' => 'Customer supplies it or moves it from another foundry',
            'stages' => ['Pattern intake check', 'Rework if needed', 'Trial & first-off', 'Approved'],
            'copies' => ['Customer and contact', 'Drawing from enquiry', 'Pattern owner'],
            'note' => 'We check, advise and trial the pattern; the customer owns it.',
        ],
    ],
    'pattern_by' => ['DECIDE_LATER' => 'Decide later', 'IN_HOUSE' => 'In-house', 'SUBCONTRACT' => 'Subcontract', 'CUSTOMER_BUILT' => 'Customer builds', 'CUSTOMER_SUPPLIED' => 'Customer supplies'],
    'who_pays' => ['CUSTOMER' => 'Customer', 'SHARED' => 'Shared', 'FOUNDRY' => 'Foundry'],
    'reasons' => ['LIFE_LIMIT' => 'Life limit reached', 'WORN' => 'Worn', 'DAMAGED' => 'Damaged', 'BEYOND_REPAIR' => 'Beyond repair', 'OTHER' => 'Other'],
];
