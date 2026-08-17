@props([
    'title',
    'subtitle' => null,
    'actionUrl' => null,
    'actionLabel' => null,
    'preheader' => null,
    'accent' => '#4338ca',
])

@php $logo = \App\Support\Brand::mailUrl(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
    <style>
        /* Inlined styles do the real work; this only handles what inline
           cannot: the small-screen breakpoint. */
        @media only screen and (max-width: 620px) {
            .sm-full { width: 100% !important; }
            .sm-px { padding-left: 20px !important; padding-right: 20px !important; }
            .sm-stack { display: block !important; width: 100% !important; text-align: left !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f2f1f4;-webkit-font-smoothing:antialiased;">
    {{-- The preview line in the inbox, before anyone opens anything. --}}
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2f1f4;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="sm-full" style="width:600px;max-width:600px;">

                    {{-- Brand --}}
                    <tr>
                        <td style="padding:0 0 20px;">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="{{ \App\Support\Brand::name() }}" style="height:26px;max-width:170px;display:block;border:0;">
                            @else
                                <span style="font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;color:#16151a;letter-spacing:-0.01em;">
                                    {{ \App\Support\Brand::name() }}
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #e6e4ea;border-radius:14px;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                {{-- A single accent rule at the top, carrying the urgency. --}}
                                <tr><td style="height:3px;background-color:{{ $accent }};line-height:3px;font-size:0;">&nbsp;</td></tr>

                                <tr>
                                    <td class="sm-px" style="padding:28px 32px 0;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
                                        <h1 style="margin:0;font-size:22px;line-height:1.3;letter-spacing:-0.02em;font-weight:700;color:#16151a;">
                                            {{ $title }}
                                        </h1>
                                        @if ($subtitle)
                                            <p style="margin:8px 0 0;font-size:15px;line-height:1.5;color:#5c5966;">{{ $subtitle }}</p>
                                        @endif
                                    </td>
                                </tr>

                                <tr>
                                    <td class="sm-px" style="padding:22px 32px 0;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#2f2d35;">
                                        {{ $slot }}
                                    </td>
                                </tr>

                                @if ($actionUrl)
                                    <tr>
                                        <td class="sm-px" style="padding:26px 32px 0;">
                                            {{-- Bulletproof-ish button: padded anchor, no VML, works
                                                 everywhere that matters and degrades to a link. --}}
                                            <a href="{{ $actionUrl }}"
                                               style="display:inline-block;background-color:{{ $accent }};color:#ffffff;text-decoration:none;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:500;line-height:1;padding:14px 24px;border-radius:10px;">
                                                {{ $actionLabel ?? 'Open the app' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif

                                <tr><td style="padding:28px 32px 0;"><div style="height:1px;background-color:#edecef;line-height:1px;font-size:0;">&nbsp;</div></td></tr>

                                <tr>
                                    <td class="sm-px" style="padding:16px 32px 26px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
                                        <p style="margin:0;font-size:12px;line-height:1.55;color:#8d8a97;">
                                            {{ $footer ?? 'Sent by '.\App\Support\Brand::name().'.' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 8px 0;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
                            <p style="margin:0;font-size:11px;line-height:1.5;color:#a9a6b2;">
                                {{ \App\Support\Brand::name() }} · Asia/Kolkata
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
