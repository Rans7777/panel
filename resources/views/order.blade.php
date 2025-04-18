<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文ページ</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        $guard = auth()->guard('web');
    @endphp
    @if ($guard->check())
        <div id="app">
            <order-page></order-page>
        </div>
    @else
        <script>
            window.location.href = "{{ route('filament.admin.auth.login') }}";
        </script>
    @endif
</body>
</html>
