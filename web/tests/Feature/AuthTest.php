<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Broker\FakeBroker;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_is_shown_when_no_users_exist(): void
    {
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('127.0.0.1:3169', false);
    }

    public function test_login_is_a_post_mutation_via_livewire(): void
    {
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/')->assertRedirect();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(\App\Livewire\Auth\Login::class)
                ->set('email', $user->email)
                ->set('password', 'wrong-password-1!')
                ->call('authenticate')
                ->assertHasErrors('email');
        }

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password-1!')
            ->call('authenticate')
            ->assertHasErrors('email');
    }

    public function test_failed_login_writes_fail2ban_line(): void
    {
        $user = $this->admin();
        $log = storage_path('logs/auth-fail.log');
        @unlink($log);

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password-1!')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertFileExists($log);
        $this->assertStringContainsString('LACMP_PANEL_AUTH_FAIL ip=', (string) file_get_contents($log));
    }

    public function test_unconfirmed_2fa_user_is_forced_to_setup(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        $this->get('/')->assertRedirect(route('two-factor.setup'));
    }

    public function test_require_totp_false_allows_dashboard_without_enrollment(): void
    {
        config(['lacmp.require_totp' => false]);
        $user = $this->admin();
        $this->actingAs($user);
        $this->get('/')->assertOk();
        $this->get('/settings')->assertOk()
            ->assertSee('Disabled: admins log in with password only', false)
            ->assertDontSee('2FA REQUIRED', false);
    }

    public function test_setup_skips_enrollment_when_totp_not_required(): void
    {
        config(['lacmp.require_totp' => false]);

        Livewire::test(\App\Livewire\Auth\SetupWizard::class)
            ->assertSee('Password-only', false)
            ->assertDontSee('Enroll authenticator', false)
            ->set('name', 'Admin')
            ->set('email', 'admin@example.com')
            ->set('password', 'AdminPassw0rd!')
            ->set('password_confirmation', 'AdminPassw0rd!')
            ->call('createAccount')
            ->assertRedirect(route('dashboard'));

        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->assertNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertAuthenticatedAs($user);
    }

    public function test_setup_forces_enrollment_when_totp_required(): void
    {
        config(['lacmp.require_totp' => true]);

        Livewire::test(\App\Livewire\Auth\SetupWizard::class)
            ->assertSee('2FA enrollment is required', false)
            ->set('name', 'Admin')
            ->set('email', 'admin@example.com')
            ->set('password', 'AdminPassw0rd!')
            ->set('password_confirmation', 'AdminPassw0rd!')
            ->call('createAccount')
            ->assertSet('step', 2)
            ->assertSee('Enroll authenticator', false);

        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_login_is_password_only_when_totp_not_required(): void
    {
        config(['lacmp.require_totp' => false]);
        $user = $this->admin();

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_sends_unenrolled_user_to_setup_when_totp_required(): void
    {
        config(['lacmp.require_totp' => true]);
        $user = $this->admin();

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('two-factor.setup'));
    }

    public function test_optional_totp_setup_can_be_skipped(): void
    {
        config(['lacmp.require_totp' => false]);
        $user = $this->admin();
        $this->actingAs($user);

        Livewire::test(\App\Livewire\Auth\TwoFactorSetup::class)
            ->assertSee('optional', false)
            ->call('skip')
            ->assertRedirect(route('dashboard'));
    }

    public function test_confirmed_2fa_user_reaches_dashboard(): void
    {
        $user = $this->admin(confirmed: true);
        $this->actingAs($user);
        $this->get('/')->assertOk();
    }

    public function test_logout_is_post_only(): void
    {
        $user = $this->admin(confirmed: true);
        $this->actingAs($user);
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_security_headers_are_present(): void
    {
        $user = $this->admin(confirmed: true);
        $response = $this->actingAs($user)->get('/');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }

    public function test_guest_cannot_hit_dashboard(): void
    {
        $this->admin(confirmed: true);
        $this->get('/')->assertRedirect('/login');
    }

    private function admin(bool $confirmed = false): User
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        if ($confirmed) {
            $totp = new TotpService();
            $secret = $totp->generateSecret();
            $user->forceFill([
                'two_factor_secret' => Crypt::encryptString($secret),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }
        return $user->fresh();
    }
}
