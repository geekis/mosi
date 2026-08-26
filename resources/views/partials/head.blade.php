<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- The Teams entry page (pages/auth/teams) sets a `mosi_teams` sessionStorage flag.
     sessionStorage is isolated per browsing context, so it marks the Teams tab without
     leaking into normal browser tabs the way a cookie does. Applied on <html> before
     paint (no flash) and re-applied after Livewire navigation, which morphs the class
     away, so Teams-only chrome (account menu, logo, sidebar toggle) stays hidden. --}}
<script>
    (function () {
        var mark = function () {
            try {
                if (sessionStorage.getItem('mosi_teams') === '1') {
                    document.documentElement.classList.add('teams-embed');
                }
            } catch (e) {}
        };
        mark();
        document.addEventListener('livewire:navigated', mark);
    })();
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
