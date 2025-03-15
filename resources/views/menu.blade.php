<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>メニュー</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        $guard = auth()->guard('web');
    @endphp
    @if ($guard->check())
        <div id="app">
            <menu-page></menu-page>
        </div>
    @else
        <script>
            window.location.href = "{{ route('filament.admin.auth.login') }}";
        </script>
    @endif
</body>
</html>
