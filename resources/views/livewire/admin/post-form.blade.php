<section class="admin-page">
    <div class="admin-page-header">
        <div class="admin-page-header-copy">
            <h1 class="admin-page-title">{{ $post?->exists ? __('Uredi objavu') : __('Nova objava') }}</h1>
            <flux:text class="admin-page-description">
                {{ __('Uredite sadržaj, objavu, kategoriju i oznake. Slug se automatski generira iz naslova.') }}
            </flux:text>
        </div>

        <div class="admin-page-actions">
            <flux:button :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'index')" wire:navigate variant="ghost" icon="arrow-left">
                {{ __('Sve objave') }}
            </flux:button>
            <flux:button type="submit" form="post-form" variant="primary" icon="check">
                {{ __('Spremi objavu') }}
            </flux:button>
        </div>
    </div>

    <form id="post-form" wire:submit="save" wire:loading.class="admin-panel-content-loading" wire:target="save" class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <x-admin-ui::loading-overlay target="save" :text="__('Spremam objavu...')" />

        <div class="space-y-6">
            <section class="admin-panel p-6">
                <div class="space-y-5">
                    <flux:input wire:model="form.title.{{ $locale }}" :label="__('Naslov')" type="text" required autofocus />
                    <flux:editor wire:model="form.content.{{ $locale }}" :label="__('Sadržaj')" :description="__('Glavni tekst objave.')" class="**:data-[slot=content]:min-h-[30rem]!" />
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="admin-panel p-6">
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

                <section class="admin-panel p-6">
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

        <aside class="space-y-6">
            <section class="admin-panel p-6">
                <div class="space-y-5">
                    <div>
                        <h2 class="admin-panel-title">{{ __('Objava') }}</h2>
                        <p class="admin-panel-description mt-1">{{ __('Postavite status, datum objave i istaknutost.') }}</p>
                    </div>

                    <flux:select wire:model="form.status" variant="listbox" :label="__('Status')">
                        @foreach (config('blog.statuses', []) as $value => $statusConfig)
                            <flux:select.option :value="$value">{{ __($statusConfig['label'] ?? $value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="space-y-4">
                        <flux:checkbox wire:model.live="schedulePublication" :label="__('Postavi datum objave')" />

                        @if ($schedulePublication)
                            <div class="space-y-4">
                                <flux:input
                                    wire:model.live="publishedDate"
                                    type="date"
                                    :label="__('Datum objave')"
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

                    @if (config('blog.features.featured_posts'))
                        <flux:checkbox wire:model="form.is_featured" :label="__('Istaknuta objava')" />
                    @endif
                </div>
            </section>

            @if (config('blog.media.enabled'))
                <section class="admin-panel p-6">
                    <div class="mb-6">
                        <div class="flex items-center gap-2">
                            <h2 class="admin-panel-title">{{ __('Naslovna slika') }}</h2>
                            <x-ui.help-tooltip :content="__('Slika se koristi i za dijeljenje objave na tražilicama i društvenim mrežama.')" />
                        </div>
                        <p class="admin-panel-description">{{ __('Istaknuta slika za prikaz uz objavu.') }}</p>
                    </div>

                    <x-admin-ui::media-upload
                        wire-model="form.featuredImageUpload"
                        :file="$form->featuredImageUpload"
                        :existing-url="$this->featuredImageUrl()"
                        :label="__('Istaknuta slika')"
                        size="w-full aspect-[4/3]"
                        remove-action="removeFeaturedImage"
                    />
                </section>
            @endif

            @if ($post?->exists)
                <section class="admin-panel p-6">
                    <div class="space-y-5">
                        <div>
                            <h2 class="admin-panel-title">{{ __('Galerija') }}</h2>
                            <p class="admin-panel-description mt-1">{{ __('Pregledajte povezanu galeriju ili odaberite drugu dostupnu galeriju.') }}</p>
                        </div>

                        <x-gallery::standalone-selector
                            :model="$post"
                            collection="images"
                            :empty-only="false"
                            :allow-replace="true"
                            :label="__('Samostalna galerija')"
                            :placeholder="__('Odaberite galeriju')"
                            :button-label="__('Poveži galeriju')"
                        />
                    </div>
                </section>
            @else
                <section class="admin-panel p-6">
                    <div class="space-y-2">
                        <h2 class="admin-panel-title">{{ __('Galerija') }}</h2>
                        <p class="admin-panel-description">{{ __('Galeriju možete povezati nakon prvog spremanja objave.') }}</p>
                    </div>
                </section>
            @endif

            <section class="admin-panel p-6">
                <div class="flex flex-col gap-3">
                    <flux:button type="submit" variant="primary" icon="check" class="w-full justify-center">
                        {{ __('Spremi objavu') }}
                    </flux:button>
                    <flux:button :href="route(config('blog.routes.admin_name_prefix', 'admin.blog.').'index')" wire:navigate variant="ghost" class="w-full justify-center">
                        {{ __('Odustani') }}
                    </flux:button>
                </div>
            </section>
        </aside>
    </form>
</section>
