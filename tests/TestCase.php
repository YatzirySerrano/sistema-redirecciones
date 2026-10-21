<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Salvaguarda: RefreshDatabase recrea el esquema, así que las pruebas solo
     * se ejecutan sobre una base cuyo nombre termine en "_testing".
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $conexion = (string) $app['config']->get('database.default');
        $base = (string) $app['config']->get("database.connections.{$conexion}.database");

        if (! str_ends_with($base, '_testing')) {
            throw new RuntimeException("Las pruebas se negaron a ejecutarse sobre la base «{$base}». Usa una base *_testing.");
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
