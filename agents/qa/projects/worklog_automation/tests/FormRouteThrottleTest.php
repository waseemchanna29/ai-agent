<?php

namespace Tests\QA;

require_once __DIR__.'/SeedsWorklogData.php';

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-13 (v3): GET /worklog/{uuid} must be rate-limited (suggested 30/min per IP) so the
 * permanent public UUID cannot be brute-force enumerated. The URL itself is unchanged.
 * Currently FAILS: the route in routes/web.php:12 has no throttle middleware.
 */
class FormRouteThrottleTest extends TestCase
{
    use RefreshDatabase, SeedsWorklogData;

    public function test_form_route_is_rate_limited(): void
    {
        $user = $this->makeEmployee();
        $url = "/worklog/{$user->uuid}";

        $last = null;
        // 30/min limit -> the 31st request within the window should be rejected.
        for ($i = 0; $i < 40; $i++) {
            $last = $this->get($url);
            if ($last->getStatusCode() === 429) {
                break;
            }
        }

        $last->assertStatus(429);
    }
}
