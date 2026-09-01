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
  /** @var list<array<string, mixed>> */
  public array $systemCards = [];

  public function mount(BrokerClient $broker): void
  {
    $runtime = $broker->call('panel.runtime');
    if ($runtime->ok) {
      $data = $runtime->data ?? [];
      $this->systemCards = [
        [
          'id' => 'caddy',
          'display_name' => 'Caddy',
          'category' => 'web',
          'system' => true,
          'managed' => true,
          'description' => 'Panel web server (single instance).',
          'status' => $data['fpm_status']['active_state'] ?? 'unknown',
        ],
        [
          'id' => 'php-8.4',
          'display_name' => 'PHP '.($data['php_version'] ?? '8.4').' (panel runtime)',
          'category' => 'runtime',
          'system' => true,
          'managed' => true,
          'description' => 'Pinned panel PHP-FPM pool — non-removable.',
          'status' => $data['fpm_status']['active_state'] ?? 'unknown',
          'socket' => $data['fpm_socket'] ?? '',
          'pool' => $data['fpm_pool'] ?? '',
        ],
      ];
    }
  }

  public function render()
  {
    return view('livewire.components')->layoutData([
      'heading' => 'Components',
      'sub' => 'System runtime (read-only stub — install in P3+)',
    ]);
  }
}
