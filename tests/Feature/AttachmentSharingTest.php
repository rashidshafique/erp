<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AttachmentLink;
use App\Models\AttachmentPin;
use App\Models\Customer;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Support\DocumentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentSharingTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $name, int $revision): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n% revision {$revision}\ntrailer<<>>\n%%EOF\n");
    }

    private function attach(string $type, string $id, string $category, string $name, int $revision = 1): Attachment
    {
        $this->post(route('attachments.store', [$type, $id]), ['files' => [$this->pdf(str_replace(' ', '-', strtolower($name)).'.pdf', $revision)], 'category' => $category, 'title' => $name])->assertSessionHasNoErrors();

        return Attachment::where('title', $name)->firstOrFail();
    }

    /** A sales order for a product that is cast on a pattern. */
    private function chain(): array
    {
        $customer = Customer::create(['code' => 'C-1', 'name' => 'Acme', 'active' => true]);
        $product = Product::create(['code' => 'PRD-1', 'name' => 'Housing']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => 'SO-1', 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'DRAFT']);
        SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'line_number' => 1, 'ordered_qty' => 5, 'uom' => 'piece']);
        $pattern = Pattern::create(['code' => 'PAT-1', 'name' => 'Housing pattern', 'pattern_type' => 'LOOSE_PATTERN']);
        $patternRevision = PatternRevision::create(['pattern_id' => $pattern->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $patternRevision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);

        return compact('order', 'product', 'pattern');
    }

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-S1', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
    }

    public function test_a_file_can_be_linked_to_another_record_without_a_copy_and_follows_new_versions(): void
    {
        Storage::fake('local');
        DocumentVersioning::save(true, ['CERTIFICATE']);
        $heat = $this->heat();
        $furnace = $heat->furnace;
        $cert = $this->attach('furnace', $furnace->id, 'CERTIFICATE', 'Refractory certificate');

        $this->getJson(route('attachments.find', ['q' => 'refractory', 'type' => 'heat', 'id' => $heat->id]))->assertJsonPath('0.name', 'Refractory certificate');
        $this->post(route('attachments.link', [$cert, 'heat', $heat->id]))->assertSessionHasNoErrors();
        $this->post(route('attachments.link', [$cert, 'heat', $heat->id]))->assertSessionHasNoErrors();   // linking twice is harmless

        $this->assertSame(1, AttachmentLink::count());
        $this->assertSame(1, Attachment::count());
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Linked files')->assertSee('Refractory certificate');
        $this->getJson(route('attachments.find', ['q' => 'refractory', 'type' => 'heat', 'id' => $heat->id]))->assertJsonCount(0);   // already linked

        $this->post(route('attachments.version', $cert), ['file' => $this->pdf('cert-2.pdf', 2), 'revision_label' => 'Rev B'])->assertSessionHasNoErrors();
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Rev B');   // the link shows the newest version

        $this->post(route('attachments.unlink', AttachmentLink::firstOrFail()))->assertSessionHasNoErrors();
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('Linked files');
        $this->assertSame(2, Attachment::count());   // the file itself stays
    }

    public function test_linking_needs_rights_on_both_records(): void
    {
        Storage::fake('local');
        $heat = $this->heat();
        $cert = $this->attach('furnace', $heat->furnace->id, 'CERTIFICATE', 'Refractory certificate');
        config(['foundry.require_login' => true]);
        $sales = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $sales->roles()->attach(Role::where('code', 'SALES')->value('id'));

        $this->actingAs($sales)->post(route('attachments.link', [$cert, 'heat', $heat->id]))->assertForbidden();   // sales cannot change a heat's files
    }

    public function test_a_record_lists_the_files_of_the_records_it_comes_from(): void
    {
        Storage::fake('local');
        ['order' => $order, 'product' => $product, 'pattern' => $pattern] = $this->chain();
        $this->attach('product', $product->id, 'DRAWING', 'Housing drawing');
        $this->attach('pattern', $pattern->id, 'SPECIFICATION', 'Pattern spec');
        $this->attach('product', $product->id, 'PHOTO', 'Housing photo');
        $this->attach('product', $product->id, 'ID_DOCUMENT', 'Secret scan');

        $this->get(route('sales-orders.show', $order))->assertOk()
            ->assertSee('From related records')->assertSee('Housing drawing')->assertSee('Pattern spec')
            ->assertDontSee('Housing photo')->assertDontSee('Secret scan');

        $this->put(route('settings.documents.update'), ['related' => 0])->assertSessionHasNoErrors();
        $this->get(route('sales-orders.show', $order))->assertOk()->assertDontSee('From related records');
    }

    public function test_pinned_versions_stay_put_when_a_newer_version_is_uploaded(): void
    {
        Storage::fake('local');
        DocumentVersioning::save(true, ['DRAWING']);
        ['order' => $order, 'product' => $product] = $this->chain();
        $v1 = $this->attach('product', $product->id, 'DRAWING', 'Housing drawing');

        $this->post(route('attachments.pin', ['sales_order', $order->id]))->assertSessionHasNoErrors();
        $this->assertSame($v1->id, AttachmentPin::firstOrFail()->attachment_id);

        $this->post(route('attachments.version', $v1), ['file' => $this->pdf('drawing-b.pdf', 2), 'revision_label' => 'Rev B'])->assertSessionHasNoErrors();
        $this->get(route('sales-orders.show', $order))->assertOk()->assertSee('Made to this version')->assertSee('Newer:')->assertSee('Rev B');

        $this->post(route('attachments.pin', ['sales_order', $order->id]))->assertSessionHasNoErrors();   // pinning again moves to the newest
        $this->assertSame(Attachment::where('version', 2)->value('id'), AttachmentPin::firstOrFail()->attachment_id);
    }

    public function test_pinning_is_unavailable_while_versioning_is_off(): void
    {
        ['order' => $order] = $this->chain();

        $this->post(route('attachments.pin', ['sales_order', $order->id]))->assertNotFound();
    }
}
