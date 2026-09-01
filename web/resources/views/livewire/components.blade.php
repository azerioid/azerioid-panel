<div class="space-y-6">
    <section class="panel p-5">
        <h2 class="text-sm font-medium">System components</h2>
        <p class="mt-1 text-sm text-zinc-400">
            Panel runtime components cannot be removed. User-installed components will appear here in Phase 2+.
        </p>
    </section>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($systemComponents as $component)
            <article class="panel p-5 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-medium text-zinc-100">{{ $component['display_name'] }}</h3>
                    <span class="rounded bg-brass-400/10 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-brass-400">system</span>
                </div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ $component['category'] }}</p>
                <p class="text-sm text-zinc-400">{{ $component['description'] }}</p>
                @if (!empty($component['fpm_socket']))
                    <dl class="mt-3 space-y-1 font-mono text-xs text-zinc-500">
                        <div><dt class="inline text-zinc-600">pool:</dt> {{ $component['fpm_pool'] ?? '—' }}</div>
                        <div><dt class="inline text-zinc-600">socket:</dt> {{ $component['fpm_socket'] }}</div>
                    </dl>
                @endif
            </article>
        @empty
            <p class="text-sm text-zinc-400">No system components reported by the broker.</p>
        @endforelse
    </div>
</div>
