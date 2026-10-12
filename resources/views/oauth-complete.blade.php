<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CVCraft AI</title>
  <style>
    body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; background: #0f0f14; color: #e5e7eb; }
    p { max-width: 420px; text-align: center; line-height: 1.5; }
  </style>
</head>
<body>
  <p>{{ $payload['message'] }}<br>You can close this window.</p>
  <script>
    (function () {
      const payload = @json($payload);
      if (window.opener && !window.opener.closed) {
        window.opener.postMessage(payload, window.location.origin);
        window.close();
      }
    })();
  </script>
</body>
</html>
