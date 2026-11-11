@if (count($tabs = $this->getCachedTabs()))
    @php
        $activeTab = strval($this->activeTab);
        $renderHookScopes = $this->getRenderHookScopes();
    @endphp

    {{-- Tampilan Mobile / HP: Dropdown Filter Standar Filament --}}
    <div class="sm:hidden">
        <x-filament::input.wrapper
            :prefix="'Status'"
            :prefix-icon="'heroicon-m-funnel'"
        >
            <x-filament::input.select
                id="mobile-tab-select-{{ $this->getId() }}"
                wire:model.live="activeTab"
            >
                @foreach ($tabs as $tabKey => $tab)
                    @php
                        $tabKeyStr = strval($tabKey);
                        $badge = $tab->getBadge();
                        $label = $tab->getLabel() ?? $this->generateTabLabel($tabKeyStr);
                        $text = filled($badge) ? "{$label} ({$badge})" : $label;
                    @endphp
                    <option value="{{ $tabKeyStr }}" @selected($activeTab === $tabKeyStr)>
                        {{ $text }}
                    </option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    {{-- Tampilan Desktop / Layar Lebar: Tabs Bar Rapi & Compact --}}
    <div class="hidden sm:flex sm:items-center">
        <x-filament::tabs class="w-fit !mx-0">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_TABS_START, scopes: $renderHookScopes) }}
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABS_START, scopes: $renderHookScopes) }}

            @foreach ($tabs as $tabKey => $tab)
                @php
                    $tabKey = strval($tabKey);
                @endphp

                <x-filament::tabs.item
                    :active="$activeTab === $tabKey"
                    :badge="$tab->getBadge()"
                    :badge-color="$tab->getBadgeColor()"
                    :badge-icon="$tab->getBadgeIcon()"
                    :badge-icon-position="$tab->getBadgeIconPosition()"
                    :icon="$tab->getIcon()"
                    :icon-position="$tab->getIconPosition()"
                    :wire:click="'$set(\'activeTab\', ' . (filled($tabKey) ? ('\'' . $tabKey . '\'') : 'null') . ')'"
                    :attributes="$tab->getExtraAttributeBag()"
                >
                    {{ $tab->getLabel() ?? $this->generateTabLabel($tabKey) }}
                </x-filament::tabs.item>
            @endforeach

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_TABS_END, scopes: $renderHookScopes) }}
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABS_END, scopes: $renderHookScopes) }}
        </x-filament::tabs>
    </div>
@endif
