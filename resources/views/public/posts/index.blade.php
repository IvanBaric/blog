<x-layouts.public :title="__('Objave')">
    <main class="mx-auto w-full max-w-6xl px-6 py-10">
        <h1 class="text-3xl font-semibold text-zinc-950 dark:text-zinc-50">{{ __('Objave') }}</h1>
        @if ($posts->isEmpty())
            <x-corexis::public-empty-state
                class="mt-8"
                icon="newspaper"
                :title="__('Objave uskoro')"
                :description="__('Objave će se prikazati ovdje kada budu spremne za objavu.')"
            />
        @else
            <div class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ($posts as $post)
                <article class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-zinc-50">{{ $post->localized('title') }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $post->localized('excerpt') }}</p>
                    <flux:button :href="route('posts.show', $post)" class="mt-4" variant="ghost">
                        {{ __('Pročitaj') }}
                    </flux:button>
                </article>
                @endforeach
            </div>
            @if ($posts->hasPages())
                <div class="mt-8">{{ $posts->links() }}</div>
            @endif
        @endif
    </main>
</x-layouts.public>
