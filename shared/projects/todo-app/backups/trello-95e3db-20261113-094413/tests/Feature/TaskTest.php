<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_tasks(): void
    {
        $this->get('/tasks')->assertRedirect('/login');
        $this->post('/tasks', ['title' => 'x'])->assertRedirect('/login');
    }

    public function test_user_sees_only_their_own_tasks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $mine = Task::factory()->for($owner)->create(['title' => 'My task']);
        Task::factory()->for($other)->create(['title' => 'Their task']);

        $this->actingAs($owner)
            ->get('/tasks')
            ->assertOk()
            ->assertSee('My task')
            ->assertDontSee('Their task');
    }

    public function test_empty_state_is_shown_when_no_tasks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/tasks')
            ->assertOk()
            ->assertSee('No tasks yet');
    }

    public function test_user_can_add_a_task(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tasks', ['title' => 'Buy milk'])
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Buy milk',
            'is_completed' => false,
        ]);
    }

    public function test_task_title_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tasks', ['title' => '   '])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_task_title_is_limited_to_255_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tasks', ['title' => str_repeat('a', 256)])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_user_can_toggle_their_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['is_completed' => false]);

        $this->actingAs($user)
            ->patch("/tasks/{$task->id}")
            ->assertRedirect('/tasks');

        $this->assertTrue($task->fresh()->is_completed);

        $this->actingAs($user)->patch("/tasks/{$task->id}");
        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_user_can_delete_their_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete("/tasks/{$task->id}")
            ->assertRedirect('/tasks');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_user_cannot_toggle_another_users_task(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $task = Task::factory()->for($owner)->create(['is_completed' => false]);

        $this->actingAs($attacker)
            ->patch("/tasks/{$task->id}")
            ->assertNotFound();

        $this->assertFalse($task->fresh()->is_completed);
    }

    public function test_user_cannot_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        $this->actingAs($attacker)
            ->delete("/tasks/{$task->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_tasks_are_listed_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Task::factory()->for($user)->create(['title' => 'Older task']);
        $newer = Task::factory()->for($user)->create(['title' => 'Newer task']);

        // Force a deterministic ordering by id (same timestamp in fast tests).
        $older->forceFill(['created_at' => now()->subMinute()])->save();
        $newer->forceFill(['created_at' => now()])->save();

        $this->actingAs($user)
            ->get('/tasks')
            ->assertOk()
            ->assertSeeInOrder(['Newer task', 'Older task']);
    }
}
