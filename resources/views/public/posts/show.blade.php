<x-layouts.public :title="$post->localized('title')" :seo-model="$post">
    <main class="mx-auto w-full max-w-4xl px-6 py-10">
        <flux:button :href="route('posts.index')" variant="ghost" icon="arrow-left">
            {{ __('Objave') }}
        </flux:button>
        <h1 class="mt-6 text-3xl font-semibold text-zinc-950 dark:text-zinc-50">{{ $post->localized('title') }}</h1>
        <p class="mt-4 text-zinc-600 dark:text-zinc-300">{{ $post->localized('content') }}</p>
    </main>
</x-layouts.public>
