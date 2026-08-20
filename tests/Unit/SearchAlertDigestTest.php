<?php

namespace Tests\Unit;

use App\Support\Warehouse\SearchAlertDigest;
use Tests\TestCase;

class SearchAlertDigestTest extends TestCase
{
    public function test_groups_caps_and_builds_headlines(): void
    {
        config(['imby.search_alerts.frontend_url' => 'http://localhost:5174']);

        $digest = new SearchAlertDigest;
        $sections = $digest->fromGeoJson([
            'features' => [
                $this->feature(1, 7, 'Home', '1 Test St', 'DA', 'Pending', 250000, 'DA-1'),
                $this->feature(2, 7, 'Home', '2 Test St', 'CDC', null, null, null),
                $this->feature(3, 8, 'Work', '3 Work St', 'DA', 'Approved', 1000000, 'DA-3'),
            ],
        ], 1);

        $this->assertCount(2, $sections);
        $this->assertSame(7, $sections[0]['id']);
        $this->assertSame('Home', $sections[0]['name']);
        $this->assertSame(2, $sections[0]['total']);
        $this->assertSame(1, $sections[0]['omitted']);
        $this->assertSame('1 Test St — DA — Pending — $250,000 — DA-1', $sections[0]['applications'][0]['headline']);
        $this->assertSame('http://localhost:5174/applications/1', $sections[0]['applications'][0]['url']);
        $this->assertSame(8, $sections[1]['id']);
        $this->assertSame(0, $sections[1]['omitted']);
        $this->assertSame(3, $digest->totalApplications($sections));
    }

    /**
     * @return array<string, mixed>
     */
    private function feature(
        int $id,
        int $searchId,
        string $searchName,
        string $location,
        ?string $type,
        ?string $decision,
        mixed $cost,
        ?string $portalNo,
    ): array {
        return [
            'type' => 'Feature',
            'properties' => [
                'id' => $id,
                'search_id' => $searchId,
                'search_name' => $searchName,
                'location' => $location,
                'type' => $type,
                'decision' => $decision,
                'estimated_cost' => $cost,
                'portal_no' => $portalNo,
            ],
        ];
    }
}
