@php
    $accent = match (true) {
        $daysRemaining <= 2 => '#b4231f',
        $daysRemaining <= 5 => '#9a5b06',
        default => '#4338ca',
    };

    $chipBg = match (true) {
        $daysRemaining <= 2 => '#fdeceb',
        $daysRemaining <= 5 => '#fdf1dd',
        default => '#eeecfb',
    };

    $urgency = match (true) {
        $daysRemaining < -1 => abs($daysRemaining).' days overdue',
        $daysRemaining === -1 => 'A day overdue',
        $daysRemaining === 0 => 'Expires today',
        $daysRemaining === 1 => 'Expires tomorrow',
        default => $daysRemaining.' days left',
    };
@endphp

<x-mail.layout
    :title="$asset->name"
    :subtitle="$asset->type->label().' for '.$asset->client->displayName()"
    :accent="$accent"
    :actionUrl="$actionUrl"
    actionLabel="Open this asset"
    :preheader="$urgency.' — '.$asset->type->label().' for '.$asset->client->displayName().', due '.$asset->expires_at->format('j M Y').'.'"
>
    {{-- The one thing this email exists to say, before anything else. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:{{ $chipBg }};border-radius:999px;padding:7px 14px;font-size:13px;font-weight:500;color:{{ $accent }};">
                @if ($isEscalation && $daysRemaining >= 0)
                    Escalated · {{ $urgency }}
                @else
                    {{ $urgency }}
                @endif
            </td>
        </tr>
    </table>

    <p style="margin:0 0 6px;">Hello {{ $greetingName }},</p>

    <p style="margin:0 0 20px;">
        @if ($daysRemaining < 0)
            This lapsed on <strong style="color:#16151a;">{{ $asset->expires_at->format('l, j F Y') }}</strong> and has not been marked renewed.
        @else
            This is due on <strong style="color:#16151a;">{{ $asset->expires_at->format('l, j F Y') }}</strong>.
        @endif
        @if ($asset->auto_renew)
            Auto-renew is on, so confirm the payment went through rather than renewing by hand.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #edecef;border-radius:10px;">
        @foreach ([
            'Provider' => $asset->provider ?: '—',
            'Account' => $asset->provider_account ?: '—',
            'Cost' => $asset->cost ? $asset->currency.' '.number_format((float) $asset->cost, 2) : '—',
            'Owner' => $asset->owner?->name ?: 'Unassigned',
        ] as $label => $value)
            <tr>
                <td style="padding:11px 16px;font-size:13px;color:#8d8a97;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">{{ $label }}</td>
                <td align="right" style="padding:11px 16px;font-size:13px;color:#2f2d35;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <x-slot:footer>
        Sent because you own this asset, manage the account, or are on the notification list.
        Reminder rules live in the app under Settings.
    </x-slot:footer>
</x-mail.layout>
