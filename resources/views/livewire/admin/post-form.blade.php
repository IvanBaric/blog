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
                <div class="mb-6">
                    <div class="flex items-center gap-2">
                        <h2 class="admin-panel-title">{{ __('Sadržaj') }}</h2>
                        <x-ui.help-tooltip :content="__('SEO naslov i opis automatski se popunjavaju iz naslova i sadržaja objave.')" />
                    </div>
                    <p class="admin-panel-description">{{ __('Naslov i glavni tekst objave koji se prikazuju na javnoj stranici.') }}</p>
                </div>

                <div class="space-y-5">
                    <flux:input wire:model="form.title.{{ $locale }}" :label="__('Naslov')" type="text" required autofocus />
                    <flux:editor wire:model="form.content.{{ $locale }}" :label="__('Sadržaj')" :description="__('Glavni tekst objave.')" />
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="admin-panel p-6">
                    <div class="space-y-5">
                        <div>
                            <h2 class="admin-panel-title">{{ __('Kategorija') }}</h2>
                            <p class="admin-panel-description mt-1">{{ __('Odaberite postojeću kategoriju ili dodajte novu izravno iz pretrage.') }}</p>
                        </div>

                        <flux:pillbox wire:model="form.categoryId" variant="combobox" :placeholder="__('Odaberi kategoriju...')">
                            <x-slot name="input">
                                <flux:pillbox.input wire:model.live.debounce.250ms="categorySearch" :placeholder="__('Pretraži kategorije...')" />
                            </x-slot>

                            @foreach ($this->categories as $category)
                                <flux:pillbox.option :wire:key="'category-'.$category->id" :value="$category->id">{{ $category->name }}</flux:pillbox.option>
                            @endforeach

                            <flux:pillbox.option.create modal="create-category">
                                {{ __('Dodaj novu kategoriju') }}
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

                        <flux:pillbox wire:model="form.tagIds" variant="combobox" multiple :placeholder="__('Odaberi oznake...')">
                            <x-slot name="input">
                                <flux:pillbox.input wire:model.live.debounce.250ms="tagSearch" :placeholder="__('Pretraži oznake...')" />
                            </x-slot>

                            @foreach ($this->tags as $tag)
                                <flux:pillbox.option :wire:key="'tag-'.$tag->id" :value="$tag->id">{{ $tag->name }}</flux:pillbox.option>
                            @endforeach

                            <flux:pillbox.option.create modal="create-tag">
                                {{ __('Dodaj novu oznaku') }}
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

                    <flux:select wire:model="form.status" :label="__('Status')">
                        @foreach (config('blog.statuses', []) as $value => $statusConfig)
                            <flux:select.option :value="$value">{{ __($statusConfig['label'] ?? $value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.published_at" :label="__('Datum objave')" type="datetime-local" />

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

    <flux:modal name="create-category" class="md:w-96">
        <form wire:submit="createCategory" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Dodaj novu kategoriju') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Unesite naziv nove kategorije objava.') }}</flux:text>
            </div>

            <flux:input wire:model="newCategoryName" :label="__('Naziv')" :placeholder="__('npr. Novosti')" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Dodaj kategoriju') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="create-tag" class="md:w-96">
        <form wire:submit="createTag" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Dodaj novu oznaku') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Unesite naziv nove oznake objava.') }}</flux:text>
            </div>

            <flux:input wire:model="newTagName" :label="__('Naziv')" :placeholder="__('npr. Istraživanje')" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Dodaj oznaku') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
