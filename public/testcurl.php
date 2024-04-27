<?php

// Initialize cURL session
$ch = curl_init();

// Set cURL options - for example, making a request to example.com
curl_setopt($ch, CURLOPT_URL, "https://ifconfig.me");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute cURL session
$response = curl_exec($ch);

// Check for errors
if ($response === false) {
    echo "cURL Error: " . curl_error($ch);
} else {
    // Output the response
    echo $response;
}

// Close cURL session
curl_close($ch);

?>
