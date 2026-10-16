<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@auth
    @if (\App\Support\Realtime::enabled() && config('taskflow.realtime.host'))
        <meta name="realtime" content="{{ json_encode(['key' => config('broadcasting.connections.reverb.key'), 'host' => config('taskflow.realtime.host'), 'port' => (int) config('taskflow.realtime.port'), 'scheme' => config('taskflow.realtime.scheme')]) }}">
    @endif
@endauth
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='14' fill='%232e6e73'/><path d='M10 16.5l4 4 8-9' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
