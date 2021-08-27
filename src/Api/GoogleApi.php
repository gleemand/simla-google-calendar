<?php

namespace App\Api;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use App\Config;
use Psr\Log\LoggerInterface;

class GoogleApi
{
    private $logger;

    public $message;
    
    private $config;

    private $client;

    private $credentials;

    private $token;

    private $calendarId;

    private $simlaUrl;

    public function __construct(LoggerInterface $logger, Config $config, $code = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->credentials = $this->config->get('google_credentials_file');
        $this->token = $this->config->get('google_token');
        $this->redirectUrl = $this->config->get('google_redirect_url');
        $this->calendarId = $this->config->get('google_calendar_id');

        $this->simlaUrl = $this->config->get('simla_api_url');

        $client = new Client();
        $client->setApplicationName('Simla to Google Calendar');
        $client->addScope(Calendar::CALENDAR_EVENTS);
        $client->addScope(Drive::DRIVE_FILE);
        $client->setAuthConfig(__DIR__ . '/../../' . $this->credentials);
        $client->setRedirectUri($this->redirectUrl);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $this->client = $this->authClient($client, $code);
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
                'date' => date_format($order->createdAt, 'Y-m-d'),
            ),
            'end' => array(
                'date' => date_format($order->createdAt, 'Y-m-d'),
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

        $eventId = $order->customFields['google_calendar_id'];
        $event = $serviceCalendar->events->get($this->calendarId, $eventId);
        $event->setDescription(
            '<ul>' .
            '<li><b>Order #</b>' . $order->id . '</li>' .
            '<li><b>Name: </b>' . $order->firstName . ' ' . $order->lastName . '</li>' .
            '<li><b>Email: </b>' . $order->email . '</li>' .
            '<li><b>Created at: </b>' . date_format($order->createdAt, 'Y-m-d H:i:s') . '</li>' .
            '<li><b>Description: </b>' . $order->customerComment . " " . $order->managerComment . '</li>' .
            '</ul>'
        );
        $serviceCalendar->events->update($this->calendarId, $event->getId(), $event);

        $this->logger->info("Event for order #$order->id updated");

        return true;
    }

    public function authClient($client, $code = null)
    {
        // Load previously authorized token from a file, if it exists.
        if (!empty($accessToken = json_decode($this->token, true))) {
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                $this->config->set('google_token', json_encode($client->getAccessToken()));
                $this->logger->info('Google token is updated');
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();

                if ($code == null) {
                    $this->message = '<button onclick="document.location=\'' . $authUrl . '\'">Connect your Google account to Simla.com</button>';
                    $this->logger->error('You have to log in by your Google account');

                    return $client;
                } else {
                    $authCode = $code;
                }

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new \Exception(join(', ', $accessToken));
                }

                $this->config->set('google_token', json_encode($client->getAccessToken()));
                $this->logger->info('Google token is stored');
            }
        }

        $this->message = 'Your Google account is connected';

        return $client;
    }
}
