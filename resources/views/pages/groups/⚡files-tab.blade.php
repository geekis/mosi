<?php

use App\Actions\Groups\DeleteGroupMedia;
use App\Actions\Groups\UploadGroupFile;
use App\Models\Group;
use App\Models\User;
use Flux\Flux;
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
        Flux::modal('upload-file')->close();
        Flux::toast(variant: 'success', text: __('File uploaded.'));
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
        Flux::toast(variant: 'success', text: __('File deleted.'));
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
        <flux:heading size="lg">{{ __('Files') }}</flux:heading>
        <flux:modal.trigger name="upload-file">
            <flux:button size="sm" variant="primary" icon="plus">{{ __('Upload file') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @forelse ($files as $file)
        @php($uploaderId = (int) $file->getCustomProperty('uploaded_by'))
        <flux:card wire:key="file-{{ $file->id }}" class="flex items-center gap-3">
            <flux:icon name="document-text" class="size-8 shrink-0 text-zinc-400" />
            <div class="min-w-0 flex-1">
                <a href="{{ route('groups.attachments', [$group, $file]) }}"
                    class="truncate font-medium hover:underline" target="_blank" rel="noopener">
                    {{ $file->file_name }}
                </a>
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ $file->human_readable_size }}
                    @if ($uploaders[$uploaderId] ?? null)
                        · {{ __('Uploaded by :name', ['name' => $uploaders[$uploaderId]->display_name]) }}
                    @endif
                    · {{ $file->created_at?->translatedFormat('j. M Y') }}
                </flux:text>
            </div>
            <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                :href="route('groups.attachments', [$group, $file])" target="_blank" />
            @if ($canManage || $uploaderId === auth()->id())
                <flux:button size="sm" variant="ghost" icon="trash"
                    wire:click="delete({{ $file->id }})"
                    wire:confirm="{{ __('Delete this file?') }}" />
            @endif
        </flux:card>
    @empty
        <flux:callout icon="document-text">{{ __('No files yet.') }}</flux:callout>
    @endforelse

    {{-- Upload modal --}}
    <flux:modal name="upload-file" class="w-full max-w-lg">
        <form wire:submit="upload" class="space-y-4">
            <flux:heading size="lg">{{ __('Upload file') }}</flux:heading>
            <flux:input type="file" wire:model="file" :label="__('File')"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt" />
            <flux:error name="file" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Upload') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
