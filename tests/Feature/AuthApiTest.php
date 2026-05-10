<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesApiFixtures;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    private function statefulHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000',
        ];
    }

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

    public function test_login_creates_session_and_updates_last_login_at(): void
    {
        $user = $this->createUser([
            'username' => 'admin_login',
            'password' => 'Password123',
            'role' => 'admin',
        ]);

        $response = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
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
            ->assertJsonMissingPath('data.token')
            ->assertJsonPath('data.last_login_at', fn (mixed $value) => is_string($value) && $value !== '');

        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'web');

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
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

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Profil pengguna berhasil diambil',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Administrator',
                    'username' => 'admin_me',
                    'role' => 'admin',
                ],
            ])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.email');
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

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = $this->createUser([
            'username' => 'logout_admin',
            'password' => 'Password123',
            'role' => 'admin',
        ]);

        $this->actingAs($user, 'web');

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Logout sukses',
            ]);

        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
