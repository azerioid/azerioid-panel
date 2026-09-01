<?php

namespace Tests\Feature;

use App\Livewire\ComponentsPage;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ComponentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_components_page_lists_registry_catalog_read_only(): void
    {
        $this->actingAs($this->admin());

        $this->get('/components')
            ->assertOk()
            ->assertSee('System (panel)', false)
            ->assertSee('Managed catalog', false)
            ->assertSee('Caddy', false)
            ->assertSee('PHP 8.4', false)
            ->assertSee('Redis', false)
            ->assertSee('Install', false);
    }

    public function test_redis_install_button_queues_operation(): void
    {
        $this->actingAs($this->admin());

        \Livewire\Livewire::test(ComponentsPage::class)
            ->call('install', 'redis')
            ->assertSet('flash', 'Queued install for redis.');

        $this->assertDatabaseHas('component_operations', [
            'component_id' => 'redis',
            'action' => 'install',
            'status' => 'completed',
        ]);
    }

    public function test_nodejs_install_prompts_for_major_version(): void
    {
        $this->actingAs($this->admin());

        \Livewire\Livewire::test(ComponentsPage::class)
            ->call('askInstall', 'nodejs')
            ->assertSet('pendingInstall', 'nodejs')
            ->set('nodeMajor', '20')
            ->call('confirmInstall')
            ->assertSet('flash', 'Queued install for nodejs.');

        $this->assertDatabaseHas('component_operations', [
            'component_id' => 'nodejs',
            'action' => 'install',
            'status' => 'completed',
        ]);

        $operation = \App\Models\ComponentOperation::query()
            ->where('component_id', 'nodejs')
            ->first();

        $this->assertNotNull($operation);
        $this->assertSame('20', $operation->options['node_major'] ?? null);
    }

    private function admin(): User
    {
        $totp = new TotpService();
        return User::factory()->create([
            'two_factor_secret' => Crypt::encryptString($totp->generateSecret()),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
