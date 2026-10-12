@php
    $seo = $page['props']['seo'];
    $content = $page['props']['content'];
    $publicLinks = $page['props']['publicLinks'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-inertia="title">{{ $seo['title'] }}</title>
    <meta data-inertia="description" name="description" content="{{ $seo['description'] }}">
    <meta data-inertia="robots" name="robots" content="{{ $seo['robots'] }}">
    <link data-inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
    <meta data-inertia="og:type" property="og:type" content="website">
    <meta data-inertia="og:locale" property="og:locale" content="fr_FR">
    <meta data-inertia="og:site_name" property="og:site_name" content="{{ $seo['site_name'] }}">
    <meta data-inertia="og:title" property="og:title" content="{{ $seo['social_title'] }}">
    <meta data-inertia="og:description" property="og:description" content="{{ $seo['social_description'] }}">
    <meta data-inertia="og:url" property="og:url" content="{{ $seo['canonical'] }}">
    <meta data-inertia="twitter:card" name="twitter:card" content="{{ $seo['social_image'] ? 'summary_large_image' : 'summary' }}">
    <meta data-inertia="twitter:title" name="twitter:title" content="{{ $seo['social_title'] }}">
    <meta data-inertia="twitter:description" name="twitter:description" content="{{ $seo['social_description'] }}">
    @if($seo['social_image'])
        <meta data-inertia="og:image" property="og:image" content="{{ $seo['social_image'] }}">
        <meta data-inertia="twitter:image" name="twitter:image" content="{{ $seo['social_image'] }}">
    @endif
    @if($seo['google_verification'])
        <meta data-inertia="google-site-verification" name="google-site-verification" content="{{ $seo['google_verification'] }}">
    @endif
    @if($seo['bing_verification'])
        <meta data-inertia="msvalidate.01" name="msvalidate.01" content="{{ $seo['bing_verification'] }}">
    @endif
    <script data-inertia="structured-data" type="application/ld+json">{!! json_encode($seo['schema'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @fonts
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
</head>
<body class="font-sans antialiased">
    <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <div id="app">@include('marketing-content')</div>
</body>
</html>
