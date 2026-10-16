<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Furnace;
use App\Models\Heat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 PNG, so the tests do not need the GD extension. */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-ATT1', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
    }

    public function test_upload_list_download_and_remove(): void
    {
        Storage::fake('local');
        $heat = $this->heat();
        $url = route('attachments.store', ['heat', $heat->id]);
        $this->post($url, ['files' => [UploadedFile::fake()->create('spectro report.pdf', 120, 'application/pdf')], 'category' => 'REPORT', 'title' => 'Spectro 14:20'])->assertSessionHasNoErrors();

        $a = Attachment::firstOrFail();
        $this->assertSame('REPORT', $a->category);
        $this->assertSame(64, strlen($a->sha256));
        $this->assertTrue(Storage::disk('local')->exists($a->path));
        $this->assertStringNotContainsString('spectro', $a->path);   // stored under a random name
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Spectro 14:20')->assertSee('Files');
        $this->get(route('attachments.download', $a))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->post(route('attachments.remove', $a), [])->assertSessionHasErrors('removal_reason');
        $this->post(route('attachments.remove', $a), ['removal_reason' => 'Wrong heat'])->assertSessionHasNoErrors();
        $this->get(route('attachments.download', $a))->assertNotFound();
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('Spectro 14:20');
        $this->assertTrue(Storage::disk('local')->exists($a->path));                                   // kept
        $this->assertTrue(AuditLog::where('entity_type', 'Attachment')->where('action', 'CREATED')->exists());
        $this->assertTrue(AuditLog::where('entity_type', 'Attachment')->where('action', 'UPDATED')->exists());
    }

    public function test_unsafe_oversized_and_disguised_files_are_refused(): void
    {
        Storage::fake('local');
        $heat = $this->heat();
        $url = route('attachments.store', ['heat', $heat->id]);
        $this->post($url, ['files' => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')], 'category' => 'OTHER'])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->create('page.html', 10, 'text/html')], 'category' => 'OTHER'])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')], 'category' => 'OTHER'])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->create('fake.pdf', 10, 'text/html')], 'category' => 'OTHER'])->assertSessionHasErrors('files');
        $this->post($url, ['files' => [], 'category' => 'OTHER'])->assertSessionHasErrors('files');
        $this->post($url, ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], 'category' => 'BOGUS'])->assertSessionHasErrors('category');
        $this->assertSame(0, Attachment::count());
        $this->post(route('attachments.store', ['nonsense', $heat->id]), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], 'category' => 'OTHER'])->assertNotFound();
    }

    public function test_main_photo_shows_in_product_and_pattern_summary_and_can_be_changed(): void
    {
        Storage::fake('local');
        $product = \App\Models\Product::create(['code' => 'PH-1', 'name' => 'Photo part']);
        $pattern = \App\Models\Pattern::create(['code' => 'PT-PH', 'name' => 'Photo pattern', 'pattern_type' => 'LOOSE']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('No photo yet');
        foreach ([['product', $product->id], ['pattern', $pattern->id]] as [$type, $id]) {
            $this->post(route('attachments.store', [$type, $id]), ['files' => [$this->png('first.png')], 'category' => 'PHOTO', 'title' => 'First view'])->assertSessionHasNoErrors();
            $this->post(route('attachments.store', [$type, $id]), ['files' => [$this->png('second.png')], 'category' => 'PHOTO', 'title' => 'Second view'])->assertSessionHasNoErrors();
        }
        // newest photo is the default
        $this->get(route('products.show', $product))->assertOk()->assertSee('alt="Second view"', false)->assertDontSee('No photo yet');
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('alt="Second view"', false);

        $first = Attachment::where('attachable_type', 'product')->where('title', 'First view')->firstOrFail();
        $this->post(route('attachments.cover', $first))->assertSessionHasNoErrors();
        $this->get(route('products.show', $product))->assertSee('alt="First view"', false);
        $this->assertSame(1, Attachment::where('attachable_type', 'product')->where('is_cover', true)->count());

        // a non-image cannot be the main photo
        $this->post(route('attachments.store', ['product', $product->id]), ['files' => [UploadedFile::fake()->create('d.pdf', 20, 'application/pdf')], 'category' => 'DRAWING']);
        $this->post(route('attachments.cover', Attachment::where('original_name', 'd.pdf')->firstOrFail()))->assertNotFound();
    }
}
