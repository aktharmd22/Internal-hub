Mail is working.

{{ $sentBy }} sent this from the settings screen to confirm the connection.
Renewal reminders and digests will arrive the same way.

Server: {{ $host ?: 'not set' }}
Port:   {{ $port ?: 'not set' }}
From:   {{ $from ?: 'not set' }}
Sent:   {{ now()->format('j M Y, g:i a') }}

--
{{ \App\Support\Brand::name() }}
