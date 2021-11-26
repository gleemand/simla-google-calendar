<?php

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use DI\ContainerBuilder;
use App\Config;
use App\HistoryReset;
use App\Api\SimlaApi;
use App\Api\GoogleApi;
use App\Command\HistoryResetCommand;
use App\Command\SyncCommand;
use App\Command\LogoutCommand;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Interfaces\RouteParserInterface;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    App::class => function (ContainerInterface $c) {
        AppFactory::setContainer($c);

        return AppFactory::create();
    },
    LoggerInterface::class => function () {
        $logger = new Logger('Log');
        $handler = new RotatingFileHandler(__DIR__ . '/../log/log.log', 30,  Logger::DEBUG);
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        return $logger;
    },
    Config::class => function (ContainerInterface $c) {
        return new Config(
            $c->get(LoggerInterface::class)
        );
    },
    SimlaApi::class => function (ContainerInterface $c) {
        return new SimlaApi(
            $c->get(LoggerInterface::class),
            $c->get(Config::class)
        );
    },
    GoogleApi::class => function (ContainerInterface $c) {
        return new GoogleApi(
            $c->get(LoggerInterface::class),
            $c->get(Config::class)
        );
    },
    HistoryResetCommand::class => function (ContainerInterface $c) {
        return new HistoryResetCommand(
            $c->get(LoggerInterface::class),
            $c->get(Config::class),
            $c->get(HistoryReset::class)
        );
    },
    SyncCommand::class => function (ContainerInterface $c) {
        return new SyncCommand(
            $c->get(LoggerInterface::class),
            $c->get(Config::class)
        );
    },
    LogoutCommand::class => function (ContainerInterface $c) {
        return new LogoutCommand(
            $c->get(LoggerInterface::class),
            $c->get(Config::class)
        );
    },
    HistoryReset::class => function (ContainerInterface $c) {
        return new HistoryReset(
            $c->get(LoggerInterface::class),
            $c->get(Config::class)
        );
    },
    Twig::class => function () {
        return Twig::create('../views/templates', ['cache' => false]);
    },
    RouteParserInterface::class =>  function (ContainerInterface $c) {
        return $c->get(App::class)->getRouteCollector()->getRouteParser();
    },
]);

$container = $containerBuilder->build();
