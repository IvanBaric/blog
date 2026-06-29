<section class="admin-page">
    <div class="admin-page-header">
        <div class="admin-page-header-copy">
            <h1 class="admin-page-title">{{ $this->title() }}</h1>
            <flux:text class="admin-page-description">
                {{ $this->descriptionText() }}
            </flux:text>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[24rem_minmax(0,1fr)]">
        <x-admin-ui::panel as="form" wire:submit="save" class="self-start overflow-hidden">
            <div class="admin-panel-header">
                <div class="flex items-start gap-3">
                    <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent-content ring-1 ring-accent/15 dark:bg-accent/15 dark:ring-accent/25">
                        <flux:icon name="tag" class="size-5" />
                    </span>

                    <div>
                        <h2 class="admin-panel-title">{{ $type === 'category' ? __('Nova kategorija') : __('Nova oznaka') }}</h2>
                        <p class="admin-panel-description">
                            {{ $type === 'category' ? __('Dodajte jasnu kategoriju za organizaciju objava.') : __('Dodajte kratku oznaku koja povezuje srodne objave.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-5 border-t border-zinc-100/80 p-5 dark:border-zinc-800/80">
                <flux:input
                    wire:model="createForm.name"
                    :label="__('Naziv')"
                    :placeholder="$type === 'category' ? __('Primjer: Radionice') : __('Primjer: recikliranje')"
                    autofocus
                />

                <flux:textarea
                    wire:model="createForm.description"
                    :label="__('Opis')"
                    :placeholder="__('Kratko pojasnite kada se ova stavka koristi.')"
                    rows="5"
                />

                <p class="text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    {{ __('Naziv je obavezan. Opis je opcionalan i pomaže urednicima da dosljedno koriste stavke.') }}
                </p>

                <flux:button type="submit" variant="primary" icon="plus" class="w-full justify-center">
                    {{ $type === 'category' ? __('Dodaj kategoriju') : __('Dodaj oznaku') }}
                </flux:button>
            </div>
        </x-admin-ui::panel>

        <x-admin-ui::panel loading loading-target="search,update,delete,sortBy,nextPage,previousPage,gotoPage" loading-text="{{ __('Ažuriram pregled stavki...') }}">
            <div class="admin-panel-header">
                <div id="table">
                    <h2 class="admin-panel-title">{{ $this->title() }}</h2>
                    <p class="admin-panel-description">
                        {{ trans_choice('{0} Još nema stavki.|{1} :count stavka ukupno.|[2,*] :count stavki ukupno.', $this->totalItems, ['count' => $this->totalItems]) }}
                    </p>
                </div>

                <div class="w-full sm:w-72">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        :placeholder="$type === 'category' ? __('Pretraži kategorije...') : __('Pretraži oznake...')"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
            </div>

            @if ($this->items->isEmpty())
                <div class="px-6 py-14 text-center">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-accent/10 text-accent-content ring-1 ring-accent/15 dark:bg-accent/15 dark:ring-accent/25">
                        <flux:icon name="tag" class="size-6" />
                    </div>

                    <h3 class="mt-4 text-base font-semibold text-zinc-950 dark:text-white">
                        {{ $type === 'category' ? __('Nema kategorija') : __('Nema oznaka') }}
                    </h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $search !== '' ? __('Promijenite pretragu ili dodajte novu stavku preko forme.') : __('Dodajte prvu stavku preko forme s lijeve strane.') }}
                    </p>

                    @if ($search !== '')
                        <flux:button wire:click="resetSearch" size="sm" variant="ghost" icon="x-mark" class="mt-4">
                            {{ __('Očisti pretragu') }}
                        </flux:button>
                    @endif
                </div>
            @else
                <div class="admin-list-header hidden grid-cols-[minmax(0,1fr)_7rem_8rem] md:grid">
                    <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 text-left">
                        <span>{{ __('Naziv') }}</span>
                        <flux:icon :name="$this->sortIcon('name')" class="size-3.5" />
                    </button>
                    <button type="button" wire:click="sortBy('posts')" class="inline-flex items-center gap-1 text-left">
                        <span>{{ __('Objave') }}</span>
                        <flux:icon :name="$this->sortIcon('posts')" class="size-3.5" />
                    </button>
                    <span class="text-right">{{ __('Akcije') }}</span>
                </div>

                <div>
                    @foreach ($this->items as $item)
                        <article wire:key="taxonomy-item-{{ $item->uuid }}" class="admin-list-row admin-taxonomy-list-row grid-cols-1 gap-3 p-5 md:grid-cols-[minmax(0,1fr)_7rem_8rem]">
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold text-zinc-950 dark:text-white">{{ $item->name }}</p>

                                @if ($item->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $item->description }}</p>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-3 md:block">
                                <span class="text-xs font-medium uppercase text-zinc-500 md:hidden">{{ __('Objave') }}</span>
                                <span class="text-sm font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $this->postCount($item) }}</span>
                            </div>

                            <div class="flex items-center justify-end gap-1">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Akcije')" />

                                    <flux:menu>
                                        <flux:modal.trigger name="taxonomy-edit">
                                            <flux:menu.item as="button" type="button" wire:click="edit('{{ $item->uuid }}')" icon="pencil-square">
                                                {{ __('Uredi') }}
                                            </flux:menu.item>
                                        </flux:modal.trigger>

                                        <flux:modal.trigger name="taxonomy-delete">
                                            <flux:menu.item as="button" type="button" wire:click="confirmDelete('{{ $item->uuid }}')" icon="trash" variant="danger">
                                                {{ __('Obriši') }}
                                            </flux:menu.item>
                                        </flux:modal.trigger>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <flux:pagination :paginator="$this->items" scroll-to="#table" />
                </div>
            @endif
        </x-admin-ui::panel>
    </div>

    <flux:modal name="taxonomy-edit" class="max-w-xl">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $type === 'category' ? __('Uredi kategoriju') : __('Uredi oznaku') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Ažurirajte naziv i opis stavke.') }}</flux:text>
            </div>

            <flux:input wire:model="editForm.name" :label="__('Naziv')" />
            <flux:textarea wire:model="editForm.description" :label="__('Opis')" rows="4" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">{{ __('Spremi') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="taxonomy-delete" class="max-w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $type === 'category' ? __('Obrisati kategoriju?') : __('Obrisati oznaku?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Ova radnja uklanja stavku iz popisa i iz povezanih objava.') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="delete" variant="danger" icon="trash">{{ __('Obriši') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
