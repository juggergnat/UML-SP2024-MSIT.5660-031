<?php

// Upload handler.
//
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if ( !empty($_FILES['uploaded_file']) ) {
    $file_parts = pathinfo(basename($_FILES['uploaded_file']['name']));
    $type = $_FILES['uploaded_file']['type'];
    $path = 'uploads/';
    $name = 'raw-' . time();
    $path = $path . $name . "." . $file_parts['extension'] ;
    if (!$_FILES['uploaded_file']['error']) {
      if ( move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $path) ) {
         echo "The file ". basename($path) ." has been uploaded";
      } else {
        echo "There was an error uploading the file, please try again!";
      }
    }
  }
}

?>
