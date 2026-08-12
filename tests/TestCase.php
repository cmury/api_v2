<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ActsAsPlan;

abstract class TestCase extends BaseTestCase
{
    use ActsAsPlan;
}
