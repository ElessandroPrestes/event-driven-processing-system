<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentacao da API | eventflow-platform</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body {
            margin: 0;
            background: #f5f7fb;
            color: #1f2937;
            font-family: "Segoe UI", sans-serif;
        }

        .docs-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            color: #fff;
        }

        .docs-header h1 {
            margin: 0 0 0.5rem;
            font-size: 1.75rem;
        }

        .docs-header p {
            margin: 0;
            max-width: 56rem;
            line-height: 1.5;
        }

        .docs-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .docs-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1rem;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
        }

        .docs-actions a.primary {
            background: #fff;
            color: #0f172a;
        }

        .docs-actions a.secondary {
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #fff;
        }

        .swagger-shell {
            padding: 1rem 0 2rem;
        }

        .swagger-ui .topbar {
            display: none;
        }
    </style>
</head>
<body>
    <header class="docs-header">
        <h1>Documentacao OpenAPI</h1>
        <p>
            Interface Swagger UI da API do eventflow-platform. O endpoint de health e publico.
            Os demais endpoints exigem as chaves <code>X-Ingest-Api-Key</code> ou
            <code>X-Operations-Api-Key</code>, conforme o escopo da operacao.
        </p>
        <div class="docs-actions">
            <a class="primary" href="{{ $openApiUrl }}">Abrir arquivo YAML</a>
            <a class="secondary" href="/api/v1/health">Health check</a>
        </div>
    </header>

    <main class="swagger-shell">
        <div id="swagger-ui"></div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                url: @json($openApiUrl),
                dom_id: '#swagger-ui',
                deepLinking: true,
                displayRequestDuration: true,
                docExpansion: 'list',
                filter: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset,
                ],
                layout: 'StandaloneLayout',
            });
        };
    </script>
</body>
</html>
