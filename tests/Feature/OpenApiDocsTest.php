<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocsTest extends TestCase
{
    public function test_openapi_json_is_available_in_testing(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', fn ($title) => is_string($title) && $title !== '');
    }

    public function test_docs_ui_is_available_in_testing(): void
    {
        $this->get('/docs/api')->assertOk();
    }
}
