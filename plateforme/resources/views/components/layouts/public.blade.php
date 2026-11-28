{{-- Gabarit du site public et de l'espace client. Usage : <x-layouts.public titre="…" page="accueil"> … </x-layouts.public> --}}
@props(['titre' => null, 'description' => null, 'page' => '', 'classeCorps' => '', 'navBasse' => true, 'entete' => 'normal'])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($titre) ? $titre.' · ' : '' }}{{ config('app.name') }}</title>
    @isset($description)<meta name="description" content="{{ $description }}">@endisset
    <meta name="theme-color" content="#0a3d3e">
    <link rel="icon" href="{{ asset('images/logo/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('images/logo/favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.styles-communs')
    @stack('head')
</head>
<body data-page="{{ $page }}" class="{{ $navBasse ? 'has-bottomnav' : '' }} {{ $classeCorps }} @auth est-connecte @endauth">
    <a class="skip-link visually-hidden-focusable" href="#contenu">Aller au contenu</a>

    @include('partials.entete', ['page' => $page, 'entete' => $entete])

    <main id="contenu">
        {{ $slot }}
    </main>

    {{-- Le pied de page n'apparaît qu'aux visiteurs : une fois connecté, on reste dans son espace --}}
    @guest
        @include('partials.pied')
    @endguest
    @if ($navBasse)
        @include('partials.nav-basse', ['page' => $page])
    @endif
    @stack('apres-contenu')

    @stack('scripts')
</body>
</html>
