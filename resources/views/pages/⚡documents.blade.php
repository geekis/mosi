<?php

use App\Actions\Documents\UploadDocument;
use App\Models\Document;
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
        $this->dispatch('modal-close', name: 'upload-document');
        $this->dispatch('toast', variant: 'success', text: __('Document uploaded.'));
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
        <x-ui.heading size="xl">{{ __('Documents') }}</x-ui.heading>
        @if ($canManage)
            <x-ui.modal.trigger name="upload-document">
                <x-ui.button size="sm" variant="primary" icon="arrow-up-tray">{{ __('Upload') }}</x-ui.button>
            </x-ui.modal.trigger>
        @endif
    </div>

    <div class="mt-6 space-y-6">
        @forelse ($documents as $category => $group)
            <div>
                <x-ui.heading size="sm" class="text-mist-500 dark:text-zinc-400">{{ $category ?: __('Uncategorised') }}</x-ui.heading>
                <div class="mt-2 space-y-2">
                    @foreach ($group as $document)
                        <a wire:key="doc-{{ $document->id }}" href="{{ route('documents.download', $document) }}"
                           class="flex items-center gap-3 rounded-xl border border-sand-200 p-3 hover:bg-sand-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <x-ui.icon name="document-text" class="size-5 text-mist-400" />
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $document->title }}</span>
                                @if ($document->description)<x-ui.text size="sm" class="truncate">{{ $document->description }}</x-ui.text>@endif
                            </div>
                            <x-ui.icon name="arrow-down-tray" class="size-4 text-mist-400" />
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <x-ui.alert icon="document-text">{{ __('No documents yet.') }}</x-ui.alert>
        @endforelse
    </div>

    @if ($canManage)
        <x-ui.modal name="upload-document" class="max-w-lg">
            <form wire:submit="upload" class="space-y-4">
                <x-ui.heading size="lg">{{ __('Upload a document') }}</x-ui.heading>
                <x-ui.input wire:model="docTitle" :label="__('Title')" required />
                <x-ui.input wire:model="category" :label="__('Category')" />
                <x-ui.input type="file" wire:model="file" :label="__('File')" required />
                <x-ui.error name="file" />
                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="primary">{{ __('Upload') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
