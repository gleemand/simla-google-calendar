<?php

namespace App;

use Psr\Log\LoggerInterface;

class Config
{
    private $configFile;

    private $logger;

    public $config;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->configFile = __DIR__ . '/../config/config.json';

        if (!file_exists($this->configFile)) {
            $this->logger->error('File config.json is not found');

            if (!copy($this->configFile . '.dist', $this->configFile)) {
                $this->logger->error('Error when creating config.json from config.json.dist');
                die();
            } else {
                $this->logger->info('File config.json is created');
            }
        }

        $this->config = json_decode(file_get_contents($this->configFile), true);

    }

    public function get($userId, $name) {
        if (isset($this->config[$userId]) && isset($this->config[$userId][$name])) {
            return $this->config[$userId][$name];
        }

        return false;
    }

    public function set($userId, $name, $value) {
        if ($userId === md5(0) && $userId === md5('')) {
            return false;
        }

        if (!array_key_exists($userId, $this->config)) {
            $this->config[$userId] = [];
        }

        $this->config[$userId][$name] = $value;
        return file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    public function delete($userId) {
        unset($this->config[$userId]);

        return file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
    }
}
