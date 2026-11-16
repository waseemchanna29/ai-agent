<?php

/**
 * QA supplementary feature tests for todo-app.
 *
 * These complement the developer's tests/Feature suite; they target gaps:
 * guest-only pages for authenticated users, cross-tenant isolation on the
 * store route, 404 on non-existent task ids, the 255-char accepted boundary,
 * logout re-locking the task page, and email-case handling at registration.
 *
 * Run: php C:/xampp/htdocs/todo-app/vendor/phpunit/phpunit/phpunit \
 *        --configuration C:/xampp/htdocs/Agents/agents/qa/projects/todo-app/tests/phpunit.qa.xml
 */

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaExtraTest extends TestCase
{
    use RefreshDatabase;

    /** FR-6/FR-5: an authenticated user should not see the guest login/register pages. */
    public function test_authenticated_user_is_redirected_away_from_login_and_register(): void
    {
        $user = User::factory()->create();

        // Laravel's `guest` middleware sends authenticated users to the home route
        // ('/'), which itself redirects on to /tasks. Either way they never see the form.
        $this->actingAs($user)->get('/login')->assertRedirect('/');
        $this->actingAs($user)->get('/register')->assertRedirect('/');
    }

    /** FR-2 (tenant isolation): a task created by user A must never appear in user B's list. */
    public function test_task_created_by_one_user_is_not_visible_to_another(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->post('/tasks', ['title' => 'Alice secret task'])
            ->assertRedirect('/tasks');

        $this->actingAs($bob)->get('/tasks')
            ->assertOk()
            ->assertDontSee('Alice secret task');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $alice->id,
            'title' => 'Alice secret task',
        ]);
    }

    /** FR-3/FR-4: toggling or deleting a non-existent task id returns 404, not 500. */
    public function test_toggle_or_delete_of_missing_task_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/tasks/999999')->assertNotFound();
        $this->actingAs($user)->delete('/tasks/999999')->assertNotFound();
    }

    /** FR-1: a title of exactly 255 characters is accepted (boundary at the limit). */
    public function test_title_of_exactly_255_characters_is_accepted(): void
    {
        $user = User::factory()->create();
        $title = str_repeat('a', 255);

        $this->actingAs($user)->post('/tasks', ['title' => $title])
            ->assertRedirect('/tasks')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }

    /** FR-1: a completely missing title field is rejected with a validation error. */
    public function test_missing_title_field_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tasks', [])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    /** FR-7: after logout the task page is locked again (redirect to login). */
    public function test_after_logout_tasks_page_is_locked_again(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tasks')->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        // Fresh guest request (no acting user) must be redirected.
        $this->get('/tasks')->assertRedirect('/login');
    }

    /** FR-3: toggling twice returns the task to its original (incomplete) state. */
    public function test_toggle_is_reversible(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['is_completed' => false]);

        $this->actingAs($user)->patch("/tasks/{$task->id}");
        $this->assertTrue($task->fresh()->is_completed);

        $this->actingAs($user)->patch("/tasks/{$task->id}");
        $this->assertFalse($task->fresh()->is_completed);
    }

    /** NFR security: another user's PATCH must not change the owner's completion state. */
    public function test_cross_user_toggle_leaves_owner_data_unchanged(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $task = Task::factory()->for($owner)->create(['is_completed' => false]);

        $this->actingAs($attacker)->patch("/tasks/{$task->id}")->assertNotFound();

        $this->assertFalse($task->fresh()->is_completed);
    }

    /**
     * FR-5: the registration 'lowercase' rule rejects (does not normalise) an
     * email containing uppercase letters. Documents current behaviour.
     */
    public function test_registration_with_uppercase_email_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Case Test',
            'email' => 'Mixed@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'mixed@example.com']);
    }
}
