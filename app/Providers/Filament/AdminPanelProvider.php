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
                    /* Proportional Dashboard 5-Card Grid on Desktop & 2-Column Compact on Mobile */
                    .fi-wi-stats-overview-stats-ctn {
                        display: grid !important;
                        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                        gap: 0.75rem !important;
                    }
                    @media (min-width: 768px) {
                        .fi-wi-stats-overview-stats-ctn {
                            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                            gap: 1rem !important;
                        }
                    }
                    @media (min-width: 1280px) {
                        .fi-wi-stats-overview-stats-ctn {
                            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)) !important;
                            gap: 1rem !important;
                        }
                    }

                    /* ContentGrid Card Polish */
                    .fi-ta-record {
                        border-radius: 0.75rem !important;
                        transition: box-shadow 0.15s ease, transform 0.15s ease;
                    }

                    /* Ensure custom HTML infolist entries expand full width for multi-card grids */
                    .motiva-full-width-entry,
                    .motiva-full-width-entry > div,
                    .motiva-full-width-entry > div > div,
                    .motiva-full-width-entry .fi-in-text-item,
                    .motiva-full-width-entry .fi-in-text-item > div,
                    .motiva-full-width-entry .prose {
                        display: block !important;
                        width: 100% !important;
                        max-width: none !important;
                    }

                    @media (max-width: 639px) {
                        .fi-main { overflow-x: clip; }
                        .fi-ta-actions { flex-wrap: wrap !important; gap: 0.375rem !important; }
                        .fi-in-entry-wrp, .fi-in-text { min-width: 0; max-width: 100%; }
                        .fi-header-actions { flex-wrap: wrap !important; gap: 0.5rem !important; }

                        /* Compact Stat Cards on Mobile & Hide Decorative Wave Charts */
                        .fi-wi-stats-overview-stat {
                            padding: 0.875rem !important;
                        }
                        .fi-wi-stats-overview-stat-chart {
                            display: none !important;
                        }
                        .fi-wi-stats-overview-stat-value {
                            font-size: 1.25rem !important;
                            line-height: 1.65rem !important;
                        }
                        /* Make odd last stat card span 2 columns nicely on mobile */
                        .fi-wi-stats-overview-stats-ctn > .fi-wi-stats-overview-stat:last-child:nth-child(odd) {
                            grid-column: span 2 / span 2 !important;
                        }

                        /* Hide redundant MemoOverviewWidget & SppdOverviewWidget header stats on mobile */
                        .motiva-hide-mobile-stat-card,
                        .fi-page-header-widgets .fi-wi-stats-overview {
                            display: none !important;
                        }
                    }
                </style>
                HTML
            );
    }
}
