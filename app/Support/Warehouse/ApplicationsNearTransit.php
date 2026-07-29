<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use App\Models\TransitStop;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Applications whose site locations fall within a radius of a transit_stops point.
 */
final class ApplicationsNearTransit
{
    public function __construct(
        private readonly TransitStopSearch $transitStopSearch = new TransitStopSearch,
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filterInput  ApplicationFilter::fromArray input (without lat/lng/radius)
     * @return array{
     *     stop: array<string, mixed>,
     *     radius_meters: int,
     *     query: Builder<Application>
     * }
     */
    public function query(
        array $filterInput,
        int $radiusMeters,
        ?int $transitStopId = null,
        ?string $stopSearch = null,
        ?string $stopType = null,
        ?string $stopState = null,
    ): array {
        $radiusMeters = max(1, min(50_000, $radiusMeters));

        $stop = $this->transitStopSearch->resolve(
            $transitStopId,
            $stopSearch,
            $stopState ?? (isset($filterInput['state']) ? (string) $filterInput['state'] : null),
            $stopType,
        );

        $lat = isset($stop->lat) ? (float) $stop->lat : null;
        $lng = isset($stop->lng) ? (float) $stop->lng : null;

        if ($lat === null || $lng === null) {
            throw new InvalidArgumentException('Transit stop has no geometry coordinates.');
        }

        $filter = ApplicationFilter::fromArray([
            ...$filterInput,
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radiusMeters,
        ]);

        $query = Application::query()->with(['authority:id,name,state']);
        $this->applicationQuery->applyToApplications($query, $filter);

        return [
            'stop' => $this->transitStopSearch->toArray($stop),
            'radius_meters' => $radiusMeters,
            'query' => $query,
        ];
    }

    /**
     * Ensure a stop instance includes lat/lng attributes for spatial filtering.
     */
    public function withCoordinates(TransitStop $stop): TransitStop
    {
        if (isset($stop->lat, $stop->lng)) {
            return $stop;
        }

        $coords = TransitStop::query()
            ->whereKey($stop->id)
            ->whereNotNull('geom')
            ->selectRaw('ST_Y(geom::geometry) AS lat, ST_X(geom::geometry) AS lng')
            ->first();

        if ($coords === null) {
            throw new InvalidArgumentException("Transit stop {$stop->id} has no geometry.");
        }

        $stop->setAttribute('lat', $coords->lat);
        $stop->setAttribute('lng', $coords->lng);

        return $stop;
    }
}
