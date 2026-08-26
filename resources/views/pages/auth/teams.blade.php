<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mosi</title>
    {{-- Mark this browsing context as the Teams tab. The app shell (partials/head) reads
         this to hide chrome Teams already provides. sessionStorage is per-tab/frame, so
         it never leaks into a normal browser session. --}}
    <script>try { sessionStorage.setItem('mosi_teams', '1'); } catch (e) {}</script>
    @vite(['resources/js/teams-auth.js'])
</head>
<body>
    {{-- The handshake config is read from these data attributes by resources/js/teams-auth.js. --}}
    <div
        id="teams-auth"
        data-store-url="{{ route('teams.auth.store') }}"
        data-dashboard-url="{{ route('dashboard') }}"
        data-csrf="{{ csrf_token() }}"
        data-fail-message="{{ __('Sign-in failed. Open Mosi in a browser to continue.') }}"
    >
        <p id="status">{{ __('Signing you in…') }}</p>
    </div>
</body>
</html>
