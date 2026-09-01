<?php

namespace App\Services\Alerts;

use App\Models\AlertIncident;
use App\Models\BackupJob;
use App\Models\Setting;
use App\Services\Broker\BrokerClient;
use Illuminate\Support\Carbon;

final class AlertEvaluator
{
    public function __construct(
        private readonly BrokerClient $broker,
        private readonly TelegramNotifier $telegram,
    ) {
    }

    /**
     * @return array{opened:int, resolved:int, notified:int}
     */
    public function run(): array
    {
        $problems = $this->collect();
        $openKeys = [];
        $opened = $resolved = $notified = 0;

        foreach ($problems as $p) {
            $key = $p['rule_key'].'|'.$p['subject'];
            $openKeys[$key] = true;
            $incident = AlertIncident::query()
                ->where('rule_key', $p['rule_key'])
                ->where('subject', $p['subject'])
                ->where('status', 'open')
                ->first();
            if ($incident) {
                continue;
            }
            $incident = AlertIncident::query()->create([
                'rule_key' => $p['rule_key'],
                'subject' => $p['subject'],
                'status' => 'open',
                'severity' => $p['severity'],
                'message' => $p['message'],
                'opened_at' => now(),
                'last_notified_at' => now(),
            ]);
            $opened++;
            if ($this->telegram->send('ALERT '.$p['subject']."\n".$p['message'])) {
                $notified++;
                $incident->forceFill(['last_notified_at' => now()])->save();
            }
        }

        $open = AlertIncident::query()->where('status', 'open')->get();
        foreach ($open as $incident) {
            $key = $incident->rule_key.'|'.$incident->subject;
            if (isset($openKeys[$key])) {
                continue;
            }
            $incident->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
            ])->save();
            $resolved++;
            if ($this->telegram->send('RESOLVED '.$incident->subject."\n".$incident->message)) {
                $notified++;
            }
        }

        return ['opened' => $opened, 'resolved' => $resolved, 'notified' => $notified];
    }

    /** @return list<array{rule_key:string,subject:string,message:string,severity:string}> */
    public function collect(): array
    {
        $rules = Setting::get('alert.rules', []);
        if (! is_array($rules)) {
            $rules = [];
        }
        $disk = (int) ($rules['disk_percent'] ?? 85);
        $ram = (int) ($rules['ram_percent'] ?? 90);
        $load = (float) ($rules['load'] ?? 4.0);
        $tlsDays = (int) ($rules['tls_days'] ?? 14);
        $backupHours = (int) ($rules['backup_stale_hours'] ?? 36);
        $serviceDown = (bool) ($rules['service_down'] ?? true);
        $observedDown = (bool) ($rules['observed_down'] ?? true);
        $reboot = (bool) ($rules['reboot_required'] ?? true);
        $tlsOn = (bool) ($rules['tls'] ?? true);
        $backupOn = (bool) ($rules['backup_stale'] ?? true);

        $out = [];
        $status = $this->broker->call('status.all', [], [], null, false);
        if ($status->ok) {
            foreach (array_merge($status->data['controlled'] ?? [], $status->data['observed'] ?? []) as $svc) {
                $running = (bool) ($svc['running'] ?? false);
                $controllable = (bool) ($svc['controllable'] ?? false);
                if ($running) {
                    continue;
                }
                if ($controllable && ! $serviceDown) {
                    continue;
                }
                if (! $controllable && ! $observedDown) {
                    continue;
                }
                $unit = (string) ($svc['unit'] ?? 'unknown');
                $out[] = [
                    'rule_key' => 'service.down',
                    'subject' => $unit,
                    'message' => $unit.' is '.($svc['active_state'] ?? 'down'),
                    'severity' => $controllable ? 'high' : 'medium',
                ];
            }
        }

        $metrics = $this->broker->call('metrics.system', [], [], null, false);
        if ($metrics->ok) {
            $m = $metrics->data;
            $total = (int) ($m['memory']['total'] ?? 0);
            $used = (int) ($m['memory']['used'] ?? 0);
            if ($total > 0 && ($used / $total) * 100 >= $ram) {
                $pct = (int) round(($used / $total) * 100);
                $out[] = [
                    'rule_key' => 'resource.ram',
                    'subject' => 'memory',
                    'message' => "RAM usage {$pct}% (threshold {$ram}%)",
                    'severity' => 'high',
                ];
            }
            $load1 = (float) ($m['loadavg']['1'] ?? 0);
            if ($load1 >= $load) {
                $out[] = [
                    'rule_key' => 'resource.load',
                    'subject' => 'load',
                    'message' => "Load average {$load1} (threshold {$load})",
                    'severity' => 'medium',
                ];
            }
            foreach ($m['disks'] ?? [] as $d) {
                $pct = (int) rtrim((string) ($d['use_percent'] ?? '0'), '%');
                if ($pct >= $disk) {
                    $mount = (string) ($d['mount'] ?? '/');
                    $out[] = [
                        'rule_key' => 'resource.disk',
                        'subject' => $mount,
                        'message' => "Disk {$mount} at {$pct}% (threshold {$disk}%)",
                        'severity' => 'high',
                    ];
                }
            }
        }

        if ($reboot) {
            $rr = $this->broker->call('system.reboot-required', [], [], null, false);
            if ($rr->ok && ($rr->data['required'] ?? false)) {
                $pkgs = implode(', ', $rr->data['packages'] ?? []);
                $out[] = [
                    'rule_key' => 'system.reboot',
                    'subject' => 'reboot-required',
                    'message' => 'System restart required'.($pkgs !== '' ? ": {$pkgs}" : ''),
                    'severity' => 'medium',
                ];
            }
        }

        if ($tlsOn) {
            $tls = $this->broker->call('tls.certs', [], [], null, false);
            if ($tls->ok) {
                foreach ($tls->data['certs'] ?? [] as $c) {
                    $days = $c['days_remaining'] ?? null;
                    if ($days === null) {
                        continue;
                    }
                    if ((int) $days <= $tlsDays) {
                        $out[] = [
                            'rule_key' => 'tls.expiry',
                            'subject' => (string) $c['domain'],
                            'message' => ($c['domain'] ?? 'cert').' expires in '.(int) $days.' days',
                            'severity' => (int) $days < 0 ? 'high' : 'medium',
                        ];
                    }
                }
            }
        }

        if ($backupOn) {
            $last = BackupJob::query()->where('status', 'ok')->latest()->first();
            $stale = $last === null || $last->created_at->lt(Carbon::now()->subHours($backupHours));
            $failed = BackupJob::query()->where('status', 'failed')->where('created_at', '>=', now()->subHours($backupHours))->exists();
            if ($failed) {
                $out[] = [
                    'rule_key' => 'backup.failed',
                    'subject' => 'backup',
                    'message' => 'A backup job failed recently.',
                    'severity' => 'high',
                ];
            } elseif ($stale && BackupJob::query()->exists()) {
                $out[] = [
                    'rule_key' => 'backup.stale',
                    'subject' => 'backup',
                    'message' => "No successful backup in {$backupHours} hours.",
                    'severity' => 'medium',
                ];
            }
        }

        $auth = $this->broker->call('auth.audit', ['200'], [], null, false);
        if ($auth->ok && ($rules['ssh'] ?? true)) {
            $failedCount = (int) ($auth->data['failed_count'] ?? 0);
            if ($failedCount >= 12) {
                $out[] = [
                    'rule_key' => 'ssh.failures',
                    'subject' => 'sshd',
                    'message' => "{$failedCount} failed SSH logins in the recent auth log window.",
                    'severity' => 'medium',
                ];
            }
            foreach ($auth->data['new_root_ips'] ?? [] as $row) {
                $ip = (string) ($row['ip'] ?? '');
                if ($ip === '') {
                    continue;
                }
                $out[] = [
                    'rule_key' => 'ssh.newroot',
                    'subject' => $ip,
                    'message' => "Root SSH login from new source IP {$ip}",
                    'severity' => 'high',
                ];
            }
        }

        return $out;
    }
}
