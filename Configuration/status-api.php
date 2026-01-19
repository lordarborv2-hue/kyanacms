<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Load the site settings
$settings = json_decode(file_get_contents('settings.json'), true);

/**
 * Checks if a port is open on a given server address.
 * @param string $address The IP address or domain name.
 * @param int $port The port number.
 * @return string "Online" or "Offline".
 */
function checkServerStatus($address, $port) {
    // A timeout of 1 second is plenty.
    $timeout = 1;
    // The @ suppresses warnings if the connection fails, which we expect.
    $socket = @fsockopen($address, $port, $errno, $errstr, $timeout);

    if ($socket) {
        // If the connection was successful, the port is open.
        fclose($socket);
        return "Online";
    } else {
        // If it failed, the port is closed or the server is down.
        return "Offline";
    }
}

// Get the server details from settings
$mid_rate_server = $settings['mid_rate_server'];
$hard_rate_server = $settings['hard_rate_server'];

// Check the status of each server
$response = [
    'mid_rate_status' => checkServerStatus($mid_rate_server['address'], $mid_rate_server['port']),
    'hard_rate_status' => checkServerStatus($hard_rate_server['address'], $hard_rate_server['port'])
];

// Send the response back to the JavaScript
echo json_encode($response);
?>