<?php

use App\Actions\Groups\DeleteGroupMedia;
use App\Actions\Groups\UploadGroupFile;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends Component {
    use WithFileUploads;

    public Group $group;

    public ?TemporaryUploadedFile $file = null;

    public function upload(UploadGroupFile $uploadGroupFile): void
    {
        abort_unless($this->group->hasMember(Auth::user()), 403);

        $this->validate([
            'file' => ['required', 'file', 'mimetypes:'.implode(',', Group::FILE_MIME_TYPES), 'max:'.(int) (UploadGroupFile::MAX_BYTES / 1024)],
        ]);

        $uploadGroupFile(Auth::user(), $this->group, $this->file);

        $this->reset('file');
        $this->group->load('media');
        $this->dispatch('modal-close', name: 'upload-file');
        $this->dispatch('toast', variant: 'success', text: __('File uploaded.'));
    }

    public function delete(int $mediaId, DeleteGroupMedia $deleteGroupMedia): void
    {
        $media = Media::query()->find($mediaId);

        if ($media === null) {
            return;
        }

        try {
            $deleteGroupMedia(Auth::user(), $this->group, $media);
        } catch (\DomainException) {
            return;
        }

        $this->group->load('media');
        $this->dispatch('toast', variant: 'success', text: __('File deleted.'));
    }

    /**
     * @return array{files: \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media>, uploaders: \Illuminate\Support\Collection<int, User>, canManage: bool}
     */
    public function with(): array
    {
        $files = $this->group->mediaIn('files');

        $uploaderIds = $files
            ->map(fn (Media $m): mixed => $m->getCustomProperty('uploaded_by'))
            ->filter()
            ->unique()
            ->values();

        return [
            'files' => $files,
            'uploaders' => User::query()->whereIn('id', $uploaderIds)->get()->keyBy('id'),
            'canManage' => Auth::user()->can('update', $this->group),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <x-ui.heading size="lg">{{ __('Files') }}</x-ui.heading>
        <x-ui.modal.trigger name="upload-file">
            <x-ui.button size="sm" variant="primary" icon="plus">{{ __('Upload file') }}</x-ui.button>
        </x-ui.modal.trigger>
    </div>

    @forelse ($files as $file)
        @php($uploaderId = (int) $file->getCustomProperty('uploaded_by'))
        <x-ui.card wire:key="file-{{ $file->id }}" class="flex items-center gap-3">
            <x-ui.icon name="document-text" class="size-8 shrink-0 text-mist-400" />
            <div class="min-w-0 flex-1">
                <a href="{{ route('groups.attachments', [$group, $file]) }}"
                    class="block truncate font-medium hover:underline" target="_blank" rel="noopener">
                    {{ $file->file_name }}
                </a>
                <x-ui.text size="sm">
                    {{ $file->human_readable_size }}
                    @if ($uploaders[$uploaderId] ?? null)
                        · {{ __('Uploaded by :name', ['name' => $uploaders[$uploaderId]->display_name]) }}
                    @endif
                    · {{ $file->created_at?->translatedFormat('j. M Y') }}
                </x-ui.text>
            </div>
            <x-ui.button size="sm" variant="ghost" icon="arrow-down-tray"
                :href="route('groups.attachments', [$group, $file])" :navigate="false" target="_blank" />
            @if ($canManage || $uploaderId === auth()->id())
                <x-ui.button size="sm" variant="ghost" icon="trash"
                    wire:click="delete({{ $file->id }})"
                    wire:confirm="{{ __('Delete this file?') }}" />
            @endif
        </x-ui.card>
    @empty
        <x-ui.alert icon="document-text">{{ __('No files yet.') }}</x-ui.alert>
    @endforelse

    {{-- Upload modal --}}
    <x-ui.modal name="upload-file" class="max-w-lg">
        <form wire:submit="upload" class="space-y-4">
            <x-ui.heading size="lg">{{ __('Upload file') }}</x-ui.heading>
            <x-ui.input type="file" wire:model="file" :label="__('File')"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt" />
            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Upload') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
