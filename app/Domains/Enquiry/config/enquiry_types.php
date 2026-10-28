<?php

/**
 * The boxes at the top of the new-request dialog, laid out as the development dialog of design 12a. Each one settles the kind of
 * request (a key of enquiry_scenarios): "source" fixes it, or "gives" lists the kinds to choose from by what the customer gives us.
 * "existing" picks Existing customer.
 */
return [
    'NEW_PART' => ['name' => 'New part', 'sub' => 'New customer or first enquiry', 'gives' => ['DRAWING', 'SAMPLE', 'CONCEPT', 'CUSTOMER_TOOL', 'TRANSFER']],
    'NEW_PRODUCT' => ['name' => 'New product', 'sub' => 'Existing customer, different part', 'gives' => ['DRAWING', 'SAMPLE', 'CONCEPT', 'CUSTOMER_TOOL', 'TRANSFER'], 'existing' => true],
    'MODIFICATION' => ['name' => 'Modification', 'sub' => 'Drawing change on a running part', 'source' => 'CHANGE', 'existing' => true],
    'REPEAT' => ['name' => 'Repeat', 'sub' => 'More of a part we already make', 'source' => 'REPEAT', 'existing' => true],
];
