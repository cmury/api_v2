<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Mockery;

trait ActsAsPlan
{
    protected function actingAsPlan(string $plan = 'free'): User
    {
        /** @var User&Mockery\MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => 1,
            'email' => 'tester@example.com',
        ]);
        $user->shouldReceive('billingPlanKey')->andReturn($plan);
        Sanctum::actingAs($user);

        return $user;
    }
}
