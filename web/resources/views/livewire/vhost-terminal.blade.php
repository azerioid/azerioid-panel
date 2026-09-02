<div class="space-y-4">
    @if ($error)
        <div class="rounded-md border border-bad/40 bg-bad/10 px-4 py-3 text-sm text-bad">{{ $error }}</div>
        <a href="/vhosts" class="btn-ghost inline-block">Back to vhosts</a>
    @elseif ($sessionId && $wsPath)
        <div class="panel p-4 space-y-3" id="vhost-terminal-panel"
             data-session-id="{{ $sessionId }}"
             data-ws-path="{{ $wsPath }}"
             data-idle-seconds="{{ $idleSeconds }}">
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-zinc-400">
                <div>
                    <span class="font-mono text-zinc-200">{{ $domain }}</span>
                    · user <span class="font-mono text-brass-400">{{ $username }}</span>
                    · cwd <span class="font-mono text-zinc-300">{{ $root }}</span>
                </div>
                <div class="flex gap-2 items-center">
                    <span class="text-xs">Ends after {{ (int) max(1, (int) ($idleSeconds / 60)) }} min idle</span>
                    <button type="button" class="btn-ghost text-xs" wire:click="stop">Close session</button>
                </div>
            </div>
            <div id="terminal-host" class="h-[min(70vh,560px)] w-full overflow-hidden rounded border border-white/10 bg-black"></div>
        </div>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/css/xterm.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/lib/xterm.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10.0/lib/addon-fit.min.js"></script>
        <script>
        (function () {
            const panel = document.getElementById('vhost-terminal-panel');
            if (!panel || !window.Terminal) return;
            const sessionId = panel.dataset.sessionId;
            const wsPath = panel.dataset.wsPath;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const term = new Terminal({
                cursorBlink: true,
                fontFamily: 'ui-monospace, Menlo, Monaco, Consolas, monospace',
                fontSize: 13,
                theme: { background: '#0a0a0a' },
            });
            const fit = window.FitAddon ? new window.FitAddon.FitAddon() : null;
            if (fit) term.loadAddon(fit);
            term.open(document.getElementById('terminal-host'));
            fit?.fit();
            window.addEventListener('resize', () => fit?.fit());
            const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
            const socket = new WebSocket(`${proto}//${location.host}${wsPath}/ws`);
            socket.onmessage = (e) => term.write(e.data);
            term.onData((d) => { if (socket.readyState === WebSocket.OPEN) socket.send(d); });
            socket.onclose = () => term.writeln('\r\n\r\n[session closed]');
            const heartbeat = setInterval(() => {
                fetch(`/terminal/heartbeat/${sessionId}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                }).catch(() => {});
            }, 60000);
            const stop = () => {
                clearInterval(heartbeat);
                const body = new URLSearchParams({ _token: csrf });
                navigator.sendBeacon(`/terminal/stop/${sessionId}`, body);
            };
            window.addEventListener('beforeunload', stop);
            window.addEventListener('pagehide', stop);
        })();
        </script>
    @endif
</div>
