<?php

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use DI\ContainerBuilder;
use App\Config;
use App\Api\SimlaApi;
use App\Api\GoogleApi;
use App\Command\HistoryResetCommand;
use App\Command\SyncCommand;
use App\Command\LogoutCommand;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
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
            $c->get(SimlaApi::class)
        );
    },
    SyncCommand::class => function (ContainerInterface $c) {
        return new SyncCommand(
            $c->get(LoggerInterface::class),
            $c->get(Config::class),
            $c->get(SimlaApi::class),
            $c->get(GoogleApi::class)
        );
    },
    LogoutCommand::class => function (ContainerInterface $c) {
        return new LogoutCommand(
            $c->get(LoggerInterface::class),
            $c->get(Config::class)
        );
    },
]);

$container = $containerBuilder->build();
