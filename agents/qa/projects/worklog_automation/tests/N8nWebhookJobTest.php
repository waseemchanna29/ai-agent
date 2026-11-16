<?php

namespace Tests\QA;

require_once __DIR__.'/SeedsWorklogData.php';

use App\Jobs\SendWorklogToN8n;
use App\Models\Worklog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** FR-5 Forward each submission to n8n */
class N8nWebhookJobTest extends TestCase
{
    use RefreshDatabase, SeedsWorklogData;

    public function test_job_posts_payload_with_secret_header(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $emp = $this->makeEmployee(['name' => 'Payload User']);
        $pf = $this->makeProjectFor('Avaib');
        $project = $this->makeProject($pf, 'Renter Vibe');
        $task = $this->makeTask($project, 'Build');
        $w1 = $this->makeWorklog($emp, $task);           // 2.5h
        $w2 = $this->makeWorklog($emp, $task);           // 2.5h

        (new SendWorklogToN8n([$w1->id, $w2->id]))->handle();

        Http::assertSent(function ($request) use ($emp) {
            return $request->url() === 'https://n8n.test/webhook/worklog'
                && $request->hasHeader('X-Webhook-Secret', 'test-secret')
                && $request['user']['id'] === $emp->id
                && $request['total_hours'] === 5.0
                && count($request['worklog_entries']) === 2;
        });
    }

    public function test_job_retry_configuration(): void
    {
        $job = new SendWorklogToN8n([1]);

        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 30, 60, 300], $job->backoff);
    }

    public function test_job_exits_quietly_when_logs_deleted(): void
    {
        Http::fake();

        // ids that reference no existing rows
        (new SendWorklogToN8n([999, 1000]))->handle();

        Http::assertNothingSent();
    }

    public function test_job_throws_on_non_2xx_so_it_retries(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $emp = $this->makeEmployee();
        $pf = $this->makeProjectFor();
        $task = $this->makeTask($this->makeProject($pf));
        $w = $this->makeWorklog($emp, $task);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        (new SendWorklogToN8n([$w->id]))->handle();
    }
}
