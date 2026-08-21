<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanvasDiagnosticsRouteTest extends TestCase
{
    public function test_dev_diagnostics_route_is_available_outside_production(): void
    {
        $response = $this->get('/dev/canvas-diagnostics');

        $response->assertOk();
        $response->assertSee('Canvas browser diagnostics');
    }

    public function test_dev_diagnostics_route_is_not_registered_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $response = $this->get('/dev/canvas-diagnostics');

        $response->assertNotFound();
    }
}
