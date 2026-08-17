<?php

use App\Enums\RecipientScope;
use App\Livewire\Settings\Index as SettingsScreen;
use App\Mail\TestMail;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AssetExpiring;
use App\Services\Reminders\ReminderEngine;
use App\Support\MailSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->admin()->create();
});

/* ------------------------------------------------------------ smtp config */

test('smtp settings saved in the app override the env defaults', function () {
    config(['mail.mailers.smtp.host' => 'from-env.test']);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('mail_host', 'smtp.hostinger.com')
        ->set('mail_port', '465')
        ->set('mail_encryption', 'ssl')
        ->set('mail_username', 'alerts@gnext.com')
        ->set('mail_from_address', 'alerts@gnext.com')
        ->set('mail_from_name', 'Gnext Hub')
        ->call('saveMail')
        ->assertHasNoErrors();

    MailSettings::apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.hostinger.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        // Laravel 11+ reads `scheme`; smtps is what turns on implicit TLS.
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.from.address'))->toBe('alerts@gnext.com');
});

test('starttls leaves the scheme unset rather than forcing smtps', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('mail_host', 'smtp.test')
        ->set('mail_port', '587')
        ->set('mail_encryption', 'tls')
        ->call('saveMail');

    MailSettings::apply();

    expect(config('mail.mailers.smtp.scheme'))->toBeNull();
});

test('blank settings fall through to the environment', function () {
    config(['mail.mailers.smtp.host' => 'from-env.test']);

    MailSettings::apply();

    expect(config('mail.mailers.smtp.host'))->toBe('from-env.test');
});

test('the stored password is encrypted and never echoed back to the browser', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('mail_host', 'smtp.test')
        ->set('mail_password', 'super-secret')
        ->call('saveMail')
        ->assertSet('mail_password', '••••••••••••');

    $raw = DB::table('settings')->where('key', 'mail_password')->value('value');

    expect($raw)->not->toBe('super-secret')
        ->and(Setting::get('mail_password'))->toBe('super-secret');
});

test('leaving the masked password alone does not wipe it', function () {
    Setting::put('mail_password', 'original', secret: true);

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('mail_host', 'smtp.test')
        ->call('saveMail');

    expect(Setting::get('mail_password'))->toBe('original');
});

/* --------------------------------------------------------------- test send */

test('the test email sends to the given address', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('test_mail_to', 'ops@gnext.com')
        ->call('sendTestMail')
        ->assertHasNoErrors();

    Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('ops@gnext.com'));
});

test('the test email refuses an address that is not one', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('test_mail_to', 'not-an-email')
        ->call('sendTestMail')
        ->assertHasErrors('test_mail_to');

    Mail::assertNothingSent();
});

/*
 * A queued test that fails an hour later into a table nobody reads is not a
 * test. The error has to reach the person who pressed the button.
 */
test('a failing smtp connection reports the reason instead of failing silently', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('Connection refused'));

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('test_mail_to', 'ops@gnext.com')
        ->call('sendTestMail')
        ->assertDispatched('toast', fn ($event, $params) => str_contains($params['message'], 'Connection refused')
            && $params['tone'] === 'danger');
});

/* -------------------------------------------------------- extra recipients */

test('recipients are parsed from commas, semicolons and newlines alike', function () {
    expect(MailSettings::parse("one@test.com, two@test.com;three@test.com\nfour@test.com"))
        ->toBe(['one@test.com', 'two@test.com', 'three@test.com', 'four@test.com']);
});

test('duplicates and casing collapse to one address', function () {
    expect(MailSettings::parse('Ops@Test.com, ops@test.com'))->toBe(['ops@test.com']);
});

test('a typo in the recipient list is named rather than silently dropped', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('notification_recipients', 'good@test.com, not-an-email')
        ->call('saveMail')
        ->assertHasErrors('notification_recipients');

    expect(Setting::get('notification_recipients'))->toBeNull();
});

test('configured addresses receive a copy of every reminder', function () {
    Notification::fake();
    Http::fake();

    Setting::put('notification_recipients', 'ops@gnext.com, accounts@gnext.com');

    $owner = User::factory()->employee()->create();

    ReminderRule::factory()->create([
        'days_before' => 10,
        'channels' => ['mail', 'database'],
    ]);

    Asset::factory()->domain()->create([
        'expires_at' => now()->addDays(10)->startOfDay(),
        'owner_id' => $owner->id,
    ]);

    app(ReminderEngine::class)->run();

    // Mail only for a bare address: there is no user behind it to hold an
    // in-app notification.
    expect(ReminderLog::where('recipient_type', 'email')->count())->toBe(2)
        ->and(ReminderLog::where('recipient_type', 'email')->pluck('channel')->unique()->all())->toBe(['mail']);

    Notification::assertSentOnDemandTimes(AssetExpiring::class, 2);
});

/*
 * The whole idempotency story has to hold for these addresses too, or a shared
 * inbox gets the same reminder every morning.
 */
test('a configured address is not mailed twice however often the engine runs', function () {
    Notification::fake();
    Http::fake();

    Setting::put('notification_recipients', 'ops@gnext.com');

    ReminderRule::factory()->create(['days_before' => 10, 'channels' => ['mail']]);

    Asset::factory()->domain()->create([
        'expires_at' => now()->addDays(10)->startOfDay(),
        'owner_id' => User::factory()->employee()->create()->id,
    ]);

    $engine = app(ReminderEngine::class);
    $engine->run();
    $engine->run();
    $engine->run();

    expect(ReminderLog::where('recipient_type', 'email')->count())->toBe(1);

    Notification::assertSentOnDemandTimes(AssetExpiring::class, 1);
});

test('a client-scoped rule never copies the agency inbox', function () {
    Notification::fake();
    Http::fake();

    Setting::put('notification_recipients', 'ops@gnext.com');

    ReminderRule::factory()
        ->scope(RecipientScope::Client)
        ->create(['days_before' => 15, 'channels' => ['mail']]);

    $client = Client::factory()->billable()->create(['email' => 'client@example.com']);

    Asset::factory()->domain()->create([
        'client_id' => $client->id,
        'expires_at' => now()->addDays(15)->startOfDay(),
    ]);

    app(ReminderEngine::class)->run();

    expect(ReminderLog::where('recipient_type', 'email')->count())->toBe(0);
});

test('the address hash is stable, so the unique index keeps working', function () {
    $first = MailSettings::recipientId('ops@gnext.com');

    expect(MailSettings::recipientId('OPS@Gnext.com '))->toBe($first)
        ->and($first)->toBeGreaterThan(0)
        ->and(MailSettings::recipientId('other@gnext.com'))->not->toBe($first);
});
