Hello {{ $greetingName }},

{{ $asset->name }} — {{ $asset->type->label() }} for {{ $asset->client->displayName() }}

@if ($daysRemaining < 0)
This lapsed on {{ $asset->expires_at->format('l, j F Y') }} and has not been renewed.
@elseif ($daysRemaining === 0)
This expires today, {{ $asset->expires_at->format('l, j F Y') }}.
@else
This is due on {{ $asset->expires_at->format('l, j F Y') }} — {{ $daysRemaining }} {{ \Illuminate\Support\Str::plural('day', $daysRemaining) }} from now.
@endif

Provider: {{ $asset->provider ?: 'not recorded' }}
Account:  {{ $asset->provider_account ?: 'not recorded' }}
Cost:     {{ $asset->cost ? $asset->currency.' '.number_format((float) $asset->cost, 2) : 'not recorded' }}
Owner:    {{ $asset->owner?->name ?: 'Unassigned' }}

Open this asset:
{{ $actionUrl }}

--
Sent by {{ config('app.name') }} because you own this asset or manage the account.
