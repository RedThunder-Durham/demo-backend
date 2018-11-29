<?php
function wait_input()
{
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return $line;
}

$TIMEZONE = 'Europe/London';

require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be ran from the command line');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Calendar API PHP Quickstart');
    $client->setScopes([
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events',
    ]);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Calendar($client);

$PAYPAL_CALENDAR_ID = '1mlpohc4b7q3ujqvpndg27vna4@group.calendar.google.com';
$ADMIN_CALENDAR_ID  = 'durhamredthunder2018@gmail.com';

// Print the next 10 events on the user's calendar.
$optParams = array(
  'maxResults' => 10,
  'orderBy' => 'startTime',
  'singleEvents' => true,
  'timeMin' => '2018-11-29T22:00:00Z',
);
//print_r($optParams);

$results = $service->events->listEvents($ADMIN_CALENDAR_ID, $optParams);
$events = $results->getItems();

if (empty($events)) {
    print "No upcoming events found.\n";
} else {
    print "Upcoming events:\n";
    foreach ($events as $event) {
        $start = $event->start->dateTime;
        $end = $event->end->dateTime;
        printf("%s (%s - %s)\n", $event->getSummary(), $start, $end);
    }
}

wait_input();

echo "Waiting for Payment.\n";
$lock_event = $service->events->insert($PAYPAL_CALENDAR_ID, new Google_Service_Calendar_Event(array(
    'summary' => 'PAYPAL-PAYMENT-ID',
    'description' => 'Badminton Court 1',
    'start' => array('dateTime' => '2018-11-30T18:00:00+01:00', 'timeZone' => $TIMEZONE),
    'end'   => array('dateTime' => '2018-11-30T20:00:00+01:00', 'timeZone' => $TIMEZONE),
)));

wait_input();

echo "Payment Confirmed.\n";
$service->events->delete($PAYPAL_CALENDAR_ID, $lock_event->id);
$booking_event = $service->events->insert($ADMIN_CALENDAR_ID, new Google_Service_Calendar_Event(array(
    'summary' => 'Badminton Court 1',
    'description' => 'Name: John Doe\nEmail: johndoe@gmail.com\nPhone Number: 07123412342',
    'start' => array('dateTime' => '2018-11-30T18:00:00+01:00', 'timeZone' => $TIMEZONE),
    'end'   => array('dateTime' => '2018-11-30T20:00:00+01:00', 'timeZone' => $TIMEZONE),
)));
echo "Booking Created.\n";

wait_input();

// Clean up
$service->events->delete($ADMIN_CALENDAR_ID, $booking_event->id);
echo "Demo Complete.\n";
?>
