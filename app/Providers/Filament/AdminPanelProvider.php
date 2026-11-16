<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->colors([
                'primary' => Color::Amber,
                'indigo' => Color::Indigo,
                'teal' => Color::Teal,
            ])
            ->font('Outfit')
            ->brandName('MOTIVA')
            ->brandLogo(fn () => asset('images/logo-motiva.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-motiva.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(fn () => asset('images/logo-motiva-icon.png'))
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(false)
            ->maxContentWidth('full')
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Dokumen')
                    ->icon('heroicon-o-document-text')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Persetujuan')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Keuangan')
                    ->icon('heroicon-o-banknotes')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Log')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Data Pegawai')
                    ->icon('heroicon-o-user-group')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Hak Akses & Pengguna')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Pengaturan')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
                \App\Filament\Admin\Pages\SignatureSettingsPage::class,
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Tanda Tangan')
                    ->url(fn (): string => \App\Filament\Admin\Pages\SignatureSettingsPage::getUrl())
                    ->icon('heroicon-o-pencil-square'),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
                <style>
                    @media (max-width: 639px) {
                        .fi-main { overflow-x: clip; }
                        .fi-ta-actions { flex-wrap: wrap !important; gap: 0.375rem !important; }
                        .fi-in-entry-wrp, .fi-in-text { min-width: 0; max-width: 100%; }
                        .fi-header-actions { flex-wrap: wrap !important; gap: 0.5rem !important; }
                    }
                </style>
                HTML
            );
    }
}
