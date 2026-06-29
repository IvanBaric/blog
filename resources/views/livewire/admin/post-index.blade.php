<x-admin-ui::page>
    <div class="admin-page-header">
        <div class="admin-page-header-copy">
            <h1 class="admin-page-title">{{ __('Objave') }}</h1>
            <flux:text class="admin-page-description">
                {{ __('Uredite novosti, sadržaj i istaknute objave za javnu stranicu.') }}
            </flux:text>
        </div>

        <div class="admin-page-actions">
            <flux:modal.trigger name="post-create-form">
                <flux:button variant="primary" icon="plus">
                    {{ __('Nova objava') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <x-admin-ui::stat-grid>
        @foreach ($this->statCards as $card)
            <x-admin-ui::stat-card :label="$card['label']" :value="$card['value']" :accent="$card['accent']">
                <x-slot:icon>
                    <flux:icon :icon="$card['icon']" variant="micro" class="size-4" />
                </x-slot:icon>
            </x-admin-ui::stat-card>
        @endforeach
    </x-admin-ui::stat-grid>

    <x-admin-ui::search-filter-toolbar
        :placeholder="__('Pretraži objave prema naslovu...')"
        :items="$this->filterOptions"
        :active="$filter"
        align="end"
    />

    <x-admin-ui::panel loading loading-target="search,setFilter,archive,nextPage,previousPage" loading-text="{{ __('Ažuriram pregled objava...') }}">
        <div class="admin-panel-header">
            <div>
                <h2 class="admin-panel-title">{{ $this->activeFilterLabel }}</h2>
                <p class="admin-panel-description">
                    {{ trans_choice('{0} Nema pronađenih objava.|{1} :count objava na ovoj stranici.|[2,*] :count objava na ovoj stranici.', $this->posts->count(), ['count' => $this->posts->count()]) }}
                </p>
            </div>

            @if ($this->isFiltered())
                <flux:button wire:click="resetFilters" size="sm" variant="ghost" icon="x-mark">
                    {{ __('Očisti filtere') }}
                </flux:button>
            @endif
        </div>

        @if ($this->posts->isEmpty())
            <div class="px-6 py-14 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-accent/10 text-accent-content ring-1 ring-accent/15 dark:bg-accent/15 dark:ring-accent/25">
                    <flux:icon name="document-text" class="size-6" />
                </div>

                <h3 class="mt-4 text-base font-semibold text-zinc-950 dark:text-white">{{ __('Nema objava') }}</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $this->isFiltered() ? __('Promijenite pretragu ili filtere za širi prikaz objava.') : __('Dodajte prvu objavu i pripremite sadržaj za javnu stranicu.') }}
                </p>
            </div>
        @else
            <div class="admin-list-header hidden grid-cols-[minmax(0,1fr)_10rem_9rem_11rem] lg:grid">
                <span>{{ __('Objava') }}</span>
                <span>{{ __('Objavljeno') }}</span>
                <span>{{ __('Status') }}</span>
                <span class="text-right">{{ __('Akcije') }}</span>
            </div>

            <div id="table" class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($this->posts as $post)
                    @php($status = $this->statusBadge($post))

                    <article wire:key="post-{{ $post->uuid }}" class="admin-list-row admin-post-list-row grid-cols-1 gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_10rem_9rem_11rem]">
                        <div class="flex min-w-0 gap-4">
                            <div class="flex h-20 w-28 shrink-0 overflow-hidden rounded-2xl bg-zinc-100 text-zinc-400 ring-1 ring-inset ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-800">
                                @if ($post->featured_image)
                                    <img src="{{ str_starts_with($post->featured_image, 'http://') || str_starts_with($post->featured_image, 'https://') ? $post->featured_image : Storage::disk('public')->url($post->featured_image) }}" alt="{{ $post->localized('title') ?: __('Naslovna slika objave') }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <flux:icon name="document-text" class="size-8" />
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 py-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $post->uuid]) }}" wire:navigate class="text-[15px] font-semibold text-zinc-950 transition hover:text-accent dark:text-white dark:hover:text-accent-content">
                                        {{ $post->localized('title') ?: __('Neimenovana objava') }}
                                    </a>

                                    @if ($post->is_featured)
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
                                    <flux:menu.item as="button" type="button" wire:click="confirmPublish('{{ $post->uuid }}')" icon="{{ $post->isPublished() ? 'eye-slash' : 'rocket-launch' }}">
                                        {{ $post->isPublished() ? __('Vrati u skicu') : __('Objavi') }}
                                    </flux:menu.item>

                                    <flux:menu.item as="button" type="button" wire:click="confirmFeatured('{{ $post->uuid }}')" icon="{{ $post->is_featured ? 'star' : 'sparkles' }}">
                                        {{ $post->is_featured ? __('Ukloni izdvojeno') : __('Istakni') }}
                                    </flux:menu.item>

                                    <flux:menu.item :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $post->uuid])" wire:navigate icon="pencil-square">
                                        {{ __('Uredi') }}
                                    </flux:menu.item>

                                    <flux:menu.separator />

                                    @if ($post->status === 'archived')
                                        <flux:menu.item icon="archive-box" disabled>
                                            {{ __('Arhivirano') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item as="button" type="button" wire:click="confirmArchive('{{ $post->uuid }}')" icon="archive-box" variant="danger">
                                            {{ __('Arhiviraj') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <flux:pagination :paginator="$this->posts" scroll-to="#table" />
            </div>
        @endif
    </x-admin-ui::panel>

    <flux:modal name="post-create-form" class="max-w-xl">
        <form wire:submit="createPost" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nova objava') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Upišite naziv objave. Nakon izrade odmah se otvara uređivanje.') }}</flux:text>
            </div>

            <flux:input wire:model="newPostTitle" :label="__('Naziv objave')" :placeholder="__('Npr. Radionica izrade ukrasa')" autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">{{ __('Izradi objavu') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="post-publish-confirm" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $publishingPostWillPublish ? __('Objaviti objavu?') : __('Vratiti objavu u skicu?') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $publishingPostWillPublish ? __('Objava će biti vidljiva na javnoj stranici. Ako datum objave nije postavljen, postavit će se trenutno vrijeme.') : __('Objava se više neće prikazivati na javnoj stranici dok je ponovno ne objavite.') }}
                </flux:text>
            </div>

            @if ($publishingPostTitle !== '')
                <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                    {{ $publishingPostTitle }}
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    wire:click="confirmPublishChange"
                    wire:loading.attr="disabled"
                    wire:target="confirmPublishChange"
                    :variant="$publishingPostWillPublish ? 'primary' : 'danger'"
                    :icon="$publishingPostWillPublish ? 'rocket-launch' : 'eye-slash'"
                >
                    {{ $publishingPostWillPublish ? __('Objavi objavu') : __('Vrati u skicu') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="post-featured-confirm" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $featuringPostWillFeature ? __('Istaknuti objavu?') : __('Ukloniti iz istaknutih?') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $featuringPostWillFeature ? __('Objava će se prikazivati u sekcijama i prikazima koji koriste istaknute objave.') : __('Objava ostaje spremljena, ali se više neće prikazivati kao istaknuta objava.') }}
                </flux:text>
            </div>

            @if ($featuringPostTitle !== '')
                <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                    {{ $featuringPostTitle }}
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    wire:click="confirmFeaturedChange"
                    wire:loading.attr="disabled"
                    wire:target="confirmFeaturedChange"
                    :variant="$featuringPostWillFeature ? 'primary' : 'danger'"
                    :icon="$featuringPostWillFeature ? 'sparkles' : 'star'"
                >
                    {{ $featuringPostWillFeature ? __('Istakni objavu') : __('Ukloni izdvojeno') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="post-archive-confirm" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Arhivirati objavu?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Objava će se premjestiti u arhivu i više se neće prikazivati kao aktivna objava.') }}
                </flux:text>
            </div>

            @if ($archivingPostTitle !== '')
                <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                    {{ $archivingPostTitle }}
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="archive" wire:loading.attr="disabled" wire:target="archive" variant="danger" icon="archive-box">
                    {{ __('Arhiviraj objavu') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin-ui::page>
