<?php

it('renders the swagger ui documentation page', function (): void {
    $this->get('/docs')
        ->assertOk()
        ->assertSee('Documentacao OpenAPI')
        ->assertSee(route('docs.openapi', absolute: false), false)
        ->assertSee('SwaggerUIBundle', false);
});

it('serves the openapi specification as yaml', function (): void {
    $this->get('/docs/openapi.yaml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8')
        ->assertSee('openapi: 3.1.0')
        ->assertSee('/api/v1/events');
});
