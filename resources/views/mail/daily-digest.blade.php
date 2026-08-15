@php
    $section = function ($title, $accent) {
        return "margin:0 0 8px;font-size:13px;font-weight:500;color:{$accent};text-transform:uppercase;letter-spacing:0.03em;";
    };
@endphp

<x-mail.layout
    title="Your day"
    :subtitle="now()->format('l, j F')"
    :actionUrl="$actionUrl"
    actionLabel="Open the dashboard"
>
    <p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#2f2d35;">
        Hello {{ $greetingName }},
    </p>

    @foreach ([
        ['Overdue', $overdue, '#b4231f'],
        ['Due today', $dueToday, '#9a5b06'],
        ['Waiting on your review', $awaitingReview, '#4338ca'],
    ] as [$heading, $tasks, $accent])
        @if ($tasks->isNotEmpty())
            <p style="{{ $section($heading, $accent) }}">{{ $heading }} · {{ $tasks->count() }}</p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;border:1px solid #edecef;border-radius:9px;">
                @foreach ($tasks as $task)
                    <tr>
                        <td style="padding:10px 14px;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">
                            <div style="font-size:14px;color:#16151a;">{{ $task->title }}</div>
                            <div style="font-size:12px;color:#8d8a97;margin-top:2px;">
                                {{ $task->reference }}@if ($task->client) · {{ $task->client->displayName() }}@endif
                                @if ($task->due_at) · {{ $task->dueLabel() }}@endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    @if ($expiringSoon->isNotEmpty())
        <p style="{{ $section('Expiring this week', '#b4231f') }}">Expiring this week · {{ $expiringSoon->count() }}</p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;border:1px solid #edecef;border-radius:9px;">
            @foreach ($expiringSoon as $asset)
                <tr>
                    <td style="padding:10px 14px;border-bottom:{{ $loop->last ? 'none' : '1px solid #edecef' }};">
                        <div style="font-size:14px;color:#16151a;">{{ $asset->name }}</div>
                        <div style="font-size:12px;color:#8d8a97;margin-top:2px;">
                            {{ $asset->type->label() }} · {{ $asset->client->displayName() }} · {{ $asset->urgencyLabel() }}
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($completedYesterday->isNotEmpty())
        <p style="{{ $section('Closed yesterday', '#12694f') }}">Closed yesterday · {{ $completedYesterday->count() }}</p>
        <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#5c5966;">
            {{ $completedYesterday->pluck('title')->implode(' · ') }}
        </p>
    @endif

    @if ($overdue->isEmpty() && $dueToday->isEmpty() && $awaitingReview->isEmpty() && $expiringSoon->isEmpty())
        <p style="margin:0;font-size:15px;line-height:1.55;color:#2f2d35;">
            Nothing is due, overdue or waiting on you today.
        </p>
    @endif
</x-mail.layout>
