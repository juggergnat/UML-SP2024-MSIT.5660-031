<?php

// CURL demo.

$ch = curl_init();
$optArray = array(
   CURLOPT_URL => 'https://ifconfig.me',
   CURLOPT_RETURNTRANSFER => true
);
curl_setopt_array($ch, $optArray);
$response  = curl_exec($ch);
curl_close($ch);

echo $response;

?>
