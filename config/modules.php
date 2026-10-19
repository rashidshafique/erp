<?php

/**
 * Modules this installation is licensed for. Leave MODULES_LICENSED unset to license every module;
 * otherwise list the module names, comma separated, for example "maintenance,tooling".
 */
return [
    'licensed' => env('MODULES_LICENSED') === null ? null : array_values(array_filter(array_map('trim', explode(',', (string) env('MODULES_LICENSED'))))),
];
