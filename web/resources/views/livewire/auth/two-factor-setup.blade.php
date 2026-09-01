<div>
    <h2 class="mb-1 text-base font-medium text-zinc-100">
        @if (config('azerioid.require_totp'))
            Two-factor is required
        @else
            Enroll authenticator (optional)
        @endif
    </h2>
    <p class="mb-4 text-sm text-zinc-500">
        @if (config('azerioid.require_totp'))
            This panel can restart services and drop databases. TOTP is required.
        @else
            TOTP is optional on this panel. You can enroll now or skip.
        @endif
    </p>
    <div class="mb-4 flex justify-center rounded-md bg-white p-3">{!! $qr !!}</div>
    <p class="mb-4 break-all text-center font-mono text-xs text-zinc-400">{{ $secret }}</p>
    <form wire:submit="confirm" class="space-y-4">
        <input type="text" inputmode="numeric" wire:model="code" maxlength="6" class="field text-center tracking-[0.4em]" placeholder="000000">
        @error('code') <p class="text-sm text-bad">{{ $message }}</p> @enderror
        <button type="submit" class="btn-primary w-full">Enable 2FA</button>
    </form>
    @unless (config('azerioid.require_totp'))
        <button type="button" class="btn-ghost mt-3 w-full" wire:click="skip">Skip</button>
    @endunless
</div>
