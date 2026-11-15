<?php

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->setPsr4('App\\', [__DIR__.'/../app']);
$loader->setPsr4('Database\\Factories\\', [__DIR__.'/../database/factories']);
$loader->setPsr4('Database\\Seeders\\', [__DIR__.'/../database/seeders']);
$loader->setPsr4('Tests\\', [__DIR__.'/../tests']);
