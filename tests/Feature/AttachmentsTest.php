<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $product = Product::create(['code' => 'PH-1', 'name' => 'Photo part']);
        $pattern = Pattern::create(['code' => 'PT-PH', 'name' => 'Photo pattern', 'pattern_type' => 'LOOSE']);

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

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function attachTo(string $type, string $id, string $category): Attachment
    {
        $this->post(route('attachments.store', [$type, $id]), ['files' => [$this->png('file.png')], 'category' => $category])->assertSessionHasNoErrors();

        return Attachment::where('attachable_type', $type)->where('category', $category)->firstOrFail();
    }

    public function test_files_follow_the_permissions_of_their_record(): void
    {
        Storage::fake('local');
        $heat = $this->heat();
        $employee = Employee::create(['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'weekly_off' => '0']);
        $heatReport = $this->attachTo('heat', $heat->id, 'REPORT');
        $employeeReport = $this->attachTo('employee', $employee->id, 'REPORT');
        $employeePhoto = $this->attachTo('employee', $employee->id, 'PHOTO');
        $passport = $this->attachTo('employee', $employee->id, 'ID_DOCUMENT');
        config(['foundry.require_login' => true]);
        $sales = $this->userWithRole('SALES');
        $hr = $this->userWithRole('HR_MANAGER');
        $melt = $this->userWithRole('MELT_SUPERVISOR');

        // Sales may attach files, but not to a heat, and cannot read HR files beyond the staff photo.
        $this->actingAs($sales)->post(route('attachments.store', ['heat', $heat->id]), ['files' => [$this->png('a.png')], 'category' => 'PHOTO'])->assertForbidden();
        $this->actingAs($sales)->post(route('attachments.remove', $heatReport), ['removal_reason' => 'x'])->assertForbidden();
        $this->actingAs($sales)->get(route('attachments.download', $heatReport))->assertOk();
        $this->actingAs($sales)->get(route('attachments.download', $employeeReport))->assertForbidden();
        $this->actingAs($sales)->get(route('attachments.download', $passport))->assertForbidden();
        $this->actingAs($sales)->get(route('attachments.download', $employeePhoto))->assertOk();

        $this->actingAs($melt)->post(route('attachments.store', ['heat', $heat->id]), ['files' => [$this->png('b.png')], 'category' => 'PHOTO'])->assertSessionHasNoErrors();

        $this->actingAs($hr)->get(route('attachments.download', $passport))->assertOk();
        $this->actingAs($hr)->post(route('attachments.store', ['employee', $employee->id]), ['files' => [$this->png('c.png')], 'category' => 'CONTRACT'])->assertSessionHasNoErrors();
    }

    public function test_id_documents_need_their_own_permission_and_are_never_the_main_photo(): void
    {
        Storage::fake('local');
        $employee = Employee::create(['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'weekly_off' => '0']);
        $passport = $this->attachTo('employee', $employee->id, 'ID_DOCUMENT');
        $this->assertNull(Attachment::coverFor('employee', $employee->id));

        config(['foundry.require_login' => true]);
        $hrWithoutSensitive = $this->userWithRole('HR_MANAGER');
        DB::table('role_permissions')->where('permission', 'documents.sensitive')->delete();

        $this->actingAs($hrWithoutSensitive)->get(route('attachments.download', $passport))->assertForbidden();
        $this->actingAs($hrWithoutSensitive)->post(route('attachments.store', ['employee', $employee->id]), ['files' => [$this->png('d.png')], 'category' => 'ID_DOCUMENT'])->assertForbidden();
        $this->actingAs($hrWithoutSensitive)->get(route('hr.employees.show', $employee))->assertOk()->assertDontSee('ID / personal document');
    }
}
