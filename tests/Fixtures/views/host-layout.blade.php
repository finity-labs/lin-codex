{{-- A host component layout: receives $slot and $title, deliberately without <x-lin-codex::styles />. --}}
<!doctype html>
<html lang="en">
<head>
    <title>{{ $title ?? 'host' }}</title>
</head>
<body>
    <div id="host-layout-marker">{{ $slot }}</div>
</body>
</html>
