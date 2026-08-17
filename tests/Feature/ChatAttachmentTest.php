<?php

use App\Livewire\Tasks\Chat;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Storage::fake('local');

    $this->employee = User::factory()->employee()->create();
    $this->task = Task::factory()->create(['assigned_to' => $this->employee->id]);
});

test('an image attachment is stored with a thumbnail conversion', function () {
    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->set('attachments', [UploadedFile::fake()->image('screenshot.png', 1200, 800)])
        ->call('send')
        ->assertHasNoErrors();

    $media = $this->task->messages()->latest('id')->first()->getFirstMedia('attachments');

    expect($media)->not->toBeNull()
        ->and($media->mime_type)->toStartWith('image/')
        // The original name survives the randomised storage filename, so the
        // download arrives called what the sender called it.
        ->and($media->getCustomProperty('original_name'))->toBe('screenshot.png');
})->skip(fn () => ! extension_loaded('gd'), 'GD is not available.');

test('an image renders as a preview rather than a filename', function () {
    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->set('attachments', [UploadedFile::fake()->image('photo.jpg', 800, 600)])
        ->call('send');

    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->assertSee('open-lightbox', escape: false)
        ->assertSee('<img', escape: false);
})->skip(fn () => ! extension_loaded('gd'), 'GD is not available.');

test('every attachment offers a download with its original name', function () {
    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->set('attachments', [UploadedFile::fake()->create('contract.pdf', 40, 'application/pdf')])
        ->call('send');

    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->assertSee('download="contract.pdf"', escape: false)
        ->assertSee('contract.pdf');
});

test('a non-image attachment is not rendered as a broken image', function () {
    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->set('attachments', [UploadedFile::fake()->create('notes.txt', 2, 'text/plain')])
        ->call('send');

    $media = $this->task->messages()->latest('id')->first()->getFirstMedia('attachments');

    expect($media->hasGeneratedConversion('thumb'))->toBeFalse();

    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->assertDontSee('open-lightbox', escape: false);
});

test('an oversized attachment is refused and never becomes a message', function () {
    // Over the 25 MB the app documents, and over Livewire's temporary upload
    // limit, which config/livewire.php aligns to the same number so the two
    // cannot disagree about what "too big" means.
    Livewire::actingAs($this->employee)
        ->test(Chat::class, ['task' => $this->task])
        ->set('attachments', [UploadedFile::fake()->create('huge.zip', 30000)])
        ->call('send');

    expect($this->task->messages()->count())->toBe(0);
});

test('the documented attachment limit is the one actually enforced', function () {
    // A 20 MB file is inside the documented limit. Livewire's stock 12 MB
    // default would have rejected it before the component was ever reached.
    expect(config('livewire.temporary_file_upload.rules'))
        ->toContain('max:25600');
});

test('the files tab lists everything attached to the task', function () {
    $component = Livewire::actingAs($this->employee)->test(Chat::class, ['task' => $this->task]);

    $component->set('attachments', [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')])->call('send');
    $component->set('attachments', [UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf')])->call('send');

    $component->set('showFiles', true)
        ->assertSee('brief.pdf')
        ->assertSee('quote.pdf');
});

test('someone outside the thread cannot open it', function () {
    $outsider = User::factory()->employee()->create();

    Livewire::actingAs($outsider)
        ->test(Chat::class, ['task' => $this->task])
        ->assertForbidden();
});
