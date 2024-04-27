<?php

// Test Content Safety.
// Undeveloped because we're doing this in JS validateForm.
//
function moderateNote($note_text) {
  /*
    # Analyze text against built in and custom blocklist.
    curl -v POST \
      "${EP}/contentsafety/text:analyze?api-version=2023-10-01" \
      -H "Ocp-Apim-Subscription-Key: ${KY}" \
      -H "Content-Type: application/json" \
      --data-ascii '{
        "text": "this laptop slaps",
        "categories": [ "Hate", "Violence" ],
        "blocklistNames": [ "${BL}" ],
        "haltOnBlocklistHit": false,
        "outputType": "FourSeverityLevels"
     }'
  */
  return true;
}

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

// Local save of image.
//
function uploadImage($ip) {
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ( !empty($_FILES['uploaded_file']) ) {
      // $file_parts = pathinfo(basename($_FILES['uploaded_file']['name']));
      // $type = $_FILES['uploaded_file']['type'];
      // $path = 'uploads/';
      // $name = 'raw-' . time();
      // $path = $path . $name . "." . $file_parts['extension'] ;
      // if (!$_FILES['uploaded_file']['error']) {
      //   if ( move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $path) ) {
      //      // echo "The file ". basename($path) ." has been uploaded";
      //      return [
      //        'url' => 'http://' . $ip . '/' . $path,
      //        'file' => basename($path),
      //      ];
      //   }
      // }
    }
  }
  return false;
}

// Storage handler.
//
function uploadBlob($filepath, $blobName, $GCS_URL, $filetype, $GC_CLIENT_ID, $GC_SECRET, $GC_TOKEN_URL, $GCS_BUCKET) {

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
  if ($response === false) {
    return [
      'url' => $URL,
      'file' => $blobName,
      'response' => $response,
      'result' => NULL,
      'message' => 'cURL error: ' . curl_error($ch),
    ];
  }  
  // Decode JSON response and extract access token
  $response_data = json_decode($response, true);
  $access_token = $response_data['access_token'];
  // Close cURL resource for obtaining access token
  curl_close($ch);

  $upload_url = $GCS_URL . urlencode($blobName);

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

  return [
    'url' => $upload_url,
    'file' => $blobName,
    'result' => $result,
    'message' => $message,
  ];
}

?>
