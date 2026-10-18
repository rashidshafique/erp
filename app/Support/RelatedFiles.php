<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\AttachmentLink;
use App\Models\AttachmentPin;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Files that belong to the records a record comes from (a sales order shows its products' and patterns' drawings, a dispatch shows
 * its lots' certificates), and the version of each that the record was made to.
 */
class RelatedFiles
{
    private const MAX_DEPTH = 3;

    private const MAX_RECORDS = 25;

    private const MAX_FILES = 40;

    /** Where each record's own show page is, for the record names above related files. */
    private const ROUTES = [
        'furnace' => 'furnaces.show', 'iron_grade' => 'iron-grades.show', 'inspection' => 'inspections.show', 'ncr' => 'ncrs.show',
        'dispatch' => 'dispatches.show', 'packing' => 'packing.show',
    ];

    /**
     * The records this one comes from, nearest first, as [type, id] pairs.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function sources(string $type, string $id): array
    {
        $seen = [$type.':'.$id => true];
        $found = [];
        $queue = [[$type, $id, 0]];
        while ($queue && count($found) < self::MAX_RECORDS) {
            [$t, $i, $depth] = array_shift($queue);
            if ($depth >= self::MAX_DEPTH) {
                continue;
            }
            foreach (self::parents($t, $i) as [$pt, $pi]) {
                if (isset($seen[$pt.':'.$pi]) || ! isset(AttachableEntities::MAP[$pt])) {
                    continue;
                }
                $seen[$pt.':'.$pi] = true;
                $found[] = [$pt, $pi];
                $queue[] = [$pt, $pi, $depth + 1];
            }
        }

        return $found;
    }

    /**
     * Files of the related records this person may see, grouped by the record they belong to. A pinned document shows the version it was pinned to.
     *
     * @return Collection<int, array{label: string, url: string|null, files: Collection<int, array{file: Attachment, pinned: bool, newer: ?Attachment}>}>
     */
    public static function for(string $type, string $id, ?User $user): Collection
    {
        $sources = self::sources($type, $id);
        if ($sources === []) {
            return collect();
        }
        $query = Attachment::current()->whereNotIn('category', ['PHOTO', ...AttachableEntities::SENSITIVE_CATEGORIES])
            ->where(function ($q) use ($sources) {
                foreach ($sources as [$t, $i]) {
                    $q->orWhere(fn ($w) => $w->where('attachable_type', $t)->where('attachable_id', $i));
                }
            });
        $files = $query->orderBy('category')->orderBy('title')->limit(self::MAX_FILES)->get()
            ->filter(fn (Attachment $a) => AttachableEntities::canView($user, $a->attachable_type, $a->category));

        return self::group($files, $type, $id, $sources);
    }

    /**
     * Documents shown on this record through a link, as their current versions.
     *
     * @return Collection<int, Attachment>
     */
    public static function linked(string $type, string $id, ?User $user): Collection
    {
        $documents = AttachmentLink::where('attachable_type', $type)->where('attachable_id', $id)->pluck('id', 'document_id');
        if ($documents->isEmpty()) {
            return collect();
        }

        return Attachment::current()->whereIn('document_id', $documents->keys())->orderBy('created_at')->get()
            ->filter(fn (Attachment $a) => AttachableEntities::canView($user, $type, $a->category))
            ->each(fn (Attachment $a) => $a->setAttribute('link_id', $documents[$a->document_id]));
    }

    /** Records the version of every versioned document this record uses, related or linked, as it is now. Returns how many were pinned. */
    public static function pin(string $type, string $id, ?User $user = null): int
    {
        if (! DocumentVersioning::enabled()) {
            return 0;
        }
        $files = collect();
        foreach (self::sources($type, $id) as [$t, $i]) {
            $files = $files->merge(Attachment::current()->where('attachable_type', $t)->where('attachable_id', $i)
                ->whereNotIn('category', ['PHOTO', ...AttachableEntities::SENSITIVE_CATEGORIES])->limit(self::MAX_FILES)->get());
        }
        $files = $files->merge(self::linkedCurrent($type, $id))->filter(fn (Attachment $a) => DocumentVersioning::appliesTo($a->category))->unique('document_id');
        foreach ($files as $file) {
            AttachmentPin::updateOrCreate(
                ['pinnable_type' => $type, 'pinnable_id' => $id, 'document_id' => $file->document_id],
                ['attachment_id' => $file->id, 'pinned_by_name' => ($user ?? Auth::user())?->name],
            );
        }

        return $files->count();
    }

    /** @return Collection<int, Attachment> */
    private static function linkedCurrent(string $type, string $id): Collection
    {
        $documents = AttachmentLink::where('attachable_type', $type)->where('attachable_id', $id)->pluck('document_id');

        return $documents->isEmpty() ? collect() : Attachment::current()->whereIn('document_id', $documents)->get();
    }

    /**
     * @param  Collection<int, Attachment>  $files
     * @param  list<array{0: string, 1: string}>  $sources
     * @return Collection<int, array{label: string, url: string|null, files: Collection<int, array{file: Attachment, pinned: bool, newer: ?Attachment}>}>
     */
    private static function group(Collection $files, string $type, string $id, array $sources): Collection
    {
        $pins = AttachmentPin::where('pinnable_type', $type)->where('pinnable_id', $id)->get()->keyBy('document_id');
        $pinned = Attachment::whereIn('id', $pins->pluck('attachment_id'))->get()->keyBy('id');
        $order = collect($sources)->map(fn ($s) => $s[0].':'.$s[1])->flip();

        return $files->groupBy(fn (Attachment $a) => $a->attachable_type.':'.$a->attachable_id)
            ->sortBy(fn ($group, $key) => $order[$key] ?? 99)
            ->map(function (Collection $group, string $key) use ($pins, $pinned) {
                [$t, $i] = explode(':', $key, 2);

                return [
                    'label' => AttachableEntities::describe($t, $i),
                    'url' => self::url($t, $i),
                    'files' => $group->map(function (Attachment $current) use ($pins, $pinned) {
                        $pin = $pins->get($current->document_id);
                        $shown = $pin ? ($pinned->get($pin->attachment_id) ?? $current) : $current;

                        return ['file' => $shown, 'pinned' => $pin !== null, 'newer' => $shown->id !== $current->id ? $current : null];
                    })->values(),
                ];
            })->values();
    }

    public static function url(string $type, string $id): ?string
    {
        $route = GlobalSearch::TYPES[$type]['route'] ?? self::ROUTES[$type] ?? null;
        if ($route) {
            return route($route, $id);
        }

        return isset(GlobalSearch::TYPES[$type]) ? route('tools.show', [$type, $id]) : null;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function parents(string $type, string $id): array
    {
        $model = fn (string $key) => AttachableEntities::MAP[$key][1];
        $one = fn (string $key, string $parentType, string $column) => ($v = $model($key)::query()->whereKey($id)->value($column)) ? [[$parentType, (string) $v]] : [];
        $many = fn (Collection $ids, string $parentType) => $ids->filter()->unique()->map(fn ($v) => [$parentType, (string) $v])->values()->all();

        return match ($type) {
            'sales_order' => $many(DB::table('sales_order_lines')->where('sales_order_id', $id)->pluck('product_id'), 'product'),
            'product' => $many(DB::table('pattern_revision_cavities as c')
                ->join('product_revisions as pr', 'pr.id', '=', 'c.product_revision_id')
                ->join('pattern_revisions as r', 'r.id', '=', 'c.pattern_revision_id')
                ->where('pr.product_id', $id)->pluck('r.pattern_id'), 'pattern'),
            'casting_lot' => array_merge($one('casting_lot', 'product', 'product_id'), $many(
                DB::table('pour_mould_assemblies')->whereIn('id', $model('casting_lot')::query()->whereKey($id)->pluck('pour_mould_assembly_id'))->pluck('pour_id'), 'pour')),
            'pour' => $one('pour', 'heat', 'heat_id'),
            'heat' => array_merge($one('heat', 'furnace', 'furnace_id'), $one('heat', 'iron_grade', 'iron_grade_id')),
            'packing' => $many(DB::table('packing_record_items')->where('packing_record_id', $id)->pluck('casting_lot_id'), 'casting_lot'),
            'dispatch' => $many(DB::table('dispatch_items as d')->join('packing_record_items as p', 'p.id', '=', 'd.packing_record_item_id')
                ->where('d.dispatch_id', $id)->pluck('p.casting_lot_id'), 'casting_lot'),
            'inspection' => $one('inspection', 'casting_lot', 'casting_lot_id'),
            'ncr' => $one('ncr', 'casting_lot', 'casting_lot_id'),
            'pattern_revision' => $one('pattern_revision', 'pattern', 'pattern_id'),
            'moulding_run' => $many(DB::table('pattern_revisions')->whereIn('id', $model('moulding_run')::query()->whereKey($id)->pluck('pattern_revision_id'))->pluck('pattern_id'), 'pattern'),
            default => [],
        };
    }
}
