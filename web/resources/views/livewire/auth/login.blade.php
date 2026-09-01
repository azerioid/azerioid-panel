<div>
    @if (session('status'))
        <p class="mb-4 text-sm text-brass-400">{{ session('status') }}</p>
    @endif
    <h2 class="mb-1 text-base font-medium text-zinc-100">Sign in</h2>
    <p class="mb-6 text-sm text-zinc-500">
        @if (config('azerioid.require_totp'))
            Password plus TOTP. No shared logins.
        @else
            Password only. No shared logins.
        @endif
    </p>
    <form wire:submit="authenticate" class="space-y-4">
        <label class="block text-xs uppercase tracking-wide text-zinc-500">Email
            <input type="email" wire:model="email" class="field mt-1" autocomplete="username" required>
        </label>
        <label class="block text-xs uppercase tracking-wide text-zinc-500">Password
            <input type="password" wire:model="password" class="field mt-1" autocomplete="current-password" required>
        </label>
        @error('email') <p class="text-sm text-bad">{{ $message }}</p> @enderror
        @error('password') <p class="text-sm text-bad">{{ $message }}</p> @enderror
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Continue</button>
    </form>
</div>
