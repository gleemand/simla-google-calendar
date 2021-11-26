<?php

namespace App\Api;

use RetailCrm\Api\Enum\PaginationLimit;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Filter\Orders\OrderHistoryFilterV4Type;
use RetailCrm\Api\Model\Request\Orders\OrdersHistoryRequest;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;
use RetailCrm\Api\Model\Filter\Files\FileFilter;
use RetailCrm\Api\Model\Request\Files\FilesRequest;
use RetailCrm\Api\Enum\CustomFields\CustomFieldEntity;
use RetailCrm\Api\Model\Entity\Integration\IntegrationModule;
use RetailCrm\Api\Model\Request\Integration\IntegrationModulesEditRequest;
use App\Config;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Model\Entity\CustomFields\CustomField;
use RetailCrm\Api\Model\Request\CustomFields\CustomFieldsCreateRequest;



class SimlaApi
{
    private $logger;

    private $config;

    private $apiUrl;

    private $apiKey;

    private $historyId;

    private $client;

    private $userId;

    private static $customFields = [
        [
            'code' => 'event_id',
            'name' => 'Event ID',
            'viewMode' => 'not_editable',
            'type' => 'string',
            'displayArea' => 'customer',
            'ordering' => 9904,

        ],
        [
            'code' => 'event_url',
            'name' => 'Event URL',
            'viewMode' => 'not_editable',
            'type' => 'string',
            'displayArea' => 'customer',
            'ordering' => 9903,

        ],
        [
            'code' => 'event_date',
            'name' => 'Event date',
            'viewMode' => 'editable',
            'type' => 'date',
            'displayArea' => 'customer',
            'ordering' => 9900,
        ],
        [
            'code' => 'event_time_start',
            'name' => 'Event start time (00:00)',
            'viewMode' => 'editable',
            'type' => 'string',
            'displayArea' => 'customer',
            'ordering' => 9901,
        ],
        [
            'code' => 'event_time_end',
            'name' => 'Event end time (00:00)',
            'viewMode' => 'editable',
            'type' => 'string',
            'displayArea' => 'customer',
            'ordering' => 9902,
        ],
    ];

    public function __construct(LoggerInterface $logger, Config $config, $userId = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->apiUrl = $this->config->get($userId, 'simla_api_url');
        $this->apiKey = $this->config->get($userId, 'simla_api_key');
        $this->historyId = $this->config->get($userId, 'simla_history_id');
        $this->userId = $userId;

        $this->client = SimpleClientFactory::createClient($this->apiUrl, $this->apiKey);
    }

    public function getHistory()
    {
        $history = [];

        $apiRequest = new OrdersHistoryRequest();
        $apiRequest->filter = new OrderHistoryFilterV4Type();
        $apiRequest->limit = PaginationLimit::LIMIT_100;
        $apiRequest->filter->sinceId = $this->historyId;

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $apiResponse = $this->client->orders->history($apiRequest);
            } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
                $this->logger->error($exception->getMessage());

                continue;
            }

            if (empty($apiResponse->history)) {
                break;
            }

            $history = array_merge($history, $apiResponse->history);
            $apiRequest->filter->startDate = null;
            $apiRequest->filter->sinceId = end($apiResponse->history)->id;
        } while ($apiResponse->pagination->currentPage < $apiResponse->pagination->totalPageCount);

        return $history;
    }

    public function getOrder($change)
    {
        try {
            $apiResponse = $this->client->orders->get($change->order->id, new BySiteRequest(ByIdentifier::ID, $change->order->site));
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Getting order #' . $change->order->id . ': ' . $exception->getMessage());

            return false;
        }

        return $apiResponse->order;
    }

    public function getFileList($orderId)
    {
        $apiRequest = new FilesRequest();
        $apiRequest->filter = new FileFilter();
        $apiRequest->filter->orderIds = [$orderId];

        try {
            $apiResponse = $this->client->files->list($apiRequest);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Getting file list of order #' . $orderId . ': ' . $exception->getMessage());

            return false;
        }

        return $apiResponse->files;
    }

    public function downloadFile($fileId)
    {
        try {
            $apiResponse = $this->client->files->download($fileId);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Downloading file #' . $fileId . ': ' . $exception->getMessage());

            return false;
        }

        return $apiResponse;
    }

    public function putEventDataToOrder($order, $event)
    {
        if (!$this->putDataToOrder($order, 'event_id', $event->id)) {
            return false;
        }

        if (!$this->putDataToOrder($order, 'event_url', $event->htmlLink)) {
            return false;
        }

        return true;
    }

    public function putDataToOrder($order, $field, $data)
    {
        $apiRequest = new OrdersEditRequest();
        $apiRequest->order = new Order();
        $apiRequest->order->customFields[$field] = $data;
        $apiRequest->site = $order->site;
        $apiRequest->by = ByIdentifier::ID;

        try {
            $apiResponse = $this->client->orders->edit($order->id, $apiRequest);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Editing order #' . $order->id . ': ' . $exception->getMessage());

            return false;
        }

        return true;
    }

    public function createCustomFields()
    {
        foreach (self::$customFields as $customField) {
            try {
                $apiResponse = $this->client->customFields->get(CustomFieldEntity::ORDER, $customField['code']);
            } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
                $this->logger->error('Custom field \'' . $customField['code'] . '\': ' . $exception->getMessage());

                if ($exception->getCode() == 404) {
                    if (!$this->createCustomField($customField)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public function getManager($id)
    {
        try {
            $apiResponse = $this->client->users->get($id);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Getting manager e-mail: ' . $exception->getMessage());

            return false;
        }

        return $apiResponse->user;
    }

    private function createCustomField($customField)
    {
        $field                 = new CustomField();
        $field->name           = $customField['name'];
        $field->code           = $customField['code'];
        $field->type           = $customField['type'];
        $field->viewMode       = $customField['viewMode'];
        $field->displayArea    = $customField['displayArea'];

        try {
            $apiResponse = $this->client->customFields->create(
                CustomFieldEntity::ORDER,
                new CustomFieldsCreateRequest($field)
            );
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Creating custom field \'' . $customField['code'] . '\': ' . $exception->getMessage());

            return false;
        }

        $this->logger->info('Custom field \'' . $customField['code'] . '\' created in CRM');

        return true;
    }

    public function checkApi()
    {
        try {
            $this->client->api->credentials();
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Simla API: ' . $exception->getMessage());

            return false;
        }

        return true;
    }

    public function connectModule()
    {
        $module = new IntegrationModule();

        $module->clientId = $this->userId;
        $module->code = 'google-calendar';
        $module->integrationCode = 'google-calendar';
        $module->name = 'Google Calendar';
        $module->logo = 'https://upload.wikimedia.org/wikipedia/commons/a/a5/Google_Calendar_icon_%282020%29.svg';
        $module->baseUrl = 'https://simla-calendar.dev.skillum.ru/';
        $module->actions = ['activity' => '/activity'];
        $module->accountUrl = 'https://simla-calendar.dev.skillum.ru/config';

        try {
            $apiResponse = $client->integration->edit('mg-fbmessenger', new IntegrationModulesEditRequest($module));
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error('Connect module: ' . $exception->getMessage());

            return false;
        }

        $this->logger->info('Connect module: ' . json_encode($apiResponse->info));

        return true;
    }
}
