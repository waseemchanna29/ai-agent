<?php

namespace Tests\QA;

require_once __DIR__.'/SeedsWorklogData.php';

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-6 Administrator login
 * FR-7 Admin dashboard + role middleware access control
 */
class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase, SeedsWorklogData;

    public function test_admin_can_login_and_reach_dashboard(): void
    {
        $admin = $this->makeAdmin(['email' => 'boss@example.com', 'password' => 'Password@123!']);

        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'Password@123!'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_employee_cannot_login_even_with_correct_password(): void
    {
        // employee WITH a password set, but role = employee -> login must be rejected
        $this->makeEmployee(['email' => 'emp@example.com', 'password' => 'Password@123!']);

        $this->post('/login', ['email' => 'emp@example.com', 'password' => 'Password@123!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeAdmin(['email' => 'boss2@example.com', 'password' => 'Password@123!']);

        $this->post('/login', ['email' => 'boss2@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_employee_forbidden_from_dashboard(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee)->get('/dashboard')->assertForbidden();
    }

    public function test_admin_dashboard_shows_counts(): void
    {
        $admin = $this->makeAdmin();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $task = $this->makeTask($project);
        $this->makeWorklog($admin, $task);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Users');
    }
}
