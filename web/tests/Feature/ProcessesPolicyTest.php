<?php

namespace Tests\Feature;

use App\Livewire\ProcessesPage;
use App\Models\User;
use App\Services\Broker\FakeBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcessesPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function test_processes_page_lists_programs_when_supervisor_installed(): void
    {
        $this->actingAs($this->admin());
        $fake = $this->app->make(FakeBroker::class);
        $fake->fakeInstalledComponents['supervisor'] = true;
        $fake->supervisorPrograms['demo'] = [
            'command' => 'node app.js',
            'directory' => '/data/www/demo.example.com',
            'user' => 'azerioid-supervised',
            'autostart' => true,
            'autorestart' => true,
            'vhost_domain' => null,
            'state' => 'running',
        ];

        Livewire::test(ProcessesPage::class)
            ->assertSee('demo')
            ->assertSee('New freeform process');
    }

    public function test_fake_broker_rejects_root_user_on_create(): void
    {
        $fake = $this->app->make(FakeBroker::class);
        $fake->fakeInstalledComponents['supervisor'] = true;
        $res = $fake->handle('supervisor.program.create', [], [
            'name' => 'evil',
            'command' => '/bin/true',
            'directory' => '/var/lib/azerioid-supervised/apps',
            'user' => 'root',
        ]);
        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Refusing privileged', (string) $res->error);
    }
}
