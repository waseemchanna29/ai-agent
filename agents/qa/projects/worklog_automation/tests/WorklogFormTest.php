<?php

namespace Tests\QA;

require_once __DIR__.'/SeedsWorklogData.php';

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-1 Open personal work-log form
 * FR-3 Submit one or more work-log entries
 */
class WorklogFormTest extends TestCase
{
    use RefreshDatabase, SeedsWorklogData;

    protected function setUp(): void
    {
        parent::setUp();
        // The store endpoint dispatches SendWorklogToN8n on the sync queue, which would
        // make a real outbound call. Stub all HTTP so these tests stay offline.
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    /** FR-1: a valid uuid renders the form */
    public function test_valid_uuid_renders_form(): void
    {
        $user = $this->makeEmployee(['name' => 'Jane Doe']);

        $this->get("/worklog/{$user->uuid}")
            ->assertOk()
            ->assertSee('Jane Doe');
    }

    /** FR-1: an unknown uuid returns 404 */
    public function test_unknown_uuid_returns_404(): void
    {
        $this->get('/worklog/does-not-exist')->assertNotFound();
    }

    private function basePayload(User $user, $pf, $project, $task): array
    {
        return [
            'user_id'         => $user->id,
            'work_date'       => now()->toDateString(),
            'project_for_id'  => $pf->id,
            'project_id'      => $project->id,
            'worklog_entries' => [
                ['task_id' => $task->id, 'hours_spent' => 3, 'details' => 'did work'],
            ],
        ];
    }

    /** FR-3: a valid submission saves rows and returns 201 {message,count} */
    public function test_store_valid_worklog(): void
    {
        $user = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $t1 = $this->makeTask($project, 'Task A');
        $t2 = $this->makeTask($project, 'Task B');

        $payload = $this->basePayload($user, $pf, $project, $t1);
        $payload['worklog_entries'][] = ['task_id' => $t2->id, 'hours_spent' => 1.25, 'details' => null];

        $res = $this->postJson('/worklogs', $payload);

        $res->assertCreated()
            ->assertJson(['message' => 'Worklog saved successfully.', 'count' => 2]);

        $this->assertDatabaseCount('worklogs', 2);
        $this->assertDatabaseHas('worklogs', [
            'user_id' => $user->id, 'task_id' => $t1->id, 'hours_spent' => 3.00,
        ]);
    }

    /** FR-3: future work_date rejected */
    public function test_store_rejects_future_date(): void
    {
        $user = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $task = $this->makeTask($project);

        $payload = $this->basePayload($user, $pf, $project, $task);
        $payload['work_date'] = now()->addDay()->toDateString();

        $this->postJson('/worklogs', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('work_date');
    }

    /** FR-3: project must belong to the chosen project_for */
    public function test_store_rejects_project_not_in_project_for(): void
    {
        $user = $this->makeEmployee();
        $pfA = $this->makeProjectFor('A');
        $pfB = $this->makeProjectFor('B');
        $projectB = $this->makeProject($pfB, 'ProjB');
        $task = $this->makeTask($projectB);

        $payload = $this->basePayload($user, $pfA, $projectB, $task);

        $this->postJson('/worklogs', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('project_id');
    }

    /** FR-3: task must belong to the chosen project */
    public function test_store_rejects_task_not_in_project(): void
    {
        $user = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project1 = $this->makeProject($pf, 'P1');
        $project2 = $this->makeProject($pf, 'P2');
        $taskOnP2 = $this->makeTask($project2, 'OnP2');

        $payload = $this->basePayload($user, $pf, $project1, $taskOnP2);

        $this->postJson('/worklogs', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('worklog_entries.0.task_id');
    }

    /** FR-3: hours below 0.25 and above 24 rejected */
    public function test_store_rejects_hours_out_of_range(): void
    {
        $user = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $task = $this->makeTask($project);

        $low = $this->basePayload($user, $pf, $project, $task);
        $low['worklog_entries'][0]['hours_spent'] = 0.1;
        $this->postJson('/worklogs', $low)
            ->assertStatus(422)
            ->assertJsonValidationErrors('worklog_entries.0.hours_spent');

        $high = $this->basePayload($user, $pf, $project, $task);
        $high['worklog_entries'][0]['hours_spent'] = 25;
        $this->postJson('/worklogs', $high)
            ->assertStatus(422)
            ->assertJsonValidationErrors('worklog_entries.0.hours_spent');
    }

    /** FR-3: at least one entry required */
    public function test_store_requires_at_least_one_entry(): void
    {
        $user = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);

        $payload = [
            'user_id'         => $user->id,
            'work_date'       => now()->toDateString(),
            'project_for_id'  => $pf->id,
            'project_id'      => $project->id,
            'worklog_entries' => [],
        ];

        $this->postJson('/worklogs', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('worklog_entries');
    }

    /**
     * SECURITY (known issue #1): the endpoint accepts ANY existing user_id from the
     * body with no link to the UUID that loaded the page. This test documents the
     * IDOR: an attacker logs hours on behalf of a victim user.
     */
    public function test_store_accepts_arbitrary_user_id_idor(): void
    {
        $victim = $this->makeEmployee(['name' => 'Victim']);
        $pf = $this->makeProjectFor();
        $project = $this->makeProject($pf);
        $task = $this->makeTask($project);

        // No authentication, no UUID ownership check — still succeeds for the victim.
        $this->postJson('/worklogs', $this->basePayload($victim, $pf, $project, $task))
            ->assertCreated();

        $this->assertDatabaseHas('worklogs', ['user_id' => $victim->id]);
    }
}
