<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Livewire requests from non-administrators: allowed by the component map, read-only parts need only the page. */
class LivewireAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    /** @param  list<string>  $calls */
    private function lw(string $component, array $calls = [], array $updates = []): array
    {
        return ['snapshot' => json_encode(['memo' => ['name' => $component]]), 'updates' => $updates, 'calls' => array_map(fn ($m) => ['method' => $m, 'params' => []], $calls)];
    }

    public function test_the_bell_poll_works_for_a_production_manager(): void
    {
        config(['foundry.require_login' => true]);
        $pm = $this->user('PRODUCTION_MANAGER');
        $html = $this->actingAs($pm)->get(route('home'))->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"[^>]*wire:id="[^"]+"[^>]*>\s*<div class="notice-center"|<div[^>]*wire:snapshot="([^"]*notification-center[^"]*)"/', $html, $m);
        $snapshot = html_entity_decode($m[2] ?? $m[1] ?? '');
        $this->assertStringContainsString('notification-center', $snapshot, 'the bell renders on the home page');

        $this->actingAs($pm)->withHeaders(['X-Livewire' => '1'])
            ->postJson(route('default-livewire.update'), ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'pollForNew', 'params' => [], 'metadata' => []]]]]])
            ->assertOk();
    }

    public function test_read_only_parts_need_only_the_page_and_changes_need_the_permission(): void
    {
        $pm = $this->user('PRODUCTION_MANAGER');
        $sales = $this->user('SALES');

        $this->assertTrue(EnforceAccess::livewireAllows($pm, [$this->lw('notification-center', ['markAllRead'])]));
        $this->assertTrue(EnforceAccess::livewireAllows($pm, [$this->lw('customer-list', ['resetFilters', 'gotoPage'], ['search' => 'acme'])]), 'filtering a list is reading');
        $this->assertFalse(EnforceAccess::livewireAllows($pm, [$this->lw('customer-list', ['save'], ['form.name' => 'X'])]), 'saving a customer needs the customer permission');
        $this->assertTrue(EnforceAccess::livewireAllows($sales, [$this->lw('customer-list', ['save'], ['form.name' => 'X'])]));
        $this->assertFalse(EnforceAccess::livewireAllows($pm, [$this->lw('ledger::opening-balances', [], ['goLive' => '2026-07-01'])]), 'a field that saves on change is a change');
        $this->assertFalse(EnforceAccess::livewireAllows($pm, [$this->lw('something-unlisted')]), 'an unlisted component stays with administrators');
        $this->assertFalse(EnforceAccess::livewireAllows($pm, [$this->lw('notification-center'), $this->lw('ledger::chart', ['deleteTicked'])]), 'every component in the request must pass');
    }
}
