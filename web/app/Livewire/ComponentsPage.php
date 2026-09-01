<?php

namespace App\Livewire;

use App\Services\Broker\BrokerClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Components · Stack Manager')]
class ComponentsPage extends Component
{
    public array $systemComponents = [];

    public function mount(BrokerClient $broker): void
    {
        $runtime = $broker->call('panel.runtime');
        if ($runtime->ok) {
            $data = $runtime->data;
            $this->systemComponents = [
                [
                    'id' => 'caddy',
                    'display_name' => 'Caddy',
                    'category' => 'web',
                    'system' => true,
                    'status' => 'active',
                    'description' => 'Panel web server (system component)',
                ],
                [
                    'id' => 'php-8.4',
                    'display_name' => 'PHP '.($data['php_version'] ?? '8.4').' (panel runtime)',
                    'category' => 'php',
                    'system' => true,
                    'status' => ($data['queue_active'] ?? false) ? 'active' : 'unknown',
                    'description' => 'Pinned panel FPM pool at '.($data['fpm_socket'] ?? '/run/php/lacmp-panel.sock'),
                    'fpm_socket' => $data['fpm_socket'] ?? null,
                    'fpm_pool' => $data['fpm_pool'] ?? null,
                ],
            ];
        }
    }

    public function render()
    {
        return view('livewire.components')->layoutData([
            'heading' => 'Components',
            'sub' => 'System runtime (read-only) · managed installs in P3',
        ]);
    }
}
