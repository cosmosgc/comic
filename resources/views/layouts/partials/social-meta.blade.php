@php
    $pageTitle = trim($__env->yieldContent('title', $defaultTitle));
    $metaTitle = trim($__env->yieldContent('meta_title', $pageTitle));
    $metaDescription = trim($__env->yieldContent('meta_description', $defaultDescription));
    $metaImage = trim($__env->yieldContent('meta_image', $defaultImage)) ?: $defaultImage;
    $metaType = trim($__env->yieldContent('meta_type', 'website'));
@endphp
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta property="og:type" content="{{ $metaType }}">
<meta property="og:site_name" content="{{ config('app.site_title') }}">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:image" content="{{ $metaImage }}">
<meta property="og:image:alt" content="{{ $metaTitle }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $metaImage }}">
