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
        ﻿html:has(.brand-splash),
        body:has(.brand-splash) {
          overflow: hidden;
        }
        
        .brand-splash {
          --brand-splash-blue: #0b55df;
          --brand-splash-blue-deep: #06338f;
          --brand-splash-red: #f40732;
          --brand-splash-ink: #081642;
          --brand-splash-muted: #d7def0;
          --brand-splash-surface: #ffffff;
          --font-body-family: "DM Sans", sans-serif;
          align-items: center;
          background:
            radial-gradient(circle at 50% 42%, rgba(11, 85, 223, 0.08), transparent 18rem),
            radial-gradient(circle at 36% 68%, rgba(244, 7, 50, 0.07), transparent 14rem),
            linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
          display: flex;
          font-family: var(--font-body-family, "DM Sans", sans-serif);
          inset: 0;
          justify-content: center;
          overflow: hidden;
          position: fixed;
          transition:
            opacity 520ms cubic-bezier(0.22, 1, 0.36, 1),
            transform 520ms cubic-bezier(0.22, 1, 0.36, 1),
            filter 520ms cubic-bezier(0.22, 1, 0.36, 1);
          z-index: 2147483000;
        }
        
        .brand-splash::before {
          background:
            radial-gradient(circle, rgba(255, 255, 255, 0.96) 0 15rem, rgba(255, 255, 255, 0) 25rem),
            radial-gradient(circle at 50% 50%, rgba(8, 22, 66, 0.045), transparent 25rem);
          content: '';
          inset: 0;
          pointer-events: none;
          position: absolute;
        }
        
        .brand-splash.is-exiting {
          filter: blur(1px);
          opacity: 0;
          pointer-events: none;
          transform: scale(0.982);
        }
        
        .brand-splash__stage {
          align-items: center;
          animation: brand-splash-stage-in 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
          display: flex;
          justify-content: center;
          padding: 1.25rem;
          position: relative;
          width: min(24rem, calc(100vw - 2rem));
          z-index: 1;
        }
        
        .brand-splash.is-handoff .brand-splash__stage {
          animation: none;
        }
        
        .brand-splash__scene {
          animation: brand-splash-breath 1800ms ease-in-out 3900ms infinite;
          height: clamp(17.5rem, 64vw, 22rem);
          position: relative;
          width: clamp(17.5rem, 64vw, 22rem);
        }
        
        .brand-splash__ambient {
          border-radius: 50%;
          filter: blur(16px);
          opacity: 0;
          position: absolute;
          transform: scale(0.82);
        }
        
        .brand-splash__ambient--blue {
          animation: brand-splash-soft-glow 5000ms ease-in-out infinite;
          background: rgba(11, 85, 223, 0.17);
          inset: 13%;
        }
        
        .brand-splash__ambient--red {
          animation: brand-splash-soft-glow 5000ms ease-in-out 500ms infinite;
          background: rgba(244, 7, 50, 0.12);
          inset: 18% 14% 12% 18%;
        }
        
        .brand-splash__orbit {
          height: 78%;
          left: 11%;
          overflow: visible;
          position: absolute;
          top: 8%;
          transform: rotate(-90deg);
          width: 78%;
        }
        
        .brand-splash__orbit-path {
          animation: brand-splash-orbit-in 500ms ease 520ms both;
          fill: none;
          opacity: 0;
          stroke: rgba(11, 85, 223, 0.22);
          stroke-dasharray: 5 8;
          stroke-linecap: round;
          stroke-width: 1.9;
        }
        
        .brand-splash__arc {
          fill: none;
          opacity: 0;
          stroke-linecap: round;
          stroke-width: 6.2;
          transform-origin: 80px 80px;
        }
        
        .brand-splash__arc--blue {
          filter: drop-shadow(0 0 9px rgba(11, 85, 223, 0.22));
          stroke: var(--brand-splash-blue);
        }
        
        .brand-splash__arc--red {
          animation: brand-splash-red 5000ms cubic-bezier(0.45, 0, 0.2, 1) infinite;
          filter: drop-shadow(0 0 9px rgba(244, 7, 50, 0.22));
          stroke: var(--brand-splash-red);
        }
        
        .brand-splash__arc--one {
          animation: brand-splash-blue-one 5000ms cubic-bezier(0.45, 0, 0.2, 1) infinite;
        }
        
        .brand-splash__arc--two {
          animation: brand-splash-blue-two 5000ms cubic-bezier(0.45, 0, 0.2, 1) infinite;
        }
        
        .brand-splash__lead {
          border: 3px solid currentColor;
          border-radius: 50%;
          box-shadow:
            0 0 0 5px rgba(255, 255, 255, 0.92),
            0 0 18px color-mix(in srgb, currentColor 36%, transparent);
          height: 0.78rem;
          left: calc(50% - 0.39rem);
          opacity: 0;
          position: absolute;
          top: calc(47% - 0.39rem);
          transform-origin: 0.39rem 0.39rem;
          width: 0.78rem;
          z-index: 5;
        }
        
        .brand-splash__lead::after {
          background: linear-gradient(90deg, color-mix(in srgb, currentColor 46%, transparent), transparent);
          border-radius: 999px;
          content: '';
          height: 0.34rem;
          position: absolute;
          right: 0.68rem;
          top: 50%;
          transform: translateY(-50%);
          width: 2.7rem;
        }
        
        .brand-splash__lead--one {
          animation: brand-splash-lead-one 5000ms cubic-bezier(0.45, 0, 0.2, 1) infinite;
          color: var(--brand-splash-blue);
        }
        
        .brand-splash__lead--two {
          animation: brand-splash-lead-two 5000ms cubic-bezier(0.45, 0, 0.2, 1) infinite;
          color: var(--brand-splash-red);
        }
        
        .brand-splash__lead--three {
          animation: brand-splash-lead-three 5000ms cubic-bezier(0.45, 0, 0.2, 1) infinite;
          color: var(--brand-splash-blue);
        }
        
        .brand-splash__motion-streak {
          animation: brand-splash-streak 5000ms ease-in-out infinite;
          background: linear-gradient(90deg, transparent, rgba(11, 85, 223, 0.28), transparent);
          border-radius: 999px;
          filter: blur(2px);
          height: 0.62rem;
          left: 5%;
          opacity: 0;
          position: absolute;
          top: 38%;
          width: 4.6rem;
          z-index: 1;
        }
        
        .brand-splash__logo {
          align-items: center;
          animation: brand-splash-logo-in 620ms cubic-bezier(0.22, 1, 0.36, 1) both;
          background: var(--brand-splash-surface);
          border: 1px solid rgba(8, 22, 66, 0.06);
          border-radius: 50%;
          box-shadow:
            0 1.2rem 3rem rgba(8, 22, 66, 0.11),
            inset 0 0 0 0.75rem rgba(245, 248, 255, 0.72);
          display: flex;
          height: 7rem;
          justify-content: center;
          left: 50%;
          position: absolute;
          top: 47%;
          transform: translate(-50%, -50%);
          width: 7rem;
          z-index: 4;
        }
        
        .brand-splash__logo-ring {
          animation: brand-splash-logo-ring 5000ms ease-in-out 3500ms infinite;
          border: 1px solid rgba(11, 85, 223, 0.1);
          border-radius: 50%;
          inset: -1.45rem;
          position: absolute;
        }
        
        .brand-splash__logo img {
          height: 4.2rem;
          object-fit: contain;
          position: relative;
          width: 4.2rem;
          z-index: 1;
        }
        
        .brand-splash__icon {
          align-items: center;
          animation: brand-splash-icon-in 560ms cubic-bezier(0.22, 1, 0.36, 1) both;
          background: rgba(255, 255, 255, 0.96);
          border: 1px solid rgba(8, 22, 66, 0.06);
          border-radius: 50%;
          box-shadow:
            0 0.9rem 2rem rgba(8, 22, 66, 0.1),
            0 0 0 0.35rem rgba(255, 255, 255, 0.82);
          display: flex;
          height: 3.6rem;
          justify-content: center;
          position: absolute;
          width: 3.6rem;
          z-index: 6;
        }
        
        .brand-splash__icon svg {
          fill: none;
          height: 1.72rem;
          stroke: currentColor;
          stroke-linecap: round;
          stroke-linejoin: round;
          stroke-width: 2;
          width: 1.72rem;
        }
        
        .brand-splash__icon--blue {
          color: var(--brand-splash-blue);
        }
        
        .brand-splash__icon--red {
          color: var(--brand-splash-red);
        }
        
        .brand-splash__icon--top {
          animation-delay: 700ms;
          left: 50%;
          top: 8%;
          transform: translateX(-50%);
        }
        
        .brand-splash__icon--right {
          animation-delay: 820ms;
          right: 13%;
          top: 54%;
          transform: translateY(-50%);
        }
        
        .brand-splash__icon--left {
          animation-delay: 940ms;
          bottom: 21%;
          left: 13%;
        }
        
        .brand-splash__icon--top::after,
        .brand-splash__icon--right::after,
        .brand-splash__icon--left::after {
          animation: brand-splash-icon-pulse 5000ms ease-in-out infinite;
          border: 1px solid currentColor;
          border-radius: inherit;
          content: '';
          inset: -0.35rem;
          opacity: 0;
          position: absolute;
        }
        
        .brand-splash__icon--top::after { animation-delay: 1700ms; }
        .brand-splash__icon--right::after { animation-delay: 2600ms; }
        .brand-splash__icon--left::after { animation-delay: 3500ms; }
        
        .brand-splash__dots {
          align-items: center;
          bottom: 10%;
          display: flex;
          gap: 0.52rem;
          justify-content: center;
          left: 50%;
          position: absolute;
          transform: translateX(-50%);
          z-index: 7;
        }
        
        .brand-splash__dot {
          animation: brand-splash-dot 1200ms ease-in-out infinite;
          border-radius: 50%;
          height: 0.55rem;
          width: 0.55rem;
        }
        
        .brand-splash__dot--blue {
          background: var(--brand-splash-blue);
        }
        
        .brand-splash__dot--muted {
          animation-delay: 120ms;
          background: var(--brand-splash-muted);
        }
        
        .brand-splash__dot--red {
          animation-delay: 240ms;
          background: var(--brand-splash-red);
        }
        
        @keyframes brand-splash-stage-in {
          from { opacity: 0; transform: translateY(0.45rem) scale(0.98); }
          to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        @keyframes brand-splash-logo-in {
          from { opacity: 0; transform: translate(-50%, -50%) scale(0.86); }
          to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        
        @keyframes brand-splash-orbit-in {
          from { opacity: 0; stroke-dashoffset: 24; }
          to { opacity: 1; stroke-dashoffset: 0; }
        }
        
        @keyframes brand-splash-icon-in {
          from { opacity: 0; scale: 0.72; }
          to { opacity: 1; scale: 1; }
        }
        
        @keyframes brand-splash-blue-one {
          0%, 18% { opacity: 0; stroke-dasharray: 0 360; stroke-dashoffset: 235; }
          30%, 70% { opacity: 1; stroke-dasharray: 78 360; stroke-dashoffset: 196; }
          88%, 100% { opacity: 0; stroke-dasharray: 78 360; stroke-dashoffset: 196; }
        }
        
        @keyframes brand-splash-red {
          0%, 34% { opacity: 0; stroke-dasharray: 0 360; stroke-dashoffset: 130; }
          48%, 76% { opacity: 1; stroke-dasharray: 88 360; stroke-dashoffset: 87; }
          90%, 100% { opacity: 0; stroke-dasharray: 88 360; stroke-dashoffset: 87; }
        }
        
        @keyframes brand-splash-blue-two {
          0%, 52% { opacity: 0; stroke-dasharray: 0 360; stroke-dashoffset: 46; }
          68%, 86% { opacity: 1; stroke-dasharray: 122 360; stroke-dashoffset: -32; }
          100% { opacity: 0; stroke-dasharray: 122 360; stroke-dashoffset: -32; }
        }
        
        @keyframes brand-splash-lead-one {
          0%, 18% { opacity: 0; transform: rotate(240deg) translateY(-7.58rem) rotate(-240deg); }
          30% { opacity: 1; transform: rotate(356deg) translateY(-7.58rem) rotate(-356deg); }
          38%, 100% { opacity: 0; transform: rotate(356deg) translateY(-7.58rem) rotate(-356deg); }
        }
        
        @keyframes brand-splash-lead-two {
          0%, 36% { opacity: 0; transform: rotate(3deg) translateY(-7.58rem) rotate(-3deg); }
          50% { opacity: 1; transform: rotate(103deg) translateY(-7.58rem) rotate(-103deg); }
          58%, 100% { opacity: 0; transform: rotate(103deg) translateY(-7.58rem) rotate(-103deg); }
        }
        
        @keyframes brand-splash-lead-three {
          0%, 56% { opacity: 0; transform: rotate(108deg) translateY(-7.58rem) rotate(-108deg); }
          74% { opacity: 1; transform: rotate(238deg) translateY(-7.58rem) rotate(-238deg); }
          82%, 100% { opacity: 0; transform: rotate(238deg) translateY(-7.58rem) rotate(-238deg); }
        }
        
        @keyframes brand-splash-streak {
          0%, 18%, 42%, 58%, 82%, 100% { opacity: 0; transform: translateX(0) scaleX(0.62); }
          25% { opacity: 0.92; transform: translateX(1.4rem) scaleX(1); }
          65% { opacity: 0.64; transform: translateX(9.4rem) scaleX(0.82); }
        }
        
        @keyframes brand-splash-icon-pulse {
          0%, 28%, 100% { opacity: 0; transform: scale(0.92); }
          35% { opacity: 0.42; transform: scale(1.16); }
          43% { opacity: 0; transform: scale(1.38); }
        }
        
        @keyframes brand-splash-soft-glow {
          0%, 66%, 100% { opacity: 0; transform: scale(0.86); }
          76%, 92% { opacity: 1; transform: scale(1.04); }
        }
        
        @keyframes brand-splash-logo-ring {
          0%, 100% { opacity: 0.48; transform: scale(0.98); }
          50% { opacity: 0.82; transform: scale(1.05); }
        }
        
        @keyframes brand-splash-breath {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.018); }
        }
        
        @keyframes brand-splash-dot {
          0%, 100% { opacity: 0.6; transform: scale(0.85); }
          50% { opacity: 1; transform: scale(1.1); }
        }
        
        @media (max-width: 520px) {
          .brand-splash__stage { padding: 1rem; }
          .brand-splash__scene {
            height: min(86vw, 20rem);
            width: min(86vw, 20rem);
          }
        }
        
        @media (prefers-reduced-motion: reduce) {
          .brand-splash,
          .brand-splash.is-exiting {
            filter: none;
            transition: opacity 220ms ease;
            transform: none;
          }
        
          .brand-splash__stage,
          .brand-splash__scene,
          .brand-splash__ambient,
          .brand-splash__orbit-path,
          .brand-splash__arc,
          .brand-splash__lead,
          .brand-splash__motion-streak,
          .brand-splash__logo,
          .brand-splash__logo-ring,
          .brand-splash__icon,
          .brand-splash__icon::after,
          .brand-splash__dot {
            animation: none !important;
          }
        
          .brand-splash__ambient--blue { opacity: 0.45; transform: scale(1); }
          .brand-splash__ambient--red { opacity: 0.28; transform: scale(1); }
          .brand-splash__orbit-path { opacity: 1; }
          .brand-splash__arc--one { opacity: 1; stroke-dasharray: 128 360; stroke-dashoffset: 196; }
          .brand-splash__arc--red { opacity: 1; stroke-dasharray: 92 360; stroke-dashoffset: 76; }
          .brand-splash__arc--two { opacity: 1; stroke-dasharray: 110 360; stroke-dashoffset: -42; }
          .brand-splash__logo { opacity: 1; transform: translate(-50%, -50%) scale(1); }
          .brand-splash__icon { opacity: 1; scale: 1; }
          .brand-splash__dot { opacity: 1; transform: scale(1); }
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
        <div class="brand-splash__stage">
            <div class="brand-splash__scene" aria-hidden="true">
                <span class="brand-splash__ambient brand-splash__ambient--blue"></span>
                <span class="brand-splash__ambient brand-splash__ambient--red"></span>
                <span class="brand-splash__motion-streak"></span>

                <svg class="brand-splash__orbit" viewBox="0 0 160 160" focusable="false">
                    <circle class="brand-splash__orbit-path" cx="80" cy="80" r="56" pathLength="360"></circle>
                    <circle class="brand-splash__arc brand-splash__arc--blue brand-splash__arc--one" cx="80" cy="80" r="56" pathLength="360"></circle>
                    <circle class="brand-splash__arc brand-splash__arc--red" cx="80" cy="80" r="56" pathLength="360"></circle>
                    <circle class="brand-splash__arc brand-splash__arc--blue brand-splash__arc--two" cx="80" cy="80" r="56" pathLength="360"></circle>
                </svg>

                <span class="brand-splash__lead brand-splash__lead--one"></span>
                <span class="brand-splash__lead brand-splash__lead--two"></span>
                <span class="brand-splash__lead brand-splash__lead--three"></span>

                <span class="brand-splash__icon brand-splash__icon--top brand-splash__icon--blue">
                    <svg viewBox="0 0 32 32" focusable="false">
                        <path d="M10 11V8.8C10 6.7 11.7 5 13.8 5h4.4C20.3 5 22 6.7 22 8.8V11"></path>
                        <path d="M7 12.5h18c1.1 0 2 .9 2 2v9.5c0 1.1-.9 2-2 2H7c-1.1 0-2-.9-2-2v-9.5c0-1.1.9-2 2-2Z"></path>
                        <path d="M5 18h22M13 10.8h6M12 18v3h8v-3"></path>
                    </svg>
                </span>

                <span class="brand-splash__icon brand-splash__icon--right brand-splash__icon--blue">
                    <svg viewBox="0 0 32 32" focusable="false">
                        <path d="M9 8.5c0-1.6 3.1-3 7-3s7 1.4 7 3-3.1 3-7 3-7-1.4-7-3Z"></path>
                        <path d="M9 8.5v5c0 1.6 3.1 3 7 3s7-1.4 7-3v-5"></path>
                        <path d="M9 13.5v5c0 1.6 3.1 3 7 3s7-1.4 7-3v-5"></path>
                        <path d="M9 18.5v5c0 1.6 3.1 3 7 3s7-1.4 7-3v-5"></path>
                    </svg>
                </span>

                <span class="brand-splash__icon brand-splash__icon--left brand-splash__icon--red">
                    <svg viewBox="0 0 32 32" focusable="false">
                        <path d="M9 14.5h13.2l-1.2 7.2H10.5L9 14.5Z"></path>
                        <path d="M8.8 14.5 7.8 10H5.5M13 14.5l3.2-4.7M20 14.5l-2.8-4.7"></path>
                        <path d="M12 25.2h.1M20.5 25.2h.1"></path>
                        <path d="M9.5 18.2h12.2"></path>
                    </svg>
                </span>

                <div class="brand-splash__logo">
                    <span class="brand-splash__logo-ring"></span>
                    <img src="/favicon-192x192.png" alt="" width="92" height="92">
                </div>

                <div class="brand-splash__dots">
                    <span class="brand-splash__dot brand-splash__dot--blue"></span>
                    <span class="brand-splash__dot brand-splash__dot--muted"></span>
                    <span class="brand-splash__dot brand-splash__dot--red"></span>
                </div>
            </div>
        </div>
    </div>
    <div id="app"></div>
</body>
</html>
