<?php

namespace Tests\QA;

require_once __DIR__.'/SeedsWorklogData.php';

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FR-4 Add a task inline (POST /tasks quickStore) */
class TaskQuickStoreTest extends TestCase
{
    use RefreshDatabase, SeedsWorklogData;

    public function test_quick_store_creates_task(): void
    {
        $user = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);

        $res = $this->postJson('/tasks', [
            'project_id' => $project->id,
            'title'      => '  New Inline Task  ',
            'user_id'    => $user->id,
        ]);

        $res->assertCreated()
            ->assertJson(['project_id' => $project->id, 'title' => 'New Inline Task']);
        $res->assertJsonStructure(['id', 'project_id', 'title']);

        // title is trimmed on create
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id, 'title' => 'New Inline Task', 'created_by' => $user->id,
        ]);
    }

    public function test_quick_store_dedupes_existing_task(): void
    {
        $creator = $this->makeEmployee();
        $other = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $existing = $this->makeTask($project, 'Shared Task', $creator);

        $res = $this->postJson('/tasks', [
            'project_id' => $project->id,
            'title'      => 'Shared Task',
            'user_id'    => $other->id,
        ]);

        $res->assertCreated()->assertJson(['id' => $existing->id]);

        // no duplicate, audit fields untouched (still the original creator)
        $this->assertSame(1, Task::where('project_id', $project->id)->where('title', 'Shared Task')->count());
        $this->assertDatabaseHas('tasks', ['id' => $existing->id, 'created_by' => $creator->id]);
    }

    public function test_quick_store_validates_required_fields(): void
    {
        // project_id and title are required in every version. (Under FR-4 v3 user_id should be
        // ignored and resolved from the UUID, so it is intentionally not asserted here.)
        $this->postJson('/tasks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'title']);
    }
}
