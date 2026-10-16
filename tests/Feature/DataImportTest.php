<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\ImportBatch;
use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\MouldConfiguration;
use App\Models\MouldLayer;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use App\Services\Import\ColumnMapper;
use App\Services\Import\FieldParser;
use App\Services\Import\GoogleSheetsFetcher;
use App\Services\Import\ImportException;
use App\Services\Import\ImportProcessor;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\XlsxFactory;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['foundry.attachments.disk' => 'local']);
    }

    private function tmp(string $content, string $ext = 'csv'): string
    {
        $p = tempnam(sys_get_temp_dir(), 'im').'.'.$ext;
        file_put_contents($p, $content);

        return $p;
    }

    /** Paste the data, map by heading (with overrides), and return the batch after the mapping is saved. */
    private function stage(string $entity, string $csv, array $override = [], array $opts = []): ImportBatch
    {
        $resp = $this->post(route('imports.store'), ['entity' => $entity, 'source' => 'PASTE', 'pasted' => $csv])->assertSessionHasNoErrors();
        preg_match('#/imports/([0-9a-f-]{36})/map#', (string) $resp->headers->get('Location'), $m);

        return $this->map(ImportBatch::findOrFail($m[1]), $override, $opts);
    }

    private function map(ImportBatch $b, array $override = [], array $opts = []): ImportBatch
    {
        $t = app(ImportProcessor::class)->table($b);
        $sug = ColumnMapper::suggest($b->entity, $t['headers']);
        $mapping = [];
        foreach ($sug as $k => $m) {
            $m = array_replace($m, $override[$k] ?? []);
            $mapping[$k] = ['col' => $m['col'], 'default' => $m['default']];
        }
        $this->post(route('imports.map.save', $b), array_merge(['sheet_index' => 0, 'header_row' => $b->header_row, 'mapping' => $mapping, 'mode' => 'UPSERT', 'match_by' => 'code', 'date_order' => 'DMY'], $opts))->assertSessionHasNoErrors();

        return $b->fresh();
    }

    private function dry(ImportBatch $b, bool $commit = false): array
    {
        return app(ImportProcessor::class)->run($b->fresh(), $commit);
    }

    // ---- readers --------------------------------------------------------------------------------------------

    public function test_csv_reader_handles_bom_semicolons_quotes_and_windows_encoding(): void
    {
        $r = new SpreadsheetReader();
        $rows = $r->csvRows($this->tmp("\xEF\xBB\xBFCode;Name;Note\nP1;\"Pump; housing\";\"two\nlines\"\nP2;Cover;\n"));
        $this->assertSame(['Code', 'Name', 'Note'], $rows[0]);
        $this->assertSame('Pump; housing', $rows[1][1]);
        $this->assertSame("two\nlines", $rows[1][2]);
        $this->assertCount(3, $rows);

        $win = $r->csvRows($this->tmp(mb_convert_encoding("Code,Name\nP1,Fa\u{E7}ade cover\n", 'Windows-1252', 'UTF-8')));
        $this->assertSame("Fa\u{E7}ade cover", $win[1][1]);

        $tab = $r->csvRows($this->tmp("sep=;\nA\tB\n1\t2\n"));
        $this->assertSame([['A', 'B'], ['1', '2']], $tab);
    }

    public function test_xlsx_reader_reads_sheets_numbers_and_dates(): void
    {
        $f = XlsxFactory::make([
            'Patterns' => [['Code', 'Name', 'Length', 'Date'], ['P1', 'Plate', 450.5, new \DateTimeImmutable('2024-03-15 00:00:00', new \DateTimeZone('UTC'))], ['P2', 'Cope', 300, null]],
            'Other' => [['x'], ['y']],
        ]);
        $r = new SpreadsheetReader();
        $this->assertSame(['Patterns', 'Other'], $r->sheets($f, 'xlsx'));
        $rows = $r->rows($f, 'xlsx', 0);
        $this->assertSame(['Code', 'Name', 'Length', 'Date'], $rows[0]);
        $this->assertSame(['P1', 'Plate', '450.5', '2024-03-15'], $rows[1]);
        $this->assertSame(['P2', 'Cope', '300', ''], $rows[2]);
        $this->assertSame([['x'], ['y']], $r->rows($f, 'xlsx', 1));
    }

    public function test_old_xls_and_garbage_are_refused_with_advice(): void
    {
        $r = new SpreadsheetReader();
        try {
            $r->sheets($this->tmp("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 50), 'xlsx'), 'xlsx');
            $this->fail('expected a refusal');
        } catch (ImportException $e) {
            $this->assertStringContainsString('.xlsx', $e->getMessage());
        }
        $this->expectException(ImportException::class);
        $r->rows($this->tmp('not a zip', 'xlsx'), 'xlsx');
    }

    // ---- parsing and mapping --------------------------------------------------------------------------------

    public function test_values_are_parsed_and_checked(): void
    {
        $dec = ['label' => 'Weight', 'type' => 'decimal', 'scale' => 2, 'min' => 0];
        $this->assertSame(1234.5, FieldParser::parse('w', $dec, '1,234.50', [])[0]);
        $this->assertSame(12.5, FieldParser::parse('w', $dec, '12.5 kg', [])[0]);
        $this->assertNotNull(FieldParser::parse('w', $dec, '2,5', [])[1], 'a lone comma is ambiguous and is refused');
        $this->assertSame(2.5, FieldParser::parse('w', $dec, '2,5', ['decimal_comma' => true])[0]);
        $this->assertSame(1234.5, FieldParser::parse('w', $dec, '1.234,5', ['decimal_comma' => true])[0]);
        $this->assertNotNull(FieldParser::parse('w', $dec, '-3', [])[1]);
        $this->assertNotNull(FieldParser::parse('w', $dec, 'heavy', [])[1]);

        $this->assertSame('2024-01-31', FieldParser::date('31/01/2024', 'DMY'));
        $this->assertSame('2024-01-31', FieldParser::date('01/31/2024', 'MDY'));
        $this->assertNull(FieldParser::date('31/01/2024', 'MDY'));
        $this->assertSame('2024-03-05', FieldParser::date('2024-03-05', 'DMY'));
        $this->assertSame('2024-03-05', FieldParser::date('5 Mar 2024', 'DMY'));

        $enum = ['label' => 'Type', 'type' => 'enum', 'enum' => ['MATCHPLATE', 'COPE_DRAG', 'LOOSE_PATTERN', 'OTHER']];
        $this->assertSame('MATCHPLATE', FieldParser::parse('t', $enum, 'Match plate', [])[0]);
        $this->assertSame('COPE_DRAG', FieldParser::parse('t', $enum, 'Cope & drag', [])[0]);
        $this->assertSame('LOOSE_PATTERN', FieldParser::parse('t', $enum, 'loose', [])[0]);
        [, $err, $unknown] = FieldParser::parse('t', $enum, 'Wooden thing', []);
        $this->assertTrue($unknown);
        $this->assertSame('OTHER', FieldParser::parse('t', $enum, 'Wooden thing', [], ['wooden thing' => 'OTHER'])[0]);
        $st = ['label' => 'Status', 'type' => 'enum', 'enum' => ['ACTIVE', 'INACTIVE', 'RETIRED']];
        $this->assertSame('RETIRED', FieldParser::parse('s', $st, 'Scrapped', [])[0]);
        $this->assertSame('ACTIVE', FieldParser::parse('s', $st, 'In use', [])[0]);

        $bool = ['label' => 'Active', 'type' => 'bool'];
        $this->assertTrue(FieldParser::parse('a', $bool, 'Yes', [])[0]);
        $this->assertFalse(FieldParser::parse('a', $bool, 'Inactive', [])[0]);
        $this->assertTrue(FieldParser::parse('a', $bool, 'perhaps', [])[2]);
        $this->assertSame('ABC123', FieldParser::parse('m', ['label' => 'Mark', 'type' => 'mark'], 'abc123', [])[0]);
        $this->assertNotNull(FieldParser::parse('m', ['label' => 'Mark', 'type' => 'mark'], 'a b', [])[1]);
    }

    public function test_columns_are_suggested_from_their_headings(): void
    {
        $m = ColumnMapper::suggest('product', ['Part Number', 'Part Name', 'Weight kg', 'Rev', 'Status', 'Colour']);
        $this->assertSame(0, $m['code']['col']);
        $this->assertSame(1, $m['name']['col']);
        $this->assertSame(2, $m['nominal_weight_kg']['col']);
        $this->assertSame(3, $m['revision']['col']);
        $this->assertSame(4, $m['active']['col']);
        $this->assertNull($m['description']['col']);
        $p = ColumnMapper::suggest('pattern', ['Pattern No', 'Length mm', 'Width', 'H', 'Stamp', 'Type']);
        $this->assertSame([0, 1, 2, 3, 4, 5], [$p['code']['col'], $p['length_mm']['col'], $p['width_mm']['col'], $p['height_mm']['col'], $p['mark_code']['col'], $p['pattern_type']['col']]);
    }

    // ---- products -------------------------------------------------------------------------------------------

    public function test_preview_changes_nothing_and_commit_adds_products_with_revisions(): void
    {
        $csv = "Part Number,Part Name,Weight kg,Rev,Status\nPRD-100,Pump housing,12.5,B,Active\nPRD-101,Valve body,\"1,250.5\",A,Inactive\n,Cover plate,3,,\n";
        $b = $this->stage('product', $csv, [], ['stop_on_error' => 0]);
        $r = $this->dry($b);
        $this->assertSame(['CREATE' => 3, 'UPDATE' => 0, 'UNCHANGED' => 0, 'SKIP' => 0, 'ERROR' => 0, 'TOTAL' => 3], $r['counts']);
        $this->assertSame(0, Product::count());
        $this->get(route('imports.preview', $b))->assertOk()->assertSee('Pump housing')->assertSee('Will add');

        $this->post(route('imports.commit', $b))->assertRedirect(route('imports.show', $b));
        $this->assertSame(3, Product::count());
        $p = Product::where('code', 'PRD-100')->firstOrFail();
        $this->assertEquals(12.5, (float) $p->nominal_weight_kg);
        $this->assertTrue((bool) $p->active);
        $this->assertSame('CASTING', $p->product_type, 'new records get the default type');
        $this->assertFalse((bool) Product::where('code', 'PRD-101')->value('active'));
        $this->assertEquals(1250.5, (float) Product::where('code', 'PRD-101')->value('nominal_weight_kg'));
        $this->assertSame(['B'], ProductRevision::where('product_id', $p->id)->pluck('revision')->all());
        $this->assertSame('DRAFT', ProductRevision::where('product_id', $p->id)->value('status'));
        $this->assertMatchesRegularExpression('/^PRD-\d+$/', Product::where('name', 'Cover plate')->value('code'), 'blank code is generated');
        $this->assertSame('COMMITTED', $b->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Product', 'action' => 'CREATED']);
        $this->get(route('imports.show', $b))->assertOk()->assertSee('3')->assertSee('added');

        $this->post(route('imports.commit', $b))->assertSessionHasErrors('import');   // cannot run twice
        $this->assertSame(3, Product::count());
    }

    public function test_running_the_same_file_again_changes_nothing_and_updates_only_what_differs(): void
    {
        $csv = "Code,Name,Weight\nPRD-1,Housing,10\nPRD-2,Cover,5\n";
        $b = $this->stage('product', $csv);
        $this->post(route('imports.commit', $b));

        $again = $this->stage('product', $csv);
        $this->assertSame(2, $this->dry($again)['counts']['UNCHANGED']);

        $changed = $this->stage('product', "Code,Name,Weight\nPRD-1,Housing,11.5\nPRD-2,,\nPRD-3,New one,2\n");
        $r = $this->dry($changed);
        $this->assertSame(1, $r['counts']['UPDATE']);
        $this->assertSame(1, $r['counts']['UNCHANGED'], 'blank cells never overwrite');
        $this->assertSame(1, $r['counts']['CREATE']);
        $upd = collect($r['rows'])->firstWhere('action', 'UPDATE');
        $this->assertSame(['10.0000', '11.5'], $upd['changes']['nominal_weight_kg']);
        $this->post(route('imports.commit', $changed));
        $this->assertEquals(11.5, (float) Product::where('code', 'PRD-1')->value('nominal_weight_kg'));
        $this->assertSame('Cover', Product::where('code', 'PRD-2')->value('name'));
        $this->assertSame(3, Product::count());
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Product', 'action' => 'UPDATED']);
    }

    public function test_bad_rows_are_reported_and_the_good_ones_still_import(): void
    {
        $csv = "Code,Name,Weight,Status\nA1,Good,5,Active\nA2,,5,Active\nA1,Duplicate,5,Active\nA3,Heavy,abc,Active\nA4,Odd status,5,Pending review\n";
        $b = $this->stage('product', $csv);
        $r = $this->dry($b);
        $by = collect($r['rows'])->keyBy('n');
        $this->assertSame('CREATE', $by[2]['action']);
        $this->assertStringContainsString('Name is required', $by[3]['errors'][0]);
        $this->assertStringContainsString('Same code as row 2', $by[4]['errors'][0]);
        $this->assertStringContainsString('not a number', $by[5]['errors'][0]);
        $this->assertSame(['Pending review' => 1], $r['unknown']['active']);
        $this->post(route('imports.commit', $b));
        $this->assertSame(['A1'], Product::pluck('code')->all());
        $show = $this->get(route('imports.show', $b))->assertOk();
        $show->assertSee('Rows that were not imported');
    }

    public function test_unrecognised_values_can_be_mapped_then_import(): void
    {
        $b = $this->stage('product', "Code,Name,Type\nT1,One,Castings\nT2,Two,Widget\n");
        $r = $this->dry($b);
        $this->assertSame(['Widget' => 1], $r['unknown']['product_type']);
        $this->get(route('imports.preview', $b))->assertOk()->assertSee('Values we do not recognise')->assertSee('Widget');
        $this->post(route('imports.values', $b), ['value_map' => ['product_type' => [base64_encode('Widget') => 'OTHER']]])->assertRedirect();
        $this->assertSame(0, $this->dry($b)['counts']['ERROR']);
        $this->post(route('imports.commit', $b));
        $this->assertSame('OTHER', Product::where('code', 'T2')->value('product_type'));
        $this->assertSame('CASTING', Product::where('code', 'T1')->value('product_type'));
    }

    public function test_import_modes_and_stop_on_error(): void
    {
        Product::create(['code' => 'E1', 'name' => 'Existing', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        $csv = "Code,Name\nE1,Renamed\nN1,Brand new\n";
        $only = $this->dry($this->stage('product', $csv, [], ['mode' => 'CREATE']));
        $this->assertSame(['CREATE' => 1, 'SKIP' => 1], array_intersect_key($only['counts'], ['CREATE' => 1, 'SKIP' => 1]));
        $upd = $this->dry($this->stage('product', $csv, [], ['mode' => 'UPDATE']));
        $this->assertSame(1, $upd['counts']['UPDATE']);
        $this->assertSame(1, $upd['counts']['SKIP']);

        $b = $this->stage('product', "Code,Name\nS1,Fine\nS2,\n", [], ['stop_on_error' => 1]);
        $this->post(route('imports.commit', $b))->assertSessionHasErrors('import');
        $this->assertSame(0, Product::where('code', 'S1')->count());
        $this->assertSame('MAPPED', $b->fresh()->status);
    }

    public function test_fixed_values_fill_blank_cells_and_matching_can_use_the_name(): void
    {
        Product::create(['code' => 'X1', 'name' => 'Gear', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        $b = $this->stage('product', "Name,Weight\nGear,7\nSprocket,3\n", ['product_type' => ['default' => 'OTHER']], ['match_by' => 'name']);
        $r = $this->dry($b);
        $this->assertSame(1, $r['counts']['UPDATE']);
        $this->assertSame(1, $r['counts']['CREATE']);
        $this->post(route('imports.commit', $b));
        $this->assertEquals(7, (float) Product::where('code', 'X1')->value('nominal_weight_kg'));
        $this->assertSame('OTHER', Product::where('name', 'Sprocket')->value('product_type'));
        $this->assertSame('OTHER', Product::where('code', 'X1')->value('product_type'), 'a fixed value applies to every row');

        Product::create(['code' => 'X2', 'name' => 'Twin', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        Product::create(['code' => 'X3', 'name' => 'Twin', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        $amb = $this->dry($this->stage('product', "Name,Weight\nTwin,1\n", [], ['match_by' => 'name']));
        $this->assertStringContainsString('More than one record', $amb['rows'][0]['errors'][0]);
    }

    public function test_a_deleted_record_blocks_reuse_of_its_code(): void
    {
        $p = Product::create(['code' => 'GONE', 'name' => 'Old', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        $p->delete();
        $r = $this->dry($this->stage('product', "Code,Name\nGONE,Again\n"));
        $this->assertStringContainsString('deleted items', $r['rows'][0]['errors'][0]);
    }

    // ---- tooling --------------------------------------------------------------------------------------------

    public function test_patterns_import_with_marks_types_and_dimensions(): void
    {
        $csv = "Pattern No,Name,Type,Status,Length mm,Width mm,Height mm,Mark\nPT-1,Pump plate,Match plate,Live,450,300,60,ab123\nPT-2,Cover set,Cope & drag,Scrapped,200,200,50,\n";
        $b = $this->stage('pattern', $csv);
        $this->assertSame(0, $this->dry($b)['counts']['ERROR']);
        $this->post(route('imports.commit', $b));
        $p = Pattern::where('code', 'PT-1')->firstOrFail();
        $this->assertSame('MATCHPLATE', $p->pattern_type);
        $this->assertSame('ACTIVE', $p->status);
        $this->assertSame('AB123', $p->mark_code);
        $this->assertDatabaseHas('tool_marks', ['mark_code' => 'AB123', 'tool_type' => 'pattern', 'tool_id' => $p->id]);
        $p2 = Pattern::where('code', 'PT-2')->firstOrFail();
        $this->assertSame('COPE_DRAG', $p2->pattern_type);
        $this->assertSame('RETIRED', $p2->status);
        $this->assertNotEmpty($p2->mark_code, 'a blank mark gets the next free mark');
        $this->assertEquals(450, (float) $p->length_mm);
    }

    public function test_patterns_import_adds_their_first_revision_once(): void
    {
        $old = Pattern::create(['code' => 'PT-OLD', 'name' => 'Old plate']);
        PatternRevision::create(['pattern_id' => $old->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $csv = "Pattern No,Name,Rev,Rev status\nPT-1,Pump plate,a,Released\nPT-OLD,Old plate,B,\nPT-3,No revision plate,,\n";
        $b = $this->stage('pattern', $csv);
        $by = collect($this->dry($b)['rows'])->keyBy('n');
        $this->assertSame('UPDATE', $by[3]['action'], 'an existing pattern without the revision gets it added');
        $this->assertContains('Adds revision B (DRAFT).', $by[3]['notes']);

        $this->post(route('imports.commit', $b));
        $this->assertSame([['A', 'APPROVED']], PatternRevision::where('pattern_id', Pattern::where('code', 'PT-1')->value('id'))->get()->map(fn ($r) => [$r->revision, $r->status])->all());
        $this->assertSame(['A', 'B'], $old->revisions()->orderBy('revision')->pluck('revision')->all());
        $this->assertSame(0, Pattern::where('code', 'PT-3')->firstOrFail()->revisions()->count());

        $again = $this->dry($this->stage('pattern', $csv));
        $this->assertSame(3, $again['counts']['UNCHANGED'], 'a revision the pattern has is not added twice');
    }

    /** Patterns PT-1 (no revision yet) and PT-2 (revision A, 2 cavities per mould); products VB-200 (A obsolete, B) and LID-9 (A). */
    private function cavityFixtures(): array
    {
        $pt1 = Pattern::create(['code' => 'PT-1', 'name' => 'Valve plate', 'mark_code' => 'VP001']);
        $pt2 = Pattern::create(['code' => 'PT-2', 'name' => 'Lid plate']);
        $pt2a = PatternRevision::create(['pattern_id' => $pt2->id, 'revision' => 'A', 'cavities_per_mould' => 2]);
        $vb = Product::create(['code' => 'VB-200', 'name' => 'Valve body']);
        ProductRevision::create(['product_id' => $vb->id, 'revision' => 'A', 'status' => 'OBSOLETE']);
        $vbB = ProductRevision::create(['product_id' => $vb->id, 'revision' => 'B', 'status' => 'APPROVED']);
        $lid = Product::create(['code' => 'LID-9', 'name' => 'Valve lid']);
        $lidA = ProductRevision::create(['product_id' => $lid->id, 'revision' => 'A', 'status' => 'APPROVED']);

        return compact('pt1', 'pt2', 'pt2a', 'vbB', 'lidA');
    }

    public function test_pattern_cavities_import_links_products_and_adds_the_pattern_revision(): void
    {
        $f = $this->cavityFixtures();
        $csv = "Pattern code,Pattern rev,Cavity no,Product code,Product rev,Role,Component,Casting weight,Poured weight\n"
            ."vp001,A,1,VB-200,,Whole,,2.4,3.1\n"
            ."PT-1,A,2,Valve lid,,Component,Lid,0.8,1\n"
            ."PT-2,,1,LID-9,A,,,,\n";
        $b = $this->stage('pattern_cavity', $csv, [], ['match_by' => 'cavity']);
        $this->get(route('imports.map', $b))->assertOk()->assertSee('Pattern, revision and cavity number');
        $r = $this->dry($b);
        $this->assertSame(['CREATE' => 3, 'UPDATE' => 0, 'UNCHANGED' => 0, 'SKIP' => 0, 'ERROR' => 0, 'TOTAL' => 3], $r['counts']);
        $this->assertSame(['Adds pattern revision A (DRAFT).'], $r['rows'][0]['notes']);
        $this->assertSame([], $r['rows'][1]['notes'], 'the new revision is announced once');
        $this->assertSame(0, PatternRevisionCavity::count());
        $this->get(route('imports.preview', $b))->assertOk()->assertSee('PT-1 rev A · cavity 2')->assertSee('LID-9 rev A · part: Lid');

        $this->post(route('imports.commit', $b))->assertRedirect(route('imports.show', $b));
        $pt1a = PatternRevision::where('pattern_id', $f['pt1']->id)->sole();
        $this->assertSame(['A', 'DRAFT'], [$pt1a->revision, $pt1a->status]);
        $map = $pt1a->cavityMap;
        $this->assertSame([$f['vbB']->id, 'WHOLE', null, 1], [$map[0]->product_revision_id, $map[0]->cavity_role, $map[0]->component_label, $map[0]->pieces_per_product]);
        $this->assertEquals(3.1, (float) $map[0]->poured_weight_kg);
        $this->assertSame([$f['lidA']->id, 'PART', 'Lid'], [$map[1]->product_revision_id, $map[1]->cavity_role, $map[1]->component_label]);
        $this->assertSame($f['lidA']->id, $f['pt2a']->cavityMap()->sole()->product_revision_id, 'a blank revision means the pattern\'s only revision');

        $this->assertSame(3, $this->dry($this->stage('pattern_cavity', $csv, [], ['match_by' => 'cavity']))['counts']['UNCHANGED']);
        $changed = $this->dry($this->stage('pattern_cavity', str_replace('2.4,3.1', '2.5,3.1', $csv), [], ['match_by' => 'cavity']), true);
        $this->assertSame(1, $changed['counts']['UPDATE']);
        $this->assertSame(['casting_weight_kg' => ['2.4000', 2.5]], $changed['rows'][0]['changes']);
        $this->assertEquals(2.5, (float) $map[0]->fresh()->casting_weight_kg);
    }

    public function test_pattern_cavity_rows_follow_the_cavity_map_rules(): void
    {
        $f = $this->cavityFixtures();
        PatternRevision::create(['pattern_id' => $f['pt1']->id, 'revision' => 'A']);
        PatternRevision::create(['pattern_id' => $f['pt1']->id, 'revision' => 'B']);
        $csv = "Pattern,Pattern revision,Cavity,Product,Product revision,Makes,Component,Casting kg,Poured kg\n"
            ."PT-9,A,1,VB-200,B,,,,\n"        // 2 unknown pattern
            ."PT-1,,1,VB-200,B,,,,\n"         // 3 pattern has several revisions
            ."PT-2,B,1,VB-200,C,,,,\n"        // 4 product has no such revision
            ."PT-2,C,1,VB-200,A,,,,\n"        // 5 obsolete product revision
            ."PT-2,D,1,VB-200,B,Part,,,\n"    // 6 part without a component
            ."PT-2,E,1,VB-200,B,,,5,4\n"      // 7 poured below casting
            ."PT-2,A,3,VB-200,B,,,,\n"        // 8 beyond the cavities per mould
            ."PT-2,A,2,VB-200,B,,,,\n"        // 9 fine
            ."PT-2,a,2,LID-9,,,,,\n";         // 10 same cavity as row 9
        $by = collect($this->dry($this->stage('pattern_cavity', $csv, [], ['match_by' => 'cavity']))['rows'])->keyBy('n');
        $expect = [
            2 => "Pattern 'PT-9' was not found", 3 => 'has revisions A, B', 4 => 'has no revision C', 5 => 'is obsolete',
            6 => 'Name the component', 7 => 'cannot be below the casting weight', 8 => 'cavity 3 does not exist', 10 => 'as row 9',
        ];
        foreach ($expect as $n => $message) {
            $this->assertSame('ERROR', $by[$n]['action'], "row $n");
            $this->assertStringContainsString($message, implode(' ', $by[$n]['errors']), "row $n");
        }
        $this->assertSame('CREATE', $by[9]['action']);
    }

    public function test_a_cavity_used_on_a_mould_keeps_its_product(): void
    {
        $f = $this->cavityFixtures();
        $f['pt2a']->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $f['vbB']->id]);
        $cfg = MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $asm = MouldAssembly::create(['mould_configuration_id' => $cfg->id, 'assembly_number' => 'ASSY-1']);
        $layer = MouldLayer::create(['mould_assembly_id' => $asm->id, 'layer_number' => 1, 'layer_type' => 'COPE']);
        MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 1, 'pattern_revision_id' => $f['pt2a']->id, 'product_revision_id' => $f['vbB']->id]);

        $r = $this->dry($this->stage('pattern_cavity', "Pattern,Cavity,Product,Notes\nPT-2,1,LID-9,\nPT-2,1,VB-200,Checked\n", [], ['match_by' => 'cavity', 'stop_on_error' => 0]));
        $this->assertStringContainsString('already used on a mould', $r['rows'][0]['errors'][0]);
        $this->assertSame('ERROR', $r['rows'][1]['action'], 'the same cavity twice in one file');

        $r = $this->dry($this->stage('pattern_cavity', "Pattern,Cavity,Notes\nPT-2,1,Checked\n", [], ['match_by' => 'cavity', 'mode' => 'UPDATE']), true);
        $this->assertSame('UPDATE', $r['rows'][0]['action'], 'other details of a used cavity can still change');
        $this->assertSame('Checked', $f['pt2a']->cavityMap()->sole()->notes);
    }

    public function test_engraving_marks_must_be_unique_across_all_tooling(): void
    {
        $existing = Pattern::create(['code' => 'OLD', 'name' => 'Old', 'mark_code' => 'MK001']);
        $csv = "Code,Name,Mark\nNEW1,One,MK001\nNEW2,Two,MK002\nNEW3,Three,MK002\nOLD,Old renamed,MK001\n";
        $r = $this->dry($this->stage('pattern', $csv));
        $by = collect($r['rows'])->keyBy('n');
        $this->assertStringContainsString('already on another tool', $by[2]['errors'][0]);
        $this->assertSame('CREATE', $by[3]['action']);
        $this->assertStringContainsString('also on row 3', $by[4]['errors'][0]);
        $this->assertSame('UPDATE', $by[5]['action'], 'a record may keep its own mark');
    }

    public function test_core_boxes_find_their_core_by_code_or_name(): void
    {
        $a = CoreDefinition::create(['code' => 'COR-A', 'name' => 'Water jacket core', 'core_type' => 'STANDARD']);
        $csv = "Box Code,Name,Core,Cavities,Rev,Commissioned\nCB-1,Jacket box,COR-A,2,A,15/03/2023\nCB-2,Jacket box 2,water jacket core,1,B,\nCB-3,Orphan,NOPE,1,A,\nCB-4,Clash,COR-A,1,A,\n";
        $b = $this->stage('core_box', $csv);
        $r = $this->dry($b);
        $by = collect($r['rows'])->keyBy('n');
        $this->assertSame('CREATE', $by[2]['action']);
        $this->assertSame('CREATE', $by[3]['action']);
        $this->assertStringContainsString("Core 'NOPE' was not found", $by[4]['errors'][0]);
        $this->assertStringContainsString('revision A', $by[5]['errors'][0]);
        $this->post(route('imports.commit', $b));
        $box = CoreBox::where('code', 'CB-1')->firstOrFail();
        $this->assertSame($a->id, $box->core_definition_id);
        $this->assertSame(2, $box->cavities);
        $this->assertSame('2023-03-15', $box->commissioned_on->format('Y-m-d'));
        $this->assertSame($a->id, CoreBox::where('code', 'CB-2')->value('core_definition_id'));
        $this->assertNotEmpty($box->fresh()->mark_code);
    }

    public function test_cores_and_mould_configurations_use_their_defaults(): void
    {
        $b = $this->stage('core_definition', "Code,Name\nCR-1,Shell core\n");
        $this->post(route('imports.commit', $b));
        $this->assertSame('STANDARD', DB::table('core_definitions')->where('code', 'CR-1')->value('core_type'));
        $b = $this->stage('mould_configuration', "Code,Name,Layers,Active\nMC-1,Two tier,3,no\nMC-2,Single,,\n");
        $this->post(route('imports.commit', $b));
        $this->assertSame(3, (int) DB::table('mould_configurations')->where('code', 'MC-1')->value('nominal_layer_count'));
        $this->assertSame(2, (int) DB::table('mould_configurations')->where('code', 'MC-2')->value('nominal_layer_count'));
        $this->assertFalse((bool) DB::table('mould_configurations')->where('code', 'MC-1')->value('is_active'));
        $this->assertSame('GREEN_SAND', DB::table('mould_configurations')->where('code', 'MC-2')->value('mould_type'));
    }

    public function test_a_big_file_is_imported_in_steps_without_repeating_rows(): void
    {
        config(['imports.commit_seconds' => 0]);   // every request handles one block of 100 rows
        $csv = "Code,Name,Weight\n";
        for ($i = 1; $i <= 250; $i++) {
            $csv .= "BIG-$i,Big part $i,".($i % 7 + 1)."\n";
        }
        $csv .= "BIG-1,Duplicate of the first,1\n,Auto coded,2\n";
        $b = $this->stage('product', $csv);
        $this->post(route('imports.commit', $b))->assertRedirect(route('imports.progress', $b));
        $this->assertSame(100, Product::count());
        $this->assertSame('RUNNING', $b->fresh()->status);
        $this->get(route('imports.progress', $b))->assertOk()->assertSee('100')->assertSee('of 252');
        $this->get(route('imports.preview', $b))->assertRedirect(route('imports.progress', $b));
        $this->post(route('imports.commit', $b))->assertRedirect(route('imports.progress', $b));
        $this->post(route('imports.commit', $b))->assertRedirect(route('imports.show', $b));
        $b = $b->fresh();
        $this->assertSame('COMMITTED', $b->status);
        $this->assertSame(251, Product::count());
        $this->assertEquals(['CREATE' => 251, 'UPDATE' => 0, 'UNCHANGED' => 0, 'SKIP' => 0, 'ERROR' => 1, 'TOTAL' => 252], $b->counts);
        $this->assertCount(252, $b->report);
        $this->assertStringContainsString('Same code as row 2', collect($b->report)->firstWhere('action', 'ERROR')['message']);
        $this->assertSame(1, Product::where('name', 'Auto coded')->count());
        $this->assertSame(1, Product::where('code', 'BIG-1')->count());
    }

    // ---- sources --------------------------------------------------------------------------------------------

    public function test_excel_file_upload_end_to_end(): void
    {
        $f = XlsxFactory::make(['Register' => [['Code', 'Name', 'Core', 'Commissioned'], ['CB-9', 'Excel box', 'CR-9', new \DateTimeImmutable('2022-11-05', new \DateTimeZone('UTC'))]]]);
        CoreDefinition::create(['code' => 'CR-9', 'name' => 'Nine', 'core_type' => 'STANDARD']);
        $this->post(route('imports.store'), ['entity' => 'core_box', 'source' => 'FILE', 'file' => new UploadedFile($f, 'register.xlsx', null, null, true)])->assertSessionHasNoErrors();
        $b = ImportBatch::firstOrFail();
        $this->assertSame('xlsx', $b->file_ext);
        $this->get(route('imports.map', $b))->assertOk()->assertSee('matched by heading')->assertSee('Core it makes');
        $b = $this->map($b);
        $this->post(route('imports.commit', $b));
        $this->assertSame('2022-11-05', CoreBox::where('code', 'CB-9')->firstOrFail()->commissioned_on->format('Y-m-d'));
    }

    public function test_google_sheet_is_read_through_its_csv_export(): void
    {
        $id = 'abcdefghijklmnopqrstuvwx12345';
        $this->assertSame("https://docs.google.com/spreadsheets/d/$id/export?format=csv&gid=77", GoogleSheetsFetcher::exportUrl("https://docs.google.com/spreadsheets/d/$id/edit#gid=77"));
        Http::fake(['docs.google.com/*' => Http::response("Code,Name\nG1,From Google\n", 200, ['Content-Type' => 'text/csv'])]);
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'GOOGLE', 'google_url' => "https://docs.google.com/spreadsheets/d/$id/edit#gid=0"])->assertSessionHasNoErrors();
        $b = $this->map(ImportBatch::firstOrFail());
        $this->post(route('imports.commit', $b));
        $this->assertSame('From Google', Product::where('code', 'G1')->value('name'));
    }

    public function test_private_or_wrong_google_links_give_advice(): void
    {
        $id = 'abcdefghijklmnopqrstuvwx12345';
        Http::fake(['docs.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html'])]);
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'GOOGLE', 'google_url' => "https://docs.google.com/spreadsheets/d/$id/edit"])->assertSessionHasErrors('source');
        $this->assertStringContainsString('Anyone with the link', session('errors')->first('source'));
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'GOOGLE', 'google_url' => 'https://evil.example.com/spreadsheets/d/'.$id])->assertSessionHasErrors('source');
        Http::assertSentCount(1);   // the second address was refused without any request
        $this->assertSame(0, ImportBatch::count());
    }

    public function test_pasted_cells_from_a_spreadsheet_are_read(): void
    {
        $b = $this->stage('pattern', "Code\tName\tLength\nPT-7\tPasted plate\t120\n");
        $this->post(route('imports.commit', $b));
        $this->assertEquals(120, (float) Pattern::where('code', 'PT-7')->value('length_mm'));
    }

    public function test_empty_data_is_refused_and_headings_can_start_lower_down(): void
    {
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'PASTE', 'pasted' => "Code,Name\n"])->assertSessionHasErrors('source');
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'PASTE', 'pasted' => "Pattern register 2024\n\nCode,Name\nH1,After a title\n"])->assertSessionHasNoErrors();
        $b = ImportBatch::firstOrFail();
        $this->assertSame(3, $b->header_row);
        $b = $this->map($b);
        $this->post(route('imports.commit', $b));
        $this->assertSame('After a title', Product::where('code', 'H1')->value('name'));
    }

    // ---- mapping rules, templates, reports, access ----------------------------------------------------------

    public function test_mapping_rules_are_enforced(): void
    {
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'PASTE', 'pasted' => "A,B\n1,2\n"]);
        $b = ImportBatch::firstOrFail();
        $base = ['sheet_index' => 0, 'header_row' => 1, 'mode' => 'UPSERT', 'match_by' => 'code', 'date_order' => 'DMY'];
        $this->post(route('imports.map.save', $b), $base + ['mapping' => ['code' => ['col' => 0]]])->assertSessionHasErrors('mapping');   // name missing
        $this->post(route('imports.map.save', $b), $base + ['mapping' => ['code' => ['col' => 0], 'name' => ['col' => 0]]])->assertSessionHasErrors('mapping');   // one column twice
        $this->post(route('imports.map.save', $b), array_merge($base, ['mode' => 'UPDATE', 'mapping' => ['name' => ['col' => 1]]]))->assertSessionHasErrors('mapping');   // nothing to match on
        $this->post(route('imports.map.save', $b), $base + ['mapping' => ['name' => ['col' => 1], 'code' => ['col' => 0]]])->assertRedirect(route('imports.preview', $b));
        $this->post(route('imports.map.save', $b), $base + ['mapping' => ['name' => ['default' => 'Everything is one name']]])->assertRedirect(route('imports.preview', $b));   // a fixed value counts
    }

    public function test_a_mapping_can_be_saved_and_reused_by_heading(): void
    {
        $b = $this->stage('product', "Item,Label,Mass\nS1,Saved,4\n", ['code' => ['col' => 0], 'name' => ['col' => 1], 'nominal_weight_kg' => ['col' => 2]], ['save_as' => 'Stores sheet']);
        $this->assertDatabaseHas('import_mapping_templates', ['entity' => 'product', 'name' => 'Stores sheet']);
        $this->post(route('imports.store'), ['entity' => 'product', 'source' => 'PASTE', 'pasted' => "Mass,Label,Item\n9,Other file,S2\n"]);
        $b2 = ImportBatch::orderByDesc('created_at')->firstOrFail();
        $t = \App\Models\ImportMappingTemplate::firstOrFail();
        $this->get(route('imports.map', [$b2, 'template' => $t->id]))->assertOk();
        $m = \App\Services\Import\ColumnMapper::fromTemplate($t, ['Mass', 'Label', 'Item']);
        $this->assertSame([2, 1, 0], [$m['code']['col'], $m['name']['col'], $m['nominal_weight_kg']['col']]);
        $this->delete(route('imports.templates.destroy', $t))->assertRedirect();
        $this->assertSame(0, \App\Models\ImportMappingTemplate::count());
    }

    public function test_report_download_neutralises_spreadsheet_formulas_and_template_downloads(): void
    {
        $b = $this->stage('product', "Code,Name\nF1,=cmd|' /C calc'!A0\n");
        $this->post(route('imports.commit', $b));
        $csv = $this->get(route('imports.report', $b))->assertOk()->getContent();
        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringNotContainsString(',=cmd', $csv);
        $tpl = $this->get(route('imports.template', 'pattern'))->assertOk()->getContent();
        $this->assertStringContainsString('Engraving mark', $tpl);
        $this->get(route('imports.template', 'nonsense'))->assertNotFound();
    }

    public function test_discarding_an_import_leaves_the_data_alone(): void
    {
        $b = $this->stage('product', "Code,Name\nD1,Discarded\n");
        $this->delete(route('imports.destroy', $b))->assertRedirect(route('imports.index'));
        $this->assertSame(0, Product::count());
        $this->get(route('imports.preview', $b))->assertNotFound();
    }

    public function test_pages_load_and_access_follows_permissions(): void
    {
        $this->get(route('imports.index'))->assertOk()->assertSee('Core boxes')->assertSee('Mould configurations')->assertSee('Pattern cavities');
        $this->get(route('imports.template', 'pattern_cavity'))->assertOk()->assertSee('Pattern revision')->assertSee('Cavity number');
        $this->get(route('imports.create', ['entity' => 'pattern']))->assertOk()->assertSee('Google Sheets')->assertSee('Engraving mark');
        $this->get(route('products.index'))->assertOk()->assertSee('Import');
        config(['foundry.require_login' => true]);
        $mk = function (string $role) {
            $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
            $u->roles()->attach(Role::where('code', $role)->value('id'));

            return $u;
        };
        $this->actingAs($mk('SALES'))->get(route('imports.index'))->assertForbidden();
        $this->actingAs($mk('SALES'))->post(route('imports.store'), [])->assertForbidden();
        $roles = config('permissions.routes');
        $this->assertContains('imports.*', array_column($roles, 'pattern'));
    }
}
