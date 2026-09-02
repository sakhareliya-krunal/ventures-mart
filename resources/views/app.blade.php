<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $seo['locale'] ?? config('app.locale', 'en-IN')) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] ?? config('app.name', 'Ventures Mart') }}</title>
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    @if (! empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
    @if (! empty($seo['verification']['google_site_verification']))
    <meta name="google-site-verification" content="{{ $seo['verification']['google_site_verification'] }}">
    @endif
    @if (! empty($seo['canonical']))
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    @foreach (($seo['hreflang'] ?? []) as $alternate)
    <link rel="alternate" hreflang="{{ $alternate['locale'] }}" href="{{ $alternate['url'] }}">
    @endforeach
    <meta property="og:site_name" content="{{ $seo['og']['site_name'] ?? ($seo['brand_name'] ?? 'Ventures Mart') }}">
    <meta property="og:type" content="{{ $seo['og']['type'] ?? 'website' }}">
    <meta property="og:locale" content="{{ $seo['og']['locale'] ?? ($seo['og_locale'] ?? 'en_IN') }}">
    <meta property="og:title" content="{{ $seo['og']['title'] ?? ($seo['title'] ?? 'Ventures Mart') }}">
    <meta property="og:description" content="{{ $seo['og']['description'] ?? ($seo['description'] ?? '') }}">
    <meta property="og:url" content="{{ $seo['og']['url'] ?? ($seo['canonical'] ?? url()->current()) }}">
    @if (! empty($seo['og']['image']))
    <meta property="og:image" content="{{ $seo['og']['image'] }}">
    @if (($seo['og']['type'] ?? '') === 'product')
    <link rel="preload" as="image" href="{{ $seo['og']['image'] }}">
    @endif
    @endif
    <meta name="twitter:card" content="{{ $seo['twitter']['card'] ?? 'summary_large_image' }}">
    <meta name="twitter:title" content="{{ $seo['twitter']['title'] ?? ($seo['title'] ?? 'Ventures Mart') }}">
    <meta name="twitter:description" content="{{ $seo['twitter']['description'] ?? ($seo['description'] ?? '') }}">
    @if (! empty($seo['twitter']['image']))
    <meta name="twitter:image" content="{{ $seo['twitter']['image'] }}">
    @endif
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#ffffff">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,700;1,400;1,700&family=Jost:wght@400&display=swap" rel="stylesheet">
    <style>
        .brand-splash {
            align-items: center;
            background: #ffffff;
            display: flex;
            inset: 0;
            justify-content: center;
            overflow: hidden;
            padding: 1rem;
            position: fixed;
            z-index: 2147483000;
        }

        .brand-splash__loader {
            display: block;
            height: auto;
            max-height: min(46vh, 14.4rem);
            max-width: min(46vw, 14.4rem);
            width: clamp(10.4rem, 21.6vw, 14.4rem);
        }

        @media (max-width: 520px) {
            .brand-splash__loader {
                max-width: min(55vw, 12.6rem);
                width: clamp(9.5rem, 54vw, 12.6rem);
            }
        }
    </style>
    <script>
        window.__APP__ = {
            googleClientId: @json(config('services.google.client_id')),
            metaPixelId: @json(config('services.meta.pixel_id')),
            seo: @json($seo ?? []),
        };
    </script>
    @foreach (($seo['json_ld'] ?? []) as $schema)
    <script type="application/ld+json" data-vm-jsonld="1">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endforeach
    @if (! empty($seo['analytics']['gtm_container_id']))
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',@json($seo['analytics']['gtm_container_id']));
    </script>
    @elseif (! empty($seo['analytics']['ga_measurement_id']))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seo['analytics']['ga_measurement_id'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($seo['analytics']['ga_measurement_id']));
    </script>
    @endif
    @vite(['resources/js/app.js'])
</head>
<body>
    @if (! empty($seo['analytics']['gtm_container_id']))
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id={{ $seo['analytics']['gtm_container_id'] }}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe>
    </noscript>
    @endif
    @if (filled(config('services.meta.pixel_id')))
    <noscript>
        <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id={{ urlencode((string) config('services.meta.pixel_id')) }}&amp;ev=PageView&amp;noscript=1"
            alt="">
    </noscript>
    @endif
    <div id="brand-splash" class="brand-splash" role="status" aria-live="polite" aria-label="Loading Ventures Mart">
        <img class="brand-splash__loader" src="/images/venturesmart-loader-fixed-3-new-icons.svg" alt="" width="320" height="320">
    </div>
    <div id="app"></div>
</body>
</html>
