<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\VerificationStatus;
use App\Livewire\Assets\Import;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ReminderLog;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use App\Services\Import\AssetCsvParser;
use App\Services\Verification\AssetVerifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->admin()->create();
});

function csvFile(string $contents)
{
    // Livewire's upload testing needs Illuminate\Http\Testing\File, which
    // carries the `name` property the temporary-upload flow reads.
    return UploadedFile::fake()->createWithContent('assets.csv', $contents);
}

/* ------------------------------------------------------------------- CSV */

test('the template lists every column the parser reads', function () {
    $template = AssetCsvParser::template();
    $header = explode("\n", $template)[0];

    expect(explode(',', $header))->toBe(AssetCsvParser::COLUMNS);
});

test('a clean csv previews without errors and imports', function () {
    Client::factory()->create(['company_name' => 'Kanchi Silks', 'name' => 'Ravi']);

    $csv = implode(',', AssetCsvParser::COLUMNS)."\n"
        ."Kanchi Silks,domain,kanchisilks.test,kanchisilks.test,GoDaddy,ACC-1,2027-03-14,2020-03-14,1200,INR,yearly,no,,\n";

    $component = Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('file', csvFile($csv))
        ->call('previewCsv')
        ->assertHasNoErrors();

    expect($component->get('preview'))->toHaveCount(1)
        ->and($component->get('preview')[0]['errors'])->toBe([])
        ->and($component->get('preview')[0]['skip'])->toBeFalse();

    $component->call('commit');

    $asset = Asset::first();

    expect($asset->name)->toBe('kanchisilks.test')
        ->and($asset->type)->toBe(AssetType::Domain)
        ->and($asset->expires_at->toDateString())->toBe('2027-03-14')
        ->and((float) $asset->cost)->toBe(1200.0);
});

test('a bad row is reported per line rather than failing the whole file', function () {
    Client::factory()->create(['company_name' => 'Kanchi Silks', 'name' => 'Ravi']);

    $csv = implode(',', AssetCsvParser::COLUMNS)."\n"
        ."Kanchi Silks,domain,good.test,good.test,GoDaddy,,2027-03-14,,1200,INR,yearly,no,,\n"
        ."Kanchi Silks,notatype,bad.test,bad.test,GoDaddy,,2027-03-14,,1200,INR,yearly,no,,\n"
        ."Kanchi Silks,domain,nodate.test,nodate.test,GoDaddy,,,,1200,INR,yearly,no,,\n";

    $component = Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('file', csvFile($csv))
        ->call('previewCsv');

    $preview = $component->get('preview');

    expect($preview)->toHaveCount(3)
        ->and($preview[0]['errors'])->toBe([])
        ->and($preview[1]['errors'][0])->toContain('Type must be one of')
        ->and($preview[2]['errors'][0])->toContain('Expiry date');

    $component->call('commit');

    // Only the clean row lands.
    expect(Asset::count())->toBe(1)
        ->and(Asset::first()->name)->toBe('good.test');
});

test('a duplicate identifier on the same client is flagged and skipped by default', function () {
    $client = Client::factory()->create(['company_name' => 'Kanchi Silks', 'name' => 'Ravi']);
    Asset::factory()->create(['client_id' => $client->id, 'identifier' => 'kanchisilks.test']);

    $csv = implode(',', AssetCsvParser::COLUMNS)."\n"
        ."Kanchi Silks,domain,kanchisilks.test,kanchisilks.test,GoDaddy,,2027-03-14,,1200,INR,yearly,no,,\n";

    $component = Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('file', csvFile($csv))
        ->call('previewCsv');

    expect($component->get('preview')[0]['duplicate'])->toBeTrue()
        ->and($component->get('preview')[0]['skip'])->toBeTrue();

    $component->call('commit');

    expect(Asset::count())->toBe(1);
});

test('a client that does not exist yet is created, and flagged as new in the preview', function () {
    $csv = implode(',', AssetCsvParser::COLUMNS)."\n"
        ."Brand New Client,domain,brandnew.test,brandnew.test,GoDaddy,,2027-03-14,,900,INR,yearly,no,,\n";

    Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('file', csvFile($csv))
        ->call('previewCsv')
        ->assertSet('preview.0.newClient', true)
        ->call('commit');

    expect(Client::where('name', 'Brand New Client')->exists())->toBeTrue()
        ->and(Asset::count())->toBe(1);
});

test('indian d/m/Y dates are read correctly', function () {
    Client::factory()->create(['company_name' => 'Kanchi Silks', 'name' => 'Ravi']);

    $csv = implode(',', AssetCsvParser::COLUMNS)."\n"
        ."Kanchi Silks,domain,dmy.test,dmy.test,GoDaddy,,14/03/2027,,1200,INR,yearly,no,,\n";

    Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('file', csvFile($csv))
        ->call('previewCsv')
        ->call('commit');

    expect(Asset::first()->expires_at->toDateString())->toBe('2027-03-14');
});

test('an employee cannot reach the import screen', function () {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee)->get(route('assets.import'))->assertForbidden();
});

/* ------------------------------------------------------- paste a list */

test('pasted domains are normalised and looked up', function () {
    $client = Client::factory()->create();

    Http::fake([
        'rdap.org/*' => Http::response([
            'events' => [['eventAction' => 'expiration', 'eventDate' => '2027-06-30T00:00:00Z']],
        ]),
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('mode', 'paste')
        ->set('pasteClientId', $client->id)
        ->set('pasted', "https://www.kanchisilks.test/\ntvmlogistics.test\nkanchisilks.test\nnotadomain")
        ->call('previewPaste');

    $preview = collect($component->get('preview'));

    // www stripped, scheme stripped, duplicate collapsed, junk dropped.
    expect($preview->pluck('attributes.identifier')->all())
        ->toBe(['kanchisilks.test', 'tvmlogistics.test'])
        ->and($preview[0]['attributes']['expires_at'])->toBe('2027-06-30');

    $component->call('commit');

    expect(Asset::count())->toBe(2)
        ->and(Asset::first()->type)->toBe(AssetType::Domain);
});

test('a domain with no lookup result still imports with a date to fix later', function () {
    $client = Client::factory()->create();

    Http::fake(['rdap.org/*' => Http::response([], 404)]);

    Livewire::actingAs($this->admin)
        ->test(Import::class)
        ->set('mode', 'paste')
        ->set('pasteClientId', $client->id)
        ->set('pasted', 'unknown.test')
        ->call('previewPaste')
        ->call('commit');

    // Never silently dropped out of the reminder window.
    expect(Asset::first()->expires_at)->not->toBeNull();
});

/* -------------------------------------------------------- verification */

test('a matching registry date marks the asset verified', function () {
    $asset = Asset::factory()->domain()->create([
        'identifier' => 'kanchisilks.test',
        'expires_at' => '2027-03-14',
    ]);

    Http::fake([
        'rdap.org/*' => Http::response([
            'events' => [['eventAction' => 'expiration', 'eventDate' => '2027-03-14T00:00:00Z']],
        ]),
    ]);

    app(AssetVerifier::class)->verify($asset);

    expect($asset->fresh()->verification_status)->toBe(VerificationStatus::Match);
});

/*
 * The point of verification: nobody has to remember to update the record after
 * paying the registrar. The next run notices and closes the loop.
 */
test('a later registry date is treated as a renewal', function () {
    $asset = Asset::factory()->domain()->create([
        'identifier' => 'kanchisilks.test',
        'expires_at' => now()->addDays(5)->toDateString(),
        'owner_id' => $this->admin->id,
    ]);

    $task = Task::createRenewalTask($asset->load('client'), $this->admin->id);

    $asset->reminderLogs()->create([
        'days_before' => 5,
        'channel' => 'mail',
        'recipient_type' => 'user',
        'recipient_id' => $this->admin->id,
        'sent_at' => now(),
        'status' => 'sent',
    ]);

    $newDate = now()->addYear()->startOfDay();

    Http::fake([
        'rdap.org/*' => Http::response([
            'events' => [['eventAction' => 'expiration', 'eventDate' => $newDate->toIso8601String()]],
        ]),
    ]);

    app(AssetVerifier::class)->verify($asset);

    $asset->refresh();

    expect($asset->expires_at->toDateString())->toBe($newDate->toDateString())
        ->and($asset->status)->toBe(AssetStatus::Active)
        // The new cycle starts with a clean idempotency slate.
        ->and(ReminderLog::where('asset_id', $asset->id)->count())->toBe(0);

    $message = TaskMessage::where('task_id', $task->id)->latest('id')->first();

    expect($message->body)->toContain('Renewal appears to be done');
});

test('a failed lookup is recorded and does not break anything', function () {
    $asset = Asset::factory()->domain()->create(['identifier' => 'nope.test']);

    Http::fake(['rdap.org/*' => Http::response([], 404)]);

    $result = app(AssetVerifier::class)->verify($asset);

    expect($result->ok)->toBeFalse()
        ->and($asset->fresh()->verification_status)->toBe(VerificationStatus::Failed)
        ->and($asset->fresh()->expires_at)->not->toBeNull();
});

test('the verify command survives a lookup that throws', function () {
    Asset::factory()->domain()->count(2)->create();

    Http::fake(fn () => throw new RuntimeException('network down'));

    $this->artisan('assets:verify-expiry')->assertSuccessful();
});

test('only domains and certificates are verifiable', function () {
    $verifier = app(AssetVerifier::class);

    expect($verifier->supports(Asset::factory()->domain()->create()))->toBeTrue()
        ->and($verifier->supports(Asset::factory()->create(['type' => AssetType::Licence])))->toBeFalse();
});
