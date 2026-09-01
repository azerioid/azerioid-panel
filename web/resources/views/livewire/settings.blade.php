<div class="space-y-6">
    @if ($flash)
        <div class="rounded-md border border-good/30 bg-good/10 px-4 py-3 text-sm text-good">{{ $flash }}</div>
    @endif

    <form wire:submit="saveProfile" class="panel grid gap-4 p-5 md:grid-cols-2">
        <h2 class="md:col-span-2 text-sm font-medium">Admin profile</h2>
        <label class="text-xs uppercase tracking-wide text-zinc-500">Name
            <input class="field mt-1" wire:model="name">
        </label>
        <label class="text-xs uppercase tracking-wide text-zinc-500">Email
            <input class="field mt-1" type="email" wire:model="email">
        </label>
        <div><button class="btn-primary" type="submit">Save profile</button></div>
    </form>

    <form wire:submit="savePassword" class="panel grid gap-4 p-5 md:grid-cols-2">
        <h2 class="md:col-span-2 text-sm font-medium">Change password</h2>
        <label class="text-xs uppercase tracking-wide text-zinc-500">Current
            <input class="field mt-1" type="password" wire:model="current_password">
        </label>
        <span></span>
        <label class="text-xs uppercase tracking-wide text-zinc-500">New
            <input class="field mt-1" type="password" wire:model="password">
        </label>
        <label class="text-xs uppercase tracking-wide text-zinc-500">Confirm
            <input class="field mt-1" type="password" wire:model="password_confirmation">
        </label>
        @error('current_password') <p class="text-sm text-bad">{{ $message }}</p> @enderror
        @error('password') <p class="text-sm text-bad md:col-span-2">{{ $message }}</p> @enderror
        <div><button class="btn-primary" type="submit">Update password</button></div>
    </form>

    <section class="panel p-5 space-y-2">
        <h2 class="text-sm font-medium">Two-factor (TOTP)</h2>
        @if ($totpRequired)
            <p class="text-sm text-zinc-400">Required for admin login (PANEL_REQUIRE_TOTP). Reinstall with <span class="font-mono">--require-totp=false</span> to allow password-only.</p>
            <p class="text-sm {{ $totpEnrolled ? 'text-good' : 'text-warn' }}">{{ $totpEnrolled ? 'This account is enrolled.' : 'This account is not enrolled yet.' }}</p>
        @else
            <p class="text-sm text-zinc-400">Disabled: admins log in with password only. Optional enrollment is available.</p>
            <p class="text-sm text-warn">If this panel is internet-facing, reinstall with <span class="font-mono">--require-totp=true</span>.</p>
            @unless ($totpEnrolled)
                <a class="btn-ghost inline-block text-xs" href="{{ route('two-factor.setup') }}">Enroll authenticator (optional)</a>
            @else
                <p class="text-sm text-good">This account is enrolled; login still asks for a code.</p>
            @endif
        @endif
    </section>

    <form wire:submit="savePanel" class="panel grid gap-4 p-5">
        <h2 class="text-sm font-medium">Session &amp; access</h2>
        <label class="text-xs uppercase tracking-wide text-zinc-500">Idle timeout (minutes)
            <input class="field mt-1 max-w-xs" type="number" wire:model="idle" min="5" max="120">
        </label>
        <label class="text-xs uppercase tracking-wide text-zinc-500">Audit retention (days)
            <input class="field mt-1 max-w-xs" type="number" wire:model="retention" min="7" max="365">
        </label>
        <label class="text-xs uppercase tracking-wide text-zinc-500">IP allowlist (one per line, empty = any)
            <textarea class="field mt-1 h-28" wire:model="ipAllowlist" placeholder="127.0.0.1"></textarea>
        </label>
        @error('ipAllowlist') <p class="text-sm text-bad">{{ $message }}</p> @enderror
        <div><button class="btn-primary" type="submit">Save</button></div>
    </form>

    @if ($panelRuntime !== [])
        <section class="panel p-5 space-y-3">
            <h2 class="text-sm font-medium">Panel runtime</h2>
            <p class="text-sm text-zinc-400">System component — pinned PHP for the panel. Non-removable while the panel runs.</p>
            <dl class="grid gap-2 font-mono text-xs text-zinc-400 md:grid-cols-2">
                <div><dt class="text-zinc-500">PHP version</dt><dd class="text-zinc-200">{{ $panelRuntime['php_version'] ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">FPM pool</dt><dd class="text-zinc-200">{{ $panelRuntime['fpm_pool'] ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">FPM socket</dt><dd class="text-zinc-200">{{ $panelRuntime['fpm_socket'] ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">FPM unit</dt><dd class="text-zinc-200">{{ $panelRuntime['fpm_unit'] ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Queue worker</dt>
                    <dd class="{{ ($panelRuntime['queue_worker']['running'] ?? false) ? 'text-good' : 'text-bad' }}">
                        {{ ($panelRuntime['queue_worker']['active_state'] ?? 'unknown') }}
                    </dd>
                </div>
                <div><dt class="text-zinc-500">Web user</dt><dd class="text-zinc-200">{{ $panelRuntime['web_user'] ?? '—' }}</dd></div>
            </dl>
        </section>
    @endif

    <section class="panel p-5 space-y-2">
        <h2 class="text-sm font-medium">Integrations</h2>
        <p class="text-sm text-zinc-400">Telegram bot token, Spaces keys, and the backup passphrase are entered on <a class="text-brass-400" href="{{ route('alerts') }}">Alerts</a> and <a class="text-brass-400" href="{{ route('backups') }}">Backups</a>. They are encrypted with <span class="font-mono">APP_KEY</span>, masked after save, and never written to the audit log in plaintext.</p>
    </section>

    @if ($phpIni !== [])
        <div class="panel p-5">
            <h2 class="mb-3 text-sm font-medium">PHP {{ $phpVersion }} · allowlisted php.ini keys</h2>
            @error('phpIni') <p class="mb-2 text-sm text-bad">{{ $message }}</p> @enderror
            <dl class="grid gap-3 md:grid-cols-2">
                @foreach ($phpIni as $key => $value)
                    <form wire:submit="saveIni('{{ $key }}', $event.target.querySelector('input').value)" class="flex items-end gap-2">
                        <label class="flex-1 text-xs uppercase tracking-wide text-zinc-500">{{ $key }}
                            <input class="field mt-1" name="value" value="{{ $value }}">
                        </label>
                        <button class="btn-ghost text-xs" type="submit">Set</button>
                    </form>
                @endforeach
            </dl>
            <div class="mt-4 border-t border-white/5 pt-4">
                <h3 class="text-sm font-medium">OPcache</h3>
                <p class="mt-1 font-mono text-xs text-zinc-500">{{ $opcache['error'] ?? ($opcache['available'] ?? false ? 'available' : 'not available') }}</p>
                @if (!empty($opcache['raw']))
                    <pre class="mt-2 max-h-40 overflow-auto font-mono text-[10px] text-zinc-400">{{ $opcache['raw'] }}</pre>
                @endif
                <button type="button" class="btn-ghost mt-3 text-xs" wire:click="resetOpcache" wire:confirm="Reset OPcache for PHP {{ $phpVersion }}?">Reset OPcache</button>
            </div>
        </div>
    @endif
</div>
