<?php

// Turn on reporting to save my sanity.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// require('_incs/curldemo.php');
// require('_incs/uploaddemo.php');

require('../config.php');
require('_incs/functions.php');

$resp_st  = '';

// Handle form submission.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  // Upload image to Azure Storage.
  //
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
      // $resp_st = uploadBlob($filepath, $storageAccountname, $containerName, $blobName, $URL, $accesskey, $filetype);
    }
  }

  // Write to Database.
  //
  if ($database_host && $database_name && $database_user && $database_pass) {
    $sql = "INSERT INTO asset_item (sku, akey, person, note, image) VALUES (?,?,?,?,?)";
    // Connect and process (or not).
    try {
      $pdo = new PDO('mysql:host=' . $database_host . '; dbname=' . $database_name, $database_user, $database_pass);
      $stmt= $pdo->prepare($sql);
      $stmt->execute([
        $_POST['sku'],
        $_POST['assettype'],
        $_POST['person'],
        $_POST['note'],
        (isset($URL) ? $URL : '')
      ]);
    } catch (PDOException $e) {
        $insert["error"] = true;
        $insert["msg"]   = $e->getMessage();
    }
  }
}

$submitted = ($_POST) ? 'Submitted' : '';

// Prepare to display data from database.
//
if ($database_host && $database_name && $database_user && $database_pass) {
  // Connect and process (or not).
  try {
    $pdo = new PDO('mysql:host=' . $database_host . '; dbname=' . $database_name, $database_user, $database_pass);
    $resp = $pdo->query('SELECT * FROM asset_item ORDER BY aid DESC');
    while ($row = $resp->fetch(PDO::FETCH_ASSOC)) {
       $output['data'][] = $row;
    }
    $resp->closeCursor();
  } catch (PDOException $e) {
      $output["error"] = true;
      $output["msg"]   = $e->getMessage();
  }
}

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
      small { display: block; clear: all; margin-left: 150px; font-size: 0.6em; padding: 8px 4px; }
      .submitted { border: 4px solid green; padding:1em; }
      .insert { border: 4px solid red; padding:1em; }
    </style>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
  </head>

  <body>
    <div class="outer">
    <h1>Asset Tracker Demo</h1>
    <p>This is a thing.</p>
    <?php if ($submitted) { ?><p class="submitted">Submitted, see below for new entry.</p><?php } ?>
    <?php if (isset($insert)) { ?><p class="insert"><?php echo $insert['msg']; ?></p><?php } ?>
    <p><strong>All fields are required.</p>
    <form id="newasset" action="" method="POST" onsubmit="event.preventDefault(); validateMyForm();" enctype="multipart/form-data">
      <p>
      <label for="assettype">Asset Type</label>
      <!-- TODO: Select from asset_type. -->
      <select name="assettype" required >
      <optgroup>
      <option disabled selected value> -- select an option -- </option>
      <option value="LAP">Laptop</option>
      <option value="MON">Monitor</option>
      <option value="PER">Peripheral</option>
      <option value="OTH">Other</option>
      </optgroup>
      </select>
      </p>
      <p>
      <label for="sku">SKU</label>
      <input type="text" name="sku" id="sku" placeholder="Enter the 6 digit code" maxlength="6" required>
      </p>
      <p>
      <label for="uploaded_file">SKU image</label>
      <input type="file" name="uploaded_file" id="uploaded_file" required accept="image/jpeg, image/png">
      </p>
      <p>
      <label for="person">Person</label>
      <input type="text" name="person" id="person" placeholder="Enter asset holder" maxlength="40"  size="32" required>
      </p>
      <p>
      <label for="note">Note</label>
      <input type="text" name="note" id="note" placeholder="Enter asset condition; e.g. good, bad" maxlength="32" size="32" required>
      <small>e.g. "this laptop slaps" or "looks beaten up"</small>
      </p>
      <p><input type="submit" value="submit"></p>
    </form>
    <p><?php echo $resp_st ?></p>
    <div>
      <?php if ($output) { ?>
        <hr />
        <h2>Current Assets</h2>
        <pre><?php
          if (isset($output['data']))     print_r($output['data']);
          elseif (isset($output['msg']))  print_r($output['msg']);
        ?></pre>
      <?php } ?>
    </div>
    </div>
    <script type="text/javascript">
      function validateMyForm() {

        // alert("The form was not yet submitted");
        // document.getElementById("newasset").submit();

        $(function() {

          $submitThis=true;

          var params = {
            // Request parameters
          };

          $body = { "text": document.getElementById("note").value,
            "categories": [ "Violence", "Hate" ],
            "blocklistNames": [ "bl-pak" ],
            "haltOnBlocklistHit": false,
            "outputType": "FourSeverityLevels" };

          $.ajax({
            url: "https://<?php echo $CS_END; ?>.cognitiveservices.azure.com/contentsafety/text:analyze?api-version=2023-10-01"  + $.param(params),
            beforeSend: function(xhrObj){
              // Request headers
              xhrObj.setRequestHeader("Content-Type","application/json");
              xhrObj.setRequestHeader("Ocp-Apim-Subscription-Key","<?php echo $CS_KEY ?>");
            },
            type: "POST",
            // Request body
            data: JSON.stringify($body),
          })
          .done(function(data) {
            if ( data.blocklistsMatch.length ) {
              $submitThis=false;
              alert("You cannot use the word/phrase: " + data.blocklistsMatch[0].blocklistItemText);
            }
            if ( data.categoriesAnalysis.length ) {
              for ( i=0; i<data.categoriesAnalysis.length; i++ ){
                if ( data.categoriesAnalysis[i].severity ) {
                  $submitThis=false;
                  alert("This submission's note field rates too high in the category: " + data.categoriesAnalysis[i].category);
                }
              }
            }
            if ( $submitThis ) {
              alert("Looks okay far. Submitting.");
              document.getElementById("newasset").submit();
            }
          })
          .fail(function() {
            alert("Error: Please contact the author. This used to work. I probably have to restart a service.");
          });
        });
      }
    </script>
  </body>
</html>
