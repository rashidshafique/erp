<?php

namespace App\Domains\Finance\Models;

use Database\Factories\InvoiceTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A print layout built on the template builder. Its key on an invoice is "custom:" and its id. */
class InvoiceTemplate extends Model
{
    use HasFactory, HasUuids;

    public const APPLIES = ['BOTH' => 'Invoices and credit notes', 'INVOICE' => 'Invoices only', 'CREDIT_NOTE' => 'Credit notes only'];

    protected $fillable = ['name', 'applies_to', 'layout', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['layout' => 'array', 'is_active' => 'boolean'];
    }

    protected static function newFactory(): Factory
    {
        return InvoiceTemplateFactory::new();
    }

    public function key(): string
    {
        return 'custom:'.$this->id;
    }

    /** Whether any invoice or credit note prints with this layout, so it can only be switched off. */
    public function isInUse(): bool
    {
        return Invoice::where('print_template', $this->key())->exists();
    }

    public function appliesTo(bool $creditNote): bool
    {
        return $this->applies_to === 'BOTH' || $this->applies_to === ($creditNote ? 'CREDIT_NOTE' : 'INVOICE');
    }
}
