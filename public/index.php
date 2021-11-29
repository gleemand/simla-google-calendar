<?php

use Slim\App;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../container/container.php';

$app = $container->get(App::class);

//$app->setBasePath('');

$app->get('/', \App\Action\HomeAction::class)->setName('home');
$app->get('/auth', \App\Action\AuthAction::class);
$app->get('/config', \App\Action\ConfigAction::class)->setName('config');
$app->get('/logout', \App\Action\LogoutAction::class);
$app->get('/delete', \App\Action\DeleteAction::class);
$app->post('/config', \App\Action\ConfigAction::class);
$app->post('/save', \App\Action\SaveAction::class);
$app->post('/activity', \App\Action\ActivityAction::class);
$app->get('/activity', \App\Action\ActivityAction::class);

$app->run();
