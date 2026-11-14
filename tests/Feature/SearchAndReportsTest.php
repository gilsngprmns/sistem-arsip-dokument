<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SearchAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_restricted_to_admins(): void
    {
        $staff = User::factory()->create(['role' => 'staf']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)->get('/reports/users')->assertForbidden();
        $this->actingAs($admin)->get('/reports/users')->assertOk();
    }

    public function test_user_can_upload_a_profile_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile', [
            '_method' => 'patch',
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertRedirect('/profile');
        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }
}