{{-- The package layout for the help center page. Livewire wraps it as a component and injects $slot; $title may be absent. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <x-lin-codex::styles />
</head>
<body class="codex-help-center-body">
    <header class="codex-help-center-header">
        <span class="codex-help-center-header__app">{{ config('app.name') }}</span>
        <a class="codex-help-center-header__back" href="{{ url('/') }}">{{ __('lin-codex::lin-codex.ui.back_to_app', ['app' => config('app.name')]) }}</a>
    </header>
    {{ $slot }}
</body>
</html>
