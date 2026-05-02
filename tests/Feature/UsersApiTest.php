<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'subject' => 'Kearsipan',
            'position' => 'Guru',
            'username' => 'testuser_'.fake()->unique()->numerify('###'),
            'password' => 'Password123',
            'role' => 'guru',
        ], $attributes));
    }

    public function test_show_user_requires_authentication(): void
    {
        $user = $this->createUser();

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ]);
    }

    public function test_show_user_returns_data_for_authenticated_user(): void
    {
        $authenticatedUser = $this->createUser();
        $targetUser = $this->createUser([
            'name' => 'Guru Target',
            'username' => 'guru_target',
        ]);

        Sanctum::actingAs($authenticatedUser);

        $this->getJson("/api/v1/users/{$targetUser->id}")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => "sukses menampilkan data user dengan id {$targetUser->id}",
                'data' => [
                    'id' => $targetUser->id,
                    'name' => 'Guru Target',
                    'username' => 'guru_target',
                    'role' => 'guru',
                ],
            ])
            ->assertJsonMissingPath('data.password');
    }

    public function test_show_user_returns_404_for_missing_resource(): void
    {
        Sanctum::actingAs($this->createUser());

        $this->getJson('/api/v1/users/999999')
            ->assertNotFound()
            ->assertJson([
                'status' => 'error',
                'message' => 'Resource tidak ditemukan',
            ]);
    }

    public function test_index_requires_admin_role(): void
    {
        Sanctum::actingAs($this->createUser(['role' => 'guru']));

        $this->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_users_for_admin(): void
    {
        $admin = $this->createUser([
            'role' => 'admin',
            'username' => 'admin_index',
        ]);
        $this->createUser(['username' => 'guru_index']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menampilkan semua data user',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_store_requires_admin_role(): void
    {
        Sanctum::actingAs($this->createUser(['role' => 'guru']));

        $this->postJson('/api/v1/users', [
            'name' => 'User Baru',
            'username' => 'user_baru',
            'password' => 'Password123',
            'role' => 'guru',
        ])->assertForbidden();
    }

    public function test_store_validates_required_payload(): void
    {
        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->postJson('/api/v1/users', [])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['name', 'username', 'password', 'role']);
    }

    public function test_store_creates_user_for_admin(): void
    {
        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Admin Baru',
            'username' => 'admin_baru',
            'password' => 'Password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses membuat user',
                'data' => [
                    'name' => 'Admin Baru',
                    'username' => 'admin_baru',
                    'role' => 'admin',
                ],
            ])
            ->assertJsonMissingPath('data.password');

        $createdUser = User::where('username', 'admin_baru')->firstOrFail();
        $this->assertSame('Admin Baru', $createdUser->name);
        $this->assertSame('admin', $createdUser->role);
        $this->assertTrue(Hash::check('Password123', $createdUser->password));
    }

    public function test_update_requires_admin_role(): void
    {
        $targetUser = $this->createUser();
        Sanctum::actingAs($this->createUser(['role' => 'guru']));

        $this->putJson("/api/v1/users/{$targetUser->id}", [
            'name' => 'Edit User',
            'username' => 'edit_user',
            'role' => 'guru',
        ])->assertForbidden();
    }

    public function test_update_validates_required_payload(): void
    {
        $targetUser = $this->createUser();
        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->putJson("/api/v1/users/{$targetUser->id}", [])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal',
            ])
            ->assertJsonValidationErrors(['name', 'username', 'role']);
    }

    public function test_update_user_without_password_keeps_existing_password(): void
    {
        $targetUser = $this->createUser([
            'name' => 'Nama Lama',
            'username' => 'user_lama',
            'password' => 'Password123',
        ]);
        $oldPasswordHash = $targetUser->password;

        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->putJson("/api/v1/users/{$targetUser->id}", [
            'name' => 'Nama Baru',
            'username' => 'user_baru_tetap',
            'password' => null,
            'role' => 'admin',
        ])->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses mengupdate user',
                'data' => [
                    'name' => 'Nama Baru',
                    'username' => 'user_baru_tetap',
                    'role' => 'admin',
                ],
            ]);

        $targetUser->refresh();
        $this->assertSame($oldPasswordHash, $targetUser->password);
        $this->assertSame('Nama Baru', $targetUser->name);
        $this->assertSame('user_baru_tetap', $targetUser->username);
        $this->assertSame('admin', $targetUser->role);
    }

    public function test_update_user_with_password_rehashes_password(): void
    {
        $targetUser = $this->createUser([
            'username' => 'user_password_lama',
            'password' => 'Password123',
        ]);

        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->putJson("/api/v1/users/{$targetUser->id}", [
            'name' => 'User Password Baru',
            'username' => 'user_password_baru',
            'password' => 'NewPass123',
            'role' => 'guru',
        ])->assertOk();

        $targetUser->refresh();
        $this->assertTrue(Hash::check('NewPass123', $targetUser->password));
        $this->assertSame('user_password_baru', $targetUser->username);
    }

    public function test_update_missing_user_returns_404(): void
    {
        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->putJson('/api/v1/users/999999', [
            'name' => 'Missing User',
            'username' => 'missing_user',
            'role' => 'guru',
        ])->assertNotFound()
            ->assertJson([
                'status' => 'error',
                'message' => 'Resource tidak ditemukan',
            ]);
    }

    public function test_destroy_requires_admin_role(): void
    {
        $targetUser = $this->createUser();
        Sanctum::actingAs($this->createUser(['role' => 'guru']));

        $this->deleteJson("/api/v1/users/{$targetUser->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_user_for_admin(): void
    {
        $targetUser = $this->createUser([
            'username' => 'hapus_saya',
        ]);

        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->deleteJson("/api/v1/users/{$targetUser->id}")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'sukses menghapus user hapus_saya',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }

    public function test_destroy_missing_user_returns_404(): void
    {
        Sanctum::actingAs($this->createUser(['role' => 'admin']));

        $this->deleteJson('/api/v1/users/999999')
            ->assertNotFound()
            ->assertJson([
                'status' => 'error',
                'message' => 'Resource tidak ditemukan',
            ]);
    }
}
