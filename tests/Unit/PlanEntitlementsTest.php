<?php

namespace Tests\Unit;

use App\Support\Billing\PlanEntitlements;
use Tests\TestCase;

class PlanEntitlementsTest extends TestCase
{
    public function test_planning_layers_start_at_core(): void
    {
        $this->assertFalse(PlanEntitlements::allows('free', PlanEntitlements::FEATURE_PLANNING_LAYERS));
        $this->assertTrue(PlanEntitlements::allows('core', PlanEntitlements::FEATURE_PLANNING_LAYERS));
        $this->assertTrue(PlanEntitlements::allows('pro', PlanEntitlements::FEATURE_PLANNING_LAYERS));
        $this->assertSame('core', PlanEntitlements::minimumPlan(PlanEntitlements::FEATURE_PLANNING_LAYERS));
    }

    public function test_analytics_start_at_pro(): void
    {
        $this->assertFalse(PlanEntitlements::allows('free', PlanEntitlements::FEATURE_ANALYTICS));
        $this->assertFalse(PlanEntitlements::allows('core', PlanEntitlements::FEATURE_ANALYTICS));
        $this->assertTrue(PlanEntitlements::allows('pro', PlanEntitlements::FEATURE_ANALYTICS));
        $this->assertTrue(PlanEntitlements::allows('enterprise', PlanEntitlements::FEATURE_ANALYTICS));
        $this->assertSame('pro', PlanEntitlements::minimumPlan(PlanEntitlements::FEATURE_ANALYTICS));
    }

    public function test_unknown_feature_is_denied(): void
    {
        $this->assertFalse(PlanEntitlements::allows('enterprise', 'not-a-feature'));
        $this->assertNull(PlanEntitlements::minimumPlan('not-a-feature'));
    }
}
