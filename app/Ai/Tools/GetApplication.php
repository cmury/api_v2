<?php

namespace App\Ai\Tools;

use App\Models\Application;
use App\Support\Insights\ToolJson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/applications/{id}.
 */
class GetApplication implements Tool
{
    use ReadsToolArgs;

    public function name(): string
    {
        return 'get_application';
    }

    public function description(): Stringable|string
    {
        return 'Fetch one application by id with authority, locations, and taxonomy labels. '
            .'Resolve the id via search_applications when the user only has an authority_no or description.';
    }

    public function handle(Request $request): Stringable|string
    {
        $id = $this->argInt($request, 'application_id') ?? 0;
        if ($id < 1) {
            return ToolJson::encode(['error' => 'application_id is required.']);
        }

        $application = Application::query()
            ->with([
                'authority:id,name,state',
                'locations:id,suburb,state,post_code,formatted_address,street',
                'applicationTypes:id,name,display_name',
                'developmentTypes:id,name,display_name',
                'decisionTypes:id,name,display_name',
            ])
            ->find($id);

        if ($application === null) {
            return ToolJson::encode(['error' => "Application {$id} not found."]);
        }

        return ToolJson::encode([
            'id' => $application->id,
            'authority_id' => $application->authority_id,
            'authority' => $application->authority?->name,
            'authority_no' => $application->authority_no,
            'portal_no' => $application->portal_no,
            'type' => $application->type,
            'description' => $application->description,
            'estimated_cost' => $application->estimated_cost,
            'submitted' => $application->submitted,
            'decision' => $application->decision,
            'decision_date' => $application->decision_date,
            'locations' => $application->locations->map(fn ($l) => [
                'suburb' => $l->suburb,
                'state' => $l->state,
                'post_code' => $l->post_code,
                'formatted_address' => $l->formatted_address,
            ])->all(),
            'application_types' => $application->applicationTypes->pluck('name')->all(),
            'development_types' => $application->developmentTypes->pluck('name')->all(),
            'decision_types' => $application->decisionTypes->pluck('name')->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'application_id' => $schema->integer()->required()->description('Application primary key.'),
        ];
    }
}
