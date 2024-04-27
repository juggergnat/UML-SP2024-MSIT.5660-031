<?php

// OCR.
// Requires a URL accessible image. Requires uploading first.
// Save local. If pass, then uploadBlob to Storage.
// Format:
//   curl -H "Ocp-Apim-Subscription-Key: <subscriptionKey>" \
//    -H "Content-Type: application/json" \
//       "https://<endpoint>.cognitiveservices.azure.com/computervision/imageanalysis:analyze?features=caption,read&model-version=latest&language=en&api-version=2024-02-01" \
//    -d "{'url':'<imageurl>'}"
// Help from: https://stackoverflow.com/questions/66794132/cognitive-services-ai-vision-tag-over-php
//
function scanImage($endpoint, $subscriptionKey, $imageurl) {

  $result = FALSE;

  // Example.
  // $uriBase = 'https://eastus.api.cognitive.microsoft.com/';
  // $request_URL = $uriBase . 'vision/v3.0/tag';
  // $params = array('language' => 'en');
  // $request_URL = $request_URL . '?' . http_build_query($params);

  $URL = "https://" . $endpoint . ".cognitiveservices.azure.com/computervision/imageanalysis:analyze?features=caption,read&model-version=latest&language=en&api-version=2024-02-01";

  $headers = [
    // 'Authorization: ' . $authHeader,
    // 'x-ms-blob-cache-control: max-age=3600',
    // 'x-ms-blob-type: BlockBlob',
    // 'x-ms-date: ' . $Date,
    // 'x-ms-version: 2019-12-12',
    // 'Content-Type: ' . $filetype,
    // 'Content-Length: ' . $fileLen
    'Content-Type' => 'application/json',
    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
    'Ocp-Apim-Trace' => true
  ];

  $data = [ 'url' => $imageurl ];

  $body = json_encode($data);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $URL);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Unnecessary
  // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Unnecessary
  // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);  // Unnecessary
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Ocp-Apim-Subscription-Key: ' . $subscriptionKey
  ));

  $response = curl_exec($ch);

  // Check for errors
  if ($response === false) {
    $result = [
      'err' => curl_errno($ch),
      'msg' => curl_error($ch)
    ];
  } else {
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $result = [
      'err' => curl_errno($ch),
      'full' => $response,
      'headers' => substr($response, 0, $headerSize),
      'data' => substr($response, $headerSize),
    ];
  }

  curl_close($ch);

  return $result;
}

// Storage handler.
//
function uploadBlob($filepath, $blobName, $GCS_URL, $filetype, $GC_CLIENT_ID, $GC_SECRET, $GC_TOKEN_URL, $GCS_BUCKET) {

  // Fetch the Access Token first.

  // Construct POST data
  $post_data = http_build_query([
    'client_id' => $GC_CLIENT_ID,
    'client_secret' => $GC_SECRET,
    'grant_type' => 'client_credentials'
  ]);

  $ch = curl_init();
  curl_setopt ( $ch, CURLOPT_URL, $GC_TOKEN_URL );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
  $response = curl_exec($ch);

  // False response is a hard failure. Response can still be no auth. 
  if ($response === false) {
    return [
      'url' => $URL,
      'file' => $blobName,
      'response' => $response,
      'result' => NULL,
      'message' => 'cURL error: ' . curl_error($ch),
    ];
  }

  // Decode JSON response and extract access token or failure.
  $response_data = json_decode($response, true);
  $access_token = isset($response_data['access_token']) ? $response_data['access_token'] : FALSE;

  // Close cURL resource for obtaining access token
  curl_close($ch);

  $upload_url = $GCS_URL . urlencode($blobName);

  if ($access_token) {

    $ch_upload = curl_init();
    curl_setopt($ch_upload, CURLOPT_URL, $upload_url);
    curl_setopt($ch_upload, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_upload, CURLOPT_POST, true);
    curl_setopt($ch_upload, CURLOPT_POSTFIELDS, file_get_contents($filepath));
    curl_setopt($ch_upload, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $access_token,
      'Content-Type: ' . $filetype,
    ]);
    $result = curl_exec($ch_upload);

    // Check for errors
    $message = ($result === false) ? 'cURL error: ' . curl_error($ch_upload) : 'Object uploaded to Google Cloud Storage.';

    curl_close($ch_upload);
  }
  else {
    $message = "Storage authentication failed. Obvious exits are complex but include OA v SA, php exec, cron, server side, etc.";
  }

  return [
    'url' => $upload_url,
    'file' => $blobName,
    'result' => (isset($result) ? $result : NULL),
    'message' => $message,
  ];
}

?>
