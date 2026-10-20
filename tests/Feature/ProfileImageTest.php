<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_image_is_stored_and_served_and_beats_gravatar(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('account.profile.update'), ['profile_image' => UploadedFile::fake()->createWithContent('me.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')), 'use_gravatar' => '1'])->assertSessionHasNoErrors();

        $user->refresh();
        Storage::disk('local')->assertExists($user->profile_image_path);
        $this->assertTrue($user->use_gravatar);
        $this->assertStringContainsString('/account/avatar/', $user->avatarUrl());
        $this->actingAs($user)->get(route('account.avatar', $user))->assertOk();

        $this->actingAs($user)->put(route('account.profile.update'), ['remove_image' => '1'])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNull($user->profile_image_path);
        $this->assertFalse($user->use_gravatar);
    }

    public function test_gravatar_is_used_only_when_switched_on_and_no_image(): void
    {
        $user = User::factory()->create(['email' => ' Me@Example.com ']);
        $this->assertNull($user->avatarUrl());
        $user->use_gravatar = true;
        $this->assertStringContainsString(md5('me@example.com'), $user->avatarUrl());
    }

    public function test_non_images_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create())->put(route('account.profile.update'), ['profile_image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('profile_image');
    }
}
