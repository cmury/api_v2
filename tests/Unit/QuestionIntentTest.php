<?php

namespace Tests\Unit;

use App\Support\Insights\QuestionIntent;
use PHPUnit\Framework\TestCase;

class QuestionIntentTest extends TestCase
{
    public function test_it_extracts_council_name_and_phone_intent(): void
    {
        $intent = QuestionIntent::fromQuestion('What is phone number for Dungog Council');

        $this->assertSame('Dungog', $intent->authoritySearch);
        $this->assertTrue($intent->wantsContact);
        $this->assertSame('phone', $intent->contactField);
    }

    public function test_it_detects_largest_area_questions(): void
    {
        $intent = QuestionIntent::fromQuestion('In NSW which authority covers the greatest area?');

        $this->assertTrue($intent->wantsLargestArea);
        $this->assertSame('NSW', $intent->state);
    }
}
