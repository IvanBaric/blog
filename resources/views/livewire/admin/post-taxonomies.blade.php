@php($taxonomyIcon = $type === 'category' ? 'folder' : 'tag')

<x-admin-ui::page>
    <x-admin-ui::page-header :title="$this->title()" :description="$this->descriptionText()" :icon="$taxonomyIcon">
        <x-slot:actions>
            <flux:button type="button" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate" variant="primary" icon="plus">
                {{ $type === 'category' ? __('Nova kategorija') : __('Nova oznaka') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::page-header>

    <x-admin-ui::panel loading loading-target="search,save,update,delete,sortBy,nextPage,previousPage,gotoPage" loading-text="{{ __('Ažuriram pregled stavki...') }}">
        @if ($this->totalItems > 0)
            <div class="admin-panel-header">
                <div id="table">
                    <h2 class="admin-panel-title">{{ $type === 'category' ? __('Popis kategorija') : __('Popis oznaka') }}</h2>
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
        @endif

        @if ($this->items->isEmpty())
            <x-admin-ui::empty-state
                :title="$type === 'category' ? __('Još nema kategorija') : __('Još nema oznaka')"
                :description="$search !== '' ? __('Nema stavki koje odgovaraju pretrazi.') : ($type === 'category' ? __('Dodajte prvu kategoriju kako biste objave organizirali u jasne cjeline.') : __('Dodajte prvu oznaku kako biste povezali objave prema temama.'))"
            >
                <x-slot:icon>
                    <flux:icon :name="$taxonomyIcon" class="size-6" />
                </x-slot:icon>

                @if ($search !== '')
                    <x-slot:actions>
                        <flux:button wire:click="resetSearch" variant="ghost" icon="x-mark">
                            {{ __('Očisti pretragu') }}
                        </flux:button>
                    </x-slot:actions>
                @else
                    <x-slot:actions>
                        <flux:button type="button" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate" variant="primary" icon="plus">
                            {{ $type === 'category' ? __('Dodaj prvu kategoriju') : __('Dodaj prvu oznaku') }}
                        </flux:button>
                    </x-slot:actions>
                @endif
            </x-admin-ui::empty-state>
        @else
            <div class="admin-list-header hidden grid-cols-[minmax(0,1fr)_8rem_5rem] uppercase md:grid">
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
                    <article wire:key="taxonomy-item-{{ $item->uuid }}" class="admin-list-row admin-taxonomy-list-row grid-cols-1 gap-3 px-5 py-4 md:grid-cols-[minmax(0,1fr)_8rem_5rem] md:px-6">
                        <div class="flex min-w-0 items-start gap-3.5">
                            <span class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content ring-1 ring-accent/10 dark:bg-accent/15 dark:ring-accent/20">
                                <flux:icon :name="$taxonomyIcon" class="size-4" />
                            </span>

                            <div class="min-w-0">
                                <button
                                    type="button"
                                    wire:click="edit('{{ $item->uuid }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="edit('{{ $item->uuid }}')"
                                    class="block min-w-0 cursor-pointer text-left text-[15px] font-semibold text-zinc-950 transition hover:text-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/30 disabled:cursor-wait dark:text-white dark:hover:text-accent"
                                >
                                    <span class="block truncate">{{ $item->name }}</span>
                                </button>

                                @if ($item->description)
                                    <p class="mt-1 line-clamp-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $item->description }}</p>
                                @else
                                    <p class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Bez opisa') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 md:justify-start">
                            <span class="text-xs font-medium uppercase text-zinc-500 md:hidden">{{ __('Objave') }}</span>
                            <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-zinc-600 ring-1 ring-inset ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-white/10">
                                {{ $this->postCount($item) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-end gap-1">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Akcije')" />

                                <flux:menu>
                                    <flux:menu.item as="button" type="button" wire:click="edit('{{ $item->uuid }}')" icon="pencil-square">
                                        {{ __('Uredi') }}
                                    </flux:menu.item>

                                    <flux:menu.item as="button" type="button" wire:click="confirmDelete('{{ $item->uuid }}')" icon="trash" variant="danger">
                                        {{ __('Obriši') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->items->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <flux:pagination :paginator="$this->items" scroll-to="#table" />
                </div>
            @endif
        @endif
    </x-admin-ui::panel>

    <flux:modal name="taxonomy-create" x-on:close="$wire.cancelCreate()" class="w-[calc(100vw-2rem)] max-w-2xl sm:w-[42rem]">
        <form wire:submit="save" wire:loading.class="admin-panel-content-loading" wire:target="save" class="relative space-y-6">
            <x-admin-ui::loading-overlay target="save" :text="__('Spremanje...')" />
            <div>
                <flux:heading size="lg">{{ $type === 'category' ? __('Nova kategorija') : __('Nova oznaka') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $type === 'category' ? __('Kategorija okuplja objave u jednu glavnu cjelinu.') : __('Oznaka povezuje objave prema zajedničkoj temi.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="createForm.name"
                :label="__('Naziv')"
                :placeholder="$type === 'category' ? __('Primjer: Radionice') : __('Primjer: recikliranje')"
                data-required
                autofocus
            />

            <flux:textarea
                wire:model="createForm.description"
                :label="__('Opis')"
                :placeholder="__('Kratko pojasnite kada se ova stavka koristi.')"
                rows="4"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <x-admin-ui::submit-button target="save" icon="plus">
                    {{ $type === 'category' ? __('Dodaj kategoriju') : __('Dodaj oznaku') }}
                </x-admin-ui::submit-button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="taxonomy-edit" x-on:close="$wire.cancelEdit()" class="w-[calc(100vw-2rem)] max-w-2xl sm:w-[42rem]">
        <form wire:submit="update" wire:loading.class="admin-panel-content-loading" wire:target="update" class="relative space-y-6">
            <x-admin-ui::loading-overlay target="update" :text="__('Spremanje...')" />
            <div>
                <flux:heading size="lg">{{ $type === 'category' ? __('Uredi kategoriju') : __('Uredi oznaku') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Ažurirajte naziv i opis stavke.') }}</flux:text>
            </div>

            <flux:input wire:model="editForm.name" :label="__('Naziv')" data-required />
            <flux:textarea wire:model="editForm.description" :label="__('Opis')" rows="5" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <x-admin-ui::submit-button target="update">{{ __('Spremi') }}</x-admin-ui::submit-button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="taxonomy-delete" x-on:close="$wire.cancelDelete()" class="w-[calc(100vw-2rem)] max-w-lg sm:w-[32rem]">
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
</x-admin-ui::page>
