<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Http\Controllers\AttachmentController;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Product;
use App\Support\AttachableEntities;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves the enquiry intake page (design 1b) for a new enquiry and for an open one: the customer (an existing one, or a new one made
 * on the spot), the kind of request with its note, every part with its volumes and quality requirements, and the files.
 * A draft only needs the customer and the kind. Sending it to feasibility, or saving one already past draft, checks everything the
 * kind needs: the note, which of our parts it is for a repeat or a change, and a drawing number or a file for new work.
 */
class EnquiryIntake
{
    /** The question each kind asks in its note box, where it asks one. Stored in the enquiry summary. */
    public const NOTES = [
        'CHANGE' => 'What has changed?',
        'CUSTOMER_TOOL' => 'Tooling they will send',
        'CONCEPT' => 'What does the part have to do?',
        'TRANSFER' => 'Where is it made today, and why move it?',
        'SAMPLE' => 'Describe the sample',
    ];

    /** Kinds that work from one of our own parts. */
    public const OWN_PART = ['REPEAT', 'CHANGE'];

    /**
     * Creates the enquiry, or saves an open one, from the page. $action is draft, send or save.
     *
     * @throws ValidationException
     */
    public function save(Request $request, ?Enquiry $enquiry = null): Enquiry
    {
        $action = in_array($request->input('action'), ['draft', 'send', 'save'], true) ? $request->input('action') : ($enquiry ? 'save' : 'send');
        $full = $action === 'send' || ($action === 'save' && $enquiry && $enquiry->status !== 'NEW');
        $d = $request->validate($this->rules($request, $enquiry, $full), $this->messages());
        $src = $d['source_type'];
        $parts = collect($d['items'] ?? [])->map(fn (array $row, $key) => $row + ['_key' => $key]);
        $kept = $parts->reject(fn ($row) => ! empty($row['remove']));
        $filesGiven = collect($request->file('files', []))->filter()->isNotEmpty();

        if ($full) {
            $this->checkComplete($src, $d, $kept, $filesGiven, $enquiry);
        }
        if (($d['customer_mode'] ?? 'EXISTING') === 'NEW' && Customer::whereHas('party', fn ($q) => $q->whereRaw('lower(name) = ?', [Str::lower(trim($d['new_name']))]))->exists()) {
            throw ValidationException::withMessages(['new_name' => 'A customer with this name already exists. Choose it from the list instead.']);
        }
        if (($d['customer_mode'] ?? 'EXISTING') === 'NEW' && in_array($src, self::OWN_PART, true)) {
            throw ValidationException::withMessages(['source_type' => 'A new customer has no parts of ours yet. Choose a new part kind of request.']);
        }
        $this->checkRemovals($parts, $enquiry);

        return DB::transaction(function () use ($d, $request, $enquiry, $action, $src, $parts, $kept, $filesGiven) {
            $customer = ($d['customer_mode'] ?? 'EXISTING') === 'NEW' ? $this->newCustomer($d) : Customer::findOrFail($d['customer_id']);
            $first = $kept->first();
            $title = trim((string) ($d['title'] ?? ''));
            if ($title === '') {
                $title = $first && filled($first['description'] ?? null)
                    ? Str::limit($first['description'], 150, '').' ('.config('enquiry_scenarios.'.$src.'.short').')'
                    : ($enquiry?->title ?? config('enquiry_scenarios.'.$src.'.short').' request');
            }
            $fields = [
                'customer_id' => $customer->id, 'title' => $title, 'source_type' => $src,
                'due_on' => $d['due_on'] ?? null, 'customer_reference' => $d['customer_reference'] ?? null,
                'contact_name' => ($d['contact_name'] ?? null) ?: ($enquiry ? null : $customer->contact_name),
                'summary' => $d['summary'] ?? null,
                'owner_id' => ($d['owner_id'] ?? null) ?: ($enquiry?->owner_id ?? Auth::id()),
            ];
            if ($enquiry) {
                $enquiry->update($fields);
            } else {
                $enquiry = Enquiry::create($fields + [
                    'status' => 'NEW', 'received_on' => $d['received_on'] ?? now()->toDateString(),
                    'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
                ]);
            }

            $this->syncParts($enquiry, $parts, $src);

            if ($filesGiven) {
                $saved = 0;
                $error = AttachmentController::saveFiles(array_values(array_filter($request->file('files'))), 'enquiry', $enquiry->id, $d['file_category'] ?? config('enquiry_scenarios.'.$src.'.file_category'), null, $saved);
                if ($error) {
                    throw ValidationException::withMessages(['files' => $error]);
                }
            }

            $created = $enquiry->wasRecentlyCreated;
            if ($created) {
                EnquiryEvent::system($enquiry, 'Received'.(filled($enquiry->customer_reference) ? ' as '.$enquiry->customer_reference : '').'.');
            }
            if ($action === 'send' && $enquiry->status === 'NEW') {
                $enquiry->update(['status' => 'FEASIBILITY']);
                EnquiryEvent::system($enquiry, 'Sent to feasibility.');
            }

            return $enquiry->fresh(['customer', 'items']);
        });
    }

    /** @return array<string, list<mixed>> */
    private function rules(Request $request, ?Enquiry $enquiry, bool $full): array
    {
        $maxKb = config('foundry.attachments.max_kb');
        $exts = config('foundry.attachments.extensions');
        $item = fn (string $rule) => 'items.*.'.$rule;
        $rules = [
            'customer_mode' => ['nullable', Rule::in(['EXISTING', 'NEW'])],
            'customer_id' => ['required_unless:customer_mode,NEW', 'nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'new_name' => ['required_if:customer_mode,NEW', 'nullable', 'string', 'max:200'],
            'new_contact' => ['nullable', 'string', 'max:120'], 'new_phone' => ['nullable', 'string', 'max:40'],
            'new_email' => ['nullable', 'email', 'max:160'], 'new_terms' => ['nullable', 'string', 'max:100'],
            'source_type' => ['required', Rule::in(array_keys(Enquiry::SOURCES))],
            'title' => ['nullable', 'string', 'max:200'],
            'received_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'summary' => ['nullable', 'string', 'max:4000'],
            'items' => [$full ? 'required' : 'nullable', 'array', 'max:50'],
            $item('id') => ['nullable', 'uuid'],
            $item('remove') => ['nullable', 'boolean'],
            $item('description') => ['required_without:'.$item('remove'), 'nullable', 'string', 'max:200'],
            $item('product_id') => ['nullable', 'exists:products,id'],
            $item('drawing_ref') => ['nullable', 'string', 'max:100'],
            $item('drawing_rev') => ['nullable', 'string', 'max:20'],
            $item('material_text') => ['nullable', 'string', 'max:200'],
            $item('material_new') => ['nullable', 'string', 'max:200'],
            $item('family') => ['nullable', Rule::in(['GREY', 'DUCTILE'])],
            $item('weight_kg') => ['nullable', 'numeric', 'min:0', 'max:100000'],
            $item('annual_qty') => ['nullable', 'integer', 'min:0'],
            $item('first_batch_qty') => ['nullable', 'integer', 'min:0'],
            $item('target_price') => ['nullable', 'numeric', 'min:0'],
            $item('heat_treatment') => ['nullable', 'boolean'],
            $item('ndt') => ['nullable', 'boolean'],
            $item('cores_count') => ['nullable', 'integer', 'min:0', 'max:200'],
            $item('machining_cost') => ['nullable', 'numeric', 'min:0'],
            $item('other_cost') => ['nullable', 'numeric', 'min:0'],
            $item('tooling_cost') => ['nullable', 'numeric', 'min:0'],
            $item('notes') => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.$maxKb, 'extensions:'.implode(',', $exts)],
            'file_category' => ['nullable', Rule::in(array_keys(AttachableEntities::CATEGORIES))],
        ];
        foreach (EnquiryItem::intakeRules() as $key => $rule) {
            $rules[$key === 'quality.*' ? 'items.*.quality.*' : $item($key)] = $rule;
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        $maxKb = config('foundry.attachments.max_kb');
        $exts = config('foundry.attachments.extensions');

        return [
            'customer_id.required_unless' => 'Choose the customer.',
            'new_name.required_if' => 'Enter the company name.',
            'source_type.required' => 'Choose the kind of request.',
            'items.required' => 'Add the part the customer wants made.',
            'items.*.description.required_without' => 'Name each part.',
            'files.*.max' => 'Each file may be at most '.Uploads::megabytes($maxKb).'.', 'files.*.uploaded' => Uploads::failedMessage(),
            'files.*.extensions' => 'That file type is not allowed. Allowed: '.implode(', ', $exts).'.',
        ];
    }

    /**
     * What the kind needs before the enquiry can go to feasibility.
     *
     * @throws ValidationException
     */
    private function checkComplete(string $src, array $d, $kept, bool $filesGiven, ?Enquiry $enquiry): void
    {
        $errors = [];
        if ($kept->isEmpty()) {
            $errors['items'] = 'Add the part the customer wants made.';
        }
        if (isset(self::NOTES[$src]) && blank($d['summary'] ?? null)) {
            $errors['summary'] = self::NOTES[$src].' Write it in the box under the kind of request.';
        }
        $needsDrawing = in_array('drawing', config('enquiry_scenarios.'.$src.'.needs', []), true) && $src !== 'CHANGE';
        $hasFiles = $filesGiven || ($enquiry && Attachment::where('attachable_type', 'enquiry')->where('attachable_id', $enquiry->id)->exists());
        foreach ($kept as $row) {
            $k = $row['_key'];
            if (in_array($src, self::OWN_PART, true) && blank($row['product_id'] ?? null)) {
                $errors["items.$k.product_id"] = 'Choose which of our parts it is.';
            }
            if (($row['material_text'] ?? null) === '__new__' && blank($row['material_new'] ?? null)) {
                $errors["items.$k.material_new"] = 'Enter the new material grade.';
            }
            if (in_array('PPAP', (array) ($row['quality'] ?? []), true) && blank($row['ppap_level'] ?? null)) {
                $errors["items.$k.ppap_level"] = 'Choose the PPAP level.';
            }
            if ($needsDrawing && blank($row['drawing_ref'] ?? null) && ! $hasFiles) {
                $errors["items.$k.drawing_ref"] = 'Give the drawing number, or attach the drawing or model.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A part on a live quotation cannot be removed.
     *
     * @throws ValidationException
     */
    private function checkRemovals($parts, ?Enquiry $enquiry): void
    {
        if (! $enquiry) {
            return;
        }
        foreach ($parts->filter(fn ($row) => ! empty($row['remove']) && ! empty($row['id'])) as $row) {
            $live = $enquiry->quotations()->whereIn('status', ['DRAFT', 'ISSUED'])->whereHas('lines', fn ($q) => $q->where('enquiry_item_id', $row['id']))->exists();
            if ($live) {
                throw ValidationException::withMessages(["items.{$row['_key']}.remove" => 'Part '.($row['description'] ?? '').' is on a live quotation. Reject or supersede that quotation first.']);
            }
        }
    }

    private function syncParts(Enquiry $enquiry, $parts, string $src): void
    {
        $existing = $enquiry->items()->get()->keyBy('id');
        $line = (int) $enquiry->items()->max('line_no');
        foreach ($parts as $row) {
            $item = ! empty($row['id']) ? $existing->get($row['id']) : null;
            if (! empty($row['remove'])) {
                $item?->delete();

                continue;
            }
            $data = $this->partData($row, $src);
            if ($item) {
                $item->update($data);
            } else {
                $enquiry->items()->create($data + ['line_no' => ++$line, 'feasibility_status' => 'PENDING']);
            }
        }
    }

    /** @return array<string, mixed> */
    private function partData(array $row, string $src): array
    {
        $intake = EnquiryItem::intakeData(collect($row)->only(array_keys(EnquiryItem::intakeRules()))->all());
        $product = ! empty($row['product_id']) ? Product::find($row['product_id']) : null;
        $material = ($row['material_text'] ?? null) === '__new__' ? ($row['material_new'] ?? null) : ($row['material_text'] ?? null);

        return $intake + [
            'description' => $row['description'], 'product_id' => $product?->id,
            'drawing_ref' => $row['drawing_ref'] ?? null, 'drawing_rev' => $row['drawing_rev'] ?? null,
            'material_text' => $material, 'family' => $row['family'] ?? null,
            'weight_kg' => ($row['weight_kg'] ?? null) ?: $product?->nominal_weight_kg,
            'annual_qty' => $row['annual_qty'] ?? null, 'first_batch_qty' => $row['first_batch_qty'] ?? null, 'target_price' => $row['target_price'] ?? null,
            'heat_treatment' => ! empty($row['heat_treatment']), 'ndt' => ! empty($row['ndt']),
            'cores_count' => $row['cores_count'] ?? null,
            'machining_cost' => $row['machining_cost'] ?? 0, 'other_cost' => $row['other_cost'] ?? 0, 'tooling_cost' => $row['tooling_cost'] ?? 0,
            'notes' => ($row['notes'] ?? null) ?: ($src === 'CUSTOMER_TOOL' ? 'Customer-owned tooling' : null),
        ];
    }

    private function newCustomer(array $d): Customer
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($d['new_name'])), 0, 4)) ?: 'CUST';
        $code = $base;
        for ($n = 1; Customer::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        }

        return Customer::create([
            'code' => $code, 'name' => trim($d['new_name']), 'contact_name' => $d['new_contact'] ?? null,
            'phone' => $d['new_phone'] ?? null, 'email' => $d['new_email'] ?? null, 'payment_terms' => $d['new_terms'] ?? null,
            'active' => true, 'is_prospect' => true,
        ]);
    }
}
