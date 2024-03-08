<?php

// Turn on reporting to save my sanity.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// require('_incs/curldemo.php');
// require('_incs/uploaddemo.php');

require('../config.php');
require('_incs/functions.php');

$response = NULL;

// Upload image.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if ( !empty($_FILES['uploaded_file']) ) {
    $filename           = $_FILES['uploaded_file']['name'];
    $filetype           = $_FILES['uploaded_file']['type'];
    $filepath           = $_FILES['uploaded_file']['tmp_name'];
    $storageAccountname = $STORAGE_NAME;
    $containerName      = $STORAGE_CONTAINER;
    $accesskey          = $STORAGE_KEY;
    $blobName           = $filename;
    $URL = "https://$storageAccountname.blob.core.windows.net/$containerName/$blobName";

    $file_parts = pathinfo(basename($_FILES['uploaded_file']['name']));
    if ($file_parts['extension'] == 'jpg' || $file_parts['extension'] == 'png') {
     // $response = uploadBlob($filepath, $storageAccountname, $containerName, $blobName, $URL, $accesskey, $filetype);
    }
  }
}

$submitted = ($_POST) ? 'Submitted' : '';

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Asset Tracker</title>
    <style>
      body { background-color: #333; color: #eee; font-family: Arial, Helvetica, sans-serif; font-size: 1.5em; }
      div.outer { max-width: 500px; margin: 0 auto; }
      label { min-width: 150px; display: inline-block; }
      input, select { padding: 8px 4px; }
      optgroup { font-size: 1.5em; }
    </style>
  </head>

  <body>
    <div class="outer">
    <h1>Asset Tracker Demo</h1>
    <p>This is a thing. <?php echo $submitted; ?></p>
    <p><strong>All fields are required.</p>
    <form action="" method="POST" onsubmit="mydo()" enctype="multipart/form-data">
      <p>
      <label for="assettype">Asset Type</label>
      <select name="assettype" required>
      <optgroup>
      <!-- TODO: Select from asset_type. -->
      <option disabled selected value> -- select an option -- </option>
      <option value="LAP">Laptop</option>
      <option value="MON">Monitor</option>
      <option value="OTH">Other</option>
      <option value="PER">Peripheral</option>
      </optgroup>
      </select>
      </p>
      <p>
      <label for="sku">SKU</label>
      <input type="text" name="sku" id="sku" placeholder="Enter the 5 digit code" maxlength="5" required>
      </p>
      <p>
      <label for="uploaded_file">SKU image</label>
      <input type="file" name="uploaded_file" id="uploaded_file" required>
      </p>
      <p>
      <label for="person">Person</label>
      <input type="text" name="person" id="person" placeholder="Enter asset holder" maxlength="40" required>
      </p>
      <p>
      <label for="note">Note</label>
      <input type="text" name="note" id="note" placeholder="Enter asset condition; e.g. good, bad" maxlength="32" required>
      </p>
      <p><input type="submit" value="submit"></p>
    </form>
    <p><?php echo $response ?></p>
    </div>
    <script>
      function mydo() {
        // alert("The form was submitted");
      }
    </script>
  </body>
</html>
