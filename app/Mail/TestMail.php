<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Brand;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately not queued. "Send a test" has to fail in front of the person
 * who pressed it, not silently into a failed-jobs table an hour later.
 */
class TestMail extends Mailable
{
    public function __construct(public string $sentBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Brand::name().' — mail is working');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
            text: 'mail.test-text',
            with: [
                'sentBy' => $this->sentBy,
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ],
        );
    }
}
