<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        spl_autoload_register(function (string $class): void {
            if (str_starts_with($class, 'App\\')) {
                $file = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, 4)).'.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            } elseif (str_starts_with($class, 'Database\\Factories\\')) {
                $file = __DIR__.'/../database/factories/'.str_replace('\\', '/', substr($class, 19)).'.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            } elseif (str_starts_with($class, 'Database\\Seeders\\')) {
                $file = __DIR__.'/../database/seeders/'.str_replace('\\', '/', substr($class, 17)).'.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }, true, true);
    }

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
