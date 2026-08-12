<?php

namespace Tests\Unit;

use App\Support\UserActivityLogger;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserActivityLoggerTest extends TestCase
{
    public function test_visitor_fingerprint_is_stable_for_same_request(): void
    {
        $logger = new UserActivityLogger;
        $request = Request::create('/api/applications/1', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'IMBYTest/1.0',
        ]);

        $a = $logger->visitorFingerprint($request);
        $b = $logger->visitorFingerprint($request);

        $this->assertNotSame('', $a);
        $this->assertSame($a, $b);
        $this->assertSame(16, strlen($logger->visitorToken($a)));
    }

    public function test_anonymous_views_config_defaults_on(): void
    {
        $this->assertTrue(config('imby.activity.log_anonymous_application_views'));
        $this->assertNull(config('imby.activity.anonymous_user_id'));
    }
}
