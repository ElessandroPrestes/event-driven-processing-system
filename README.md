# eventflow-platform

Plataforma de processamento assíncrono de eventos com Laravel, RabbitMQ, PostgreSQL, Redis e API RESTful.

## Stack
- Laravel 13
- PHP 8.4
- PostgreSQL
- Redis
- RabbitMQ
- Docker Compose
- Pest
- Laravel Pint
- PHPStan com Larastan
- GitHub Actions

## Estrutura inicial
- `app/Application`: casos de uso e ações.
- `app/Domain`: regras de domínio e contratos.
- `app/Infrastructure`: integrações e adapters.
- `app/Interfaces`: pontos de entrada HTTP e futuros adapters externos.

Detalhes adicionais estão em `docs/architecture.md`.

## Subida local
```bash
docker compose up --build
```

Serviços expostos:
- API Laravel: `http://localhost:8081`
- Documentacao Swagger UI: `http://localhost:8081/docs`
- Arquivo OpenAPI YAML: `http://localhost:8081/docs/openapi.yaml`
- Health check da API: `http://localhost:8081/api/v1/health`
- Exportacao de metricas Prometheus: `GET http://localhost:8081/api/v1/metrics`
- Recepcao de eventos: `POST http://localhost:8081/api/v1/events`
- Resumo operacional: `GET http://localhost:8081/api/v1/events/summary`
- Consulta de eventos: `GET http://localhost:8081/api/v1/events`
- Consulta da quarentena: `GET http://localhost:8081/api/v1/quarantine`
- Consulta de evento: `GET http://localhost:8081/api/v1/events/{id}`
- Historico do evento: `GET http://localhost:8081/api/v1/events/{id}/history`
- Reenfileiramento manual: `POST http://localhost:8081/api/v1/events/{id}/retry`
- Replay da quarentena: `POST http://localhost:8081/api/v1/quarantine/replay`
- Health nativo do Laravel: `http://localhost:8081/up`
- PostgreSQL: `localhost:55432`
- Redis: `localhost:6380`
- RabbitMQ AMQP: `localhost:5673`
- RabbitMQ Management: `http://localhost:15673`

Na primeira subida o container da aplicação:
- usa a própria imagem Docker como artefato de runtime, sem depender de bind mount do código-fonte
- carrega a configuração a partir de `.env.docker`
- propaga `APP_KEY` do `.env` local quando ela estiver definida
- gera e compartilha `APP_KEY` automaticamente entre `app`, `worker` e `ingest-worker` quando ela não estiver definida
- executa as migrations

Os serviços `worker` e `ingest-worker` iniciam junto com `docker compose up`.
- `worker`: consome a fila interna de processamento com `php artisan events:consume`
- `ingest-worker`: consome a fila de entrada AMQP com `php artisan events:consume-ingest`

A imagem de runtime já inclui o código da aplicação e as dependências PHP instaladas em build time. Se o código mudar localmente, é necessário reconstruir a imagem com `docker compose up --build`.
O arquivo `.env.docker` fica sem segredo versionado; para fixar uma chave localmente, defina `APP_KEY` no `.env` ignorado pelo Git antes de subir os containers.
Se precisar sobrescrever as portas expostas no host, defina `APP_PORT`, `POSTGRES_PORT_FORWARD`, `REDIS_PORT_FORWARD`, `RABBITMQ_PORT` e `RABBITMQ_MANAGEMENT_PORT` no `.env` local ou no shell antes do `docker compose up`.

## Resiliencia do consumo
- falhas transitórias de processamento passam por retry automático com atraso progressivo até `EVENT_CONSUMER_MAX_ATTEMPTS`
- o atraso progressivo pode ser ajustado com `EVENT_CONSUMER_RETRY_BASE_DELAY_MS`, `EVENT_CONSUMER_RETRY_MULTIPLIER` e `EVENT_CONSUMER_RETRY_MAX_DELAY_MS`
- mensagens inválidas, órfãs ou com falha definitiva de processamento são encaminhadas para a fila de dead-letter `eventflow.processing.dead`
- mensagens inválidas na entrada AMQP são encaminhadas para a fila de dead-letter `eventflow.ingest.dead`
- a topologia de ingestao AMQP pode ser customizada com `RABBITMQ_INGEST_EXCHANGE`, `RABBITMQ_INGEST_QUEUE`, `RABBITMQ_INGEST_BINDING_KEY`, `RABBITMQ_INGEST_DEAD_LETTER_EXCHANGE`, `RABBITMQ_INGEST_DEAD_LETTER_QUEUE` e `RABBITMQ_INGEST_DEAD_LETTER_ROUTING_KEY`
- a topologia de retry pode ser customizada com `RABBITMQ_RETRY_EXCHANGE`, `RABBITMQ_RETRY_QUEUE`, `RABBITMQ_RETRY_ROUTING_KEY` e `RABBITMQ_RETRY_RETURN_ROUTING_KEY`
- a topologia de dead-letter pode ser customizada com `RABBITMQ_DEAD_LETTER_EXCHANGE`, `RABBITMQ_DEAD_LETTER_QUEUE` e `RABBITMQ_DEAD_LETTER_ROUTING_KEY`
- a API operacional permite inspecionar e reenfileirar a DLQ sem depender do painel do RabbitMQ Management
- o replay da quarentena pode ser feito em lote ou de forma direcionada por `message_id`
- a inspecao da quarentena faz um peek por AMQP com requeue e pode alterar a ordem relativa das mensagens na DLQ

## Ingestao via RabbitMQ
- publicar eventos brutos no exchange `eventflow.events.ingest`
- a fila de entrada padrao e `eventflow.ingest`
- o contrato aceito e equivalente ao da API REST
- `event_name` pode vir no corpo, na propriedade AMQP `type` ou na routing key
- `payload` pode vir no campo `payload` ou ser inferido a partir do corpo sem os campos reservados
- `idempotency_key` pode vir no corpo, no header AMQP `idempotency_key` ou na propriedade `message_id`
- `trace_id` pode vir no corpo, no header AMQP `trace_id` ou na propriedade `correlation_id`

Exemplo de corpo JSON:
```json
{
  "event_name": "user.created",
  "payload": {
    "user_id": "9a1fd14b-4ce9-4321-a8af-b8d98d67a111",
    "email": "user@example.com"
  },
  "metadata": {
    "source": "erp"
  },
  "idempotency_key": "evt-user-created-amqp-001",
  "trace_id": "trace-user-created-amqp-001",
  "occurred_at": "2026-03-28T12:00:00Z"
}
```

## Autenticacao da API
- ingestao de eventos: enviar `X-Ingest-Api-Key` com o valor configurado em `EVENT_INGEST_API_KEY`
- operacao e consultas: enviar `X-Operations-Api-Key` com o valor configurado em `EVENT_OPERATIONS_API_KEY`
- exportacao de metricas: reutiliza `X-Operations-Api-Key`
- `GET /api/v1/health` permanece publico para health check

## Protecao contra abuso
- a API aplica limitacao de taxa por escopo e por IP, separando buckets de ingestao e operacao
- ingestao usa `EVENT_INGEST_RATE_LIMIT_MAX_ATTEMPTS` e `EVENT_INGEST_RATE_LIMIT_DECAY_SECONDS`
- operacao usa `EVENT_OPERATIONS_RATE_LIMIT_MAX_ATTEMPTS` e `EVENT_OPERATIONS_RATE_LIMIT_DECAY_SECONDS`
- quando o limite e excedido, a API responde com `429 Too Many Requests` e envia `Retry-After`, `X-RateLimit-Limit` e `X-RateLimit-Remaining`

## Correlacao e trace
- a API aceita o header opcional `X-Trace-Id` para correlacionar a requisicao com o evento persistido e com a mensagem publicada no RabbitMQ
- quando o header nao e enviado, a aplicacao gera um `trace_id` automaticamente e o devolve no header de resposta `X-Trace-Id`
- a listagem de eventos aceita o filtro `trace_id` para localizar todos os registros ligados a uma mesma correlacao

## Paginacao operacional
- `GET /api/v1/events` usa paginacao com `page` e `per_page`
- o tamanho padrao da pagina e controlado por `EVENT_API_EVENTS_DEFAULT_PER_PAGE`
- o teto aceito por requisicao e controlado por `EVENT_API_EVENTS_MAX_PER_PAGE`
- `GET /api/v1/events/{id}/history` tambem usa paginacao com `page` e `per_page`
- o tamanho padrao do historico por pagina e controlado por `EVENT_API_EVENT_HISTORY_DEFAULT_PER_PAGE`
- o teto aceito por requisicao no historico e controlado por `EVENT_API_EVENT_HISTORY_MAX_PER_PAGE`
- a resposta inclui `count`, `current_page`, `per_page`, `total`, `last_page` e `has_more_pages`

## Qualidade
```bash
composer lint
composer analyse
composer test
composer test:coverage
composer quality
```

`composer test:coverage` exige um driver de cobertura (`pcov` ou `xdebug`). A imagem Docker do projeto e a workflow do GitHub Actions passam a usar `pcov` e aplicam cobertura minima de 80%.

## Entrega continua
- a pipeline de CI continua em `.github/workflows/ci.yml`, validando estilo, analise estatica e cobertura minima
- a pipeline de CD publica a imagem Docker do runtime em `ghcr.io/<owner>/eventflow-platform`
- a publicacao acontece pela workflow `.github/workflows/cd.yml`
- a workflow de CD roda em `push` para `develop`, `main`, `master`, tags `v*` e tambem pode ser disparada manualmente com `workflow_dispatch`
- a mesma imagem publicada atende API, worker e ingest-worker; o comportamento final depende apenas do comando executado no container

## Exemplo de envio de evento
```bash
curl --request POST \
  --url http://localhost:8081/api/v1/events \
  --header 'Content-Type: application/json' \
  --header 'X-Ingest-Api-Key: change-me-ingest' \
  --header 'X-Trace-Id: trace-user-created-001' \
  --header 'Idempotency-Key: evt-user-created-001' \
  --data '{
    "event_name": "user.created",
    "payload": {
      "user_id": "9a1fd14b-4ce9-4321-a8af-b8d98d67a111",
      "email": "user@example.com"
    }
  }'
```

## Exemplo de consulta de eventos processados
```bash
curl --request GET \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url 'http://localhost:8081/api/v1/events?status=processed,processing_failed&trace_id=trace-user-created-001&page=1&per_page=20'
```

## Exemplo de resumo operacional
```bash
curl --request GET \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url 'http://localhost:8081/api/v1/events/summary'
```

## Exemplo de exportacao de metricas
```bash
curl --request GET \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url 'http://localhost:8081/api/v1/metrics'
```

## Exemplo de reenfileiramento manual
```bash
curl --request POST \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url http://localhost:8081/api/v1/events/9a1fd14b-4ce9-4321-a8af-b8d98d67a111/retry
```

## Exemplo de consulta do historico
```bash
curl --request GET \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url 'http://localhost:8081/api/v1/events/9a1fd14b-4ce9-4321-a8af-b8d98d67a111/history?page=1&per_page=20'
```

## Exemplo de inspecao da quarentena
```bash
curl --request GET \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url 'http://localhost:8081/api/v1/quarantine?limit=10'
```

## Exemplo de replay da quarentena
```bash
curl --request POST \
  --header 'Content-Type: application/json' \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url http://localhost:8081/api/v1/quarantine/replay \
  --data '{
    "limit": 1
  }'
```

## Exemplo de replay direcionado da quarentena
```bash
curl --request POST \
  --header 'Content-Type: application/json' \
  --header 'X-Operations-Api-Key: change-me-operations' \
  --url http://localhost:8081/api/v1/quarantine/replay \
  --data '{
    "message_ids": ["evt-quarantine-replay-001"]
  }'
```

## Escopo da etapa atual
Esta etapa estabelece a base do backend, a infraestrutura local, a recepcao de eventos por API REST e por RabbitMQ, o processamento assincrono por worker RabbitMQ, os controles operacionais de resumo, listagem paginada de eventos, historico paginado de transicoes, reenfileiramento manual, autenticacao por chave de API com escopos separados, rate limit por escopo e IP, correlacao distribuida por `trace_id`, exportacao de metricas operacionais em formato Prometheus, retry automatico com atraso progressivo, quarentena de mensagens terminais via dead-letter queue e operacao autenticada de inspecao e replay da DLQ.
