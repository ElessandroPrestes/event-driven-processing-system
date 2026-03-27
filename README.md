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

## Qualidade
```bash
composer lint
composer analyse
composer test
composer quality
```

## Escopo da etapa atual
Esta etapa estabelece a base do backend, a infraestrutura local e a linha de base de qualidade. Publicação de eventos, consumers RabbitMQ, persistência de eventos processados e documentação OpenAPI serão implementados nas próximas etapas.
