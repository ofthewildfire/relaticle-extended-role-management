<!DOCTYPE html>
<html>
<head>
    <title>Resource Access Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-info { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #eee; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Resource Access Debug Information</h1>
    
    <div class="error">{{ $message ?? 'Debug Information' }}</div>
    
    <div class="debug-info">
        <h3>Middleware Debug Info:</h3>
        <pre>{{ json_encode($debug_info ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>
    
    @if(session('debug_info'))
    <div class="debug-info">
        <h3>Session Debug Info:</h3>
        <pre>{{ json_encode(session('debug_info'), JSON_PRETTY_PRINT) }}</pre>
    </div>
    @endif
    
    <div class="debug-info">
        <h3>Current User Info:</h3>
        @auth
            <p><strong>User ID:</strong> {{ auth()->user()->id }}</p>
            <p><strong>User Email:</strong> {{ auth()->user()->email }}</p>
            <p><strong>Teams:</strong> {{ auth()->user()->teams->pluck('name')->implode(', ') }}</p>
        @else
            <p>Not authenticated</p>
        @endauth
    </div>
    
    <div class="debug-info">
        <h3>Available Resources:</h3>
        @php
            $plugin = app(\Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin::class);
            $resources = $plugin->getAvailableResources();
        @endphp
        <pre>{{ json_encode($resources, JSON_PRETTY_PRINT) }}</pre>
    </div>
    
    @if(auth()->check() && \Filament\Facades\Filament::getTenant())
    <div class="debug-info">
        <h3>User Permissions for Current Team:</h3>
        @php
            $user = auth()->user();
            $team = \Filament\Facades\Filament::getTenant();
            $permissions = $plugin->getUserResourcePermissions($user, $team);
        @endphp
        <pre>{{ json_encode($permissions, JSON_PRETTY_PRINT) }}</pre>
    </div>
    
    <div class="debug-info">
        <h3>First Accessible Resource:</h3>
        @php
            $firstAccessible = $plugin->getFirstAccessibleResource($user, $team);
        @endphp
        <p><strong>{{ $firstAccessible ?? 'None found' }}</strong></p>
    </div>
    @endif
    
    <div class="debug-info">
        <h3>Request Info:</h3>
        <p><strong>Path:</strong> {{ request()->path() }}</p>
        <p><strong>URL:</strong> {{ request()->url() }}</p>
        <p><strong>Method:</strong> {{ request()->method() }}</p>
    </div>
    
    <p><a href="javascript:history.back()">Go Back</a></p>
</body>
</html>