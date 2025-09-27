<x-mail::message>
# {{ $title }}

@if ($data && count($data))
<x-mail::table>
| Website           | Avg response | Downtimes | Availability |
| :---------------- | :----------: | :-------: | -----------: |
@foreach ($data as $row)
	@if ($row->website->active && $row->attempts > 0)
| <a href="{{ $row->website->url }}" target="_blank"><strong>{{ $row->website->name }}</strong></a> | {{ $row->avg_duration }}ms | {{ $row->downtimes_count }} | <span style="color: {!! $row->availability == 100 ? '#22c55e' : '#ce8847ff' !!};"><strong>{{ $row->availability }}%</strong></span> |
	@endif
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="'https://webmonitor.pa2lo.net/'">
Open Webmonitor
</x-mail::button>

Best regards,<br>
{{ config('app.name') }}
</x-mail::message>
