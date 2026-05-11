<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            background: #09090b;
        }

        iframe {
            width: 100%;
            height: 100vh;
            border: 0;
            display: block;
            background: white;
        }
    </style>
</head>
<body>
    <iframe
        id="mcp-app-frame"
        title="{{ $title }}"
        sandbox="allow-scripts allow-forms allow-popups"
        srcdoc="{{ $appHtml }}"
    ></iframe>

    <script>
        const frame = document.getElementById('mcp-app-frame');
        const endpoint = @json(route('mcp-apps.project-dashboard.rpc'));

        window.addEventListener('message', async (event) => {
            if (event.source !== frame.contentWindow) {
                return;
            }

            const message = parseMessage(event.data);
            if (!message || message.jsonrpc !== '2.0') {
                return;
            }

            if (message.id === undefined) {
                return;
            }

            try {
                if (message.method === 'ui/initialize') {
                    reply(message.id, {
                        hostInfo: {
                            name: 'Growth Local MCP App Host',
                            version: '0.1.0',
                        },
                        hostCapabilities: {},
                        hostContext: {
                            theme: 'light',
                        },
                    });

                    return;
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                    },
                    body: JSON.stringify(message),
                });

                frame.contentWindow.postMessage(await response.json(), '*');
            } catch (error) {
                frame.contentWindow.postMessage({
                    jsonrpc: '2.0',
                    id: message.id,
                    error: {
                        code: -32603,
                        message: error instanceof Error ? error.message : 'Unknown app host error',
                    },
                }, '*');
            }
        });

        function reply(id, result) {
            frame.contentWindow.postMessage({
                jsonrpc: '2.0',
                id,
                result,
            }, '*');
        }

        function parseMessage(data) {
            if (typeof data === 'string') {
                try {
                    return JSON.parse(data);
                } catch {
                    return null;
                }
            }

            return data && typeof data === 'object' ? data : null;
        }
    </script>
</body>
</html>
