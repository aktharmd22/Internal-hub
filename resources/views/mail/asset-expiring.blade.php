@php
    $urgent = $daysRemaining <= 2;
    $accent = $urgent ? '#b4231f' : ($daysRemaining <= 5 ? '#9a5b06' : '#4338ca');
    $chipBg = $urgent ? '#fdeceb' : ($daysRemaining <= 5 ? '#fdf1dd' : '#eeecfb');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $asset->name }}</title>
</head>
<body style="margin:0;padding:0;background:#f7f6f8;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $asset->type->label() }} for {{ $asset->client->displayName() }}, due {{ $asset->expires_at->format('j M Y') }}.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border:1px solid #edecef;border-radius:12px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#16151a;">
                    <tr>
                        <td style="padding:24px 24px 0;">
                            <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:{{ $chipBg }};color:{{ $accent }};font-size:12px;font-weight:500;">
                                @if ($isEscalation && $daysRemaining >= 0)
                                    Escalated · {{ $daysRemaining }} {{ \Illuminate\Support\Str::plural('day', $daysRemaining) }} left
                                @elseif ($daysRemaining < 0)
                                    {{ abs($daysRemaining) }} {{ \Illuminate\Support\Str::plural('day', abs($daysRemaining)) }} overdue
                                @elseif ($daysRemaining === 0)
                                    Expires today
                                @else
                                    {{ $daysRemaining }} {{ \Illuminate\Support\Str::plural('day', $daysRemaining) }} left
                                @endif
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 24px 0;">
                            <h1 style="margin:0;font-size:22px;line-height:1.25;letter-spacing:-0.02em;font-weight:700;color:#16151a;">
                                {{ $asset->name }}
                            </h1>
                            <p style="margin:6px 0 0;font-size:14px;color:#5c5966;">
                                {{ $asset->type->label() }} · {{ $asset->client->displayName() }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 24px 0;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#2f2d35;">
                                Hello {{ $greetingName }},
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.55;color:#2f2d35;">
                                @if ($daysRemaining < 0)
                                    This lapsed on <strong>{{ $asset->expires_at->format('l, j F Y') }}</strong> and has not been renewed.
                                @else
                                    This is due on <strong>{{ $asset->expires_at->format('l, j F Y') }}</strong>.
                                @endif
                                @if ($asset->auto_renew)
                                    Auto-renew is on, so confirm the payment went through rather than renewing by hand.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 24px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #edecef;border-radius:9px;">
                                @foreach ([
                                    'Provider' => $asset->provider ?: '—',
                                    'Account' => $asset->provider_account ?: '—',
                                    'Cost' => $asset->cost ? $asset->currency.' '.number_format((float) $asset->cost, 2) : '—',
                                    'Owner' => $asset->owner?->name ?: 'Unassigned',
                                ] as $label => $value)
                                    <tr>
                                        <td style="padding:9px 14px;font-size:13px;color:#8d8a97;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">{{ $label }}</td>
                                        <td align="right" style="padding:9px 14px;font-size:13px;color:#2f2d35;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 24px 26px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;background:#4338ca;color:#ffffff;text-decoration:none;font-size:15px;font-weight:500;padding:12px 20px;border-radius:9px;">
                                Open this asset
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px 24px;border-top:1px solid #edecef;">
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#8d8a97;">
                                Sent by {{ config('app.name') }} because you own this asset or manage the account.
                                Reminder settings live in the app.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
