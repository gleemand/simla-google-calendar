<?php

namespace App;

use Psr\Log\LoggerInterface;

class Config
{
    private $configFile;

    private $logger;

    private $config;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->configFile = __DIR__ . '/../config/config.json';

        if (file_exists($this->configFile)) {
            $this->config = json_decode(file_get_contents($this->configFile));
        } else {
            $this->logger->error('File config.json is not found');
            
            if (!copy($this->configFile . '.dist', $this->configFile)) {
                $this->logger->error('Error when creating config.json from config.json.dist');
            } else {
                $this->logger->info('File config.json is created. Do not forget to fill it up!');
            }
            
            exit();
        }

        $this->checkParams();
        
    }

    public function get($name) {
        return $this->config->$name;
    }

    public function set($name, $value) {
        $this->config->$name = $value;
        return file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    public function checkParams() {
        $empty = false;

        foreach ($this->config as $key => $param) {
            if (empty($param)) {
                $this->logger->error($key . ' parameter in config.json is empty');
                $empty = true;
            }
        }

        if ($empty) {
            exit();
        }
    }
}
