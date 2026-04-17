<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesApiFixtures;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    public function test_login_validates_required_payload(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createUser([
            'username' => 'admin_login',
            'password' => 'Password123',
            'role' => 'admin',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_login',
            'password' => 'WrongPassword123',
        ])->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_login_returns_token_and_updates_last_login_at(): void
    {
        $user = $this->createUser([
            'username' => 'admin_login',
            'password' => 'Password123',
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_login',
            'password' => 'Password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'id' => $user->id,
                    'username' => 'admin_login',
                    'role' => 'admin',
                ],
            ])
            ->assertJsonPath('data.token', fn (mixed $token) => is_string($token) && $token !== '')
            ->assertJsonPath('data.last_login_at', fn (mixed $value) => is_string($value) && $value !== '');

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ]);
    }

    public function test_me_returns_authenticated_profile(): void
    {
        $user = $this->createUser([
            'name' => 'Administrator',
            'username' => 'admin_me',
            'password' => 'Password123',
            'role' => 'admin',
        ]);
        $user->forceFill(['last_login_at' => now()])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Profil pengguna berhasil diambil',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Administrator',
                    'email' => null,
                    'role' => 'admin',
                ],
            ])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.username');
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ]);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = $this->createUser([
            'username' => 'logout_admin',
            'password' => 'Password123',
            'role' => 'admin',
        ]);
        $plainTextToken = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Logout sukses',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
