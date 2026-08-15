<x-mail.layout
    title="Renewals due in the next 45 days"
    :subtitle="$total.' '.\Illuminate\Support\Str::plural('renewal', $total).' · '.($cost > 0 ? '₹'.number_format($cost) : 'no recorded cost')"
    :actionUrl="$actionUrl"
    actionLabel="Open assets"
>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#2f2d35;">
        Hello {{ $greetingName }},
    </p>

    @if ($overdue > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;background:#fdeceb;border-radius:9px;">
            <tr>
                <td style="padding:12px 14px;font-size:14px;color:#b4231f;">
                    <strong>{{ $overdue }} {{ \Illuminate\Support\Str::plural('asset', $overdue) }}</strong>
                    already past the expiry date and not marked renewed.
                </td>
            </tr>
        </table>
    @endif

    @forelse ($weeks as $label => $assets)
        <p style="margin:0 0 8px;font-size:13px;font-weight:500;color:#8d8a97;text-transform:uppercase;letter-spacing:0.03em;">
            {{ $label }}
        </p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;border:1px solid #edecef;border-radius:9px;">
            @foreach ($assets as $asset)
                <tr>
                    <td style="padding:10px 14px;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">
                        <div style="font-size:14px;color:#16151a;">{{ $asset->name }}</div>
                        <div style="font-size:12px;color:#8d8a97;margin-top:2px;">
                            {{ $asset->type->label() }} · {{ $asset->client->displayName() }}
                        </div>
                    </td>
                    <td align="right" style="padding:10px 14px;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};white-space:nowrap;">
                        <div style="font-size:13px;color:#2f2d35;">{{ $asset->expires_at->format('D j M') }}</div>
                        @if ($asset->cost)
                            <div style="font-size:12px;color:#8d8a97;margin-top:2px;">{{ $asset->currency }} {{ number_format((float) $asset->cost) }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @empty
        <p style="margin:0 0 12px;font-size:15px;line-height:1.55;color:#2f2d35;">
            Nothing falls due in the next 45 days. Everything on the books is paid up.
        </p>
    @endforelse
</x-mail.layout>
