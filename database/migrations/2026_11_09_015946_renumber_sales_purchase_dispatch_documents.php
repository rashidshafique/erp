<?php

use App\Support\DocumentNumbers;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const TYPES = ['enquiry', 'quotation', 'sales_order', 'dispatch', 'packing', 'purchase_order'];

    /** Enquiries E-, quotations Q-, sales orders S-, dispatches D-, packing K- and purchase orders P- from 10000, in date order. */
    public function up(): void
    {
        DocumentNumbers::renumberTypes(self::TYPES);
    }

    public function down(): void
    {
        DocumentNumbers::restoreTypes(self::TYPES);
    }
};
