<?php

namespace Tests\Unit;

use App\Models\Authority;
use App\Support\Insights\InsightsDirectResolver;
use App\Support\Warehouse\AuthoritySearch;
use PHPUnit\Framework\TestCase;

class InsightsDirectResolverTest extends TestCase
{
    public function test_it_returns_contact_answer_for_named_council(): void
    {
        $authority = new Authority([
            'name' => 'Dungog Shire Council',
            'state' => 'NSW',
            'phone' => '02 4995 7777',
            'email' => 'council@dungog.nsw.gov.au',
        ]);

        $search = $this->createMock(AuthoritySearch::class);
        $search->expects($this->once())
            ->method('findBestMatch')
            ->with('Dungog', null)
            ->willReturn($authority);

        $result = (new InsightsDirectResolver($search))->try('What is phone number for Dungog Council');

        $this->assertNotNull($result);
        $this->assertStringContainsString('02 4995 7777', $result['answer']);
        $this->assertSame('high', $result['confidence']);
    }
}
