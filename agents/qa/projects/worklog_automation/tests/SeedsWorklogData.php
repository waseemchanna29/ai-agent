<?php

namespace Tests\QA;

use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectFor;
use App\Models\Task;
use App\Models\User;
use App\Models\Worklog;

/**
 * Small helpers to build the Project For -> Project -> Task tree and users,
 * since the app ships no factories for those models.
 */
trait SeedsWorklogData
{
    protected function makeAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'        => 'Admin User',
            'email'       => 'admin'.uniqid().'@example.com',
            'designation' => 'Manager',
            'role'        => Role::Admin,
            'password'    => 'Password@123!',
        ], $overrides));
    }

    protected function makeEmployee(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'        => 'Employee User',
            'email'       => 'emp'.uniqid().'@example.com',
            'designation' => 'Developer',
            'role'        => Role::Employee,
        ], $overrides));
    }

    protected function makeProjectFor(string $name = 'Avaib'): ProjectFor
    {
        return ProjectFor::create(['name' => $name]);
    }

    protected function makeProject(ProjectFor $pf, string $name = 'Renter Vibe'): Project
    {
        return Project::create(['project_for_id' => $pf->id, 'name' => $name]);
    }

    protected function makeTask(Project $project, string $title = 'Quick Task', ?User $by = null): Task
    {
        return Task::create([
            'project_id' => $project->id,
            'title'      => $title,
            'created_by' => $by?->id,
            'updated_by' => $by?->id,
        ]);
    }

    protected function makeWorklog(User $user, Task $task): Worklog
    {
        return Worklog::create([
            'user_id'        => $user->id,
            'project_for_id' => $task->project->project_for_id,
            'project_id'     => $task->project_id,
            'task_id'        => $task->id,
            'work_date'      => now()->toDateString(),
            'hours_spent'    => 2.5,
            'details'        => 'seed',
        ]);
    }
}
