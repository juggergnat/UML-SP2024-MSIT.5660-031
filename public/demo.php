<?php

// Turn on reporting to save my sanity.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// require('_incs/curldemo.php');
// require('_incs/uploaddemo.php');

require('../../config.php');
require('_incs/functions.php');

$DEVINTERRUPT = FALSE;

// Handle form submission.
if (!$DEVINTERRUPT && $_SERVER["REQUEST_METHOD"] == "POST") {

  $PROCEED = FALSE;

  // First, prepare the image for OCR scan.
  //
  if ( !empty($_FILES['uploaded_file']) ) {

    // These come from the uploaded file.
    $filename   = $_FILES['uploaded_file']['name'];
    $filetype   = $_FILES['uploaded_file']['type'];     // e.g. 'image/jpeg'; aka $content_type
    $filepath   = $_FILES['uploaded_file']['tmp_name']; // e.g. '/path/to/your/file.jpg';
    $blobName   = date('YmdGis') . '__' . $filename;

    $file_parts = pathinfo(basename($_FILES['uploaded_file']['name']));

    if ($file_parts['extension'] == 'jpg' || $file_parts['extension'] == 'png') {

      $rawblob = uploadBlob($filepath, $blobName, $GCS_URL, $filetype, $GC_CLIENT_ID, $GC_SECRET, $GC_TOKEN_URL, $GCS_BUCKET);
      if (isset($rawblob['message'])) {
        echo $rawblob['message'];
      }
      // $PROCEED = TRUE;
    }
    else {
      $failure = "Sorry, only jpg and png.";
    }
  }

  // Run OCR on image.
  //
  if ($PROCEED) {
  if ( ! $rawblob['url'] ) {
    $failure = "Could not run OCR. Call the author.";
  }
  else {
    $scanResult = scanImage($CV_END, $CV_KEY, $rawblob['url']);
    if ( ! $scanResult ) {
      $failure = "Could not complete OCR. Call the author.";
    }
    else {
      if ( isset($scanResult['err']) && ! $scanResult['err'] && isset($scanResult['data'])) {

        // Decode and re-encode JSON data with pretty-printing.
        // $decodedData = json_decode($scanResult['data'], true);
        // $prettyPrintedJson = json_encode($decodedData, JSON_PRETTY_PRINT);
        // $ocr_result = '<pre>' . print_r($prettyPrintedJson, true) . '</pre>';

        $ocrlines = [];
        $ocrdata  = json_decode($scanResult['data'], true);
        if (isset($ocrdata['readResult']) && is_array($ocrdata['readResult'])) {
          foreach ($ocrdata['readResult'] as $readResult) {
            // if (isset($readResult['blocks']) && is_array($readResult['blocks'])) {
            if (is_array($readResult)) { 
              foreach ($readResult as $block) {
                if (isset($block['lines']) && is_array($block['lines'])) {
                  foreach ($block['lines'] as $line) {
                    $ocrlines[] = $line['text'];
                  }        
                }
              }
            }
          }
        }

        $ocrmatch = FALSE;
        foreach ($ocrlines as $l) {
          if ($_POST['sku'] == preg_replace("/[^0-9]/", "", $l) ) {
            $ocrmatch = 'The sku you entered matches what we found in the image. Neat!';
            $PROCEED = TRUE;
          }
        }
        if ( ! $ocrmatch ) {
          $failure = "The OCR did not match the SKU entered. Try again?";
        }

      }
      else {
        $failure = "Could not intepret OCR. Call the author.";
      }
    }
  }
  }

  // Upload image to Azure Storage.
  //
  /*
  if ( $PROCEED && !empty($_FILES['uploaded_file']) && isset($rawblob['file'])) {
    $filename           = $_FILES['uploaded_file']['name'];
    $filetype           = $_FILES['uploaded_file']['type'];
    $filepath           = $_FILES['uploaded_file']['tmp_name'];
    $storageAccountname = $STORAGE_NAME;
    $containerName      = $STORAGE_CONTAINER;
    $accesskey          = $STORAGE_KEY;
    $blobName           = preg_replace("/[^0-9]/", "", $_POST['sku']) . '__' . $rawblob['file'];
    $URL = "https://$storageAccountname.blob.core.windows.net/$containerName/$blobName";

    $file_parts = pathinfo(basename($_FILES['uploaded_file']['name']));
    if ($file_parts['extension'] == 'jpg' || $file_parts['extension'] == 'png') {
      $cookedblob = uploadBlob($filepath, $storageAccountname, $containerName, $blobName, $URL, $accesskey, $filetype);
    }
    else {
      $PROCEED = FALSE;
      $failure = "Image issue. Needs to be jpg or png.";
    }
  }
  else {
    $PROCEED = FALSE;
    $failure = "Image issue. Call the author.";
  }
  */

  // Write to Database.
  //
  if ( $PROCEED && $database_host && $database_name && $database_user && $database_pass && isset($cookedblob['url']) ) {
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
        $cookedblob['url'],
      ]);
    } catch (PDOException $e) {
        $PROCEED = FALSE;
        $insert["error"] = true;
        $insert["msg"]   = $e->getMessage();
    }
  }
  else {
    $PROCEED = FALSE;
    $insert["error"] = true;
    $insert["msg"]   = "Didn't attempt to write to the database.";
  }
}

$submitted = ($_POST && $PROCEED) ? 'Submitted' : '';

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
      body { background-color: #333; color: #eee; font-family: Arial, Helvetica, sans-serif; font-size: 1.4em; }
      div.outer { max-width: 500px; margin: 0 auto; }
      label { min-width: 150px; display: inline-block; }
      input, select { padding: 8px 4px; }
      optgroup { font-size: 1.5em; }
      small { display: block; clear: all; margin-left: 150px; font-size: 0.6em; padding: 8px 4px; }
      .submitted, .ocrmatch { border: 4px solid green; padding:1em; }
      .ocrlines { border: 4px solid black; padding:1em; }
      .insert, .failure { border: 4px solid red; padding:1em; }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
  </head>

  <body>
    <div class="outer">
    <h1>Asset Tracker Demo</h1>
    <p>This is a thing on <?php echo gethostname(); ?>.</p>
    <?php if (isset($ocrlines) && $ocrlines) { ?><p class="ocrlines">Lines: <?php echo implode(', ', $ocrlines); ?></p><?php } ?>
    <?php if (isset($ocrmatch) && $ocrmatch) { ?><p class="ocrmatch"><?php echo $ocrmatch; ?></p><?php } ?>
    <?php if (isset($failure)) { ?><p class="failure"><?php echo $failure; ?></p><?php } ?>
    <?php if (isset($insert)) { ?><p class="insert"><?php echo $insert['msg']; ?></p><?php } ?>
    <?php if ($submitted) { ?><p class="submitted">Submitted, see below for new entry.</p><?php } ?>

    <p><strong>All fields are required.</p>
    <form id="newasset" action="" method="POST" enctype="multipart/form-data">
    <!--
    <form id="newasset" action="" method="POST" onsubmit="event.preventDefault(); validateMyForm();" enctype="multipart/form-data">
    -->
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
      <input type="text" name="sku" id="sku" placeholder="Enter the 5 or 6 digit code" maxlength="6" required>
      </p>
      <p>
      <label for="uploaded_file">SKU image</label>
      <input type="file" name="uploaded_file" id="uploaded_file" required accept="image/jpeg, image/png">
      <small>Restricted to jpg and png files only.</small>
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
    <div>
      <?php if ($output) { ?>
        <hr />
        <h2>Current Assets</h2>
        <pre><?php
          if (isset($output['msg']))  print_r($output['msg']);
          // if (isset($output['data']))  print_r($output['data']);
        ?></pre>
        <?php if (isset($output['data']) && is_array($output['data'])) { ?>
          <?php foreach ($output['data'] as $it) { ?>
            <?php 
              if (!isset($it['image'])) {
                $it['image'] = "https://storage.googleapis.com/pak2d-sb1/symphony-slang-it-200.jpg";
                $it['image_note'] = "(GCP placeholder image) <br/>";
              }
              else {
                $it['image_note'] = '';
              }
            ?>
            <p><img src="<?php echo $it['image']; ?>" title="<?php echo $it['image']; ?>" width="300" /><br>
            id: <?php echo $it['aid']; ?><br/> 
            sku: <?php echo $it['sku']; ?> <br/>
            type: <?php echo $it['akey']; ?> <br/>
            person: <?php echo $it['person']; ?><br/>
            note: <?php echo $it['note']; ?> <br />
            image url: <?php echo $it['image_note']; ?>  <?php echo $it['image']; ?></p>
          <?php } ?>
        <?php } ?>
      <?php } ?>
    </div>
    </div>
    <script type="text/javascript">
      function validateMyForm() {

        // alert("The form was not yet submitted");
        // document.getElementById("newasset").submit();

        $(function() {

          alert('validateMyForm disabled');
          return;

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
              alert("Looks okay so far. Submitting.");
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
