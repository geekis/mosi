<?php

use App\Actions\Documents\UploadDocument;
use App\Models\Document;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Documents')] class extends Component {
    use WithFileUploads;

    public string $docTitle = '';

    public string $category = '';

    public ?TemporaryUploadedFile $file = null;

    public function upload(UploadDocument $uploadDocument): void
    {
        abort_unless(Auth::user()->can('create', Document::class), 403);

        $validated = $this->validate([
            'docTitle' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $uploadDocument(Auth::user(), [
            'title' => ['is' => $validated['docTitle'], 'en' => $validated['docTitle']],
            'category' => $validated['category'] ?: null,
        ], $this->file);

        $this->reset('docTitle', 'category', 'file');
        Flux::modal('upload-document')->close();
        Flux::toast(variant: 'success', text: __('Document uploaded.'));
    }

    /**
     * @return array{documents: \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Document>>, canManage: bool}
     */
    public function with(): array
    {
        return [
            'documents' => Document::query()->published()->with('media')->latest()->get()->groupBy('category'),
            'canManage' => Auth::user()->can('create', Document::class),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Documents') }}</flux:heading>
        @if ($canManage)
            <flux:modal.trigger name="upload-document">
                <flux:button size="sm" variant="primary" icon="arrow-up-tray">{{ __('Upload') }}</flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    <div class="mt-6 space-y-6">
        @forelse ($documents as $category => $group)
            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $category ?: __('Uncategorised') }}</flux:heading>
                <div class="mt-2 space-y-2">
                    @foreach ($group as $document)
                        <a wire:key="doc-{{ $document->id }}" href="{{ route('documents.download', $document) }}"
                           class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <flux:icon icon="document-text" class="size-5 text-zinc-400" />
                            <div class="min-w-0 flex-1">
                                <flux:text class="truncate font-medium">{{ $document->title }}</flux:text>
                                @if ($document->description)<flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $document->description }}</flux:text>@endif
                            </div>
                            <flux:icon icon="arrow-down-tray" class="size-4 text-zinc-400" />
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:callout icon="document-text">{{ __('No documents yet.') }}</flux:callout>
        @endforelse
    </div>

    @if ($canManage)
        <flux:modal name="upload-document" class="w-full max-w-lg">
            <form wire:submit="upload" class="space-y-4">
                <flux:heading size="lg">{{ __('Upload a document') }}</flux:heading>
                <flux:input wire:model="docTitle" :label="__('Title')" required />
                <flux:input wire:model="category" :label="__('Category')" />
                <flux:input type="file" wire:model="file" :label="__('File')" required />
                <flux:error name="file" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Upload') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
