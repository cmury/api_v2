<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = Illuminate\Support\Facades\DB::connection(config('database.data_connection', 'data'));

echo "Submitted date coverage for MP-like apps:\n";
$row = $c->selectOne("
  select
    count(*) as n,
    count(submitted) as with_submitted,
    max(submitted) as max_submitted,
    min(submitted) filter (where submitted is not null) as min_submitted
  from applications a
  where a.type ilike '%significant%'
     or a.type ilike '%major project%'
     or a.portal_no ilike 'SSD-%'
     or a.portal_no ilike 'SSI-%'
     or a.portal_no ilike 'MP-%'
");
echo "  total={$row->n} with_submitted={$row->with_submitted} max={$row->max_submitted} min={$row->min_submitted}\n";

echo "\nMost recent WITH submitted date:\n";
foreach ($c->select("
  select a.id, a.portal_no, a.type, a.submitted,
         l.formatted_address, ST_Y(l.geom::geometry) as lat, ST_X(l.geom::geometry) as lng
  from applications a
  left join application_locations al on al.application_id = a.id
  left join locations l on l.id = al.location_id
  where a.submitted is not null
    and (
      a.type ilike '%significant%'
      or a.type ilike '%major project%'
      or a.portal_no ilike 'SSD-%'
      or a.portal_no ilike 'SSI-%'
      or a.portal_no ilike 'MP-%'
    )
  order by a.submitted desc
  limit 15
") as $r) {
    echo "  {$r->submitted} | {$r->portal_no} | {$r->type} | {$r->lat},{$r->lng} | {$r->formatted_address} | id={$r->id}\n";
}

echo "\nMost recently created (by id desc) with geom:\n";
foreach ($c->select("
  select a.id, a.portal_no, a.type, a.submitted, a.created_at,
         l.formatted_address, ST_Y(l.geom::geometry) as lat, ST_X(l.geom::geometry) as lng
  from applications a
  join application_locations al on al.application_id = a.id
  join locations l on l.id = al.location_id
  where l.geom is not null
    and (
      a.type ilike '%significant%'
      or a.type ilike '%major project%'
      or a.portal_no ilike 'SSD-%'
      or a.portal_no ilike 'SSI-%'
      or a.portal_no ilike 'MP-%'
    )
  order by a.id desc
  limit 10
") as $r) {
    echo "  id={$r->id} created={$r->created_at} submitted={$r->submitted} | {$r->portal_no} | {$r->type} | {$r->lat},{$r->lng} | {$r->formatted_address}\n";
}

echo "\napplication_types class_id for MP types:\n";
foreach ($c->select("
  select id, name, application_class_id
  from application_types
  where name ilike '%significant%'
     or name ilike '%major project%'
     or name ilike 'SSD%'
     or name ilike 'SSI%'
  order by id
") as $r) {
    $cid = $r->application_class_id ?? 'null';
    echo "  {$r->id} {$r->name} class={$cid}\n";
}
