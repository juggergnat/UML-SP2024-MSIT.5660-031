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
      $file_parts = pathinfo(basename($_FILES['uploaded_file']['name']));
      $type = $_FILES['uploaded_file']['type'];
      $path = 'uploads/';
      $name = 'raw-' . time();
      $path = $path . $name . "." . $file_parts['extension'] ;
      if (!$_FILES['uploaded_file']['error']) {
        if ( move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $path) ) {
           // echo "The file ". basename($path) ." has been uploaded";
           return [
             'url' => 'http://' . $ip . '/' . $path,
             'file' => basename($path),
           ];
        }
      }
    }
  }
  return false;
}

// Storage handler.
//
function uploadBlob($filepath, $storageAccountname, $containerName, $blobName, $URL, $accesskey, $filetype) {

  $Date = gmdate('D, d M Y H:i:s \G\M\T');
  $handle = fopen($filepath, "r");
  $fileLen = filesize($filepath);

  $headerResource = "x-ms-blob-cache-control:max-age=3600\nx-ms-blob-type:BlockBlob\nx-ms-date:$Date\nx-ms-version:2019-12-12";
  $urlResource = "/$storageAccountname/$containerName/$blobName";

  $arraysign = array();
  $arraysign[] = 'PUT';            /*HTTP Verb*/
  $arraysign[] = '';               /*Content-Encoding*/
  $arraysign[] = '';               /*Content-Language*/
  $arraysign[] = $fileLen;         /*Content-Length (include value when zero)*/
  $arraysign[] = '';               /*Content-MD5*/
  $arraysign[] = $filetype;        /*Content-Type*/
  $arraysign[] = '';               /*Date*/
  $arraysign[] = '';               /*If-Modified-Since */
  $arraysign[] = '';               /*If-Match*/
  $arraysign[] = '';               /*If-None-Match*/
  $arraysign[] = '';               /*If-Unmodified-Since*/
  $arraysign[] = '';               /*Range*/
  $arraysign[] = $headerResource;  /*CanonicalizedHeaders*/
  $arraysign[] = $urlResource;     /*CanonicalizedResource*/

  $str2sign = implode("\n", $arraysign);

  $sig = base64_encode(hash_hmac('sha256', urldecode(utf8_encode($str2sign)), base64_decode($accesskey), true));
  $authHeader = "SharedKey $storageAccountname:$sig";

  $headers = [
    'Authorization: ' . $authHeader,
    'x-ms-blob-cache-control: max-age=3600',
    'x-ms-blob-type: BlockBlob',
    'x-ms-date: ' . $Date,
    'x-ms-version: 2019-12-12',
    'Content-Type: ' . $filetype,
    'Content-Length: ' . $fileLen
  ];

  $ch = curl_init();
  curl_setopt ( $ch, CURLOPT_URL, $URL );
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
  curl_setopt($ch, CURLOPT_INFILE, $handle); 
  curl_setopt($ch, CURLOPT_INFILESIZE, $fileLen); 
  curl_setopt($ch, CURLOPT_UPLOAD, true); 
  $result = curl_exec($ch);

  curl_close($ch);

  return 'Uploaded successfully: ' . print_r($result, true);
}

?>
