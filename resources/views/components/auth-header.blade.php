@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <x-ui.heading size="xl">{{ $title }}</x-ui.heading>
    <x-ui.subheading>{{ $description }}</x-ui.subheading>
</div>
