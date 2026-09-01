<div>
    @if ($step === 1)
        <h2 class="mb-1 text-base font-medium text-zinc-100">Create the admin account</h2>
        <p class="mb-6 text-sm text-zinc-500">
            This is the only login.
            @if (config('lacmp.require_totp'))
                2FA enrollment is required after this step.
            @else
                Password-only (TOTP is not required).
            @endif
        </p>
        <form wire:submit="createAccount" class="space-y-4">
            <label class="block text-xs uppercase tracking-wide text-zinc-500">Name
                <input type="text" wire:model="name" class="field mt-1" required>
            </label>
            <label class="block text-xs uppercase tracking-wide text-zinc-500">Email
                <input type="email" wire:model="email" class="field mt-1" required>
            </label>
            <label class="block text-xs uppercase tracking-wide text-zinc-500">Password
                <input type="password" wire:model="password" class="field mt-1" autocomplete="new-password" required>
            </label>
            <label class="block text-xs uppercase tracking-wide text-zinc-500">Confirm password
                <input type="password" wire:model="password_confirmation" class="field mt-1" required>
            </label>
            @error('name') <p class="text-sm text-bad">{{ $message }}</p> @enderror
            @error('email') <p class="text-sm text-bad">{{ $message }}</p> @enderror
            @error('password') <p class="text-sm text-bad">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primary w-full">Create account</button>
        </form>
    @else
        <h2 class="mb-1 text-base font-medium text-zinc-100">Enroll authenticator</h2>
        <p class="mb-4 text-sm text-zinc-500">Scan with any TOTP app, then enter a 6-digit code.</p>
        <div class="mb-4 flex justify-center rounded-md bg-white p-3">{!! $qr !!}</div>
        <p class="mb-4 break-all text-center font-mono text-xs text-zinc-400">{{ $secret }}</p>
        <form wire:submit="confirmTwoFactor" class="space-y-4">
            <input type="text" inputmode="numeric" wire:model="code" maxlength="6" class="field text-center tracking-[0.4em]" placeholder="000000">
            @error('code') <p class="text-sm text-bad">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primary w-full">Confirm 2FA</button>
        </form>
    @endif
</div>
