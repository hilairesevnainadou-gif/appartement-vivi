<?php

namespace App\Providers\Filament;

use App\Filament\AvatarVivi;
use App\Filament\Pages\Auth\Connexion;
use App\Filament\Pages\TableauDeBord;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Connexion::class)
            // Cloche : alertes de surveillance et autres notifications de l'équipe (actualisée toutes les 30 s)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->brandName('VIVI · Administration')
            ->brandLogo(asset('images/logo/vivi-logo.svg'))
            ->darkModeBrandLogo(asset('images/logo/vivi-logo-blanc.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/logo/favicon.svg'))
            // Polices de la charte, servies par Vite avec admin.css (aucun appel à Google ou Bunny Fonts)
            ->font('DM Sans Variable', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(AvatarVivi::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render("@vite('resources/css/admin.css')"))
            // Les clients et propriétaires se connectent par code sur le site, pas ici
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => '<p class="vivi-login-renvoi">Client ou propriétaire ? <a href="'.e(route('connexion')).'">Connexion par code</a></p>')
            ->colors([
                // Gamme turquoise de la charte (maquette, vivi.css) : le #136f6e du logo
                // est la nuance 600, assez foncée pour du texte blanc sur les boutons.
                // Color::hex() et Color::Teal donnaient des boutons cyan clair.
                'primary' => [
                    50 => '#eff8f7',
                    100 => '#d8eeec',
                    200 => '#b4dedb',
                    300 => '#8ccdc9',
                    400 => '#64b8b8',
                    500 => '#1f8583',
                    600 => '#136f6e',
                    700 => '#0e5655',
                    800 => '#0a3d3e',
                    900 => '#082f30',
                    950 => '#051f20',
                ],
                // Gris légèrement teintés de turquoise, comme les textes et filets du site
                'gray' => [
                    50 => '#f6f8f8',
                    100 => '#eef2f2',
                    200 => '#e2e8e7',
                    300 => '#c9d5d4',
                    400 => '#91a3a4',
                    500 => '#5f7072',
                    600 => '#4b5d5f',
                    700 => '#3b4d4f',
                    800 => '#24383a',
                    900 => '#14282a',
                    950 => '#0b1a1b',
                ],
                'warning' => Color::Amber,
            ])
            ->navigationGroups(['Logements', 'Réservations', 'Finances', 'Abonnements', 'Administration'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                TableauDeBord::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->maxContentWidth('7xl')
            ->sidebarWidth('18rem')
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
            ]);
    }
}
