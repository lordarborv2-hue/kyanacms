<?php
// ULTIMATE DEBUG WEBHOOK
$log_file = __DIR__ . '/webhook_log.txt';
file_put_contents($log_file, "\n\n[" . date('Y-m-d H:i:s') . "] ==== NEW WEBHOOK HIT ====\n", FILE_APPEND);
file_put_contents($log_file, "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);

$payload = file_get_contents('php://input');
file_put_contents($log_file, "Raw Payload: " . ($payload ?: "EMPTY (Probably a browser visit or blank ping)") . "\n", FILE_APPEND);

$event = json_decode($payload, true);

if (!$event || !isset($event['data']['attributes']['type'])) {
    file_put_contents($log_file, "Result: Rejected (Invalid or missing event type).\n", FILE_APPEND);
    http_response_code(400);
    exit('Invalid payload');
}

$event_type = $event['data']['attributes']['type'];
file_put_contents($log_file, "Event Type: " . $event_type . "\n", FILE_APPEND);

if ($event_type === 'link.payment.paid') {
    
    $link_data = $event['data']['attributes']['data']['attributes'] ?? [];
    $remarks = $link_data['remarks'] ?? 'NO_REMARKS'; 
    $status = $link_data['status'] ?? 'NO_STATUS';

    file_put_contents($log_file, "Status: $status | Remarks: $remarks\n", FILE_APPEND);

    if ($status === 'paid' && $remarks !== 'NO_REMARKS') {
        
        // Extract Data
        if (preg_match('/ACCOUNT:(.+)\|CREDITS:(\d+)\|SERVER:(.+)/', $remarks, $matches)) {
            $account_id = $matches[1];
            $credits_to_add = (int)$matches[2];
            $server_key = $matches[3];

            file_put_contents($log_file, "Regex Matched! Acc: $account_id, Credits: $credits_to_add, Server: $server_key\n", FILE_APPEND);

            require_once __DIR__ . '/../config.php';
            $settings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
            $db_config = $settings['database'][$server_key] ?? null;

            if ($db_config) {
                function decrypt_pass($garbled, $key) {
                    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
                    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
                }

                $conn = sqlsrv_connect($db_config['host'], [
                    "Database" => $db_config['name'], "Uid" => $db_config['user'],
                    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
                    "TrustServerCertificate" => 1, "Encrypt" => 0
                ]);

                if ($conn) {
                    file_put_contents($log_file, "DB Connected successfully.\n", FILE_APPEND);
                    $check = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$account_id]);
                    $row = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
                    
                    if ($row) {
                        sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?", [$credits_to_add, $account_id]);
                        file_put_contents($log_file, "Update query executed. Credits Added!\n", FILE_APPEND);
                    } else {
                        sqlsrv_query($conn, "INSERT INTO WebCredits (memb___id, credits) VALUES (?, ?)", [$account_id, $credits_to_add]);
                        file_put_contents($log_file, "Insert query executed. Credits Added!\n", FILE_APPEND);
                    }
                    sqlsrv_close($conn);
                } else {
                    file_put_contents($log_file, "DB Connection Failed: " . print_r(sqlsrv_errors(), true) . "\n", FILE_APPEND);
                }
            } else {
                file_put_contents($log_file, "No DB Config found for server key: $server_key\n", FILE_APPEND);
            }
        } else {
            file_put_contents($log_file, "Regex did not match remarks. Check how remarks are formatted.\n", FILE_APPEND);
        }
    }
}

// Always reply 200 OK
http_response_code(200);
echo "Webhook Received.";
?>