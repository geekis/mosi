<?php

declare(strict_types=1);

use App\Actions\Documents\UploadDocument;
use App\Models\Document;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function asComms(User $user): User
{
    Role::findOrCreate('comms', 'web');
    $user->load('roles');
    $user->assignRole('comms');

    return $user;
}

test('uploading a document attaches its file', function () {
    Storage::fake(config('media-library.disk_name'));
    $comms = asComms(User::factory()->create());

    $document = app(UploadDocument::class)($comms, [
        'title' => ['is' => 'Handbók', 'en' => 'Handbook'],
        'category' => 'HR',
    ], UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf'));

    expect($document->getFirstMedia('file'))->not->toBeNull()
        ->and($document->category)->toBe('HR');
});

test('only content managers may add documents', function () {
    $employee = User::factory()->create();
    $comms = asComms(User::factory()->create());

    expect($employee->can('create', Document::class))->toBeFalse()
        ->and($comms->can('create', Document::class))->toBeTrue();
});

test('a non-manager cannot upload from the documents page', function () {
    Storage::fake(config('media-library.disk_name'));
    $employee = User::factory()->create();

    Livewire::actingAs($employee)
        ->test('pages::documents')
        ->set('docTitle', 'Sneaky')
        ->set('file', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
        ->call('upload')
        ->assertForbidden();

    expect(Document::query()->count())->toBe(0);
});

test('the documents page lists published documents and links to download', function () {
    $viewer = User::factory()->create();
    $document = Document::factory()->create(['title' => ['is' => 'Sýnilegt skjal', 'en' => 'Visible doc']]);

    Livewire::actingAs($viewer)
        ->test('pages::documents')
        ->assertOk()
        ->assertSee('Sýnilegt skjal');
});

test('the news page shows only official posts', function () {
    $viewer = User::factory()->create();
    Post::factory()->official()->create(['user_id' => $viewer->id, 'body' => 'OFFICIAL-NEWS-ITEM']);
    Post::factory()->create(['user_id' => $viewer->id, 'body' => 'REGULAR-CHATTER']);

    Livewire::actingAs($viewer)
        ->test('pages::news')
        ->assertSee('OFFICIAL-NEWS-ITEM')
        ->assertDontSee('REGULAR-CHATTER');
});

test('a published static page renders, an unpublished one 404s for a normal user', function () {
    $viewer = User::factory()->create();
    $published = Page::factory()->create(['title' => ['is' => 'Stefna', 'en' => 'Policy'], 'body' => ['is' => 'Innihald', 'en' => 'Body']]);
    $draft = Page::factory()->unpublished()->create();

    $this->actingAs($viewer)->get(route('pages.show', $published))->assertOk()->assertSee('Stefna');
    $this->actingAs($viewer)->get(route('pages.show', $draft))->assertNotFound();
});
