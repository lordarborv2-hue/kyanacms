<?php
// config.php

// --- IMPORTANT SECURITY ---
// Using the specific key provided for your server environment
define('ENCRYPTION_KEY', 'h;s6wl+D]56e])96=efSe1rYBngciBjw'); //

// --- ADMIN CONFIGURATION ---
define('ADMIN_PASSWORD', 'This1sDef4ult!1234'); //

// --- DO NOT EDIT BELOW ---
define('ENCRYPTION_CIPHER', 'aes-256-cbc'); //

// Automatically load the global encryption functions for all scripts
require_once __DIR__ . '/encryption.php'; //
?>