<?php

namespace App\Domains\Ledger\Livewire;

use App\Domains\Ledger\Models\BankAccount;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\DefaultChart;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Models\AppSetting;
use Illuminate\Support\Collection;

/**
 * The new and edit dialogs for a nominal account or a section (design 19i to 19l), shared by the chart and the sections list.
 * Codes are automatic and never change; what is locked follows the postings, as the design describes.
 */
trait ChartDialog
{
    /** @var array{kind?: string, target?: string, cat?: string, sec?: string, name?: string, desc?: string, dc?: bool, inactive?: bool, line?: string} */
    public array $m = [];

    public string $toast = '';

    public string $toastTone = 'ok';

    public function openAccount(string $section = ''): void
    {
        $this->m = ['kind' => 'acc', 'cat' => $section ? substr($section, 0, 2) : '', 'sec' => $section, 'name' => '', 'desc' => '', 'dc' => false];
    }

    public function openAccountEdit(string $id): void
    {
        $a = LedgerAccount::findOrFail($id);
        $this->m = ['kind' => 'accEdit', 'target' => $a->id, 'name' => $a->name, 'desc' => (string) $a->description, 'dc' => $a->is_charge, 'inactive' => ! $a->is_active];
    }

    public function openSection(string $category = ''): void
    {
        $this->m = ['kind' => 'sec', 'cat' => $category, 'name' => '', 'line' => ''];
    }

    public function openSectionEdit(string $id): void
    {
        $s = LedgerAccount::findOrFail($id);
        $this->m = ['kind' => 'secEdit', 'target' => $s->id, 'name' => $s->name, 'line' => (string) $s->report_line];
    }

    public function closeDialog(): void
    {
        $this->m = [];
    }

    public function updatedMCat(): void
    {
        $this->m['sec'] = '';
        $this->m['line'] = '';
    }

    public function toggleCharge(): void
    {
        $this->m['dc'] = ! ($this->m['dc'] ?? false);
    }

    public function setInactive(bool $inactive): void
    {
        $d = $this->dialog();
        if ($inactive && ! $d['canInactive']) {
            return;
        }
        $this->m['inactive'] = $inactive;
    }

    public function saveDialog(): void
    {
        $d = $this->dialog();
        if (! $d['canSave']) {
            return;
        }
        $kind = $this->m['kind'];
        $name = $d['nameTrim'];
        if ($kind === 'acc') {
            LedgerAccount::create(['code' => $d['code'], 'name' => $name, 'parent_id' => $d['section']->id, 'type' => DefaultChart::typeOf($d['code']), 'description' => trim($this->m['desc'] ?? '') ?: null, 'is_charge' => (bool) ($this->m['dc'] ?? false)]);
            $this->opened($d['code']);
            $this->say('Added '.$d['code'].' '.$name.'.');
        } elseif ($kind === 'sec') {
            $category = $d['category'];
            LedgerAccount::create(['code' => $d['code'], 'name' => $name, 'parent_id' => $category->id, 'type' => DefaultChart::typeOf($d['code']), 'report_line' => $this->m['line'], 'sort_order' => (int) $category->children()->max('sort_order') + 1]);
            $this->opened($d['code']);
            $this->say('Added section '.$d['code'].' '.$name.'.');
        } elseif ($kind === 'accEdit') {
            $a = $d['account'];
            $a->update(['name' => $name, 'description' => trim($this->m['desc'] ?? '') ?: null, 'is_charge' => (bool) ($this->m['dc'] ?? false), 'is_active' => ! ($this->m['inactive'] ?? false)]);
            $this->say('Saved '.$a->code.'.');
        } else {
            $s = $d['sectionEdit'];
            $s->update(['name' => $name] + ($d['lineLocked'] ? [] : ['report_line' => $this->m['line']]));
            $this->say('Saved section '.$s->code.'.');
        }
        $this->m = [];
    }

    public function deleteFromDialog(): void
    {
        $d = $this->dialog();
        if (! $d['canDelete']) {
            return;
        }
        $target = $d['account'] ?? $d['sectionEdit'];
        $target->delete();
        $this->m = [];
        $this->say('Deleted '.$target->code.'.');
    }

    /** Hook for the chart to open the new code's section; the sections list has nothing to open. */
    protected function opened(string $code): void {}

    protected function say(string $text, string $tone = 'ok'): void
    {
        $this->toast = $text;
        $this->toastTone = $tone;
    }

    /**
     * Why an account cannot be deleted, or null when it can: never posted to, not a control account, not a bank account and not
     * named by a posting rule.
     */
    protected function deleteBlock(LedgerAccount $a, bool $posted): ?string
    {
        if ($posted || $a->control) {
            return 'has postings or a balance. Deactivate it instead.';
        }
        if (BankAccount::where('account_id', $a->id)->exists()) {
            return 'it holds bank details. Deactivate it instead.';
        }
        if (in_array($a->id, $this->ruleAccountIds(), true)) {
            return 'a posting rule uses it. Change the rule first.';
        }

        return null;
    }

    /** @return list<string> accounts the posting rules name, set or by default */
    protected function ruleAccountIds(): array
    {
        $ids = [];
        foreach (array_keys(LedgerSettings::ROLES) as $role) {
            $ids[] = AppSetting::getValue('ledger.account.'.$role) ?: LedgerAccount::where('code', LedgerSettings::ROLES[$role][1])->value('id');
        }

        return array_values(array_filter(array_merge($ids, array_values(LedgerSettings::methodMap()), array_values(LedgerSettings::taxMap()))));
    }

    /** @return Collection<int, LedgerAccount> sections of a category in display order */
    protected function sectionsOf(string $category): Collection
    {
        return LedgerAccount::where('code', 'like', $category.'-__')->withCount('children')->orderBy('sort_order')->orderBy('code')->get();
    }

    /**
     * Everything the dialog shows, worked out from its fields.
     *
     * @return array<string, mixed>
     */
    protected function dialog(): array
    {
        $m = $this->m;
        $kind = $m['kind'] ?? '';
        if ($kind === '') {
            return ['open' => false, 'canSave' => false, 'canDelete' => false, 'canInactive' => false];
        }
        $isAcc = in_array($kind, ['acc', 'accEdit'], true);
        $isEdit = in_array($kind, ['accEdit', 'secEdit'], true);
        $account = $kind === 'accEdit' ? LedgerAccount::find($m['target']) : null;
        $sectionEdit = $kind === 'secEdit' ? LedgerAccount::find($m['target']) : null;
        $cat = $account ? substr($account->code, 0, 2) : ($sectionEdit ? substr($sectionEdit->code, 0, 2) : ($m['cat'] ?? ''));
        $sec = $account ? substr($account->code, 0, 5) : ($m['sec'] ?? '');
        $category = $cat !== '' ? LedgerAccount::where('code', $cat)->first() : null;
        $section = $sec !== '' ? LedgerAccount::where('code', $sec)->first() : null;
        $code = match ($kind) {
            'acc' => $section ? LedgerAccount::nextCode($section->code) : null,
            'sec' => $category ? LedgerAccount::nextCode($category->code) : null,
            default => ($account ?? $sectionEdit)?->code,
        };
        $name = trim((string) ($m['name'] ?? ''));
        $lower = mb_strtolower($name);
        $posted = $account ? $account->hasPostings() : false;
        $balance = $account ? $account->balance() : 0.0;
        $sectionAccounts = $sectionEdit ? LedgerAccount::where('parent_id', $sectionEdit->id)->get() : collect();
        $lineLocked = $sectionEdit && $sectionAccounts->isNotEmpty() && \App\Domains\Ledger\Models\JournalLine::whereIn('account_id', $sectionAccounts->pluck('id'))->exists();

        $err = '';
        if ($kind === 'acc' && (! $category || ! $section)) {
            $err = 'Pick a category and a section.';
        } elseif ($kind === 'sec' && ! $category) {
            $err = 'Pick a category.';
        } elseif ($name === '') {
            $err = 'Name is required.';
        } elseif ($isAcc && $section && LedgerAccount::where('parent_id', $section->id)->when($account, fn ($q) => $q->whereKeyNot($account->id))->get()->contains(fn ($a) => mb_strtolower($a->name) === $lower)) {
            $err = 'An account called '.$name.' already exists in '.$section->code.'.';
        } elseif (! $isAcc && $category && LedgerAccount::where('parent_id', $category->id)->when($sectionEdit, fn ($q) => $q->whereKeyNot($sectionEdit->id))->get()->contains(fn ($s) => mb_strtolower($s->name) === $lower)) {
            $err = 'A section called '.$name.' already exists in '.$category->code.'.';
        } elseif ($kind === 'acc' && ! $code) {
            $err = 'Section '.$section->code.' is full (99 accounts). Add a new section.';
        } elseif ($kind === 'sec' && ! $code) {
            $err = 'Category '.$category->code.' is full.';
        } elseif ($kind === 'sec' && empty($m['line'])) {
            $err = 'Pick a report line.';
        } elseif (mb_strlen($name) > 150) {
            $err = 'Keep the name to 150 characters.';
        }

        $dirty = match ($kind) {
            'accEdit' => $name !== $account->name || trim($m['desc'] ?? '') !== (string) $account->description || (bool) ($m['dc'] ?? false) !== $account->is_charge || (bool) ($m['inactive'] ?? false) !== ! $account->is_active,
            'secEdit' => $name !== $sectionEdit->name || ($m['line'] ?? '') !== (string) $sectionEdit->report_line,
            default => true,
        };
        $block = $account ? $this->deleteBlock($account, $posted || $balance != 0) : null;
        $canDelete = $account ? $block === null : ($sectionEdit ? $sectionAccounts->isEmpty() : false);
        $delNote = $account
            ? ($block === null ? 'Not used yet, so it can be deleted.' : 'Delete: '.$block)
            : ($sectionEdit ? ($canDelete ? 'Empty, so it can be deleted.' : 'Delete: has '.$sectionAccounts->count().' accounts. Delete or deactivate them first.') : '');
        $canInactive = $account && ! $account->control && $balance == 0;
        $money = fn (float $v) => \App\Support\Formats::money(abs($v), 0).($v > 0 ? ' Dr' : ' Cr');

        $list = collect();
        if ($isAcc && $section) {
            $list = LedgerAccount::where('parent_id', $section->id)->orderBy('code')->get()->map(fn ($a) => ['name' => $a->name, 'code' => $a->code, 'tag' => $account && $a->id === $account->id ? 'This account' : '', 'on' => $account && $a->id === $account->id, 'off' => ! $a->is_active]);
            if ($kind === 'acc' && $code) {
                $list->push(['name' => $name ?: 'New account', 'code' => $code, 'tag' => 'New', 'on' => true, 'off' => false, 'placeholder' => $name === '']);
            }
        } elseif (! $isAcc && $category) {
            $list = $this->sectionsOf($category->code)->map(fn ($s) => ['name' => $s->name, 'count' => $s->children_count, 'code' => $s->code, 'tag' => $sectionEdit && $s->id === $sectionEdit->id ? 'This section' : '', 'on' => $sectionEdit && $s->id === $sectionEdit->id, 'off' => false]);
            if ($kind === 'sec' && $code) {
                $list->push(['name' => $name ?: 'New section', 'code' => $code, 'tag' => 'New', 'on' => true, 'off' => false, 'placeholder' => $name === '']);
            }
        }

        return [
            'open' => true, 'kind' => $kind, 'isAcc' => $isAcc, 'isSec' => ! $isAcc, 'isEdit' => $isEdit,
            'title' => ['acc' => 'New nominal account', 'accEdit' => 'Edit nominal account', 'sec' => 'New section', 'secEdit' => 'Edit section'][$kind],
            'account' => $account, 'sectionEdit' => $sectionEdit, 'category' => $category, 'section' => $section, 'cat' => $cat, 'sec' => $sec,
            'catLabel' => $category ? $category->code.'  '.$category->name : '', 'secLabel' => $section ? $section->code.'  '.$section->name : '',
            'categories' => LedgerAccount::whereNull('parent_id')->orderBy('sort_order')->get(),
            'sections' => $category ? $this->sectionsOf($category->code) : collect(),
            'nameLabel' => $isAcc ? 'Nominal account' : 'Section', 'nameTrim' => $name,
            'code' => $code ?: '—',
            'codeHint' => $isEdit
                ? ($account ? ($posted ? 'Locked: the account has postings. Renaming is fine.' : 'Codes never change once saved.') : 'Codes never change. Use Up and Down on the sections list to reorder.')
                : ($kind === 'acc' ? ($section ? 'Next free number in '.$section->code.'. Assigned in order and cannot be changed.' : 'Filled in when you pick a section.')
                    : ($category ? 'Next free number in category '.$category->code.'.'.($code ? ' Its first account will be '.$code.'-01.' : '') : 'Filled in when you pick a category.')),
            'codeLocked' => $isEdit,
            'lines' => $category ? DefaultChart::reportLines($category->code) : [],
            'lineLocked' => $lineLocked,
            'lineHint' => $lineLocked ? 'Locked: accounts in this section have postings.' : 'Where the section shows on the balance sheet or P&L.'.($kind === 'sec' ? ' Locked once its accounts have postings.' : ''),
            'canInactive' => $canInactive,
            'statusNote' => ! $account ? '' : ($account->control ? 'Control accounts stay active.' : ($balance != 0 ? 'Balance is '.$money($balance).'. Clear it to zero before deactivating.' : (($m['inactive'] ?? false) ? 'Hidden from pickers and new vouchers. Reports keep its history.' : 'Balance is zero, so it can be deactivated.'))),
            'list' => $list->all(),
            'listTitle' => $isAcc ? ($section ? 'Existing accounts in '.$section->code : 'Existing nominal accounts') : ($category ? 'Existing sections in '.$category->code : 'Existing sections'),
            'listEmpty' => $isAcc ? 'Pick a section to see its accounts.' : 'Pick a category to see its sections.',
            'msg' => $err ?: ($isEdit ? ($dirty ? 'Unsaved changes.'.($delNote && ! $canDelete ? ' '.$delNote : '') : $delNote) : 'Saves as '.($kind === 'sec' ? 'section ' : '').$code.' '.$name.'.'),
            'msgBad' => $err !== '',
            'canSave' => $err === '' && $dirty, 'canDelete' => $canDelete,
        ];
    }
}
