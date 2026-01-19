<?php
header('Content-Type: text/html');

echo "<h1>PHP SQL Driver Status</h1>";

// 1. Check if extension is loaded
if (extension_loaded("sqlsrv")) {
    echo "<h2 style='color:green'>✅ Success! The 'sqlsrv' driver is LOADED.</h2>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
} else {
    echo "<h2 style='color:red'>❌ Error: The 'sqlsrv' driver is NOT loaded.</h2>";
    echo "<p><strong>Possible causes:</strong></p>";
    echo "<ul>
            <li>You didn't restart Apache after editing php.ini.</li>
            <li>You are missing the <strong>ODBC Driver 17</strong> (See Step 1 above).</li>
            <li>You edited the wrong php.ini file.</li>
          </ul>";
    
    // Show where PHP is looking for php.ini
    echo "<p><strong>Loaded Configuration File:</strong> " . php_ini_loaded_file() . "</p>";
}

// 2. Show all errors to help debug
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<hr><h3>Detailed Info:</h3>";
phpinfo();
?>