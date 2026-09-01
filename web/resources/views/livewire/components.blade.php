<div class="space-y-6">
    <p class="text-sm text-zinc-400">
        Read-only view of system components. Managed component install/remove arrives in Phase 3.
    </p>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($systemCards as $card)
            <article class="panel p-5 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-medium text-zinc-100">{{ $card['display_name'] }}</h2>
                    <span class="rounded bg-brass-400/10 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-brass-400">system</span>
                </div>
                <p class="text-xs text-zinc-500">{{ $card['description'] }}</p>
                <dl class="grid gap-1 font-mono text-[11px] text-zinc-400">
                    <div><dt class="inline text-zinc-500">id:</dt> {{ $card['id'] }}</div>
                    <div><dt class="inline text-zinc-500">category:</dt> {{ $card['category'] }}</div>
                    <div><dt class="inline text-zinc-500">status:</dt> {{ $card['status'] }}</div>
                    @if (!empty($card['socket']))
                        <div><dt class="inline text-zinc-500">socket:</dt> {{ $card['socket'] }}</div>
                    @endif
                    @if (!empty($card['pool']))
                        <div><dt class="inline text-zinc-500">pool:</dt> {{ $card['pool'] }}</div>
                    @endif
                </dl>
            </article>
        @empty
            <p class="text-sm text-zinc-500">No system components reported by broker.</p>
        @endforelse
    </div>
</div>
