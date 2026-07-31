<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Applications whose site locations fall within a radius of a facilities point.
 */
final class ApplicationsNearFacility
{
    public function __construct(
        private readonly FacilitySearch $facilitySearch = new FacilitySearch,
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filterInput  ApplicationFilter::fromArray input (without lat/lng/radius)
     * @return array{
     *     facility: array<string, mixed>,
     *     radius_meters: int,
     *     query: Builder<Application>
     * }
     */
    public function query(
        array $filterInput,
        int $radiusMeters,
        ?int $facilityId = null,
        ?string $search = null,
        ?string $facilityType = null,
        ?string $state = null,
    ): array {
        $radiusMeters = max(1, min(50_000, $radiusMeters));

        $facility = $this->facilitySearch->resolve(
            $facilityId,
            $search,
            $state ?? (isset($filterInput['state']) ? (string) $filterInput['state'] : null),
            $facilityType,
        );

        $lat = isset($facility->lat) ? (float) $facility->lat : null;
        $lng = isset($facility->lng) ? (float) $facility->lng : null;

        if ($lat === null || $lng === null) {
            throw new InvalidArgumentException('Facility has no geometry coordinates.');
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
            'facility' => $this->facilitySearch->toArray($facility),
            'radius_meters' => $radiusMeters,
            'query' => $query,
        ];
    }

    /**
     * Ensure a facility instance includes lat/lng attributes for spatial filtering.
     */
    public function withCoordinates(Facility $facility): Facility
    {
        if (isset($facility->lat, $facility->lng)) {
            return $facility;
        }

        $coords = Facility::query()
            ->whereKey($facility->id)
            ->whereNotNull('geom')
            ->selectRaw('ST_Y(geom::geometry) AS lat, ST_X(geom::geometry) AS lng')
            ->first();

        if ($coords === null) {
            throw new InvalidArgumentException("Facility {$facility->id} has no geometry.");
        }

        $facility->setAttribute('lat', $coords->lat);
        $facility->setAttribute('lng', $coords->lng);

        return $facility;
    }
}
