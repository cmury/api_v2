<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV rows for applications matching a map/warehouse filter.
 * Replaces the old stub `/map/markers/csv` with real filtered data.
 */
final class MapCsvExport
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'application_id',
        'portal_no',
        'authority_no',
        'authority',
        'state',
        'type',
        'description',
        'estimated_cost',
        'submitted',
        'decision',
        'decision_date',
        'location_id',
        'location',
        'lat',
        'lng',
    ];

    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    /**
     * @return Collection<int, object>
     */
    public function rows(ApplicationFilter $filter, ?int $limit = null): Collection
    {
        $limit ??= (int) config('imby.csv_limit', 5000);

        $idsQuery = Application::query()
            ->from('applications as a')
            ->select('a.id')
            ->orderByDesc('a.submitted')
            ->orderByDesc('a.id')
            ->limit($limit);

        $this->applicationQuery->applyToApplications($idsQuery, $filter, 'a');

        $ids = $idsQuery->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::connection(Application::query()->getConnection()->getName())
            ->table('applications as a')
            ->leftJoin('authorities as auth', 'auth.id', '=', 'a.authority_id')
            ->leftJoin('application_locations as al', 'al.application_id', '=', 'a.id')
            ->leftJoin('locations as l', 'l.id', '=', 'al.location_id')
            ->whereIn('a.id', $ids->all())
            ->select([
                'a.id as application_id',
                'a.portal_no',
                'a.authority_no',
                'auth.name as authority',
                'auth.state',
                'a.type',
                'a.description',
                'a.estimated_cost',
                'a.submitted',
                'a.decision',
                'a.decision_date',
                'l.id as location_id',
                'l.formatted_address as location',
            ])
            ->selectRaw('ST_Y(l.geom::geometry) AS lat')
            ->selectRaw('ST_X(l.geom::geometry) AS lng')
            ->orderByDesc('a.submitted')
            ->orderByDesc('a.id')
            ->get()
            ->unique('application_id')
            ->values();
    }

    public function stream(ApplicationFilter $filter, string $filename = 'imby-applications.csv'): StreamedResponse
    {
        $rows = $this->rows($filter);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::HEADERS);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->application_id,
                    $row->portal_no,
                    $row->authority_no,
                    $row->authority,
                    $row->state,
                    $row->type,
                    $row->description,
                    $row->estimated_cost,
                    $row->submitted,
                    $row->decision,
                    $row->decision_date,
                    $row->location_id,
                    GeoJson::stripCountry($row->location !== null ? (string) $row->location : null),
                    $row->lat,
                    $row->lng,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
