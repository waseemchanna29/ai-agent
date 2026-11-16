<?php

namespace App\Jobs;

use App\Models\Worklog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendWorklogToN8n implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300];

    public function __construct(public array $worklogIds) {}

    public function handle(): void
    {
        $logs = Worklog::with(['user', 'projectFor', 'project', 'task'])
            ->whereIn('id', $this->worklogIds)
            ->get();

        // Row was deleted before the job ran
        $first = $logs->first();
        if (! $first) {
            return;
        }

        $payload = [
            'user' => [
                'id'          => $first->user->id,
                'name'        => $first->user->name,
                'email'       => $first->user->email,
                'designation' => $first->user->designation,
            ],
            'work_date'   => $first->work_date->toDateString(),
            'project_for' => ['id' => $first->projectFor->id, 'name' => $first->projectFor->name],
            'project'     => ['id' => $first->project->id, 'name' => $first->project->name],
            'worklog_entries' => $logs->map(fn($l) => [
                'task_id'     => $l->task_id,
                'task_title'  => $l->task->title,
                'hours_spent' => (float) $l->hours_spent,
                'details'     => $l->details,
                'timestamp' => $l->created_at
            ])->values(),
            'total_hours' => (float) $logs->sum('hours_spent'),
        ];


        Http::withHeaders(['X-Webhook-Secret' => config('services.n8n.secret')])
            ->timeout(15)
            ->post(config('services.n8n.webhook_url'), $payload)
            ->throw(); // non-2xx -> exception -> job retries
    }
}
