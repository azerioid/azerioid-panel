<div class="space-y-6">
    @if ($error)
        <div class="rounded-md border border-bad/40 bg-bad/10 px-4 py-3 text-sm text-bad">{{ $error }}</div>
    @endif
    @if ($flash)
        <div class="rounded-md border border-good/30 bg-good/10 px-4 py-3 text-sm text-good">{{ $flash }}</div>
    @endif

    <div class="flex justify-end">
        <button type="button" class="btn-primary" wire:click="$toggle('showForm')">{{ $showForm ? 'Close' : 'Add vhost' }}</button>
    </div>

    @if ($showForm)
        <form wire:submit="create" class="panel grid gap-4 p-5 md:grid-cols-2">
            <label class="text-xs uppercase tracking-wide text-zinc-500">Domain
                <input class="field mt-1" wire:model.blur="domain" placeholder="app.example.com" required>
            </label>
            <label class="text-xs uppercase tracking-wide text-zinc-500">Web root
                <input class="field mt-1" wire:model="root" placeholder="/data/www/app.example.com" required>
            </label>
            <label class="text-xs uppercase tracking-wide text-zinc-500">Type
                <select class="field mt-1" wire:model.live="type">
                    <option value="php">PHP-FPM</option>
                    <option value="static">Static</option>
                    <option value="proxy">Reverse proxy (127.0.0.1)</option>
                </select>
            </label>
            @if ($type === 'php')
                <label class="text-xs uppercase tracking-wide text-zinc-500">PHP version
                    <select class="field mt-1" wire:model="php_version">
                        @foreach ($phpVersions as $ver)
                            <option value="{{ $ver }}">{{ $ver }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($type === 'proxy')
                <label class="text-xs uppercase tracking-wide text-zinc-500">Upstream
                    <input class="field mt-1" wire:model="upstream" placeholder="127.0.0.1:9000">
                </label>
            @endif
            <div class="md:col-span-2">
                <button class="btn-primary" type="submit">Validate &amp; create</button>
            </div>
        </form>
    @endif

    <div class="panel overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="font-mono text-[11px] uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Domain</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Root / upstream</th>
                    <th class="px-4 py-3">PHP</th>
                    <th class="px-4 py-3">TLS</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($vhosts as $v)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $v['domain'] }}</td>
                        <td class="px-4 py-3">
                            {{ $v['type'] }}
                            @if (!empty($v['readonly']))
                                <span class="ml-1 rounded bg-ink-700 px-1.5 py-0.5 font-mono text-[10px] uppercase text-zinc-400">read-only</span>
                            @endif
                            @if (isset($v['enabled']) && $v['enabled'] === false)
                                <span class="ml-1 rounded bg-ink-700 px-1.5 py-0.5 font-mono text-[10px] uppercase text-zinc-500">disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-zinc-400">{{ $v['reverse_proxy'] ?? $v['root'] }}</td>
                        <td class="px-4 py-3 font-mono">{{ $v['php_version'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ !empty($v['tls']) ? 'yes' : 'http' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if (empty($v['readonly']))
                                <button type="button" class="text-xs text-bad" wire:click="delete('{{ $v['domain'] }}')" wire:confirm="Delete vhost {{ $v['domain'] }}? Files will be kept.">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No virtual hosts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
