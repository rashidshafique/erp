<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportMappingTemplate;
use App\Services\Import\ColumnMapper;
use App\Services\Import\GoogleSheetsFetcher;
use App\Services\Import\ImportException;
use App\Services\Import\ImportProcessor;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DataImportController extends Controller
{
    public function __construct(private SpreadsheetReader $reader, private ImportProcessor $proc)
    {
    }

    private function def(string $entity): array
    {
        $d = ImportBatch::definition($entity);
        abort_unless($d, 404);

        return $d;
    }

    private function open(ImportBatch $b): ImportBatch
    {
        abort_if($b->status === 'DISCARDED', 404);

        return $b;
    }

    public function index()
    {
        return view('imports.index', [
            'entities' => config('imports.entities'),
            'recent' => ImportBatch::orderByDesc('created_at')->limit(15)->get(),
            'templates' => ImportMappingTemplate::orderBy('entity')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $entity = $request->input('entity', 'product');

        return view('imports.create', ['entity' => $entity, 'def' => $this->def($entity), 'entities' => config('imports.entities'), 'maxKb' => config('imports.max_upload_kb')]);
    }

    /** A blank file with the right headings and one example row. */
    public function template(string $entity)
    {
        $def = $this->def($entity);
        $head = array_map(fn ($f) => $f['label'], $def['fields']);
        $example = array_map(fn ($f, $k) => $f['type'] === 'enum' ? $f['enum'][0] : ($f['type'] === 'bool' ? 'yes' : ($f['type'] === 'decimal' || $f['type'] === 'int' ? '1' : ($f['type'] === 'date' ? '2024-01-31' : ($k === 'name' ? 'Example '.strtolower($def['label']) : '')))), $def['fields'], array_keys($def['fields']));
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, $head, ',', '"', '');
        fputcsv($h, $example, ',', '"', '');
        rewind($h);
        $csv = stream_get_contents($h);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="import-'.$entity.'-template.csv"']);
    }

    public function store(Request $request)
    {
        $max = config('imports.max_upload_kb');
        $d = $request->validate([
            'entity' => ['required', Rule::in(array_keys(config('imports.entities')))],
            'source' => ['required', Rule::in(['FILE', 'GOOGLE', 'PASTE'])],
            'file' => ['required_if:source,FILE', 'nullable', 'file', 'max:'.$max, 'extensions:csv,txt,tsv,xlsx,xls'],
            'google_url' => ['required_if:source,GOOGLE', 'nullable', 'string', 'max:500'],
            'pasted' => ['required_if:source,PASTE', 'nullable', 'string', 'max:5000000'],
        ], ['file.required_if' => 'Choose a file.', 'google_url.required_if' => 'Paste the Google Sheets address.', 'pasted.required_if' => 'Paste the cells copied from your sheet.', 'file.extensions' => 'Upload a .csv or .xlsx file.']);

        $disk = config('foundry.attachments.disk');
        $id = (string) Str::uuid();
        try {
            if ($d['source'] === 'FILE') {
                $f = $request->file('file');
                $label = Str::limit(basename($f->getClientOriginalName()), 250, '');
                $head = (string) file_get_contents($f->getRealPath(), false, null, 0, 4);
                $ext = str_starts_with($head, 'PK') ? 'xlsx' : 'csv';
                $path = $f->storeAs('imports', "$id.$ext", $disk);
            } else {
                if ($d['source'] === 'GOOGLE') {
                    $text = (new GoogleSheetsFetcher())->fetch($d['google_url']);
                    $label = Str::limit($d['google_url'], 250, '');
                } else {
                    $text = $d['pasted'];
                    $label = 'Pasted cells';
                }
                $ext = 'csv';
                $path = "imports/$id.csv";
                Storage::disk($disk)->put($path, $text);
            }
            $local = Storage::disk($disk)->path($path);
            $sheets = $this->reader->sheets($local, $ext);
            $rows = $this->reader->rows($local, $ext, 0);
        } catch (ImportException $e) {
            if (isset($path)) {
                Storage::disk($disk)->delete($path);
            }

            return back()->withErrors(['source' => $e->getMessage()])->withInput();
        }
        if (count(array_filter($rows, fn ($r) => array_filter($r, fn ($v) => $v !== ''))) < 2) {
            Storage::disk($disk)->delete($path);

            return back()->withErrors(['source' => 'There is nothing to import: the data needs a heading row and at least one row below it.'])->withInput();
        }
        $header = 1;
        foreach (array_slice($rows, 0, 15, true) as $i => $r) {
            if (count(array_filter($r, fn ($v) => $v !== '')) >= 2) {
                $header = $i + 1;
                break;
            }
        }
        $b = new ImportBatch([
            'entity' => $d['entity'], 'source_type' => $d['source'], 'source_label' => $label, 'file_disk' => $disk, 'file_path' => $path, 'file_ext' => $ext,
            'sheet_index' => 0, 'header_row' => $header, 'status' => 'UPLOADED', 'options' => ['mode' => 'UPSERT', 'match_by' => array_key_first($this->def($d['entity'])['match']), 'date_order' => 'DMY'],
            'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
        ]);
        $b->id = $id;
        $b->save();

        return redirect()->route('imports.map', $b)->with('success', count($sheets) > 1 ? 'File read. It has '.count($sheets).' sheets; choose the one to import.' : 'Data read. Now match its columns to the fields.');
    }

    public function map(Request $request, ImportBatch $batch)
    {
        $this->open($batch);
        abort_if($batch->status === 'COMMITTED', 404);
        $def = $batch->def();
        $local = Storage::disk($batch->file_disk)->path($batch->file_path);
        $sheets = $this->reader->sheets($local, $batch->file_ext);
        if ($request->filled('sheet')) {
            $batch->sheet_index = max(0, min(count($sheets) - 1, (int) $request->input('sheet')));
        }
        if ($request->filled('header')) {
            $batch->header_row = max(1, (int) $request->input('header'));
        }
        try {
            $t = $this->proc->table($batch);
        } catch (ImportException $e) {
            return redirect()->route('imports.index')->withErrors(['import' => $e->getMessage()]);
        }
        $headers = $t['headers'];
        $template = $request->filled('template') ? ImportMappingTemplate::where('entity', $batch->entity)->find($request->input('template')) : null;
        $suggest = $batch->mapping && ! $request->filled('sheet') && ! $request->filled('header') && ! $template
            ? collect($batch->mapping)->map(fn ($m) => ['col' => $m['col'] ?? null, 'default' => $m['default'] ?? null, 'auto' => false])->all()
            : ($template ? ColumnMapper::fromTemplate($template, $headers) : ColumnMapper::suggest($batch->entity, $headers));
        foreach ($def['fields'] as $k => $f) {
            $suggest[$k] ??= ['col' => null, 'default' => null, 'auto' => false];
        }

        return view('imports.map', [
            'b' => $batch, 'def' => $def, 'headers' => $headers, 'sample' => array_slice($t['rows'], 0, 5), 'rowCount' => count($t['rows']), 'sheets' => $sheets,
            'mapping' => $suggest, 'opt' => array_merge(['mode' => 'UPSERT', 'match_by' => 'code', 'date_order' => 'DMY'], $batch->options ?? []),
            'templates' => ImportMappingTemplate::where('entity', $batch->entity)->orderBy('name')->get(), 'usedTemplate' => $template,
        ]);
    }

    public function saveMap(Request $request, ImportBatch $batch)
    {
        $this->open($batch);
        abort_if($batch->status === 'COMMITTED', 404);
        $def = $batch->def();
        $d = $request->validate([
            'sheet_index' => ['required', 'integer', 'min:0', 'max:200'], 'header_row' => ['required', 'integer', 'min:1', 'max:1000'],
            'mapping' => ['array'], 'mapping.*.col' => ['nullable', 'integer', 'min:0', 'max:500'], 'mapping.*.default' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', Rule::in(['UPSERT', 'CREATE', 'UPDATE'])],
            'match_by' => ['required', Rule::in(array_keys($def['match']))],
            'date_order' => ['required', Rule::in(['DMY', 'MDY'])],
            'save_as' => ['nullable', 'string', 'max:120'],
        ]);
        $batch->sheet_index = $d['sheet_index'];
        $batch->header_row = $d['header_row'];
        $t = $this->proc->table($batch);
        $headers = $t['headers'];

        $mapping = [];
        $used = [];
        foreach ($def['fields'] as $k => $f) {
            $col = $d['mapping'][$k]['col'] ?? null;
            $col = ($col === null || $col === '') ? null : (int) $col;
            if ($col !== null && $col >= count($headers)) {
                $col = null;
            }
            $default = isset($d['mapping'][$k]['default']) && trim($d['mapping'][$k]['default']) !== '' ? trim($d['mapping'][$k]['default']) : null;
            $mapping[$k] = ['col' => $col, 'default' => $default];
            if ($col !== null) {
                if (isset($used[$col])) {
                    return back()->withErrors(['mapping' => 'Column "'.($headers[$col] ?: 'column '.($col + 1)).'" is chosen for both '.$def['fields'][$used[$col]]['label'].' and '.$f['label'].'. Each column can feed one field.'])->withInput();
                }
                $used[$col] = $k;
            }
        }
        $has = fn ($k) => $mapping[$k]['col'] !== null || $mapping[$k]['default'] !== null;
        if ($d['mode'] !== 'UPDATE') {
            foreach ($def['fields'] as $k => $f) {
                if (! empty($f['required']) && ! $has($k)) {
                    return back()->withErrors(['mapping' => 'Choose a column for '.$f['label'].', or give a fixed value. New records need it.'])->withInput();
                }
            }
        }
        $matchColumnMissing = collect($def['match_fields'][$d['match_by']] ?? [$d['match_by']])->contains(fn ($k) => $mapping[$k]['col'] === null);
        if ($d['mode'] === 'UPDATE' && $matchColumnMissing) {
            return back()->withErrors(['mapping' => 'To update existing records, choose the column that holds their '.$def['match'][$d['match_by']].'.'])->withInput();
        }
        if ($matchColumnMissing && $d['mode'] !== 'CREATE') {
            // Upsert with no match column can only ever create, which is allowed but worth saying.
            session()->flash('success', 'No column is matched to '.$def['match'][$d['match_by']].', so every row will be added as a new record.');
        }

        $opt = $batch->options ?? [];
        $opt = array_merge($opt, [
            'mode' => $d['mode'], 'match_by' => $d['match_by'], 'date_order' => $d['date_order'],
            'decimal_comma' => $request->boolean('decimal_comma'), 'stop_on_error' => $request->boolean('stop_on_error'),
        ]);
        $batch->fill(['mapping' => $mapping, 'options' => $opt, 'status' => 'MAPPED'])->save();

        if (filled($d['save_as'] ?? null)) {
            $tm = ['headers' => $headers, 'mapping' => collect($mapping)->map(fn ($m) => ['header' => $m['col'] !== null ? $headers[$m['col']] : null, 'default' => $m['default']])->all(),
                'options' => collect($opt)->except('value_map')->all()];
            ImportMappingTemplate::updateOrCreate(['entity' => $batch->entity, 'name' => trim($d['save_as'])], $tm);
        }

        return redirect()->route('imports.preview', $batch);
    }

    public function preview(Request $request, ImportBatch $batch)
    {
        $this->open($batch);
        abort_if($batch->status === 'COMMITTED', 404);
        if ($batch->status === 'RUNNING') {
            return redirect()->route('imports.progress', $batch);
        }
        if (! $batch->mapping) {
            return redirect()->route('imports.map', $batch);
        }
        try {
            $r = $this->proc->run($batch, false);
        } catch (ImportException $e) {
            return redirect()->route('imports.map', $batch)->withErrors(['mapping' => $e->getMessage()]);
        }
        $show = $request->input('show', 'ALL');
        $rows = $show === 'ALL' ? $r['rows'] : array_values(array_filter($r['rows'], fn ($x) => $x['action'] === $show));
        $per = config('imports.preview_rows');
        $page = max(1, (int) $request->input('page', 1));

        return view('imports.preview', [
            'b' => $batch, 'def' => $batch->def(), 'counts' => $r['counts'], 'unknown' => $r['unknown'], 'rows' => array_slice($rows, ($page - 1) * $per, $per),
            'total' => count($rows), 'page' => $page, 'per' => $per, 'show' => $show,
        ]);
    }

    public function values(Request $request, ImportBatch $batch)
    {
        $this->open($batch);
        abort_if($batch->status === 'COMMITTED', 404);
        $in = $request->validate(['value_map' => ['array'], 'value_map.*' => ['array'], 'value_map.*.*' => ['nullable', 'string', 'max:60']]);
        $def = $batch->def();
        $opt = $batch->options ?? [];
        foreach ($in['value_map'] ?? [] as $field => $pairs) {
            $f = $def['fields'][$field] ?? null;
            if (! $f) {
                continue;
            }
            foreach ($pairs as $encoded => $choice) {
                $raw = strtolower((string) base64_decode((string) $encoded, true));
                if ($raw === '' || $choice === null || $choice === '') {
                    continue;
                }
                $allowed = $f['type'] === 'enum' ? array_merge($f['enum'], ['__SKIP__']) : ['1', '0'];
                if (in_array($choice, $allowed, true)) {
                    $opt['value_map'][$field][$raw] = $choice;
                }
            }
        }
        $batch->update(['options' => $opt]);

        return redirect()->route('imports.preview', $batch)->with('success', 'Values saved. The preview was checked again.');
    }

    /**
     * Starts the import, or carries on with one that is running. Each call writes for about 20 seconds and then hands over to the
     * progress page, which calls this again, so a big file never runs into a web server time limit.
     */
    public function commit(ImportBatch $batch)
    {
        $this->open($batch);
        @set_time_limit(0);
        try {
            $b = DB::transaction(function () use ($batch) {
                $b = ImportBatch::lockForUpdate()->findOrFail($batch->id);
                if ($b->status === 'MAPPED') {
                    $b->update(['status' => 'RUNNING', 'progress' => 0, 'counts' => ['CREATE' => 0, 'UPDATE' => 0, 'UNCHANGED' => 0, 'SKIP' => 0, 'ERROR' => 0, 'TOTAL' => 0], 'report' => []]);
                } elseif ($b->status !== 'RUNNING') {
                    throw new ImportException('This import was already run, or has not been mapped.');
                }

                return $b;
            });
            $start = (int) $b->progress;
            $r = $this->proc->run($b, true, $start, (float) config('imports.commit_seconds'));
        } catch (ImportException $e) {
            if (isset($b) && (int) $b->progress === 0) {
                $b->update(['status' => 'MAPPED']);
            }

            return back()->withErrors(['import' => $e->getMessage()]);
        } catch (\Throwable $e) {
            if (isset($b) && (int) $b->progress === 0) {
                $b->update(['status' => 'MAPPED']);
            }
            throw $e;
        }

        $counts = $b->counts;
        $report = $b->report ?? [];
        foreach (array_slice($r['rows'], $r['window'][0], $r['window'][1] - $r['window'][0]) as $x) {
            $counts[$x['action']]++;
            $report[] = [
                'n' => $x['n'], 'action' => $x['action'], 'code' => $x['code'], 'name' => $x['name'],
                'message' => implode(' ', array_merge($x['errors'], $x['notes'], array_map(fn ($c, $k) => "$k: {$c[0]} → {$c[1]}", $x['changes'], array_keys($x['changes'])))),
            ];
        }
        $counts['TOTAL'] = $r['counts']['TOTAL'];
        if ($r['next'] === null) {
            $b->update(['status' => 'COMMITTED', 'counts' => $counts, 'report' => $report, 'progress' => $counts['TOTAL'], 'committed_at' => now()]);

            return redirect()->route('imports.show', $batch)->with('success', "Import finished: {$counts['CREATE']} added, {$counts['UPDATE']} updated.");
        }
        $b->update(['counts' => $counts, 'report' => $report, 'progress' => $r['next']]);

        return redirect()->route('imports.progress', $batch);
    }

    public function progress(ImportBatch $batch)
    {
        $this->open($batch);
        if ($batch->status === 'COMMITTED') {
            return redirect()->route('imports.show', $batch);
        }
        if ($batch->status !== 'RUNNING') {
            return redirect()->route($batch->mapping ? 'imports.preview' : 'imports.map', $batch);
        }

        return view('imports.progress', ['b' => $batch, 'def' => $batch->def()]);
    }

    public function show(ImportBatch $batch)
    {
        abort_if($batch->status === 'DISCARDED', 404);
        if ($batch->status === 'RUNNING') {
            return redirect()->route('imports.progress', $batch);
        }
        if ($batch->status !== 'COMMITTED') {
            return redirect()->route($batch->mapping ? 'imports.preview' : 'imports.map', $batch);
        }

        return view('imports.show', ['b' => $batch, 'def' => $batch->def()]);
    }

    public function report(ImportBatch $batch)
    {
        abort_unless($batch->status === 'COMMITTED', 404);
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, ['Row', 'Result', ...($batch->def()['columns'] ?? ['Code', 'Name']), 'Details'], ',', '"', '');
        foreach ($batch->report ?? [] as $r) {
            // A cell that starts with = + - @ would run as a formula when the report is opened in Excel.
            $cell = fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v;
            fputcsv($h, [$r['n'], $r['action'], $cell($r['code']), $cell($r['name']), $cell($r['message'])], ',', '"', '');
        }
        rewind($h);

        return response(stream_get_contents($h), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="import-'.$batch->entity.'-report.csv"']);
    }

    public function destroy(ImportBatch $batch)
    {
        if (in_array($batch->status, ['UPLOADED', 'MAPPED'], true)) {
            Storage::disk($batch->file_disk)->delete($batch->file_path);
            $batch->update(['status' => 'DISCARDED']);
        }

        return redirect()->route('imports.index')->with('success', 'Import discarded. Nothing was changed.');
    }

    public function deleteTemplate(ImportMappingTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Saved mapping removed.');
    }
}
