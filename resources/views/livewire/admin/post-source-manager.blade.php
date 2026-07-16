<div class="min-w-0">
    @if ($creatingPost || $this->editingPost)
        <div class="mb-4 flex items-center justify-between gap-3">
            <flux:button type="button" wire:click="showList" variant="ghost" size="sm" icon="arrow-left">
                {{ __('Objave') }}
            </flux:button>
        </div>

        @livewire(
            \IvanBaric\Blog\Livewire\Admin\PostForm::class,
            ['post' => $this->editingPost, 'embedded' => true],
            key('source-post-form-'.($editingPostUuid ?: 'new'))
        )
    @else
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-blog-source-toolbar>
            <flux:button type="button" wire:click="createPost" wire:loading.attr="disabled" wire:target="createPost" variant="primary" size="sm" icon="plus" class="w-full justify-center sm:w-auto">
                {{ __('Nova objava') }}
            </flux:button>

            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                <div class="w-full sm:w-96">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        :placeholder="__('Pretraži objave prema naslovu...')"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                <flux:dropdown position="bottom" align="end">
                    <flux:button type="button" variant="filled" size="sm" icon="funnel" :aria-label="__('Filter')" />

                    <flux:menu>
                        <flux:menu.submenu heading="{{ __('Status') }}">
                            <flux:menu.radio.group>
                                @foreach ($this->filterOptions as $key => $item)
                                    <flux:menu.radio as="button" type="button" wire:click="setFilter('{{ $key }}')" :checked="$filter === (string) $key">
                                        <span class="inline-flex min-w-0 items-center gap-2">
                                            @if (! empty($item['icon']))
                                                <flux:icon :icon="$item['icon']" variant="micro" class="size-3.5 shrink-0" />
                                            @endif

                                            <span>{{ $item['label'] ?? $key }}</span>

                                            @if (array_key_exists('count', $item))
                                                <span class="ms-1 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">{{ $item['count'] }}</span>
                                            @endif
                                        </span>
                                    </flux:menu.radio>
                                @endforeach
                            </flux:menu.radio.group>
                        </flux:menu.submenu>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        <x-admin-ui::panel loading loading-target="search,setFilter,nextPage,previousPage" loading-text="{{ __('Ažuriram pregled objava...') }}">
            @if ($this->isFiltered())
                <div class="mb-4 flex justify-end">
                    <flux:button wire:click="resetFilters" size="sm" variant="ghost" icon="x-mark">
                        {{ __('Očisti filtere') }}
                    </flux:button>
                </div>
            @endif

            @if ($this->posts->isEmpty())
                <x-admin-ui::empty-state
                    :title="__('Nema objava')"
                    :description="$this->isFiltered() ? __('Promijenite pretragu ili filtere za širi prikaz objava.') : __('Dodajte prvu objavu i pripremite sadržaj za javnu stranicu.')"
                >
                    <x-slot:icon>
                        <flux:icon name="document-text" class="size-6" />
                    </x-slot:icon>
                </x-admin-ui::empty-state>
            @else
                <div class="admin-list-header hidden grid-cols-[minmax(0,1fr)_10rem_9rem_11rem] lg:grid">
                    <span>{{ __('Objava') }}</span>
                    <span>{{ __('Objavljeno') }}</span>
                    <span>{{ __('Status') }}</span>
                    <span class="text-right">{{ __('Akcije') }}</span>
                </div>

                <div id="source-posts-table" class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->posts as $post)
                        @php($status = $this->statusBadge($post))
                        @php($featuredImageUrl = $post->featuredImageUrl('medium'))

                        <article wire:key="source-post-{{ $post->uuid }}" class="admin-list-row admin-post-list-row grid-cols-1 gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_10rem_9rem_11rem]">
                            <div class="flex min-w-0 gap-4">
                                <div class="admin-list-thumbnail">
                                    @if ($featuredImageUrl)
                                        <img
                                            src="{{ $featuredImageUrl }}"
                                            alt="{{ $post->localized('title') ?: __('Naslovna slika objave') }}"
                                            @class([
                                                'h-full w-full object-contain transition duration-200',
                                                'grayscale contrast-75 opacity-70' => $post->status === 'archived',
                                            ])
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    @else
                                        <div class="flex h-full w-full items-center justify-center">
                                            <flux:icon name="document-text" class="size-8" />
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 py-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" wire:click="editPost('{{ $post->uuid }}')" class="cursor-pointer text-left text-[15px] font-semibold text-zinc-950 transition hover:text-accent dark:text-white dark:hover:text-accent-content">
                                            {{ $post->localized('title') ?: __('Neimenovana objava') }}
                                        </button>

                                        @if ($post->is_featured && $post->status === 'published')
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-[0.12em] text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                                                {{ __('Izdvojeno') }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[13px] text-zinc-500 dark:text-zinc-400">
                                        <span class="truncate">{{ $this->categoryLabel($post) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3 lg:block">
                                <span class="text-xs font-medium uppercase text-zinc-500 lg:hidden">{{ __('Objavljeno') }}</span>
                                <span class="text-sm tabular-nums text-zinc-600 dark:text-zinc-300">{{ $this->publishedAt($post) }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-3 lg:block">
                                <span class="text-xs font-medium uppercase text-zinc-500 lg:hidden">{{ __('Status') }}</span>
                                <x-admin-ui::badge variant="custom" class="{{ $status['class'] }}">
                                    {{ $status['label'] }}
                                </x-admin-ui::badge>
                            </div>

                            <div class="flex items-center justify-end gap-1">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Akcije')" />

                                    <flux:menu>
                                        <flux:menu.item as="button" type="button" wire:click="editPost('{{ $post->uuid }}')" icon="pencil-square">
                                            {{ __('Uredi') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($this->posts->hasPages())
                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <flux:pagination :paginator="$this->posts" scroll-to="#source-posts-table" />
                    </div>
                @endif
            @endif
        </x-admin-ui::panel>
    @endif
</div>
