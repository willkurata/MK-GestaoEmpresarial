<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#3f4d2c">
        <meta property="og:site_name" content="MK Negócios">
        <meta property="og:locale" content="pt_BR">

        <style>
            html {
                background-color: #fbfaf7;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>MK Negócios</title>
        </x-inertia::head>
    </head>
    <body class="site font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
