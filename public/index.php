<?php

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../container/container.php';

AppFactory::setContainer($container);

$app = AppFactory::create();

$app->get('/auth', \App\Action\AuthAction::class);

$app->run();
