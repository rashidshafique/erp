<?php

namespace App\Services\Stock;

use RuntimeException;

/** Thrown when a lot, or a location within it, holds less than the quantity asked for. */
class InsufficientStockException extends RuntimeException {}
