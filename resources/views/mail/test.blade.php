<x-mail.layout
    title="Mail is working"
    subtitle="This is a test message. Nothing needs doing."
    preheader="Your SMTP settings are correct — reminders will reach this inbox."
    accent="#12694f"
>
    <p style="margin:0 0 18px;">
        {{ $sentBy }} sent this from the settings screen to confirm the connection.
        Renewal reminders and digests will arrive the same way.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #edecef;border-radius:10px;">
        @foreach ([
            'Server' => $host ?: 'not set',
            'Port' => $port ?: 'not set',
            'From' => $from ?: 'not set',
            'Sent' => now()->format('j M Y, g:i a'),
        ] as $label => $value)
            <tr>
                <td style="padding:11px 16px;font-size:13px;color:#8d8a97;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">{{ $label }}</td>
                <td align="right" style="padding:11px 16px;font-size:13px;color:#2f2d35;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <x-slot:footer>
        If you were not expecting this, somebody with admin access pressed "Send a test".
    </x-slot:footer>
</x-mail.layout>
