<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = Illuminate\Support\Facades\DB::connection(config('database.data_connection', 'data'));

echo "Major Projects class (id=12) type linkage:\n";
foreach ($c->select('
  select at.id, at.name, at.application_class_id, count(aat.application_id) as n
  from application_types at
  left join application_application_types aat on aat.application_type_id = at.id
  where at.application_class_id = 12
     or at.name ilike \'%significant%\'
     or at.name ilike \'%major project%\'
     or at.name ilike \'%ssd%\'
     or at.name ilike \'%ssi%\'
  group by at.id, at.name, at.application_class_id
  order by n desc
') as $r) {
    echo "  type {$r->id} [class={$r->application_class_id}] {$r->name}: {$r->n}\n";
}

echo "\nCounts:\n";
$row = $c->selectOne('
  select
    count(distinct a.id) as via_class,
    count(distinct a.id) filter (where l.geom is not null) as with_geom
  from applications a
  join application_application_types aat on aat.application_id = a.id
  join application_types at on at.id = aat.application_type_id
  left join application_locations al on al.application_id = a.id
  left join locations l on l.id = al.location_id
  where at.application_class_id = 12
');
echo "  via class 12: {$row->via_class} (with geom: {$row->with_geom})\n";

$row2 = $c->selectOne("
  select count(*) as n
  from applications
  where type ilike '%significant%'
     or type ilike '%major project%'
     or type ilike 'SSD%'
     or type ilike 'SSI%'
     or portal_no ilike 'SSD-%'
     or portal_no ilike 'SSI-%'
");
echo "  by type/portal heuristics: {$row2->n}\n";

echo "\nMost recent by class 12:\n";
foreach ($c->select('
  select a.id, a.portal_no, a.type, a.submitted, a.description,
         l.formatted_address, ST_Y(l.geom::geometry) as lat, ST_X(l.geom::geometry) as lng
  from applications a
  join application_application_types aat on aat.application_id = a.id
  join application_types at on at.id = aat.application_type_id and at.application_class_id = 12
  left join application_locations al on al.application_id = a.id
  left join locations l on l.id = al.location_id
  order by a.submitted desc nulls last
  limit 10
') as $r) {
    echo "  {$r->submitted} | {$r->portal_no} | {$r->type} | {$r->lat},{$r->lng} | {$r->formatted_address} | id={$r->id}\n";
}

echo "\nMost recent by type/portal heuristics:\n";
foreach ($c->select("
  select a.id, a.portal_no, a.type, a.submitted, a.description,
         l.formatted_address, ST_Y(l.geom::geometry) as lat, ST_X(l.geom::geometry) as lng,
         (
           select string_agg(distinct coalesce(at.application_class_id::text, 'null'), ',')
           from application_application_types aat
           join application_types at on at.id = aat.application_type_id
           where aat.application_id = a.id
         ) as class_ids
  from applications a
  left join application_locations al on al.application_id = a.id
  left join locations l on l.id = al.location_id
  where a.type ilike '%significant%'
     or a.type ilike '%major project%'
     or a.type ilike 'SSD%'
     or a.type ilike 'SSI%'
     or a.portal_no ilike 'SSD-%'
     or a.portal_no ilike 'SSI-%'
     or a.portal_no ilike 'MP-%'
  order by a.submitted desc nulls last
  limit 10
") as $r) {
    echo "  {$r->submitted} | {$r->portal_no} | {$r->type} | classes={$r->class_ids} | {$r->lat},{$r->lng} | {$r->formatted_address} | id={$r->id}\n";
}
