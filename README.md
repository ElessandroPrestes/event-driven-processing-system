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
- API Laravel: `http://localhost:8080`
- Health check da API: `http://localhost:8080/api/v1/health`
- Recepcao de eventos: `POST http://localhost:8080/api/v1/events`
- Resumo operacional: `GET http://localhost:8080/api/v1/events/summary`
- Consulta de eventos: `GET http://localhost:8080/api/v1/events`
- Consulta de evento: `GET http://localhost:8080/api/v1/events/{id}`
- Reenfileiramento manual: `POST http://localhost:8080/api/v1/events/{id}/retry`
- Health nativo do Laravel: `http://localhost:8080/up`
- PostgreSQL: `localhost:5432`
- Redis: `localhost:6379`
- RabbitMQ AMQP: `localhost:5672`
- RabbitMQ Management: `http://localhost:15672`

Na primeira subida o container da aplicação:
- copia `.env.example` para `.env` se necessário
- instala dependências PHP
- gera `APP_KEY` se necessário
- executa as migrations

O serviço `worker` inicia junto com `docker compose up` e fica consumindo a fila RabbitMQ com o comando `php artisan events:consume`.

## Qualidade
```bash
composer lint
composer analyse
composer test
composer quality
```

## Exemplo de envio de evento
```bash
curl --request POST \
  --url http://localhost:8080/api/v1/events \
  --header 'Content-Type: application/json' \
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
  --url 'http://localhost:8080/api/v1/events?status=processed,processing_failed'
```

## Exemplo de resumo operacional
```bash
curl --request GET \
  --url 'http://localhost:8080/api/v1/events/summary'
```

## Exemplo de reenfileiramento manual
```bash
curl --request POST \
  --url http://localhost:8080/api/v1/events/9a1fd14b-4ce9-4321-a8af-b8d98d67a111/retry
```

## Escopo da etapa atual
Esta etapa estabelece a base do backend, a infraestrutura local, a recepcao de eventos, o processamento assincrono por worker RabbitMQ e os controles operacionais de resumo e reenfileiramento manual. Evolucoes futuras incluem autenticacao, telemetria externa e politicas de resiliencia ainda mais refinadas.
