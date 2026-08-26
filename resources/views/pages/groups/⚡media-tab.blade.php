<?php

use App\Actions\Groups\DeleteGroupMedia;
use App\Actions\Groups\UploadGroupMedia;
use App\Models\Group;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends Component {
    use WithFileUploads;

    public Group $group;

    public ?TemporaryUploadedFile $upload = null;

    public function save(UploadGroupMedia $uploadGroupMedia): void
    {
        abort_unless($this->group->hasMember(Auth::user()), 403);

        $this->validate([
            'upload' => ['required', 'file', 'mimetypes:'.implode(',', Group::GALLERY_MIME_TYPES), 'max:'.(int) (UploadGroupMedia::MAX_BYTES / 1024)],
        ]);

        $uploadGroupMedia(Auth::user(), $this->group, $this->upload);

        $this->reset('upload');
        $this->group->load('media');
        Flux::modal('upload-media')->close();
        Flux::toast(variant: 'success', text: __('Media uploaded.'));
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
        Flux::toast(variant: 'success', text: __('Media deleted.'));
    }

    /**
     * @return array{items: \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media>, canManage: bool}
     */
    public function with(): array
    {
        return [
            'items' => $this->group->mediaIn('gallery'),
            'canManage' => Auth::user()->can('update', $this->group),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">{{ __('Media') }}</flux:heading>
        <flux:modal.trigger name="upload-media">
            <flux:button size="sm" variant="primary" icon="plus">{{ __('Upload media') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($items->isEmpty())
        <flux:callout icon="photo">{{ __('No media yet.') }}</flux:callout>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($items as $item)
                @php($uploaderId = (int) $item->getCustomProperty('uploaded_by'))
                @php($src = route('groups.attachments', [$group, $item]))
                <div wire:key="media-{{ $item->id }}" class="group relative overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    @if (str_starts_with((string) $item->mime_type, 'video/'))
                        <video src="{{ $src }}" controls class="aspect-square w-full object-cover"></video>
                    @else
                        <img src="{{ $src }}" alt="{{ $item->file_name }}" class="aspect-square w-full object-cover" loading="lazy" />
                    @endif

                    @if ($canManage || $uploaderId === auth()->id())
                        <flux:button size="xs" variant="filled" icon="trash"
                            class="absolute right-1 top-1 opacity-0 transition group-hover:opacity-100"
                            wire:click="delete({{ $item->id }})"
                            wire:confirm="{{ __('Delete this item?') }}" />
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Upload modal --}}
    <flux:modal name="upload-media" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ __('Upload media') }}</flux:heading>
            <flux:input type="file" wire:model="upload" :label="__('Media')" accept="image/*,video/*" />
            <flux:error name="upload" />
            @if ($upload && $upload->isPreviewable())
                <img src="{{ $upload->temporaryUrl() }}" alt="" class="max-h-48 w-full rounded-lg object-cover" />
            @endif
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Upload') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
