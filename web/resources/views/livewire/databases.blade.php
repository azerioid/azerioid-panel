<div class="space-y-6">
    @if ($error)
        <div class="rounded-md border border-bad/40 bg-bad/10 px-4 py-3 text-sm text-bad">{{ $error }}</div>
    @endif
    @if ($flash)
        <div class="rounded-md border border-good/30 bg-good/10 px-4 py-3 text-sm text-good">{{ $flash }}</div>
    @endif

    @if ($configuredEngines === [])
        <div class="panel border border-warn/30 p-5 text-sm text-zinc-300">
            No database engine is configured yet. Install <span class="font-mono">mariadb</span> or <span class="font-mono">postgresql</span> from the Components page.
        </div>
    @elseif (count($configuredEngines) > 1)
        <div class="panel p-5">
            <label class="text-xs uppercase tracking-wide text-zinc-500">Engine
                <select class="field mt-1" wire:model.live="selectedEngine">
                    @foreach ($configuredEngines as $engine)
                        <option value="{{ $engine['id'] }}">{{ $engine['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    @endif

    @if ($revealedPassword)
        <div class="panel border border-brass-500/40 p-5">
            <div class="text-xs uppercase tracking-wide text-warn">One-time password reveal</div>
            <div class="mt-2 flex items-center gap-3">
                <code class="font-mono text-sm text-zinc-100" x-ref="pw">{{ $revealedPassword }}</code>
                <button type="button" class="btn-ghost text-xs" @click="navigator.clipboard.writeText($refs.pw.innerText)">Copy</button>
            </div>
            <p class="mt-2 text-xs text-zinc-500">This value is not stored by the panel and is not written to logs.</p>
        </div>
    @endif

    @if ($selectedEngine !== '')
        <div class="flex justify-end">
            <button type="button" class="btn-primary" wire:click="$toggle('showForm')">{{ $showForm ? 'Close' : 'Add database' }}</button>
        </div>

        @if ($showForm)
            <form wire:submit="create" class="panel grid gap-4 p-5 md:grid-cols-2">
                <label class="text-xs uppercase tracking-wide text-zinc-500">Database name
                    <input class="field mt-1" wire:model="name" maxlength="32" required>
                </label>
                <label class="text-xs uppercase tracking-wide text-zinc-500">User (default: same as db)
                    <input class="field mt-1" wire:model="user" maxlength="32">
                </label>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="btn-primary">Create</button>
                    <p class="self-center text-xs text-zinc-500">A one-time password is generated only after {{ $engineLabel }} confirms the create.</p>
                </div>
            </form>
        @endif

        <div class="panel overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="font-mono text-[11px] uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Size</th>
                        <th class="px-4 py-3">Tables</th>
                        <th class="px-4 py-3">Users</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($databases as $db)
                        <tr>
                            <td class="px-4 py-3 font-mono">
                                {{ $db['name'] }}
                                @if (!empty($db['protected']))
                                    <span class="ml-1 rounded bg-ink-700 px-1.5 py-0.5 font-mono text-[10px] uppercase text-zinc-400">protected</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $format::bytes((int) $db['size_bytes']) }}</td>
                            <td class="px-4 py-3 font-mono">{{ $db['table_count'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-zinc-400">
                                @foreach ($db['users'] as $u)
                                    {{ $u['user'].'@'.$u['host'] }}@if (!$loop->last), @endif
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                @if (empty($db['protected']))
                                    @php $firstUser = $db['users'][0]['user'] ?? $db['name']; @endphp
                                    <button type="button" class="text-xs text-brass-400" wire:click="startReset('{{ $firstUser }}')" wire:confirm="Reset password for {{ $firstUser }}?">Reset pw</button>
                                    <button type="button" class="text-xs text-bad" wire:click="$set('confirmDelete', '{{ $db['name'] }}')">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No databases visible.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($confirmDelete)
            <form wire:submit="delete" class="panel border border-bad/40 p-5">
                <p class="text-sm">Type <span class="font-mono text-bad">{{ $confirmDelete }}</span> to drop the database and its user.</p>
                <input class="field mt-3" wire:model="confirmTyped" autocomplete="off">
                <div class="mt-3 flex gap-2">
                    <button class="btn-danger" type="submit">Drop database</button>
                    <button class="btn-ghost" type="button" wire:click="$set('confirmDelete', null)">Cancel</button>
                </div>
            </form>
        @endif

        @if ($resetUser)
            <div class="panel p-5">
                <p class="text-sm">Reset password for <span class="font-mono">{{ $resetUser }}</span>? A new password is shown only if {{ $engineLabel }} accepts the change.</p>
                <button type="button" class="btn-primary mt-3" wire:click="confirmReset" wire:confirm="Apply a new password now?">Apply reset</button>
                <button type="button" class="btn-ghost mt-3" wire:click="$set('resetUser', null)">Cancel</button>
            </div>
        @endif
    @endif
</div>
