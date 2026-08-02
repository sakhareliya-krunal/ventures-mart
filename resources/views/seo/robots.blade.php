User-agent: *
@if ($enabled)
@forelse ($disallow as $path)
Disallow: {{ $path }}
@empty
Allow: /
@endforelse
@else
Disallow: /
@endif

Sitemap: {{ $sitemap }}
