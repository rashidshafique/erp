<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Furnace;
use App\Models\Material;
use App\Models\Pattern;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    /** @return array<string, array{string, string, \Closure}> */
    public static function records(): array
    {
        return [
            'product' => ['product', 'products.show', fn () => Product::create(['code' => 'VALVE-1', 'name' => 'Valve body'])],
            'pattern' => ['pattern', 'patterns.show', fn () => Pattern::create(['code' => 'P-0123', 'name' => 'Flange plate'])],
            'material' => ['material', 'materials.show', fn () => Material::create(['code' => 'PIG-1', 'name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE'])],
            'furnace' => ['furnace', 'furnaces.show', fn () => Furnace::create(['code' => 'IF-1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE'])],
        ];
    }

    #[DataProvider('records')]
    public function test_photos_are_added_after_saving_and_open_in_the_lightbox(string $type, string $route, \Closure $make): void
    {
        Storage::fake(config('foundry.attachments.disk'));
        /** @var Model $record */
        $record = $make();
        $page = route($route, $record);
        $this->get($page)->assertOk()->assertSee('Add a photo')->assertSee(route('attachments.store', [$type, $record->getKey()]), false);

        $this->from($page)->post(route('attachments.store', [$type, $record->getKey()]), ['category' => 'PHOTO', 'files' => [$this->png('front.png'), $this->png('side.png')]])
            ->assertRedirect($page)->assertSessionHasNoErrors();

        $photos = Attachment::where('attachable_type', $type)->where('attachable_id', $record->getKey())->get();
        $this->assertSame(['PHOTO', 'PHOTO'], $photos->pluck('category')->all());
        $this->get($page)->assertOk()
            ->assertSee('Add photos')
            ->assertSee('data-lightbox="photos-'.$type.'-'.$record->getKey().'"', false)
            ->assertSee('href="'.route('attachments.download', $photos[0]).'"', false)->assertSee('href="'.route('attachments.download', $photos[1]).'"', false)
            ->assertSee('id="lightbox"', false);
    }

    public function test_the_photo_box_on_a_maintenance_record_has_no_upload_button(): void
    {
        $html = view('attachments._cover', ['type' => 'pattern', 'id' => Pattern::create(['code' => 'P-1', 'name' => 'Plate'])->id, 'upload' => false])->render();

        $this->assertStringNotContainsString('Add a photo', $html);
        $this->assertStringContainsString('No photo yet', $html);
    }
}
