# Arquitetura Inicial

## Objetivo
Estabelecer a base do `eventflow-platform` como um backend Laravel orientado a eventos, preparado para evoluir em pequenas etapas revisáveis.

## Camadas
- `app/Application`: orquestra casos de uso e ações de aplicação.
- `app/Domain`: concentrará regras centrais de domínio e contratos independentes de framework.
- `app/Infrastructure`: concentrará integrações externas, mensageria, persistência e adapters.
- `app/Interfaces`: expõe HTTP, CLI e demais pontos de entrada.

## Decisões desta etapa
- Laravel 13 como base do backend.
- PostgreSQL como banco principal.
- Redis como cache e fila auxiliar do Laravel.
- RabbitMQ reservado como backbone de eventos externos.
- Docker Compose como ambiente local padrão.
- Pest, Pint e PHPStan/Larastan como baseline de qualidade.
- Logs estruturados em JSON direcionados para `stdout`, favorecendo observabilidade em containers.
- Exportacao de metricas operacionais em formato Prometheus via endpoint autenticado para integracao com monitoramento externo.

## Segurança e OWASP considerados
- Não expor segredos em código; variáveis sensíveis permanecem em ambiente.
- Manter imagem de aplicação com dependências mínimas para reduzir superfície de ataque.
- Evitar exposição desnecessária de detalhes internos no endpoint de health.
- Proteger endpoints de ingestão e operação com chaves de API distintas para reduzir exposição indevida de payloads e comandos operacionais.
- Propagar `trace_id` entre HTTP, persistência e RabbitMQ para ampliar rastreabilidade e investigação operacional sem depender apenas de timestamps.
- Preparar separação de camadas para reduzir acoplamento e facilitar validação futura de entrada, autorização e rastreabilidade.
