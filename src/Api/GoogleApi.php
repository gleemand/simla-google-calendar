<?php

namespace App\Api;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Oauth2;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use App\Config;
use Psr\Log\LoggerInterface;

class GoogleApi
{
    private $logger;

    public $userId;

    private $config;

    private $client;

    private $credentials;

    private $token;

    private $calendarId;

    private $simlaUrl;

    private $code;

    public function __construct(LoggerInterface $logger, Config $config, $userId = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->credentials = $this->config->get('main', 'google_credentials_file');
        $this->token = $this->config->get($userId, 'google_token');
        $this->redirectUrl = $this->config->get('main', 'google_redirect_url');
        $this->calendarId = $this->config->get($userId, 'google_calendar_id');

        $this->simlaUrl = $this->config->get($userId, 'simla_api_url');

        $this->newClient();
    }

    public function newClient()
    {
        $this->client = new Client();
        $this->client->setLogger($this->logger);
        $this->client->setApplicationName('Simla calendar');
        $this->client->addScope(Calendar::CALENDAR_EVENTS);
        $this->client->addScope(Drive::DRIVE_FILE);
        $this->client->addScope(Oauth2::USERINFO_EMAIL);
        $this->client->setAuthConfig(__DIR__ . '/../../' . $this->credentials);
        $this->client->setRedirectUri($this->redirectUrl);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client = $this->authClient();
    }

    public function generateAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    public function uploadFile($fileToUpload, $order)
    {
        $serviceDrive = new Drive($this->client);
        $serviceFile = new DriveFile;
        $serviceFile->setName($fileToUpload->fileName);
        $serviceFile->setDescription('Attachment of order #' . $order->id);
        $result = $serviceDrive->files->create(
            $serviceFile,
                array(
                    'data' => file_get_contents('./temp/' . $fileToUpload->fileName),
                    'mimeType' => 'application/octet-stream',
                    'uploadType' => 'multipart'
                )
        );

        unlink ('./temp/' . $fileToUpload->fileName);

        return $result;
    }

    public function createEvent($order, $attachments)
    {
        $serviceCalendar = new Calendar($this->client);

        $date = $this->prepareDate($order, false);

        $event = new Event(array(
            'summary' => 'Order #' . $order->id,
            'description' =>    '<ul>' .
                                    '<li><b>Order #</b>' . $order->id . '</li>' .
                                    '<li><b>Name: </b>' . $order->firstName . ' ' . $order->lastName . '</li>' .
                                    '<li><b>Email: </b>' . $order->email . '</li>' .
                                    '<li><b>Created at: </b>' . date_format($order->createdAt, 'Y-m-d H:i:s') . '</li>' .
                                    '<li><b>Description: </b>' . $order->customerComment . " " . $order->managerComment . '</li>' .
                                '</ul>',
            'start' => array(
                'dateTime' => $date['start'],
            ),
            'end' => array(
                'dateTime' => $date['end'],
            ),
            'source' => array(
                'title' => 'Manage on Simla.com',
                'url' => rtrim($this->simlaUrl, '/\\') . '/orders/' . $order->id . '/edit',
            ),
            'attachments' => $attachments,
        ));

        $event = $serviceCalendar->events->insert($this->calendarId, $event, ['supportsAttachments' => true]);

        $this->logger->info("Event for order #$order->id created");

        return $event;
    }

    public function updateEvent($order)
    {
        $serviceCalendar = new Calendar($this->client);

        $date = $this->prepareDate($order);

        $startDate = new EventDateTime();
        $startDate->setDateTime($date['start']);

        $endDate = new EventDateTime();
        $endDate->setDateTime($date['end']);

        $eventId = $order->customFields['event_id'];
        $event = $serviceCalendar->events->get($this->calendarId, $eventId);

        if ($event->status == 'cancelled') {
            $this->logger->error("Event for order #$order->id is cancelled");
            return false;
        }

        $event->setDescription(
            '<ul>' .
            '<li><b>Order #</b>' . $order->id . '</li>' .
            '<li><b>Name: </b>' . $order->firstName . ' ' . $order->lastName . '</li>' .
            '<li><b>Email: </b>' . $order->email . '</li>' .
            '<li><b>Created at: </b>' . date_format($order->createdAt, 'Y-m-d H:i:s') . '</li>' .
            '<li><b>Description: </b>' . $order->customerComment . " " . $order->managerComment . '</li>' .
            '</ul>'
        );

        $event->setStart($startDate);
        $event->setEnd($endDate);
        $serviceCalendar->events->update($this->calendarId, $event->getId(), $event);

        $this->logger->info("Event for order #$order->id updated");

        return true;
    }

    public function authClient($code = null)
    {
        if (!empty($accessToken = json_decode($this->token, true))) {
            $this->client->setAccessToken($accessToken);
        }

        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());

                $this->config->set($this->userId, 'google_token', json_encode($this->client->getAccessToken()));

                $this->logger->info('Google token is updated');
            } else {
                if ($code == null) {
                    $this->logger->error('Code from google is absente');

                    return $this->client;
                }

                $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
                $this->client->setAccessToken($accessToken);

                if (array_key_exists('error', $accessToken)) {
                    throw new \Exception(join(', ', $accessToken));

                    $this->config->set($this->userId, 'google_token', '');
                    $this->logger->info('Logged out');
                } else {
                    $token = $this->client->getAccessToken();
                    $this->userId = $this->getUserFromToken();
                    $this->config->set($this->userId, 'google_token', json_encode($token));
                    $this->logger->info('Google token is stored');
                }
            }
        }

        return $this->client;
    }

    public function getUserFromToken() {
        $data = $this->client->verifyIdToken();

        if (
            $data
            && isset($data['email_verified'])
            && $data['email_verified'] == 1
        ) {
            return $data['email'];
        }

        return false;
    }

    private function prepareDate($order)
    {
        $date = [];

        if (
            isset($order->customFields['event_date'])
            && !empty($order->customFields['event_date'])
            && isset($order->customFields['event_time_start'])
            && !empty($order->customFields['event_time_start'])
            && isset($order->customFields['event_time_end'])
            && !empty($order->customFields['event_time_end'])
        ) {
            $date['start'] = date_format(date_create(
                $order->customFields['event_date'] .
                $order->customFields['event_time_start'] .
                '+03'),
            DATE_RFC3339);
            $date['end'] = date_format(date_create(
                $order->customFields['event_date'] .
                $order->customFields['event_time_end'] .
                '+03'),
            DATE_RFC3339);
        } else {
            $date['start'] = date_format($order->createdAt, DATE_RFC3339);
            $date['end'] = $date['start'];
        }

        return $date;
    }
}
