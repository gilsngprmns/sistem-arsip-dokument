<?php

namespace Tests\Feature;

use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        // 4x gagal: counter naik, akun tetap aktif
        for ($i = 0; $i < 4; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
            $this->assertGuest();
            $response->assertSessionHasErrors('email');
        }

        $user->refresh();
        $this->assertEquals(4, $user->login_attempts, 'Counter harus 4 setelah 4x gagal');
        $this->assertTrue($user->is_active);

        // Percobaan ke-5 mengunci akun permanen
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertEquals(5, $user->login_attempts, 'Counter harus 5 setelah 5x gagal');
        $this->assertFalse($user->is_active, 'Akun harus nonaktif setelah 5x gagal');
    }

    public function test_inactive_user_cannot_login_with_correct_password(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_successful_login_resets_login_attempts(): void
    {
        $user = User::factory()->create();

        // 4x gagal dulu
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertEquals(4, $user->fresh()->login_attempts);

        // Login benar
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticated();

        $this->assertEquals(0, $user->fresh()->login_attempts);
    }

    public function test_reactivation_resets_login_attempts(): void
    {
        $seksi   = Seksi::factory()->create();
        $admin   = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $locked  = User::factory()->create([
            'role'           => 'staf',
            'seksi_id'       => $seksi->id,
            'is_active'      => false,
            'login_attempts' => 5,
        ]);

        $response = $this->actingAs($admin)->put(route('users.update', $locked), [
            'name'      => $locked->name,
            'email'     => $locked->email,
            'role'      => 'staf',
            'seksi_id'  => $seksi->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('recovery');
        $this->assertSame($locked->email, session('recovery_email'));

        $locked->refresh();
        $this->assertTrue($locked->is_active);
        $this->assertEquals(0, $locked->login_attempts, 'login_attempts harus 0 setelah recovery');
    }

    public function test_reactivation_triggers_recovery_flash(): void
    {
        $seksi   = Seksi::factory()->create();
        $admin   = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $locked  = User::factory()->create([
            'role'           => 'staf',
            'seksi_id'       => $seksi->id,
            'is_active'      => false,
            'login_attempts' => 5,
        ]);

        $response = $this->actingAs($admin)->put(route('users.update', $locked), [
            'name'      => $locked->name,
            'email'     => $locked->email,
            'role'      => 'staf',
            'seksi_id'  => $seksi->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('recovery');
        $response->assertSessionHas('recovery_email', $locked->email);
    }

    public function test_lockout_error_message_is_specific_not_generic(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $error = session('errors')->first('email');
        $this->assertStringContainsString('dinonaktifkan', $error);
        $this->assertStringContainsString('Admin Tata Usaha', $error);
    }
}
