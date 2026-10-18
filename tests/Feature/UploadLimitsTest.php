<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(): Pattern
    {
        return Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
    }

    private function attach(Pattern $pattern, UploadedFile $file)
    {
        return $this->post(route('attachments.store', ['pattern', $pattern->id]), ['files' => [$file], 'category' => 'DRAWING']);
    }

    public function test_the_default_limit_is_eight_megabytes(): void
    {
        $this->assertSame(8192, Uploads::appMaxKb());
    }

    public function test_a_file_over_the_limit_gets_a_message_with_the_size_in_megabytes(): void
    {
        $pattern = $this->pattern();

        $this->attach($pattern, UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf'))
            ->assertSessionHasErrors(['files.0' => 'Each file may be at most 8 MB.']);
    }

    public function test_a_file_that_php_refused_gets_a_clear_message_instead_of_failed_to_upload(): void
    {
        $pattern = $this->pattern();
        $refused = new UploadedFile(__FILE__, 'big.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $this->attach($pattern, $refused)->assertSessionHasErrors('files.0');
        $this->assertStringNotContainsString('failed to upload', session('errors')->first('files.0'));
        $this->assertStringContainsString('could not be uploaded', session('errors')->first('files.0'));
    }

    public function test_a_wrong_file_type_names_the_allowed_types(): void
    {
        $this->attach($this->pattern(), UploadedFile::fake()->create('tool.exe', 10))
            ->assertSessionHasErrors(['files.0']);
        $this->assertStringContainsString('Allowed:', session('errors')->first('files.0'));
    }

    public function test_the_attachments_panel_states_the_limit(): void
    {
        $this->get(route('patterns.show', $this->pattern()))->assertSee('up to '.Uploads::megabytes(Uploads::effectiveMaxKb()).' each', false);
    }

    public function test_megabytes_reads_naturally(): void
    {
        $this->assertSame(['8 MB', '1.5 MB', '2 MB'], [Uploads::megabytes(8192), Uploads::megabytes(1536), Uploads::megabytes(2048)]);
    }
}
