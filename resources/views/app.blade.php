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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        #brand-splash.brand-splash {
            align-items: center;
            background:
                radial-gradient(ellipse 80% 60% at 20% 20%, rgba(230, 30, 77, 0.12), transparent 55%),
                radial-gradient(ellipse 70% 50% at 85% 75%, rgba(255, 193, 7, 0.16), transparent 50%),
                linear-gradient(160deg, #e8eef8 0%, #f5f7fb 42%, #ffffff 100%);
            display: flex;
            font-family: "Poppins", sans-serif;
            inset: 0;
            justify-content: center;
            overflow: hidden;
            position: fixed;
            z-index: 100;
        }
        #brand-splash .brand-splash__orb {
            border-radius: 50%;
            filter: blur(2px);
            position: absolute;
        }
        #brand-splash .brand-splash__orb--1 {
            animation: brand-splash-float 7s ease-in-out infinite;
            background: rgba(11, 46, 138, 0.14);
            height: 18rem;
            left: -4rem;
            top: 12%;
            width: 18rem;
        }
        #brand-splash .brand-splash__orb--2 {
            animation: brand-splash-float 9s ease-in-out infinite reverse;
            background: rgba(230, 30, 77, 0.1);
            bottom: 8%;
            height: 14rem;
            right: -3rem;
            width: 14rem;
        }
        #brand-splash .brand-splash__orb--3 {
            animation: brand-splash-float 8s ease-in-out infinite;
            background: rgba(255, 193, 7, 0.14);
            height: 9rem;
            left: 55%;
            top: 18%;
            width: 9rem;
        }
        #brand-splash .brand-splash__inner {
            align-items: center;
            animation: brand-splash-enter 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
            position: relative;
            text-align: center;
            z-index: 1;
        }
        #brand-splash .brand-splash__mark {
            height: 7.5rem;
            position: relative;
            width: 7.5rem;
        }
        #brand-splash .brand-splash__ring {
            animation: brand-splash-orbit 2.4s linear infinite;
            border: 2px solid rgba(11, 46, 138, 0.12);
            border-radius: 50%;
            border-top-color: #0b2e8a;
            border-right-color: rgba(230, 30, 77, 0.55);
            inset: 0;
            position: absolute;
        }
        #brand-splash .brand-splash__ring--inner {
            animation-direction: reverse;
            animation-duration: 1.8s;
            border-color: transparent;
            border-bottom-color: #e61e4d;
            border-left-color: rgba(11, 46, 138, 0.45);
            inset: 12%;
        }
        #brand-splash .brand-splash__logo {
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 12px 32px rgba(11, 46, 138, 0.12);
            height: 68%;
            left: 50%;
            object-fit: contain;
            padding: 0.55rem;
            position: absolute;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 68%;
        }
        #brand-splash .brand-splash__name {
            color: #1c2c4c;
            font-size: clamp(1.35rem, 3vw, 1.75rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin: 0;
        }
        #brand-splash .brand-splash__tagline {
            color: #6b7a99;
            font-size: 0.95rem;
            font-weight: 500;
            margin: 0;
        }
        #brand-splash .brand-splash__bar {
            background: rgba(11, 46, 138, 0.1);
            border-radius: 999px;
            height: 3px;
            margin-top: 0.35rem;
            overflow: hidden;
            width: 7.5rem;
        }
        #brand-splash .brand-splash__bar-fill {
            animation: brand-splash-shimmer 1.2s ease-in-out infinite;
            background: linear-gradient(90deg, #0b2e8a, #e61e4d, #ffc107);
            border-radius: inherit;
            height: 100%;
            width: 40%;
        }
        @keyframes brand-splash-enter {
            from { opacity: 0; transform: translateY(12px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes brand-splash-orbit {
            to { transform: rotate(360deg); }
        }
        @keyframes brand-splash-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-18px); }
        }
        @keyframes brand-splash-shimmer {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(280%); }
        }
        @media (prefers-reduced-motion: reduce) {
            #brand-splash .brand-splash__orb,
            #brand-splash .brand-splash__ring,
            #brand-splash .brand-splash__bar-fill,
            #brand-splash .brand-splash__inner {
                animation: none !important;
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
    <div id="brand-splash" class="brand-splash" role="status" aria-live="polite" aria-label="Loading Ventures Mart">
        <span class="brand-splash__orb brand-splash__orb--1" aria-hidden="true"></span>
        <span class="brand-splash__orb brand-splash__orb--2" aria-hidden="true"></span>
        <span class="brand-splash__orb brand-splash__orb--3" aria-hidden="true"></span>
        <div class="brand-splash__inner">
            <div class="brand-splash__mark">
                <span class="brand-splash__ring" aria-hidden="true"></span>
                <span class="brand-splash__ring brand-splash__ring--inner" aria-hidden="true"></span>
                <img class="brand-splash__logo" src="/images/ventures-mart-logo.png" alt="Ventures Mart" width="120" height="120" />
            </div>
            <p class="brand-splash__name">Ventures Mart</p>
            <p class="brand-splash__tagline">Toys &amp; lunch boxes</p>
            <div class="brand-splash__bar" aria-hidden="true">
                <span class="brand-splash__bar-fill"></span>
            </div>
        </div>
    </div>
    <div id="app"></div>
</body>
</html>
