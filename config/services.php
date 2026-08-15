<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
        'key' => env('POSTMARK_TOKEN', env('POSTMARK_API_KEY')),
        'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID', 'outbound'),
    ],

    /*
     * A dead-man's-switch ping, hit at the end of every successful scheduled
     * run. If the host's cron stops firing, nothing inside this application
     * can tell anyone — only the absence of this ping will.
     */
    'healthcheck' => [
        'url' => env('HEALTHCHECK_URL'),
    ],

    /*
     * WhatsApp Cloud API. Blank credentials make the channel a clean no-op,
     * so WhatsApp stays optional without any conditionals at the call site.
     */
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'template' => env('WHATSAPP_TEMPLATE_NAME', 'renewal_reminder'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
