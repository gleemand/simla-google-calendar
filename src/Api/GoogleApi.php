<?php

namespace App\Api;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Oauth2;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception;
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

    private $createMeet;

    private $timeZone;

    private $code;

    public function __construct(LoggerInterface $logger, Config $config, $userId = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->userId = $userId;

        $this->credentials = $this->config->get('main', 'google_credentials_file');
        $this->token = $this->config->get($this->userId, 'google_token');
        $this->redirectUrl = $this->config->get('main', 'google_redirect_url');
        $this->calendarId = $this->config->get($this->userId, 'google_calendar_id');
        $this->createMeet = $this->config->get($this->userId, 'create_meet');
        $this->timeZone = $this->config->get($this->userId, 'time_zone');

        $this->simlaUrl = $this->config->get($this->userId, 'simla_api_url');

        $this->newClient();
    }

    public function newClient()
    {
        $this->client = new Client();
        $this->client->setLogger($this->logger);
        $this->client->setApplicationName('Simla Calendar');
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
        $serviceFile->setDescription('Attachment of order #' . $order->number);

        try {
            $result = $serviceDrive->files->create(
                $serviceFile,
                    array(
                        'data' => file_get_contents('./temp/' . $fileToUpload->fileName),
                        'mimeType' => 'application/octet-stream',
                        'uploadType' => 'multipart'
                    )
            );
        } catch (Exception $exception) {
            $this->logger->error('Uploading file for order #' . $order->id . ': ' . json_encode($exception->getErrors()));

            return false;
        }

        unlink ('./temp/' . $fileToUpload->fileName);

        return $result;
    }

    public function createEvent($order, $manager, $attachments)
    {
        $serviceCalendar = new Calendar($this->client);

        $date = $this->prepareDate($order, false);

        if ($this->createMeet) {
            $attendees = [];

            isset($order->email) ? $attendees[] = [
                'displayName' => $order->firstName . ' ' . $order->lastName,
                'email' => $order->email,
            ] : null;

            isset($manager->email) ? $attendees[] = [
                'displayName' => $manager->firstName . ' - Manager',
                'email' => $manager->email,
            ] : null;

            isset($this->userId) ? $attendees[] = [
                'displayName' => 'Organizer',
                'email' => $this->userId,
            ] : null;

            $order->customerComment = '';
            $order->managerComment = '';
        } else {
            $attendees = [];
        }

        echo "<pre>", print_r($attendees, true), "</pre>";

        $event = new Event(array(
            'summary' => 'Order #' . $order->number,
            'description' =>    '<ul>' .
                                    '<li><b>Order #</b>' . $order->number . '</li>' .
                                    '<li><b>Name: </b>' . $order->firstName . ' ' . $order->lastName . '</li>' .
                                    '<li><b>Email: </b>' . $order->email . '</li>' .
                                    '<li><b>Created at: </b>' . date_format($order->createdAt, 'Y-m-d H:i:s') . '</li>' .
                                    '<li><b>Customer comment: </b>' . $order->customerComment .
                                    '<li><b>Manager comment: </b>' . $order->managerComment .
                                '</ul>',
            'start' => array(
                'dateTime' => $date['start'],
            ),
            'end' => array(
                'dateTime' => $date['end'],
            ),
            'source' => array(
                'title' => 'Manage order',
                'url' => rtrim($this->simlaUrl, '/\\') . '/orders/' . $order->id . '/edit',
            ),
            'attachments' => $attachments,
            'attendees' => $attendees,
        ));

        if ($this->createMeet) {
            $solution_key = new ConferenceSolutionKey();
            $solution_key->setType("hangoutsMeet");
            $conferenceRequest = new CreateConferenceRequest();
            $conferenceRequest->setRequestId(random_int(10000000, 99999999));
            $conferenceRequest->setConferenceSolutionKey($solution_key);
            $conference = new ConferenceData();
            $conference->setCreateRequest($conferenceRequest);
            $event->setConferenceData($conference);
        }

        try {
            $event = $serviceCalendar->events->insert($this->calendarId, $event, [
                'supportsAttachments' => true,
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all',
            ]);
        } catch (Exception $exception) {
            $this->logger->error('Creating event for order #' . $order->id . ': ' . json_encode($exception->getErrors()));

            return false;
        }

        $this->logger->info("Event for order #$order->number created");

        return $event;
    }

    public function updateEvent($order, $manager)
    {
        $serviceCalendar = new Calendar($this->client);

        $date = $this->prepareDate($order);

        $startDate = new EventDateTime();
        $startDate->setDateTime($date['start']);

        $endDate = new EventDateTime();
        $endDate->setDateTime($date['end']);

        $eventId = $order->customFields['event_id'];

        try {
            $event = $serviceCalendar->events->get($this->calendarId, $eventId);
        } catch (Exception $exception) {
            $this->logger->error('Getting event for order #' . $order->id . ': ' . json_encode($exception->getErrors()));

            return false;
        }

        if ($event->status == 'cancelled') {
            $this->logger->error("Event for order #$order->number is cancelled");
            return false;
        }

        if ($this->createMeet) {
            $order->customerComment = '';
            $order->managerComment = '';
        }

        $event->setDescription(
            '<ul>' .
                '<li><b>Order #</b>' . $order->number . '</li>' .
                '<li><b>Name: </b>' . $order->firstName . ' ' . $order->lastName . '</li>' .
                '<li><b>Email: </b>' . $order->email . '</li>' .
                '<li><b>Created at: </b>' . date_format($order->createdAt, 'Y-m-d H:i:s') . '</li>' .
                '<li><b>Customer comment: </b>' . $order->customerComment .
                '<li><b>Manager comment: </b>' . $order->managerComment .
            '</ul>',
        );

        $event->setStart($startDate);
        $event->setEnd($endDate);

        try {
            $serviceCalendar->events->update($this->calendarId, $event->getId(), $event, [
                'supportsAttachments' => true,
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all',
            ]);
        } catch (Exception $exception) {
            $this->logger->error('Updating event for order #' . $order->id . ': ' . json_encode($exception->getErrors()));

            return false;
        }

        $this->logger->info("Event for order #$order->number updated");

        return true;
    }

    public function authClient($code = null)
    {
        if (!empty($accessToken = json_decode($this->token, true))) {
            $this->client->setAccessToken($accessToken);
            $userId = $this->getUserFromToken();

            if ($userId) {
                $this->userId = $userId;
            }
        }

        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());

                $this->config->set($this->userId, 'google_token', json_encode($this->client->getAccessToken()));

                $this->logger->info('Google token is updated');
            } else {
                if ($code == null) {
                    $this->logger->error('Auth code is absent');

                    return $this->client;
                }

                $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
                $this->client->setAccessToken($accessToken);

                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));

                    $this->config->set($this->userId, 'google_token', '');
                    $this->logger->error('Error -> logged out');
                } else {
                    $token = $this->client->getAccessToken();
                    $userId = $this->getUserFromToken();

                    if ($userId) {
                        $this->userId = $userId;
                    }

                    if ($this->userId) {
                        $this->config->set($this->userId, 'google_token', json_encode($token));
                        $this->logger->info('Google token is stored');
                    } else {
                        $this->logger->error('Can not receive your e-mail from google api!');
                    }
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
                $order->customFields['event_time_start'],
                timezone_open($this->timeZone)),
            DATE_RFC3339);
            $date['end'] = date_format(date_create(
                $order->customFields['event_date'] .
                $order->customFields['event_time_end'],
                timezone_open($this->timeZone)),
            DATE_RFC3339);
        } else {
            $date['start'] = date_format($order->createdAt, DATE_RFC3339);
            $date['end'] = $date['start'];
        }

        return $date;
    }
}
