<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文履歴</title>
    <script src="https://cdn.jsdelivr.net/npm/js-md5@0.8.3/src/md5.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        $guard = auth()->guard('web');
    @endphp
    @if ($guard->check())
        <div id="app">
            <order-history></order-history>
        </div>
    @else
        <script>
            window.location.href = "{{ route('filament.admin.auth.login') }}";
        </script>
    @endif
</body>
</html>
