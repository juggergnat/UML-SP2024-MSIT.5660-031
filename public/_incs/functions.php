<?php

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
