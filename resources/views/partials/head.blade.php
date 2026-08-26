<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- Mosi is only ever framed by the Microsoft Teams tab — the security headers forbid
     any other framer — so being embedded means we are in Teams. Mark it on <html>
     before paint, so Teams-only chrome (the account menu) can be hidden without a flash
     and without leaking into normal browser sessions the way a cookie would. --}}
<script>
    if (window.self !== window.top) {
        document.documentElement.classList.add('teams-embed');
    }
</script>
<style>html.teams-embed [data-hide-in-teams]{display:none !important}</style>

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Mosi') : config('app.name', 'Mosi') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

{{-- Inter, self-servable via Bunny Fonts (GDPR-friendly, no Google tracking). --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap">

@vite(['resources/css/app.css', 'resources/js/app.js'])
