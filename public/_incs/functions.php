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
function uploadBlob($filepath, $blobName, $URL, $filetype, $CRED_PATH, $PROJ_ID) {

  $file_data = file_get_contents($filepath);
  $accessToken = getAccessToken($CRED_PATH, $PROJ_ID);

  $ch = curl_init();
  curl_setopt ( $ch, CURLOPT_URL, $URL );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: ' . $filetype
  ]);

  $result = curl_exec($ch);

  // Check for errors
  $message = ($result === false) ? 'cURL error: ' . curl_error($ch) : 'Object uploaded to Google Cloud Storage.';

  curl_close($ch);

  return [
    'url' => $URL,
    'file' => $blobName,
    'result' => $result,
    'message' => $message,
  ];
}

// Function to get access token using Google Cloud service account credentials
function getAccessToken($CRED_PATH, $PROJ_ID) {

    // global $credentials_file_path, $project_id;
    $credentials_file_path = $CRED_PATH;
    $project_id = $PROJ_ID;

    $credentials = json_decode(file_get_contents($credentials_file_path), true);

    $token_url = 'https://oauth2.googleapis.com/token';
    $data = [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => getTokenAssertion($credentials)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);

    if ($response === false) {
        echo 'cURL error: ' . curl_error($ch);
    } else {
        $response_data = json_decode($response, true);
        return $response_data['access_token'];
    }

    curl_close($ch);
}

// Function to generate JWT token assertion
function getTokenAssertion($credentials) {
    $now = time();
    $expires = $now + 3600; // Token expires in 1 hour

    $jwt_header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];

    $jwt_payload = [
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $expires,
        'iat' => $now
    ];

    $jwt_header_encoded = base64_encode(json_encode($jwt_header));
    $jwt_payload_encoded = base64_encode(json_encode($jwt_payload));

    $signature_input = $jwt_header_encoded . '.' . $jwt_payload_encoded;
    openssl_sign($signature_input, $jwt_signature, $credentials['private_key'], 'SHA256');
    $jwt_signature_encoded = base64_encode($jwt_signature);

    return $jwt_header_encoded . '.' . $jwt_payload_encoded . '.' . $jwt_signature_encoded;
}

?>
