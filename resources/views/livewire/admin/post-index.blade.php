<x-admin-ui::page>
    <x-admin-ui::page-header
        :title="__('Objave')"
        :description="__('Uredite novosti, sadržaj i istaknute objave za javnu stranicu.')"
        icon="newspaper"
    >
        <x-slot:actions>
            <flux:button type="button" wire:click="openCreatePost" wire:loading.attr="disabled" wire:target="openCreatePost" variant="primary" icon="plus">
                {{ __('Nova objava') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::page-header>

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

    <x-admin-ui::panel loading loading-target="search,setFilter,archive,delete,nextPage,previousPage" loading-text="{{ __('Ažuriram pregled objava...') }}">
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
            <x-admin-ui::empty-state
                :title="__('Nema objava')"
                :description="$this->isFiltered() ? __('Promijenite pretragu ili filtere za širi prikaz objava.') : __('Dodajte prvu objavu i pripremite sadržaj za javnu stranicu.')"
            >
                <x-slot:icon>
                    <flux:icon name="document-text" class="size-6" />
                </x-slot:icon>
            </x-admin-ui::empty-state>
        @else
            <div class="admin-list-header hidden grid-cols-[minmax(0,1fr)_8rem_10rem_9rem_11rem] lg:grid">
                <span>{{ __('Objava') }}</span>
                <span>{{ __('Galerija') }}</span>
                <span>{{ __('Objavljeno') }}</span>
                <span>{{ __('Status') }}</span>
                <span class="text-right">{{ __('Akcije') }}</span>
            </div>

            <div id="table" class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($this->posts as $post)
                    @php($status = $this->statusBadge($post))
                    @php($featuredImageUrl = $post->featuredImageUrl('medium'))
                    @php($galleryPhotoCount = (int) $post->galleries->sum('media_count'))

                    <article wire:key="post-{{ $post->uuid }}" class="admin-list-row admin-post-list-row grid-cols-1 gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_8rem_10rem_9rem_11rem]">
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
                                    />
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
                            <span class="text-xs font-medium uppercase text-zinc-500 lg:hidden">{{ __('Galerija') }}</span>
                            <span class="inline-flex items-center gap-1.5 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                                <flux:icon name="photo" class="size-4 text-zinc-400 dark:text-zinc-500" />
                                {{ trans_choice('{1} :count fotografija|[2,4] :count fotografije|[5,*] :count fotografija', $galleryPhotoCount, ['count' => $galleryPhotoCount]) }}
                            </span>
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
                                    @if ($post->status === 'archived')
                                        <flux:menu.item :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $post->uuid])" wire:navigate icon="eye">
                                            {{ __('Pregledaj') }}
                                        </flux:menu.item>
                                        <flux:menu.item as="button" type="button" wire:click="confirmPublish('{{ $post->uuid }}')" icon="arrow-uturn-left">
                                            {{ __('Vrati u skicu') }}
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item as="button" type="button" wire:click="confirmDelete('{{ $post->uuid }}')" icon="trash" variant="danger">
                                            {{ __('Obriši') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item as="button" type="button" wire:click="confirmPublish('{{ $post->uuid }}')" icon="{{ $post->isPublished() ? 'eye-slash' : 'rocket-launch' }}">
                                            {{ $post->isPublished() ? __('Vrati u skicu') : __('Objavi') }}
                                        </flux:menu.item>

                                        @if ($post->status === 'published')
                                            <flux:menu.item as="button" type="button" wire:click="confirmFeatured('{{ $post->uuid }}')" icon="{{ $post->is_featured ? 'star' : 'sparkles' }}">
                                                {{ $post->is_featured ? __('Ukloni izdvojeno') : __('Istakni') }}
                                            </flux:menu.item>
                                        @endif

                                        <flux:menu.item :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $post->uuid])" wire:navigate icon="pencil-square">
                                            {{ __('Uredi') }}
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item as="button" type="button" wire:click="confirmArchive('{{ $post->uuid }}')" icon="archive-box" variant="danger">
                                            {{ __('Arhiviraj') }}
                                        </flux:menu.item>

                                        @if ($post->status === 'draft')
                                            <flux:menu.item as="button" type="button" wire:click="confirmDelete('{{ $post->uuid }}')" icon="trash" variant="danger">
                                                {{ __('Obriši') }}
                                            </flux:menu.item>
                                        @endif
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->posts->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <flux:pagination :paginator="$this->posts" scroll-to="#table" />
                </div>
            @endif
        @endif
    </x-admin-ui::panel>

    <flux:modal name="post-create-form" x-on:close="$wire.cancelCreatePost()" class="max-w-xl">
        <form wire:submit="createPost" wire:loading.class="admin-panel-content-loading" wire:target="createPost" class="relative space-y-6">
            <x-admin-ui::loading-overlay target="createPost" :text="__('Spremanje...')" />
            <div>
                <flux:heading size="lg">{{ __('Nova objava') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Upišite naziv objave. Nakon izrade odmah se otvara uređivanje.') }}</flux:text>
            </div>

            <flux:input wire:model="newPostTitle" :label="__('Naziv objave')" :placeholder="__('Npr. Radionica izrade ukrasa')" data-required autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <x-admin-ui::submit-button target="createPost" icon="plus">{{ __('Izradi objavu') }}</x-admin-ui::submit-button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="post-publish-confirm" x-on:close="$wire.cancelPublish()" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $publishingPostIsArchived ? __('Vratiti arhiviranu objavu u skicu?') : ($publishingPostWillPublish ? __('Objaviti objavu?') : __('Vratiti objavu u skicu?')) }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $publishingPostIsArchived
                        ? __('Objava će se vratiti među skice i ponovno će se moći uređivati. Prethodni datum objave bit će uklonjen, a objava neće biti javna dok je ponovno ne objavite.')
                        : ($publishingPostWillPublish
                            ? __('Objava će biti vidljiva na javnoj stranici. Ako datum objave nije postavljen, postavit će se trenutno vrijeme.')
                            : __('Objava se više neće prikazivati na javnoj stranici dok je ponovno ne objavite.')) }}
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
                    :variant="$publishingPostWillPublish || $publishingPostIsArchived ? 'primary' : 'danger'"
                    :icon="$publishingPostIsArchived ? 'arrow-uturn-left' : ($publishingPostWillPublish ? 'rocket-launch' : 'eye-slash')"
                >
                    {{ $publishingPostWillPublish ? __('Objavi objavu') : __('Vrati u skicu') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="post-featured-confirm" x-on:close="$wire.cancelFeatured()" class="max-w-lg">
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

    <flux:modal name="post-archive-confirm" x-on:close="$wire.cancelArchive()" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Arhivirati objavu?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Objava će biti zaključana i uklonjena s javne stranice te iz istaknutih objava. Sadržaj i prethodni datum objave ostat će sačuvani dok je ne vratite u skicu.') }}
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

    <flux:modal name="post-delete-confirm" x-on:close="$wire.cancelDelete()" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $deletingPostStatus === 'archived' ? __('Izbrisati arhiviranu objavu?') : __('Izbrisati skicu?') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $deletingPostStatus === 'archived'
                        ? __('Arhivirana objava bit će uklonjena iz administracije. Ovu radnju nije moguće poništiti putem administracije.')
                        : __('Skica će biti uklonjena iz administracije. Ovu radnju nije moguće poništiti putem administracije.') }}
                </flux:text>
            </div>

            @if ($deletingPostTitle !== '')
                <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                    {{ $deletingPostTitle }}
                </div>
            @endif

            @if ($deletingPostGalleryTitle)
                <flux:callout color="zinc">
                    <flux:callout.heading icon="photo">{{ __('Povezana galerija ostaje sačuvana') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ trans_choice('Galerija „:title” s :count fotografijom neće biti obrisana.|Galerija „:title” s :count fotografije neće biti obrisana.|Galerija „:title” s :count fotografija neće biti obrisana.', $deletingPostGalleryPhotoCount, ['title' => $deletingPostGalleryTitle, 'count' => $deletingPostGalleryPhotoCount]) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" variant="danger" icon="trash">
                        {{ __('Obriši objavu') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</x-admin-ui::page>
