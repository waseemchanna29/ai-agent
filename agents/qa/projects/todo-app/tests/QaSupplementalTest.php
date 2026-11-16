<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA agent supplemental test suite — todo-app (requirements v2).
 *
 * Covers boundary values, security (XSS / mass-assignment / ownership),
 * guest-middleware redirects and not-found handling that the developer's
 * own suite does not explicitly exercise. Run against the app bootstrap:
 *   cd C:\xampp\htdocs\todo-app
 *   vendor\bin\phpunit <path-to-this-file>
 */
class QaSupplementalTest extends TestCase
{
    use RefreshDatabase;

    // ---- FR-1 Add task: boundaries -------------------------------------

    public function test_title_of_exactly_255_chars_is_accepted(): void
    {
        $user = User::factory()->create();
        $title = str_repeat('a', 255);

        $this->actingAs($user)
            ->post('/tasks', ['title' => $title])
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }

    public function test_new_task_defaults_to_not_completed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tasks', ['title' => 'Fresh task']);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Fresh task',
            'is_completed' => false,
        ]);
    }

    // ---- Security: XSS output escaping ---------------------------------

    public function test_task_title_is_html_escaped_in_the_list(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create([
            'title' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($user)->get('/tasks');
        $response->assertOk();
        // Raw script tag must NOT appear; the escaped form must.
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    // ---- Security: mass assignment ------------------------------------

    public function test_user_id_cannot_be_mass_assigned_on_create(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($user)->post('/tasks', [
            'title' => 'Mine',
            'user_id' => $victim->id, // attempt to plant it on the victim
        ])->assertRedirect('/tasks');

        // The task must be owned by the acting user, not the injected id.
        $this->assertDatabaseHas('tasks', [
            'title' => 'Mine',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Mine',
            'user_id' => $victim->id,
        ]);
    }

    // ---- FR-3 / FR-4: not-found handling ------------------------------

    public function test_toggling_a_missing_task_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/tasks/999999')->assertNotFound();
    }

    public function test_deleting_a_missing_task_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/tasks/999999')->assertNotFound();
    }

    // ---- FR-5/6/7: guest middleware redirects -------------------------

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect();
    }

    public function test_authenticated_user_is_redirected_away_from_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/register')->assertRedirect();
    }

    // ---- FR-5: email validation ---------------------------------------

    public function test_registration_rejects_invalid_email_format(): void
    {
        $this->post('/register', [
            'name' => 'Bad Email',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_requires_name(): void
    {
        $this->post('/register', [
            'name' => '',
            'email' => 'noname@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('name');

        $this->assertGuest();
    }

    /**
     * Documents current behaviour: the `lowercase` validation rule REJECTS a
     * mixed-case email rather than normalising it. A user who types
     * "Alice@Example.com" cannot register. Captured as a known usability issue.
     */
    public function test_registration_rejects_mixed_case_email(): void
    {
        $this->post('/register', [
            'name' => 'Mixed Case',
            'email' => 'Mixed@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ---- FR-2: ordering & isolation defence-in-depth ------------------

    public function test_other_users_task_is_not_visible_via_toggle_response(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $task = Task::factory()->for($owner)->create(['title' => 'Secret']);

        // Attacker's own list must never contain the owner's task.
        $this->actingAs($attacker)->get('/tasks')->assertDontSee('Secret');
    }
}
