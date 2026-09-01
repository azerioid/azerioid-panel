<div>
    <h2 class="mb-1 text-base font-medium text-zinc-100">Authenticator code</h2>
    <p class="mb-6 text-sm text-zinc-500">Enter the 6-digit code from your TOTP app.</p>
    <form wire:submit="verify" class="space-y-4">
        <input type="text" inputmode="numeric" wire:model="code" maxlength="6" class="field text-center tracking-[0.4em]" placeholder="000000" autofocus>
        @error('code') <p class="text-sm text-bad">{{ $message }}</p> @enderror
        <button type="submit" class="btn-primary w-full">Verify</button>
    </form>
</div>
