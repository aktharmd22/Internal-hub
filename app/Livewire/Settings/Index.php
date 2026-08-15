<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\AssetType;
use App\Enums\RecipientScope;
use App\Enums\ReminderChannel;
use App\Models\ReminderRule;
use App\Models\Setting;
use App\Services\Healthcheck;
use App\Support\Channels;
use App\Support\Permissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Index extends Component
{
    #[Url(except: 'company')]
    public string $tab = 'company';

    public string $company_name = '';

    public string $reminder_send_time = '09:00';

    public string $healthcheck_url = '';

    public string $whatsapp_phone_number_id = '';

    public string $whatsapp_token = '';

    // Reminder rule editor
    public ?int $ruleId = null;

    public ?string $rule_asset_type = null;

    public int $rule_days_before = 10;

    public array $rule_channels = ['mail', 'database'];

    public string $rule_recipient_scope = 'owner';

    public function mount(): void
    {
        abort_unless(auth()->user()->can(Permissions::MANAGE_SETTINGS), 403);

        $this->company_name = (string) Setting::get('company_name', config('app.name'));
        $this->reminder_send_time = (string) Setting::get('reminder_send_time', '09:00');
        $this->healthcheck_url = (string) Setting::get('healthcheck_url', config('services.healthcheck.url'));
        $this->whatsapp_phone_number_id = (string) Setting::get('whatsapp_phone_number_id', '');
        $this->whatsapp_token = Setting::get('whatsapp_token') ? '••••••••••••' : '';
    }

    public function saveCompany(): void
    {
        $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'reminder_send_time' => ['required', 'date_format:H:i'],
            'healthcheck_url' => ['nullable', 'url', 'max:500'],
        ], attributes: ['healthcheck_url' => 'healthcheck URL']);

        Setting::put('company_name', $this->company_name);
        Setting::put('reminder_send_time', $this->reminder_send_time);
        Setting::put('healthcheck_url', $this->healthcheck_url);

        $this->dispatch('toast', message: 'Settings saved.', tone: 'ok');
    }

    public function saveChannels(): void
    {
        $this->validate([
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
        ]);

        Setting::put('whatsapp_phone_number_id', $this->whatsapp_phone_number_id);

        // A masked field means "leave it alone", not "set it to bullets".
        if (filled($this->whatsapp_token) && ! str_starts_with($this->whatsapp_token, '•')) {
            Setting::put('whatsapp_token', $this->whatsapp_token, secret: true);
            $this->whatsapp_token = '••••••••••••';
        }

        $this->dispatch('toast', message: 'Channel credentials saved.', tone: 'ok');
    }

    public function testHealthcheck(Healthcheck $healthcheck): void
    {
        Setting::put('healthcheck_url', $this->healthcheck_url);

        $ok = $healthcheck->ping();

        $this->dispatch(
            'toast',
            message: $ok ? 'Ping sent. Check the service shows it as received.' : 'No URL set, or the ping failed.',
            tone: $ok ? 'ok' : 'warn',
        );
    }

    /* ----------------------------------------------------- reminder rules */

    public function editRule(int $id): void
    {
        $rule = ReminderRule::findOrFail($id);

        $this->ruleId = $rule->id;
        $this->rule_asset_type = $rule->asset_type?->value;
        $this->rule_days_before = $rule->days_before;
        $this->rule_channels = $rule->channels ?? [];
        $this->rule_recipient_scope = $rule->recipient_scope->value;

        $this->dispatch('open-modal', 'rule-form');
    }

    public function newRule(): void
    {
        $this->reset(['ruleId', 'rule_asset_type']);
        $this->rule_days_before = 10;
        $this->rule_channels = ['mail', 'database'];
        $this->rule_recipient_scope = 'owner';
        $this->resetValidation();

        $this->dispatch('open-modal', 'rule-form');
    }

    public function saveRule(): void
    {
        $this->validate([
            'rule_days_before' => ['required', 'integer', 'between:-30,365'],
            'rule_channels' => ['required', 'array', 'min:1'],
            'rule_recipient_scope' => ['required'],
        ], attributes: [
            'rule_days_before' => 'timing',
            'rule_channels' => 'channels',
            'rule_recipient_scope' => 'recipients',
        ]);

        ReminderRule::updateOrCreate(
            $this->ruleId
                ? ['id' => $this->ruleId]
                : [
                    'asset_type' => $this->rule_asset_type,
                    'days_before' => $this->rule_days_before,
                    'recipient_scope' => $this->rule_recipient_scope,
                ],
            [
                'asset_type' => $this->rule_asset_type,
                'days_before' => $this->rule_days_before,
                'channels' => array_values($this->rule_channels),
                'recipient_scope' => $this->rule_recipient_scope,
                'is_active' => true,
            ],
        );

        $this->dispatch('close-modal', 'rule-form');
        $this->dispatch('toast', message: 'Reminder rule saved.', tone: 'ok');
        $this->reset(['ruleId']);
    }

    public function toggleRule(int $id): void
    {
        $rule = ReminderRule::findOrFail($id);
        $rule->forceFill(['is_active' => ! $rule->is_active])->save();
    }

    public function deleteRule(int $id): void
    {
        ReminderRule::findOrFail($id)->delete();

        $this->dispatch('toast', message: 'Rule removed.', tone: 'ok');
    }

    public function render(): View
    {
        return view('livewire.settings.index', [
            'rules' => ReminderRule::query()->orderByDesc('days_before')->get(),
            'availableChannels' => Channels::available(),
            'assetTypes' => AssetType::options(),
            'scopes' => RecipientScope::options(),
            'allChannels' => ReminderChannel::options(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'queueDepth' => DB::table('jobs')->count(),
            'backups' => $this->backups(),
        ]);
    }

    /** @return array{count: int, latest: ?string} */
    private function backups(): array
    {
        $path = storage_path('app/private/'.config('backup.backup.name'));

        if (! File::isDirectory($path)) {
            $path = storage_path('app/'.config('backup.backup.name'));
        }

        if (! File::isDirectory($path)) {
            return ['count' => 0, 'latest' => null];
        }

        $files = collect(File::files($path))->sortByDesc(fn ($file) => $file->getMTime());

        return [
            'count' => $files->count(),
            'latest' => $files->first()
                ? Carbon::createFromTimestamp($files->first()->getMTime())->diffForHumans()
                : null,
        ];
    }
}
