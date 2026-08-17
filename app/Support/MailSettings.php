<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Applies mail credentials stored in the database over the .env defaults.
 *
 * On shared hosting the person who needs to change the SMTP password is not
 * the person with SSH access to edit .env, so these live in Settings. Anything
 * left blank falls through to the environment, which keeps a working .env
 * config working and makes the database purely an override.
 */
final class MailSettings
{
    /** @return list<string> */
    public static function keys(): array
    {
        return [
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
        ];
    }

    public static function apply(): void
    {
        // Runs on every request, including before the table exists during a
        // first `migrate`, so failure here must never take the app down.
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = Setting::all();
        } catch (\Throwable) {
            return;
        }

        if (filled($settings['mail_host'] ?? null)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $settings['mail_host'],
                'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? 587),
                'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
                'mail.mailers.smtp.password' => $settings['mail_password'] ?? null,
                // Laravel 11+ reads `scheme`; "smtps" is what turns on implicit
                // TLS on port 465. STARTTLS on 587 needs no scheme at all.
                'mail.mailers.smtp.scheme' => ($settings['mail_encryption'] ?? null) === 'ssl' ? 'smtps' : null,
            ]);
        }

        if (filled($settings['mail_from_address'] ?? null)) {
            config(['mail.from.address' => $settings['mail_from_address']]);
        }

        if (filled($settings['mail_from_name'] ?? null)) {
            config(['mail.from.name' => $settings['mail_from_name']]);
        }
    }

    /**
     * Extra addresses that receive every reminder and digest, on top of the
     * people the rules resolve to.
     *
     * @return list<string>
     */
    public static function extraRecipients(): array
    {
        return self::parse(Setting::get('notification_recipients'));
    }

    /**
     * Accepts commas, semicolons or newlines, because that is how a list of
     * addresses actually gets pasted in.
     *
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        if (blank($raw)) {
            return [];
        }

        return collect(preg_split('/[,;\r\n]+/', $raw))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A stable positive integer for an address, so `reminder_logs` can key on
     * it exactly as it does for a user id and the unique index still prevents
     * duplicate sends.
     */
    public static function recipientId(string $email): int
    {
        return (int) hexdec(substr(md5(strtolower(trim($email))), 0, 12));
    }

    public static function isConfigured(): bool
    {
        return filled(Setting::get('mail_host', config('mail.mailers.smtp.host')));
    }
}
