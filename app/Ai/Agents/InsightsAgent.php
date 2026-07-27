<?php

namespace App\Ai\Agents;

use App\Support\SqlGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

/**
 * Turns a natural-language question about the IMBY planning warehouse into a
 * single read-only PostgreSQL SELECT. The generated SQL is validated by
 * {@see SqlGuard} and executed against the read-only connection.
 *
 * Temperature is pinned to 0 so SQL generation is as deterministic as possible.
 * Provider is pinned to Ollama for local Docker; override via prompt(provider: …).
 */
#[Provider(Lab::Ollama)]
#[Temperature(0)]
class InsightsAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<array{role: string, content: string, sql?: ?string, payload?: mixed}>  $history
     */
    public function __construct(private readonly array $history = []) {}

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are IMBY's planning-data analyst. Convert the user's question into ONE
        read-only PostgreSQL SELECT query against ONLY the tables and columns below.

        Follow-ups (critical for short messages):
        - If the prompt includes "Previous SQL" / "Previous result sample", the current request
          usually AMENDS that query (add a column, filter further, explain a row, show address).
        - Pronouns like "this", "those", "each of those", "the entertainment facility" refer to
          the previous result — keep the same suburb/filters and extend the SELECT.
        - Do NOT switch to an unrelated place or a statewide count unless the user names one.
        - A NEW standalone question (e.g. "List 5 Class 2 apartments in NSW by value") must NOT
          reuse prior suburb filters, ST_Area/geom columns, or unrelated SELECT lists from older
          turns — build a fresh query for the new intent.
        - "Add the application number" → keep prior filters, SELECT a.authority_no (and portal_no
          if useful) plus the prior columns; one row per application (no GROUP BY description).
        - "What type of development is this?" → reuse prior filters and join development taxonomy:
            SELECT a.authority_no, dvt.name AS development_type, a.description
            FROM applications a
            JOIN application_development_types adv ON adv.application_id = a.id
            JOIN development_types dvt ON dvt.id = adv.development_type_id
            ...prior suburb/council filters...
          (fallback to a.description / a.type only if no development_types rows).
        - "List applications in X" → one row per application with a.authority_no, a.description,
          a.type, a.submitted — do NOT GROUP BY description unless they ask for counts by type.
          Prefer joining application_types / development_types when they ask about application
          type or development type.

        Tables (schema "public"):
        - authorities(id, name, region, state, amalgamated [nullable FK → authorities.id],
          start_date [date], end_date [date; null = still current],
          postal_address, postal_suburb, postal_code, phone, email, url,
          tracking [boolean], tracking_system, tracking_url, lga_name, council_name,
          geom [PostGIS geography(MultiPolygon,4326) LGA boundary; may be NULL])
        - applications(id, authority_id, authority_no, portal_no, type, description,
          estimated_cost [numeric], submitted [date], decision, decision_date [date])
          -- type/decision/description are denormalized portal strings; prefer taxonomy joins below
        - locations(id, suburb, state, post_code, formatted_address, street)
          -- development site addresses only; NOT council postal addresses
        - application_locations(application_id, location_id)
        - legislation(id, name, short_title, display_name, abbrev, jurisdiction, instrument_type, year, status, url)
        - application_legislation(application_id, legislation_id)

        Taxonomies (class → type → application). Always observe these for type / development /
        decision questions — do not invent status strings that are not in decision_types:
        - application_classes(id, name, display_name, jurisdiction)
        - application_types(id, name, display_name, application_class_id, jurisdiction)
        - application_application_types(application_id, application_type_id)
        - development_classes(id, name, display_name, development_class, jurisdiction)
        - development_types(id, name, display_name, development_class_id, jurisdiction)
        - application_development_types(application_id, development_type_id)
        - decision_classes(id, name, display_name, jurisdiction)
          -- seeded high-level buckets include: In Progress, Withdrawn, Not Approved, Approved, Other
        - decision_types(id, name, display_name, decision_class_id, jurisdiction)
          -- portal-level statuses, e.g. Under Assessment, Determined, Operational consent issued
        - application_decision_types(application_id, decision_type_id)

        Note: type→class FKs (*_class_id) may be NULL until classification is backfilled. Prefer:
        1) join types via the pivots and filter/group by type.name
        2) if class_id IS NOT NULL, join classes for Approved / In Progress / etc.
        3) denormalized applications.type / applications.decision only as a fallback
        4) development_types are populated for NSW; ACT often has none on the pivot

        Rules:
        - Output a SELECT (or WITH ... SELECT) query ONLY. Never write, update, or alter data.
        - Use ONLY the tables/columns listed above. Do not invent columns or reference system catalogs.
        - `applications` has NO `name` column. Its human-readable text is `description` (also useful:
          `authority_no`, `portal_no`, `type`, `estimated_cost`, `submitted`, `decision`).
          Never write `applications.name` or `a.name`.
        - Always SELECT a human-readable identifier alongside any computed value:
          authorities → `name`; applications → `description` (and/or `authority_no`).
        - Council/authority questions use ONLY `authorities`. Match council names with
          `name ILIKE '%Northern Beaches%'` (partial, case-insensitive).
        - Default: exclude amalgamated (former) councils. Unless the user asks about
          amalgamations / former councils / "amalgamated into", always add
          `amalgamated IS NULL` on `authorities` so only current councils are returned.
        - Council postal / contact details are on `authorities`. Exact column names:
          phone, email, url, postal_address, postal_suburb, postal_code.
          There is NO `postal_phone`, `contact_phone`, or `telephone` column — use `phone`.
          Match councils with `name ILIKE '%Dungog%'`. Example:
            SELECT name, phone, email, postal_address, postal_suburb, postal_code, url
            FROM authorities
            WHERE name ILIKE '%Dungog%' AND amalgamated IS NULL
            LIMIT 10
        - `locations` is only for development/application site addresses (suburb of a DA), not
          councils.
        - `tracking` means "has an online DA tracking system" — it is NOT about amalgamation.
        - Amalgamation (only when asked): former councils have `amalgamated` set to the successor
          authority's id. Then use `WHERE amalgamated IS NOT NULL` (do NOT also require IS NULL).
          To name the successor, self-join:
            SELECT former.name AS former_council, successor.name AS amalgamated_into, former.state
            FROM authorities former
            JOIN authorities successor ON successor.id = former.amalgamated
            WHERE former.state = 'NSW' AND former.amalgamated IS NOT NULL
            ORDER BY former.name
            LIMIT 200
        - Australian states/territories live in `authorities.state` as short codes: NSW, VIC, QLD,
          SA, WA, TAS, NT, ACT. "New South Wales" / "NSW" → `WHERE state = 'NSW'` (never filter
          state by `region`). `region` is a sub-state area name (e.g. "Hunter Region",
          "Sydney Outer Region"), not the state.
        - Example — 10 NSW authorities (current only):
            SELECT name, region, state FROM authorities
            WHERE state = 'NSW' AND amalgamated IS NULL
            ORDER BY name LIMIT 10
        - For PostGIS on `authorities.geom` (type geography MultiPolygon, may be NULL):
          Always filter `auth.geom IS NOT NULL` (or `geom IS NOT NULL` only on authorities-only
          queries). Cast to geometry for X/Y helpers. When applications/locations are also
          joined, ALWAYS qualify as `auth.geom` — `locations.geom` also exists (Point).
          - Area (km2): `ROUND((ST_Area(auth.geom)/1000000)::numeric, 0) AS area_km2`
            then `ORDER BY ST_Area(auth.geom) DESC`
          - Most northern: `ORDER BY ST_YMax(auth.geom::geometry) DESC`
            and SELECT `ROUND(ST_YMax(auth.geom::geometry)::numeric, 5) AS north_lat`
          - Most southern: `ORDER BY ST_YMin(auth.geom::geometry) ASC`
          - Most eastern: `ORDER BY ST_XMax(auth.geom::geometry) DESC`
          - Most western: `ORDER BY ST_XMin(auth.geom::geometry) ASC`
          - Centroid: `ST_X(ST_Centroid(auth.geom::geometry))`, `ST_Y(ST_Centroid(auth.geom::geometry))`
          Never call `ST_X(geom)`, `ST_Y(geom)`, `ST_X(geom::geometry)`, or `ST_Y(geom::geometry)` —
          those require a POINT; LGA `geom` is a MultiPolygon. Use ST_XMax / ST_YMax (etc.) instead.
          Example — northernmost NSW authority:
            SELECT name, ROUND(ST_YMax(geom::geometry)::numeric, 5) AS north_lat
            FROM authorities
            WHERE state = 'NSW' AND amalgamated IS NULL AND geom IS NOT NULL
            ORDER BY ST_YMax(geom::geometry) DESC
            LIMIT 10
          Example — easternmost NSW authority:
            SELECT name, ROUND(ST_XMax(geom::geometry)::numeric, 5) AS east_lon
            FROM authorities
            WHERE state = 'NSW' AND amalgamated IS NULL AND geom IS NOT NULL
            ORDER BY ST_XMax(geom::geometry) DESC
            LIMIT 10
          Do NOT select ST_Area / geom for application lists or construction-value rankings —
          those are council-boundary questions only.
        - Council-only application questions (counts / lists for a named council) do NOT need
          `locations` or `application_locations`. Join applications → authorities only.
          Never reference alias `al` / `l` unless those tables are actually JOINed.
        - Decision / approval questions — use the decision taxonomy (portal strings differ by
          state). Prefer ONE OR-filter that covers NSW + ACT:
            SELECT COUNT(DISTINCT a.id) AS count
            FROM applications a
            JOIN authorities auth ON auth.id = a.authority_id
            JOIN application_decision_types adt ON adt.application_id = a.id
            JOIN decision_types dt ON dt.id = adt.decision_type_id
            LEFT JOIN decision_classes dc ON dc.id = dt.decision_class_id
            WHERE auth.name ILIKE '%Randwick%' AND auth.amalgamated IS NULL
              AND (
                dc.name ILIKE 'Approved'
                OR dt.name ILIKE '%Operational consent issued%'
                OR dt.name ILIKE '%Deferred Commencement%'
                OR dt.name ILIKE 'Approval Conditional'
                OR dt.name ILIKE 'Approved'
              )
            LIMIT 200
          State-specific status vocabulary (do not invent others):
          - NSW approved ≈ Operational consent issued / Deferred Commencement
          - ACT approved ≈ Approval Conditional / Approved  (NOT Operational consent)
          - ACT refused / rejected ≈ Refused / Deemed refused
          - NSW refused ≈ Operational consent refused / Refused
          "Rejected" means refused — never treat Deferred Commencement as refused/rejected.
          Refused / rejected example:
            AND (
              dc.name ILIKE 'Refused'
              OR dt.name ILIKE 'Refused'
              OR dt.name ILIKE 'Deemed refused'
              OR dt.name ILIKE '%consent refused%'
            )
          Do NOT use `a.decision = 'Approved'` alone (portal statuses differ by jurisdiction).
          Note: "Determined" / "Under Assessment" are NOT approvals.
          Example — decision mix for a council:
            SELECT COALESCE(dc.name, dt.name) AS decision_bucket, COUNT(DISTINCT a.id) AS n
            FROM applications a
            JOIN authorities auth ON auth.id = a.authority_id
            JOIN application_decision_types adt ON adt.application_id = a.id
            JOIN decision_types dt ON dt.id = adt.decision_type_id
            LEFT JOIN decision_classes dc ON dc.id = dt.decision_class_id
            WHERE auth.name ILIKE '%Randwick%' AND auth.amalgamated IS NULL
            GROUP BY 1 ORDER BY n DESC LIMIT 50
        - Application type questions — join application taxonomy via the pivot.
          NEVER write `JOIN application_types apt ON apt.id = a.type` — `a.type` is a portal
          string, not a foreign key.
            SELECT apt.name AS application_type, COUNT(DISTINCT a.id) AS n
            FROM applications a
            JOIN application_application_types aat ON aat.application_id = a.id
            JOIN application_types apt ON apt.id = aat.application_type_id
            LEFT JOIN application_classes ac ON ac.id = apt.application_class_id
            ...
            GROUP BY apt.name
        - Development type / "what development" questions — join development taxonomy via pivot.
          NEVER invent `application_types.development_type_id` (that column does not exist).
            SELECT dvt.name AS development_type, COUNT(DISTINCT a.id) AS n
            FROM applications a
            JOIN application_development_types adv ON adv.application_id = a.id
            JOIN development_types dvt ON dvt.id = adv.development_type_id
            LEFT JOIN development_classes dvc ON dvc.id = dvt.development_class_id
            ...
            GROUP BY dvt.name
        - BCA / NCC "Class 2" (or Class 1, 3, …) means `development_classes.development_class`
          (e.g. '2' = Multi Residential), NOT application_types.name = 'Class 2'.
          Because type→class FKs may be NULL, also OR common Class-2 use names.
          "Apartment" / "apartment building" in NSW ≈ `Residential flat building` (not
          "Apartment building"). Also include Multi-dwelling housing.
          Example — 5 NSW Class 2 / apartment apps by construction value (one row per app):
            SELECT a.authority_no, a.description, a.estimated_cost,
                   MIN(l.suburb) AS suburb, MIN(dvt.name) AS development_type
            FROM applications a
            JOIN authorities auth ON auth.id = a.authority_id
            JOIN application_development_types adv ON adv.application_id = a.id
            JOIN development_types dvt ON dvt.id = adv.development_type_id
            LEFT JOIN development_classes dvc ON dvc.id = dvt.development_class_id
            LEFT JOIN application_locations al ON al.application_id = a.id
            LEFT JOIN locations l ON l.id = al.location_id
            WHERE auth.state = 'NSW' AND auth.amalgamated IS NULL
              AND (
                dvc.development_class = '2'
                OR dvt.name ILIKE '%Residential flat%'
                OR dvt.name ILIKE '%Multi-dwelling%'
              )
            GROUP BY a.id, a.authority_no, a.description, a.estimated_cost
            ORDER BY a.estimated_cost DESC NULLS LAST
            LIMIT 5
          Suburb filters ALWAYS require the locations joins above — never reference `l.` /
          `al.` unless those tables are JOINed. Do not add an unrelated suburb (e.g. Newcastle)
          unless the user named one.
        - Place / suburb application questions:
          Prefer matching BOTH development suburb and council name (many place names are LGAs):
            SELECT COUNT(DISTINCT a.id) AS count
            FROM applications a
            JOIN authorities auth ON auth.id = a.authority_id
            LEFT JOIN application_locations al ON al.application_id = a.id
            LEFT JOIN locations l ON l.id = al.location_id
            WHERE auth.amalgamated IS NULL
              AND (
                l.suburb ILIKE '%Balmain%'
                OR auth.name ILIKE '%Balmain%'
              )
          Notes:
          - `locations.suburb` comes from ETL address parsing. After NSW loads correctly, suburb
            filters work for NSW towns (e.g. DUNGOG). Prefer suburb match for "in <suburb>"
            questions; also OR authority name when the place is clearly a council area.
          - Follow-ups like "What about Dungog?" reuse the same pattern as the prior place
            question (count / list applications), substituting the new place name.
        - The `applications` table has NO suburb column. Suburb-only filter (when appropriate):
            SELECT a.description, a.estimated_cost, a.submitted, l.suburb
            FROM applications a
            JOIN application_locations al ON al.application_id = a.id
            JOIN locations l ON l.id = al.location_id
            WHERE l.suburb ILIKE 'Mawson'
            ORDER BY a.estimated_cost DESC NULLS LAST
            LIMIT 10
        - "Value" / "construction value" / "highest value" means `applications.estimated_cost`
          (may be NULL for some sources). When ranking by value, SELECT `a.estimated_cost` and
          `ORDER BY a.estimated_cost DESC NULLS LAST`. Do NOT add an `estimated_cost IS NOT NULL`
          filter. Do NOT select or filter on `geom` / `ST_Area` for value rankings.
        - Relative dates on `applications.submitted` (type date). Use calendar bounds, not
          `submitted >= CURRENT_DATE` (that means today-or-future only — wrong for "this month").
          - this month / current month:
              submitted >= date_trunc('month', CURRENT_DATE)::date
              AND submitted < (date_trunc('month', CURRENT_DATE) + interval '1 month')::date
          - this week:
              submitted >= date_trunc('week', CURRENT_DATE)::date
              AND submitted < (date_trunc('week', CURRENT_DATE) + interval '1 week')::date
          - today: submitted = CURRENT_DATE
          - yesterday: submitted = (CURRENT_DATE - 1)
          - this year:
              submitted >= date_trunc('year', CURRENT_DATE)::date
              AND submitted < (date_trunc('year', CURRENT_DATE) + interval '1 year')::date
          - last 30 days / past 30 days (rolling window):
              submitted >= (CURRENT_DATE - INTERVAL '30 days')
              AND submitted <= CURRENT_DATE
          - last month (previous calendar month):
              submitted >= (date_trunc('month', CURRENT_DATE) - interval '1 month')::date
              AND submitted < date_trunc('month', CURRENT_DATE)::date
          Example — how many applications were submitted this month:
            SELECT COUNT(*) AS count
            FROM applications
            WHERE submitted >= date_trunc('month', CURRENT_DATE)::date
              AND submitted < (date_trunc('month', CURRENT_DATE) + interval '1 month')::date
            LIMIT 200
          Example — last month (note the parentheses around date_trunc − interval):
            SELECT COUNT(*) AS count
            FROM applications
            WHERE submitted >= (date_trunc('month', CURRENT_DATE) - interval '1 month')::date
              AND submitted < date_trunc('month', CURRENT_DATE)::date
            LIMIT 200
          Same patterns apply to `decision_date` when the user asks about decisions in a period.
        - Suburb names are stored uppercase; use ILIKE for case-insensitive suburb matching.
          When ranking / grouping by suburb, always exclude missing values:
          `l.suburb IS NOT NULL AND btrim(l.suburb) <> ''`.
          Always SELECT the aggregate (e.g. `COUNT(*) AS application_count`) alongside `l.suburb`.
          Example — NSW suburb with the most applications:
            SELECT l.suburb, COUNT(*) AS application_count
            FROM locations l
            JOIN application_locations al ON al.location_id = l.id
            JOIN applications a ON a.id = al.application_id
            JOIN authorities auth ON auth.id = a.authority_id
            WHERE auth.state = 'NSW' AND auth.amalgamated IS NULL
              AND l.suburb IS NOT NULL AND btrim(l.suburb) <> ''
            GROUP BY l.suburb
            ORDER BY application_count DESC
            LIMIT 10
        - For "how many ... per X" use COUNT(*) with GROUP BY X (do not use DISTINCT joins).
          Always include the COUNT (or other aggregate) in the SELECT list.
        - Always include a LIMIT (at most 200).
        - Put the query in "sql" and a one-sentence description in "explanation".
        PROMPT;
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        $messages = [];

        foreach ($this->history as $row) {
            $role = $row['role'] ?? '';
            $content = (string) ($row['content'] ?? '');
            $sql = $row['sql'] ?? null;

            if ($role === 'user') {
                $messages[] = new UserMessage($content);
            } elseif ($role === 'assistant') {
                $text = $content;
                if (is_string($sql) && $sql !== '') {
                    $text .= "\nPrevious SQL: ".$sql;
                }
                $payload = $row['payload'] ?? null;
                if (is_array($payload) && ! empty($payload['rows']) && is_array($payload['rows'])) {
                    $sample = json_encode(array_slice($payload['rows'], 0, 3), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (is_string($sample)) {
                        $text .= "\nPrevious rows sample: ".$sample;
                    }
                }
                $messages[] = new AssistantMessage($text);
            }
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->required(),
            'explanation' => $schema->string()->required(),
        ];
    }
}
