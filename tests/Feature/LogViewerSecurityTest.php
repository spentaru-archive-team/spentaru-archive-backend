<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_viewer_requires_admin_user(): void
    {
        $this->get('/log-viewer')->assertForbidden();

        $guru = User::create([
            'name' => 'Guru Log',
            'subject' => 'Kearsipan',
            'position' => 'Guru',
            'username' => 'guru_log',
            'password' => 'Password123',
            'role' => 'guru',
        ]);

        $this->actingAs($guru, 'web')
            ->get('/log-viewer')
            ->assertForbidden();
    }
}
