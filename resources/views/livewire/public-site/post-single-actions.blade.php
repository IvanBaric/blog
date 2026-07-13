@php
    $hasActions = filled($editUrl) || $canCycleSinglePostLayout;
    $buttonClass = 'inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white/80 text-zinc-500 shadow-sm shadow-zinc-950/5 ring-1 ring-zinc-950/10 transition duration-200 focus:outline-none focus:ring-2 focus:ring-[color:var(--niva-primary-200)] focus:ring-offset-2 hover:bg-[color:var(--niva-primary-50)] hover:text-[color:var(--niva-primary-800)] hover:ring-[color:var(--niva-primary-200)] disabled:cursor-wait disabled:opacity-60 dark:bg-zinc-950/70 dark:text-zinc-300 dark:ring-white/10 dark:focus:ring-[color:var(--niva-primary-700)] dark:hover:bg-zinc-900 dark:hover:text-[color:var(--niva-primary-200)]';
@endphp

<div class="contents">
    @if ($hasActions)
        <div class="flex shrink-0 items-center gap-2" data-public-post-single-actions>
            @if ($editUrl)
                <flux:tooltip :content="__('Uredi objavu')" position="bottom">
                    <a
                        href="{{ $editUrl }}"
                        data-public-post-edit-link="{{ $postUuid }}"
                        class="{{ $buttonClass }}"
                        aria-label="{{ __('Uredi objavu') }}"
                        title="{{ __('Uredi objavu') }}"
                    >
                        <flux:icon name="pencil" class="size-4" />
                        <span class="sr-only">{{ __('Uredi objavu') }}</span>
                    </a>
                </flux:tooltip>
            @endif

            @if ($canCycleSinglePostLayout)
                <flux:tooltip :content="__('Sljedeći izgled objave: :layout', ['layout' => $nextSinglePostLayoutLabel])" position="bottom">
                    <button
                        type="button"
                        wire:click="cycleSinglePostLayout"
                        wire:loading.attr="disabled"
                        wire:target="cycleSinglePostLayout"
                        data-public-post-layout-cycle="{{ $sectionUuid }}"
                        class="{{ $buttonClass }}"
                        aria-label="{{ __('Promijeni izgled objave') }}"
                        title="{{ __('Promijeni izgled objave') }}"
                    >
                        <flux:icon name="layout-grid" class="size-4" wire:loading.remove wire:target="cycleSinglePostLayout" />
                        <flux:icon.loading class="size-4" wire:loading wire:target="cycleSinglePostLayout" />
                        <span class="sr-only">{{ __('Promijeni izgled objave') }}</span>
                    </button>
                </flux:tooltip>
            @endif
        </div>
    @endif
</div>
