<?php

/**
 * The ways a request reaches a foundry, with what each needs before it can be quoted and what normally follows.
 * Used by the new-request wizard and by the "What happens next" panel on an enquiry.
 * needs: product | drawing | tool | concept | sample | transfer | change (which questions the wizard asks)
 */
return [
    'REPEAT' => [
        'label' => 'Repeat of a part we already make', 'short' => 'Repeat',
        'blurb' => 'The customer wants more of a casting that is already in the system. Pick it and the approved drawing, weight and revision are used.',
        'needs' => ['product'], 'file_category' => 'CUSTOMER_DOCUMENT',
        'advice' => ['Confirm the approved revision is still the one the customer wants.', 'Check the price against the last order and today\'s metal and energy cost.'],
    ],
    'CHANGE' => [
        'label' => 'Change to a part we make', 'short' => 'Change',
        'blurb' => 'Same part, new drawing revision or new requirement. A new draft revision is opened when the quote is accepted, and the old one stays approved until the new one passes samples.',
        'needs' => ['product', 'change', 'drawing'], 'file_category' => 'DRAWING',
        'advice' => ['Compare the new drawing with the approved revision, and list what changed.', 'Decide whether the pattern and core boxes can be altered or new ones are needed.', 'The first castings of the new revision are sample castings and need the customer\'s approval.'],
    ],
    'DRAWING' => [
        'label' => 'New part from a drawing or CAD model', 'short' => 'Drawing / CAD',
        'blurb' => 'A drawing or 3-D model and no history with us. Upload the model and run the casting analysis before quoting.',
        'needs' => ['drawing'], 'file_category' => 'DRAWING',
        'advice' => ['Upload an STL or STEP model to the casting analyser for solidification, feeding and tooling.', 'Read the drawing for castability: walls, draft, machining allowance and tolerances.', 'Price tooling, then decide who pays for it.'],
    ],
    'SAMPLE' => [
        'label' => 'Customer sample, no drawing', 'short' => 'Sample',
        'blurb' => 'The customer sends or describes a physical part. There is nothing to analyse until it is scanned or measured.',
        'needs' => ['sample'], 'file_category' => 'PHOTO',
        'advice' => ['Photograph, weigh and measure the sample, or have it scanned to STL.', 'Identify the material (chemistry and hardness) before promising a grade.', 'Agree who owns the drawing that results, and get the customer to approve it.'],
    ],
    'CUSTOMER_TOOL' => [
        'label' => 'Customer supplies the pattern or tooling', 'short' => 'Customer tool',
        'blurb' => 'The customer owns the pattern, core box or die. It is their property, so condition on arrival and ownership are recorded.',
        'needs' => ['tool', 'drawing'], 'file_category' => 'PHOTO',
        'advice' => ['Inspect the tooling on arrival and record its condition with photos.', 'Check it suits our moulding line: flask size, plate fit, draft and gating.', 'Agree responsibility for wear, repair and return.'],
    ],
    'CONCEPT' => [
        'label' => 'Concept only, no drawing yet', 'short' => 'Concept',
        'blurb' => 'The customer describes the function. Capture the requirements now; the part design comes before any price.',
        'needs' => ['concept'], 'file_category' => 'CUSTOMER_DOCUMENT',
        'advice' => ['Agree the design inputs: load, environment, standards and quantities.', 'Offer design-for-casting advice, then a rough budget price marked as an estimate.', 'Do not issue a firm quotation until there is a drawing or model.'],
    ],
    'TRANSFER' => [
        'label' => 'Transfer from another foundry', 'short' => 'Transfer',
        'blurb' => 'A running part moving to us. Ask why it is moving and what goes wrong at the current supplier.',
        'needs' => ['transfer', 'drawing'], 'file_category' => 'DRAWING',
        'advice' => ['Get the current drawing, inspection reports and the scrap history.', 'Ask whether the pattern comes with the job and who owns it.', 'First castings are sample castings and need the customer\'s approval before production.'],
    ],
];
