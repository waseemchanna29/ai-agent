<?php

namespace Tests\QA;

require_once __DIR__.'/SeedsWorklogData.php';

use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectFor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-8..FR-11 referential-integrity and self-protection guards for the admin CRUD.
 */
class AdminCrudGuardsTest extends TestCase
{
    use RefreshDatabase, SeedsWorklogData;

    /* ---- FR-8 Users ---- */

    public function test_cannot_delete_own_account(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_cannot_remove_own_admin_role(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email,
            'designation' => 'x', 'role' => Role::Employee->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_cannot_delete_user_with_worklogs(): void
    {
        $admin = $this->makeAdmin();
        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $task = $this->makeTask($this->makeProject($pf));
        $this->makeWorklog($emp, $task);

        $this->actingAs($admin)->delete("/admin/users/{$emp->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $emp->id]);
    }

    public function test_user_email_must_be_unique(): void
    {
        $admin = $this->makeAdmin();
        $existing = $this->makeEmployee(['email' => 'taken@example.com']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Dup', 'email' => 'taken@example.com',
            'designation' => 'x', 'role' => Role::Employee->value,
        ])->assertSessionHasErrors('email');
    }

    /* ---- FR-9 Project For ---- */

    public function test_cannot_delete_project_for_with_worklogs(): void
    {
        $admin = $this->makeAdmin();
        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $task = $this->makeTask($this->makeProject($pf));
        $this->makeWorklog($emp, $task);

        $this->actingAs($admin)->delete("/admin/project-fors/{$pf->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('project_fors', ['id' => $pf->id]);
    }

    public function test_project_for_name_unique(): void
    {
        $admin = $this->makeAdmin();
        $this->makeProjectFor('Avaib');

        $this->actingAs($admin)->post('/admin/project-fors', ['name' => 'Avaib'])
            ->assertSessionHasErrors('name');
    }

    /* ---- FR-10 Projects ---- */

    public function test_cannot_delete_project_with_worklogs(): void
    {
        $admin = $this->makeAdmin();
        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $task = $this->makeTask($project);
        $this->makeWorklog($emp, $task);

        $this->actingAs($admin)->delete("/admin/projects/{$project->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_cannot_move_used_project_to_other_project_for(): void
    {
        $admin = $this->makeAdmin();
        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor('Home');
        $pf2 = $this->makeProjectFor('Other');
        $project = $this->makeProject($pf, 'MovableProj');
        $task = $this->makeTask($project);
        $this->makeWorklog($emp, $task);

        $this->actingAs($admin)->put("/admin/projects/{$project->id}", [
            'project_for_id' => $pf2->id, 'name' => 'MovableProj',
        ])->assertSessionHasErrors('project_for_id');

        $this->assertSame($pf->id, $project->fresh()->project_for_id);
    }

    public function test_project_name_unique_within_project_for(): void
    {
        $admin = $this->makeAdmin();
        $pf = $this->makeProjectFor();
        $this->makeProject($pf, 'DupName');

        $this->actingAs($admin)->post('/admin/projects', [
            'project_for_id' => $pf->id, 'name' => 'DupName',
        ])->assertSessionHasErrors('name');
    }

    /* ---- FR-11 Tasks ---- */

    public function test_cannot_delete_task_with_worklogs(): void
    {
        $admin = $this->makeAdmin();
        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $task = $this->makeTask($this->makeProject($pf));
        $this->makeWorklog($emp, $task);

        $this->actingAs($admin)->delete("/admin/tasks/{$task->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_cannot_move_used_task_to_other_project(): void
    {
        $admin = $this->makeAdmin();
        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project1 = $this->makeProject($pf, 'P1');
        $project2 = $this->makeProject($pf, 'P2');
        $task = $this->makeTask($project1, 'Used');
        $this->makeWorklog($emp, $task);

        $this->actingAs($admin)->put("/admin/tasks/{$task->id}", [
            'project_id' => $project2->id, 'title' => 'Used',
        ])->assertSessionHasErrors('project_id');

        $this->assertSame($project1->id, $task->fresh()->project_id);
    }

    public function test_task_title_unique_within_project(): void
    {
        $admin = $this->makeAdmin();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $this->makeTask($project, 'DupTask');

        $this->actingAs($admin)->post('/admin/tasks', [
            'project_id' => $project->id, 'title' => 'DupTask',
        ])->assertSessionHasErrors('title');
    }

    /* ---- access: employee blocked from admin CRUD ---- */

    public function test_employee_cannot_reach_admin_users(): void
    {
        $this->actingAs($this->makeEmployee())->get('/admin/users')->assertForbidden();
    }
}
