@props(['title' => null, 'description' => null, 'image' => null, 'noindex' => false])
@php
    $brand = config('faithfellows.brand.name');
    $fullTitle = $title ? $title.' · '.$brand : $brand.' — '.config('faithfellows.brand.tagline');
    $desc = $description ?: 'FaithFellows gives every church its own digital home — website, sermons, live streams, events, giving and member care — built for Uganda, from UGX 60,000/month.';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#BC2649">
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ Str::limit(strip_tags($desc), 160) }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@endif
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ Str::limit(strip_tags($desc), 200) }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $image ?: asset('images/og-image.png') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Clarity+City:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
@vite(['resources/scss/app.scss', 'resources/css/faithfellows.css', 'resources/js/app.js'])
@stack('head')
