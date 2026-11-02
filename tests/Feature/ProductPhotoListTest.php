<?php

namespace Tests\Feature;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class ProductPhotoListTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    /** A real 1x1 PNG, so the test does not need the GD extension. */
    private function photoFor(string $productId): Attachment
    {
        $png = UploadedFile::fake()->createWithContent('part.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->post(route('attachments.store', ['product', $productId]), ['files' => [$png], 'category' => 'PHOTO'])->assertSessionHasNoErrors();

        return Attachment::where('attachable_type', 'product')->where('attachable_id', $productId)->firstOrFail();
    }

    public function test_the_lots_list_looks_up_every_product_photo_in_one_query(): void
    {
        Storage::fake('local');
        $lots = collect([$this->castingLot(), $this->castingLot(), $this->castingLot()]);
        $photos = $lots->map(fn ($lot) => $this->photoFor($lot->product_id));
        Attachment::forgetCovers();

        DB::enableQueryLog();
        $response = $this->get(route('lots.index'))->assertOk();
        $lookups = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'from "attachments"'));

        $this->assertCount(1, $lookups);
        $photos->each(fn (Attachment $photo) => $response->assertSee(route('attachments.download', $photo)));
    }

    public function test_a_photo_added_after_a_look_up_is_seen_in_the_same_request(): void
    {
        Storage::fake('local');
        $lot = $this->castingLot();
        Attachment::primeCovers('product', [$lot->product_id]);
        $this->assertNull(Attachment::cachedCover('product', $lot->product_id));

        $photo = $this->photoFor($lot->product_id);

        $this->assertTrue(Attachment::cachedCover('product', $lot->product_id)?->is($photo));
    }
}
