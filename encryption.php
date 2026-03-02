<?php
// encryption.php

function encrypt_data($raw, $key) {
    if (empty($raw)) return ''; //
    $ivlen = openssl_cipher_iv_length(ENCRYPTION_CIPHER); //
    $iv = openssl_random_pseudo_bytes($ivlen); //
    $encrypted_data = openssl_encrypt($raw, ENCRYPTION_CIPHER, $key, 0, $iv); //
    return base64_encode($encrypted_data . '::' . $iv); //
}

function decrypt_data($garbled, $key) {
    if (empty($garbled)) return ''; //
    
    // Decode the base64 string FIRST to check for the separator
    $decoded = base64_decode($garbled); //
    
    // Check if it contains the special '::' separator used in your encryption
    if ($decoded === false || strpos($decoded, '::') === false) {
        return $garbled; // Return as-is if it is plain text
    }
    
    list($encrypted_data, $iv) = explode('::', $decoded, 2); //
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv); //
}

// Alias to keep older CMS files working without breaking
function decrypt_pass($garbled, $key) {
    return decrypt_data($garbled, $key); //
}
?>