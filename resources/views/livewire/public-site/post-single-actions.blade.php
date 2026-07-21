@php
    $hasActions = $canEditPost || $canCycleSinglePostLayout;
@endphp

<div class="contents">
    <x-admin-ui::action-loading target="openPostEditor,cycleSinglePostLayout,__dispatch" :text="__('Učitavanje...')" />

    @if ($hasActions)
        <flux:dropdown position="bottom" align="end" data-public-post-single-actions data-public-post-actions-menu>
            <flux:button
                type="button"
                variant="ghost"
                size="sm"
                icon="ellipsis-horizontal"
                :aria-label="__('Akcije objave')"
                class="!size-8 !rounded-full !bg-white/80 !p-0 !text-zinc-500 !shadow-sm !ring-1 !ring-zinc-950/10 transition duration-200 hover:!bg-[color:var(--niva-primary-50)] hover:!text-[color:var(--niva-primary-800)] focus:!ring-2 focus:!ring-[color:var(--niva-primary-200)] focus:!ring-offset-2 dark:!bg-zinc-950/70 dark:!text-zinc-300 dark:!ring-white/10 dark:hover:!bg-zinc-900 dark:hover:!text-[color:var(--niva-primary-200)]"
            />

            <flux:menu class="min-w-56">
                @if ($canEditPost)
                    <flux:menu.item
                        as="button"
                        type="button"
                        wire:click="openPostEditor"
                        wire:loading.attr="disabled"
                        wire:target="openPostEditor"
                        icon="pencil-square"
                        data-public-post-edit-link="{{ $postUuid }}"
                    >
                        {{ __('Uredi objavu') }}
                    </flux:menu.item>
                @endif

                @if ($canEditPost && $canCycleSinglePostLayout)
                    <flux:menu.separator />
                @endif

                @if ($canCycleSinglePostLayout)
                    <flux:menu.item
                        as="button"
                        type="button"
                        wire:click="$dispatch('pages-open-public-section-editor', { sectionUuid: '{{ $sectionUuid }}', editorTab: 'single' })"
                        icon="layout-grid"
                        data-public-post-layout-cycle="{{ $sectionUuid }}"
                    >
                        {{ __('Promijeni izgled objave') }}
                    </flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>
    @endif

    @if ($canEditPost)
        <flux:modal
            :name="$this->editorModalName()"
            x-on:close="$wire.cancelPostEditor()"
            flyout
            variant="floating"
            :closable="false"
            class="w-full p-0! md:w-2xl xl:w-5xl"
            data-public-post-editor="{{ $postUuid }}"
        >
            @if ($editingPostUuid && $post)
                <div
                    x-data="{ saving: false }"
                    x-on:pages-save-finished.window="saving = false"
                    class="min-w-0"
                >
                    <div class="sticky top-0 z-40 flex min-h-14 items-center justify-between gap-3 border-b border-zinc-200 bg-white/95 px-4 py-2 backdrop-blur sm:px-6 dark:border-zinc-700 dark:bg-zinc-800/95">
                        <flux:heading size="lg" class="truncate">{{ __('Uredi objavu') }}</flux:heading>

                        <div class="flex shrink-0 items-center gap-1">
                            <flux:button
                                type="button"
                                variant="primary"
                                size="sm"
                                data-admin-submit-button
                                x-on:click="saving = true"
                                x-bind:disabled="saving"
                                wire:click="$dispatch('pages-save-section-editor')"
                            >
                                <span x-show="! saving" class="inline-flex items-center gap-2">
                                    <flux:icon name="check" class="size-4 shrink-0" />
                                    <span>{{ __('Spremi') }}</span>
                                </span>
                                <span x-cloak x-show="saving" class="inline-flex items-center gap-2">
                                    <span class="admin-submit-spinner" aria-hidden="true"></span>
                                    <span>{{ __('Spremanje...') }}</span>
                                </span>
                            </flux:button>

                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" size="sm" icon="x-mark" :aria-label="__('Zatvori')" />
                            </flux:modal.close>
                        </div>
                    </div>

                    <div class="relative min-w-0 px-4 pb-6 pt-4 sm:px-6">
                        <div x-cloak x-show="saving" x-transition.opacity.duration.150ms class="pointer-events-none absolute inset-0 z-30 flex items-start justify-center bg-white/35 pt-4 backdrop-blur-[1px] dark:bg-zinc-950/25">
                            <div class="admin-loading-pill">
                                <span class="admin-loading-spinner" aria-hidden="true"></span>
                                <span>{{ __('Spremanje...') }}</span>
                            </div>
                        </div>

                        <div x-bind:class="{ 'admin-panel-content-loading': saving }">
                            @livewire(
                                \IvanBaric\Blog\Livewire\Admin\PostForm::class,
                                ['post' => $post, 'embedded' => true, 'publicFlyout' => true],
                                key('public-post-single-editor-'.$editingPostUuid)
                            )
                        </div>
                    </div>
                </div>
            @endif
        </flux:modal>
    @endif
</div>
