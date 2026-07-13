<section class="admin-page">
@if ($post?->status === 'archived')
    <x-admin-ui::editor-header
        :eyebrow="__('Arhivirana objava')"
        icon="archive-box"
        :context-as-title="true"
    >
        <x-slot:meta>
            <span class="inline-flex items-center gap-1.5">
                <flux:icon name="clock" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <span>{{ __('Arhivirano: :time', ['time' => $lastSavedAt ?: $post->updated_at?->format('d.m.Y. H:i')]) }}</span>
            </span>
            @if ($lastSavedBy)
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="user" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                    <span>{{ __('Arhivirao/la: :name', ['name' => $lastSavedBy]) }}</span>
                </span>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <flux:button :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'index')" wire:navigate variant="ghost" icon="arrow-left">
                {{ __('Sve objave') }}
            </flux:button>
            <flux:button
                type="button"
                wire:click="confirmDelete"
                wire:loading.attr="disabled"
                wire:target="confirmDelete"
                variant="danger"
                icon="trash"
            >
                {{ __('Obriši objavu') }}
            </flux:button>
            <flux:button
                type="button"
                wire:click="confirmRestore"
                wire:loading.attr="disabled"
                wire:target="confirmRestore"
                variant="primary"
                icon="arrow-uturn-left"
            >
                {{ __('Vrati u skicu') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::editor-header>

    <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <section class="admin-panel p-4 sm:p-6">
            <div class="space-y-5">
                <flux:callout color="zinc">
                    <flux:callout.heading icon="lock-closed">{{ __('Arhivirana objava je zaključana') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Sadržaj, status, datum, slike i povezane galerije ne mogu se mijenjati dok je objava u arhivi. Za nastavak uređivanja prvo je vratite u skicu.') }}</flux:callout.text>
                </flux:callout>

                <div>
                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Naslov') }}</div>
                    <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ $post->localized('title') }}</p>
                </div>

                <div>
                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Sadržaj') }}</div>
                    <article class="mt-3 text-sm leading-7 text-zinc-700 dark:text-zinc-300 [&_a]:text-accent-content [&_a]:no-underline [&_blockquote]:border-s-2 [&_blockquote]:border-zinc-300 [&_blockquote]:ps-4 [&_h2]:mb-3 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:mt-5 [&_h3]:font-semibold [&_li]:ms-5 [&_ol]:list-decimal [&_ol]:space-y-1 [&_p]:mb-4 [&_ul]:list-disc [&_ul]:space-y-1">
                        {!! $this->archivedContentHtml() ?: '<p>'.e(__('Nema sadržaja.')).'</p>' !!}
                    </article>
                </div>
            </div>
        </section>

        <aside class="min-w-0 space-y-6">
            <section class="admin-panel p-4 sm:p-6">
                <div class="space-y-4">
                    <h2 class="admin-panel-title">{{ __('Arhivski podaci') }}</h2>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Arhivirano') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Prethodno objavljeno') }}</dt>
                            <dd class="text-right font-medium tabular-nums text-zinc-800 dark:text-zinc-200">
                                {{ $post->published_at?->format('d.m.Y. H:i') ?: __('Nije bilo objavljeno') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            @php($gallerySummary = $this->gallerySummary)
            <section class="admin-panel p-4 sm:p-6">
                <div class="space-y-3">
                    <h2 class="admin-panel-title">{{ __('Povezana galerija') }}</h2>

                    @if ($gallerySummary['attached'])
                        <div>
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $gallerySummary['title'] }}</p>
                            <p class="mt-1 inline-flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="photo" class="size-4" />
                                {{ trans_choice('{1} :count fotografija|[2,4] :count fotografije|[5,*] :count fotografija', $gallerySummary['count'], ['count' => $gallerySummary['count']]) }}
                            </p>
                        </div>
                        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Galerija i fotografije ostaju sačuvane dok je objava arhivirana.') }}</p>
                    @else
                        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Objava nema povezanu galeriju.') }}</p>
                    @endif
                </div>
            </section>

            @if ($featuredImageUrl = $this->featuredImageUrl())
                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-3">
                        <h2 class="admin-panel-title">{{ __('Istaknuta slika') }}</h2>
                        <img src="{{ $featuredImageUrl }}" alt="{{ $post->localized('title') }}" class="aspect-video w-full select-none rounded-lg bg-zinc-50 object-contain grayscale contrast-75 opacity-70 dark:bg-zinc-900" />
                    </div>
                </section>
            @endif
        </aside>
    </div>

    <flux:modal name="post-detail-restore-confirm" x-on:close="$wire.cancelRestore()" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Vratiti arhiviranu objavu u skicu?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Objava će se vratiti među skice i ponovno će se moći uređivati. Prethodni datum objave bit će uklonjen, a objava neće biti javna dok je ponovno ne objavite.') }}
                </flux:text>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                {{ $post->localized('title') ?: __('Neimenovana objava') }}
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="restoreFromArchive" wire:loading.attr="disabled" wire:target="restoreFromArchive" variant="primary" icon="arrow-uturn-left">
                    {{ __('Vrati u skicu') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="post-detail-delete-confirm" x-on:close="$wire.cancelDelete()" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Izbrisati arhiviranu objavu?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Arhivirana objava bit će uklonjena iz administracije. Ovu radnju nije moguće poništiti putem administracije.') }}
                </flux:text>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                {{ $post->localized('title') ?: __('Neimenovana objava') }}
            </div>

            @php($gallerySummary = $this->gallerySummary)
            @if ($gallerySummary['attached'])
                <flux:callout color="zinc">
                    <flux:callout.heading icon="photo">{{ __('Povezana galerija ostaje sačuvana') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ trans_choice('Galerija „:title” s :count fotografijom neće biti obrisana.|Galerija „:title” s :count fotografije neće biti obrisana.|Galerija „:title” s :count fotografija neće biti obrisana.', $gallerySummary['count'], ['title' => $gallerySummary['title'], 'count' => $gallerySummary['count']]) }}
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
@else
    <form id="post-form" wire:submit="save" wire:poll.180000ms="autoSave" wire:loading.class="admin-panel-content-loading" wire:target="save" class="relative space-y-6">
        <x-admin-ui::loading-overlay target="save" :text="__('Spremanje...')" />

        <x-admin-ui::editor-header
            :eyebrow="$post?->exists ? __('Uredi objavu') : __('Nova objava')"
            icon="newspaper"
            :context-as-title="true"
        >
            <x-slot:meta>
                @if ($lastSavedAt)
                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="clock" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        <span>{{ __('Posljednje spremanje: :time', ['time' => $lastSavedAt]) }}</span>
                    </span>
                    @if ($lastSavedBy)
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="user" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                            <span>{{ __('Uredio/la: :name', ['name' => $lastSavedBy]) }}</span>
                        </span>
                    @endif
                @else
                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="clock" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        <span>{{ __('Objava još nije spremljena.') }}</span>
                    </span>
                @endif
            </x-slot:meta>

            <x-slot:actions>
                <flux:button :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'index')" wire:navigate variant="ghost" icon="arrow-left">
                    {{ __('Sve objave') }}
                </flux:button>
                @if ($publicPostUrl = $this->publicPostUrl())
                    @if ($this->publicPostCanBeViewed())
                        <flux:button :href="$publicPostUrl" target="_blank" variant="filled" icon-trailing="arrow-up-right">
                            {{ __('Pogledaj objavu') }}
                        </flux:button>

                    @else
                        <flux:tooltip :content="__('Objava je skica i ne može se javno vidjeti dok nije objavljena.')" position="bottom">
                            <flux:button type="button" variant="filled" icon="exclamation-triangle" disabled>
                                {{ __('Pogledaj objavu') }}
                            </flux:button>
                        </flux:tooltip>
                    @endif
                @endif
                <x-admin-ui::submit-button target="save">
                    {{ __('Spremi objavu') }}
                </x-admin-ui::submit-button>
            </x-slot:actions>
        </x-admin-ui::editor-header>

        <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="min-w-0 space-y-6">
                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-5">
                        <flux:input wire:model="form.title.{{ $locale }}" :label="__('Naslov')" type="text" data-required autofocus />
                        <flux:editor wire:model="form.content.{{ $locale }}" :label="__('Sadržaj')" :description="__('Glavni tekst objave.')" class="admin-rich-text-editor-tall" />
                    </div>
                </section>

            <div class="grid min-w-0 gap-6 lg:grid-cols-2">
                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-5">
                        <div>
                            <h2 class="admin-panel-title">{{ __('Kategorije') }}</h2>
                            <p class="admin-panel-description mt-1">{{ __('Odaberite jednu ili više kategorija ili dodajte novu izravno iz pretrage.') }}</p>
                        </div>

                        <flux:pillbox wire:model.live="form.categoryUuids" variant="combobox" multiple :placeholder="__('Odaberi kategorije...')">
                            <x-slot name="input">
                                <flux:pillbox.input wire:model.live.debounce.200ms="categorySearch" :placeholder="__('Pretraži kategorije...')" />
                            </x-slot>

                            @foreach ($this->categories as $category)
                                <flux:pillbox.option :wire:key="'category-'.$category->uuid" :value="(string) $category->uuid">{{ $category->name }}</flux:pillbox.option>
                            @endforeach

                            <flux:pillbox.option.create wire:click="createCategory" min-length="2">
                                {{ __('Dodaj kategoriju') }} "<span wire:text="categorySearch"></span>"
                            </flux:pillbox.option.create>
                        </flux:pillbox>
                    </div>
                </section>

                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-5">
                        <div>
                            <h2 class="admin-panel-title">{{ __('Oznake') }}</h2>
                            <p class="admin-panel-description mt-1">{{ __('Dodajte jednu ili više oznaka za tematsko povezivanje objava.') }}</p>
                        </div>

                        <flux:pillbox wire:model.live="form.tagUuids" variant="combobox" multiple :placeholder="__('Odaberi oznake...')">
                            <x-slot name="input">
                                <flux:pillbox.input wire:model.live.debounce.200ms="tagSearch" :placeholder="__('Pretraži oznake...')" />
                            </x-slot>

                            @foreach ($this->tags as $tag)
                                <flux:pillbox.option :wire:key="'tag-'.$tag->uuid" :value="(string) $tag->uuid">{{ $tag->name }}</flux:pillbox.option>
                            @endforeach

                            <flux:pillbox.option.create wire:click="createTag" min-length="2">
                                {{ __('Dodaj oznaku') }} "<span wire:text="tagSearch"></span>"
                            </flux:pillbox.option.create>
                        </flux:pillbox>
                    </div>
                </section>
            </div>
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="admin-panel p-4 sm:p-6">
                <div class="space-y-5">
                    <flux:select wire:model="form.status" variant="listbox" :label="__('Status')">
                        <flux:select.option value="draft">{{ __('Skica') }}</flux:select.option>
                        <flux:select.option value="published">{{ __('Objavljeno') }}</flux:select.option>
                        @if ($post?->exists)
                            <flux:select.option value="archived">{{ __('Arhivirano') }}</flux:select.option>
                        @endif
                    </flux:select>

                    @if ($publicationNotice = $this->publicationVisibilityNotice())
                        <flux:callout
                            wire:key="publication-visibility-notice-{{ $publicationNotice['color'] }}"
                            wire:transition
                            :color="$publicationNotice['color']"
                        >
                            <flux:callout.heading :icon="$publicationNotice['icon']">{{ $publicationNotice['heading'] }}</flux:callout.heading>
                            <flux:callout.text>{{ $publicationNotice['text'] }}</flux:callout.text>
                        </flux:callout>
                    @endif
                </div>
            </section>

            <section class="admin-panel p-4 sm:p-6">
                <div class="space-y-5">
                    <div>
                        <h2 class="admin-panel-title">{{ __('Datum objave') }}</h2>
                        <p class="admin-panel-description mt-1">{{ __('Odredite kada će objava postati dostupna posjetiteljima.') }}</p>
                    </div>

                    <div class="space-y-4">
                        <flux:checkbox wire:model.live="schedulePublication" :label="__('Postavi datum objave')" />

                        @if ($schedulePublication)
                            <div class="space-y-4">
                                <flux:date-picker
                                    wire:model.live="publishedDate"
                                    type="input"
                                    :invalid="$errors->has('publishedDate')"
                                    :data-admin-date-picker-invalid="$errors->has('publishedDate') ? 'true' : 'false'"
                                    locale="hr-HR"
                                    start-day="1"
                                    selectable-header
                                    clearable
                                    :label="__('Datum objave')"
                                    :placeholder="__('Odaberite datum')"
                                />

                                <flux:time-picker
                                    wire:model.live="publishedTime"
                                    type="input"
                                    time-format="24-hour"
                                    clearable
                                    :label="__('Vrijeme objave')"
                                />
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            @if (config('blog.features.featured_posts'))
                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-4">
                        <h2 class="admin-panel-title">{{ __('Istaknuta objava') }}</h2>

                        <div class="space-y-2">
                            <flux:checkbox
                                wire:model.live="form.is_featured"
                                :label="__('Istakni ovu objavu')"
                                :disabled="$post?->status !== 'published'"
                            />

                            @if ($post?->status !== 'published')
                                <p class="ps-7 text-sm leading-5 text-zinc-500 dark:text-zinc-400">
                                    {{ __('Objavu možete istaknuti nakon što je objavite i spremite promjene.') }}
                                </p>
                            @elseif ($form->is_featured)
                                <p wire:transition class="ps-7 text-sm leading-5 text-zinc-500 dark:text-zinc-400">
                                    {{ __('Prikazivat će se u bloku „Istaknute objave” na stranicama na kojima je taj blok uključen.') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            @if (config('blog.media.enabled'))
                <section class="admin-panel p-4 sm:p-6">
                    <x-admin-ui::media-upload
                        wire-model="form.featuredImageUpload"
                        :file="$form->featuredImageUpload"
                        :existing-url="$this->featuredImageUrl()"
                        :label="__('Istaknuta slika')"
                        :help="corexis_image_upload()->helpText()"
                        size="w-full aspect-video"
                        remove-action="removeFeaturedImage"
                    />
                </section>
            @endif

            @if ($post?->exists)
                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-5">
                        <div>
                            <h2 class="admin-panel-title">{{ __('Galerija') }}</h2>
                            @php($gallerySummary = $this->gallerySummary)
                            <p class="admin-panel-description mt-1">
                                {{ $gallerySummary['attached']
                                    ? trans_choice('Povezana galerija „:title” sadrži :count fotografiju.|Povezana galerija „:title” sadrži :count fotografije.|Povezana galerija „:title” sadrži :count fotografija.', $gallerySummary['count'], ['title' => $gallerySummary['title'], 'count' => $gallerySummary['count']])
                                    : __('Objava nema povezanu galeriju. Odaberite dostupnu galeriju za povezivanje.') }}
                            </p>
                        </div>

                        <x-gallery::standalone-selector
                            :model="$post"
                            collection="images"
                            :empty-only="false"
                            :allow-replace="true"
                            :replace-after-detach-only="true"
                            :label="__('Samostalna galerija')"
                            :placeholder="__('Odaberite galeriju')"
                            :button-label="__('Poveži galeriju')"
                        />
                    </div>
                </section>
            @else
                <section class="admin-panel p-4 sm:p-6">
                    <div class="space-y-2">
                        <h2 class="admin-panel-title">{{ __('Galerija') }}</h2>
                        <p class="admin-panel-description">{{ __('Galeriju možete povezati nakon prvog spremanja objave.') }}</p>
                    </div>
                </section>
            @endif

        </aside>
        </div>
    </form>

    @if ($post?->exists)
        <flux:modal name="post-detail-archive-confirm" x-on:close="$wire.cancelArchive()" class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Arhivirati objavu?') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Trenutačne izmjene prvo će se spremiti. Objava će zatim biti zaključana i uklonjena s javne stranice te iz istaknutih objava. Povezana galerija i fotografije ostat će sačuvane.') }}
                    </flux:text>
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 text-sm font-medium text-zinc-700 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-white/10">
                    {{ $post->localized('title') ?: __('Neimenovana objava') }}
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="button" wire:click="archiveAndSave" wire:loading.attr="disabled" wire:target="archiveAndSave" variant="danger" icon="archive-box">
                        {{ __('Spremi i arhiviraj') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
@endif
</section>
