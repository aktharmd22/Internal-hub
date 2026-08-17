@props(['title', 'subtitle' => null, 'actionUrl' => null, 'actionLabel' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f7f6f8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #edecef;border-radius:12px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#16151a;">
                    @if ($logo = \App\Support\Brand::mailUrl())
                        <tr>
                            <td style="padding:24px 24px 0;">
                                {{-- Height only: a wide logo must not be squashed into a square. --}}
                                <img src="{{ $logo }}" alt="{{ \App\Support\Brand::name() }}" style="height:28px;max-width:180px;display:block;">
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:{{ isset($logo) && $logo ? '16px' : '24px' }} 24px 0;">
                            <h1 style="margin:0;font-size:20px;line-height:1.3;letter-spacing:-0.01em;font-weight:700;">{{ $title }}</h1>
                            @if ($subtitle)
                                <p style="margin:6px 0 0;font-size:14px;color:#5c5966;">{{ $subtitle }}</p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 24px 0;">
                            {{ $slot }}
                        </td>
                    </tr>

                    @if ($actionUrl)
                        <tr>
                            <td style="padding:22px 24px 26px;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;background:#4338ca;color:#ffffff;text-decoration:none;font-size:15px;font-weight:500;padding:12px 20px;border-radius:9px;">
                                    {{ $actionLabel ?? 'Open the app' }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 24px 24px;border-top:1px solid #edecef;">
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#8d8a97;">
                                Sent by {{ config('app.name') }}. Digest settings live in the app.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
