<div class="panel overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="font-mono text-[11px] uppercase tracking-wide text-zinc-500">
            <tr>
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Action</th>
                <th class="px-4 py-3">OK</th>
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">IP</th>
                <th class="px-4 py-3">Error</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse ($logs as $log)
                <tr>
                    <td class="px-4 py-2 font-mono text-xs text-zinc-400">{{ $log->created_at }}</td>
                    <td class="px-4 py-2 font-mono">{{ $log->action }}</td>
                    <td class="px-4 py-2 {{ $log->ok ? 'text-good' : 'text-bad' }}">{{ $log->ok ? 'yes' : 'no' }}</td>
                    <td class="px-4 py-2 text-xs">{{ $log->user?->email ?? '—' }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $log->ip }}</td>
                    <td class="px-4 py-2 text-xs text-zinc-500">{{ $log->error }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">Audit log is empty.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $logs->links() }}</div>
</div>
