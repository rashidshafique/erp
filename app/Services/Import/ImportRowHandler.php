<?php

namespace App\Services\Import;

/**
 * Checks and writes the rows of an import entity that is not one record per code, such as the cavities of a pattern.
 * Rows use the same shape as the standard import so the preview, progress and report screens show them unchanged.
 */
interface ImportRowHandler
{
    /**
     * @param  array<int, array{0:int, 1:array}>  $rows  sheet row number and its cells
     * @return array{0: array<int, array>, 1: array<string, array<string, int>>} the checked rows, unrecognised values
     */
    public function check(array $def, array $rows, array $mapping, array $options): array;

    /** Writes one row whose action is CREATE or UPDATE. Runs inside a transaction; throwing marks the row as an error. */
    public function save(array &$row): void;
}
