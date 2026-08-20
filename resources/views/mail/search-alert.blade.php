<x-mail::message>
# New applications in your saved searches

Hi {{ $user->name ?: 'there' }},

{{ $total === 1 ? '1 application was added to IMBY that matches your saved searches.' : $total.' applications were added to IMBY that match your saved searches.' }}

@foreach ($searches as $search)
## {{ $search['name'] }}

@foreach ($search['applications'] as $application)
- @if (! empty($application['url']))[{{ $application['headline'] }}]({{ $application['url'] }})@else{{ $application['headline'] }}@endif

@endforeach
@if ($search['omitted'] > 0)
_{{ $search['omitted'] }} more in IMBY._

@endif
@endforeach

Thanks,
{{ config('app.name') }}
</x-mail::message>
