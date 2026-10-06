<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · CMT Tender Hub</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F5F4FA;color:#1E1B2E;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif}
        @media (prefers-color-scheme:dark){body{background:#141312;color:#F5F4F0}}
        main{max-width:28rem;padding:2rem;text-align:center}
        a{color:inherit}
    </style>
</head>
<body><main>
    <h1>@yield('heading')</h1>
    <p>@yield('message')</p>
    <p><a href="{{ url('/') }}">Back to Tender Hub</a></p>
</main></body>
</html>
