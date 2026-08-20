<?php

use App\Models\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mosi')] class extends Component {
    public Page $page;

    public function mount(Page $page): void
    {
        abort_unless(Auth::user()->can('view', $page), 404);

        $this->page = $page;
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ $page->title }}</flux:heading>
    <div class="prose prose-sm mt-6 max-w-none whitespace-pre-line dark:prose-invert">{!! nl2br(e($page->body)) !!}</div>
</div>
