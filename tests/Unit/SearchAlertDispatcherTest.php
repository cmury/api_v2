<?php

namespace Tests\Unit;

use App\Mail\SearchAlertMail;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserSearch;
use App\Support\Warehouse\SearchAlertDispatcher;
use App\Support\Warehouse\SearchNotifications;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SearchAlertDispatcherTest extends TestCase
{
    public function test_never_frequency_is_skipped(): void
    {
        Mail::fake();

        $user = $this->user('never', [$this->search()]);
        $outcome = (new SearchAlertDispatcher)->processUser($user);

        $this->assertSame('skipped', $outcome);
        Mail::assertNothingSent();
    }

    public function test_not_due_weekly_search_is_skipped(): void
    {
        Mail::fake();

        $search = $this->search();
        $search->last_notified_at = now()->subDays(2);
        $user = $this->user('weekly', [$search]);

        $outcome = (new SearchAlertDispatcher)->processUser($user);

        $this->assertSame('skipped', $outcome);
        Mail::assertNothingSent();
    }

    public function test_empty_due_run_stamps_without_mail(): void
    {
        Mail::fake();

        $search = Mockery::mock(UserSearch::class)->makePartial();
        $search->id = 7;
        $search->notify = true;
        $search->last_notified_at = null;
        $search->shouldReceive('save')->once()->andReturn(true);

        $notifications = Mockery::mock(SearchNotifications::class);
        $notifications->shouldReceive('forSearches')
            ->once()
            ->andReturn(['type' => 'FeatureCollection', 'features' => [], 'frequency' => 'weekly', 'since' => null]);

        $dispatcher = new SearchAlertDispatcher($notifications);
        $user = $this->user('weekly', [$search]);

        $this->assertSame('empty', $dispatcher->processUser($user));
        Mail::assertNothingSent();
        $this->assertNotNull($search->last_notified_at);
    }

    public function test_empty_dry_run_does_not_stamp(): void
    {
        Mail::fake();

        $search = Mockery::mock(UserSearch::class)->makePartial();
        $search->id = 7;
        $search->notify = true;
        $search->last_notified_at = null;
        $search->shouldReceive('save')->never();

        $notifications = Mockery::mock(SearchNotifications::class);
        $notifications->shouldReceive('forSearches')
            ->once()
            ->andReturn(['type' => 'FeatureCollection', 'features' => [], 'frequency' => 'weekly', 'since' => null]);

        $dispatcher = new SearchAlertDispatcher($notifications);
        $user = $this->user('weekly', [$search]);

        $this->assertSame('empty', $dispatcher->processUser($user, dryRun: true));
        Mail::assertNothingSent();
        $this->assertNull($search->last_notified_at);
    }

    public function test_sends_digest_and_stamps_on_matches(): void
    {
        Mail::fake();

        $search = Mockery::mock(UserSearch::class)->makePartial();
        $search->id = 7;
        $search->name = 'Home';
        $search->notify = true;
        $search->last_notified_at = null;
        $search->shouldReceive('save')->once()->andReturn(true);

        $notifications = Mockery::mock(SearchNotifications::class);
        $notifications->shouldReceive('forSearches')
            ->once()
            ->andReturn([
                'type' => 'FeatureCollection',
                'frequency' => 'immediately',
                'since' => null,
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [
                        'id' => 99,
                        'search_id' => 7,
                        'search_name' => 'Home',
                        'location' => '1 Test St',
                        'type' => 'DA',
                    ],
                ]],
            ]);

        $dispatcher = new SearchAlertDispatcher($notifications);
        $user = $this->user('immediately', [$search]);

        $this->assertSame('sent', $dispatcher->processUser($user));
        Mail::assertSent(SearchAlertMail::class, function (SearchAlertMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->total === 1
                && $mail->searches[0]['name'] === 'Home';
        });
        $this->assertNotNull($search->last_notified_at);
    }

    public function test_dry_run_with_matches_does_not_send_or_stamp(): void
    {
        Mail::fake();

        $search = Mockery::mock(UserSearch::class)->makePartial();
        $search->id = 7;
        $search->notify = true;
        $search->last_notified_at = null;
        $search->shouldReceive('save')->never();

        $notifications = Mockery::mock(SearchNotifications::class);
        $notifications->shouldReceive('forSearches')
            ->once()
            ->andReturn([
                'type' => 'FeatureCollection',
                'frequency' => 'daily',
                'since' => null,
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [
                        'id' => 99,
                        'search_id' => 7,
                        'search_name' => 'Home',
                        'location' => '1 Test St',
                    ],
                ]],
            ]);

        $dispatcher = new SearchAlertDispatcher($notifications);
        $user = $this->user('daily', [$search]);

        $this->assertSame('sent', $dispatcher->processUser($user, dryRun: true));
        Mail::assertNothingSent();
        $this->assertNull($search->last_notified_at);
    }

    /**
     * @param  list<UserSearch>  $searches
     */
    private function user(string $frequency, array $searches): User
    {
        $user = new User([
            'email' => 'ada@example.com',
            'name' => 'Ada',
        ]);
        $user->id = 1;
        $user->setRelation('preferences', new UserPreference([
            'notification_frequency' => $frequency,
        ]));
        $user->setRelation('searches', collect($searches));

        return $user;
    }

    private function search(): UserSearch
    {
        $search = new UserSearch;
        $search->id = 7;
        $search->notify = true;
        $search->name = 'Home';

        return $search;
    }
}
