<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mosi</title>
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
