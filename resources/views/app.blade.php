<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['theme-light' => $appearance == 'light', 'theme-dark' => $appearance == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

		@if (Cookie::get('theme') == 'light')
			<meta name="theme-color" content="hsl(213, 39%, 95%)" />
		@elseif (Cookie::get('theme') == 'dark')
			<meta name="theme-color" content="hsl(215, 71%, 6%)" />
		@else
			<meta name="theme-color" content="hsl(213, 39%, 95%)" />
		@endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
