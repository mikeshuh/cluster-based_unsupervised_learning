<?php
require_once 'login.php';
require 'ml_algos/k_means.php';

// connect to db
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) die($conn->connect_error);

session_start();

// prevent session fixation
if (!isset($_SESSION['initiated'])) {
  session_regenerate_id();
  $_SESSION['initiated'] = true;
}

// prevent session hijacking
if (
  isset($_SESSION['check'])
  && $_SESSION['check'] != hash('ripemd128', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'])
) different_user();

if (!authenticateUser()) {
  $conn->close();
  header('Location: signup_login.php');
  exit();
}

// check log out
if (isset($_POST['logout'])) {
  // destroy session
  destroy_session_and_data();
  $conn->close();

  // redirect to second page
  header('Location: signup_login.php');
  exit();
}

$username = $_SESSION['username'];

if (isset($_POST['modelName']) && isset($_POST['algo']) && isset($_POST['numClusters']) && isset($_POST['inputType'])) {
  $modelName = sanitizeString($_POST['modelName']);
  $algo = $_POST['algo'];
  $numClusters = $_POST['numClusters'];
  $inputType = $_POST['inputType'];

  if ($inputType == 'file' && $_FILES['trainFile']['error'] === UPLOAD_ERR_NO_FILE) {
    echo '<script>alert("Please select a file to upload.");</script>';
  } elseif ($inputType == 'file' && $_FILES) {
    $file = $_FILES['trainFile']['name'];
    // Sanitize file name
    $file = strtolower(preg_replace('[^A-Za-z0-9.]', '', $file));

    // Ensure file type is txt
    if ($_FILES['trainFile']['type'] == 'text/plain') {
      // Move file from tmp location
      move_uploaded_file($_FILES['trainFile']['tmp_name'], $file);

      // Read all file contents
      $content = file_get_contents($file);

      if (validateDataSet($content)) {
        $data = stringToNumbersArray($content);
        if ($algo == 'kMeans') {
          $centroids = kMeans($data, $numClusters);
          insertDBKCluster($modelName, $username, $centroids);
          echo '<script>alert("Model trained.");</script>';
        }
      } else {
        echo '<script>alert("File contents must only consist of numbers separated by commas.");</script>';
      }
    } else {
      echo '<script>alert("Invalid file. Must be a txt file.");</script>';
    }
  }

  if ($inputType == 'text' && !isset($_POST['text'])) {
    echo '<script>alert("Please enter text for training.");</script>';
  } elseif ($inputType == 'text' && isset($_POST['text'])) {
    $content = $_POST['text'];

    if (validateDataSet($content)) {
      $data = stringToNumbersArray($content);
      if ($algo == 'kMeans') {
        $centroids = kMeans($data, $numClusters);
        insertDBKCluster($modelName, $username, $centroids);
        echo '<script>alert("Model trained.");</script>';
      }
    } else {
      echo '<script>alert("Text input must only consist of numbers separated by commas.");</script>';
    }
  }
}

include('private_html/dashboard.html');

$conn->close();

// authenticate user by checking if session var is set
function authenticateUser()
{
  return isset($_SESSION['username']);
}

// sanatize strings
function sanitizeString($var)
{
  $var = stripslashes($var);
  $var = htmlentities($var);
  $var = strip_tags($var);
  return $var;
}

// destroy session data
function destroy_session_and_data()
{
  session_start();
  $_SESSION = array();
  setcookie(session_name(), '', time() - 2592000, '/');
  session_destroy();
}

// if user tries to hijack session, destroy session data and redirect to second page
function different_user()
{
  global $conn;
  destroy_session_and_data();
  $conn->close();
  echo <<<HTML
    <script>
      alert("Technical error during signup/login");
      window.location.href = "./signup_login.php";
    </script>
HTML;
}

function validateDataSet($content)
{
  $pattern = '/^\d+(,\d+)*$/';
  return preg_match($pattern, $content) ? true : false;
}

function stringToNumbersArray($numbersString)
{
  $numberArray = explode(',', $numbersString);
  $numberArray = array_map('intval', $numberArray);
  return $numberArray;
}

function insertDBKCluster($modelName, $username, $centroids)
{
  global $conn;
  $stmt = $conn->prepare('INSERT INTO k_cluster VALUES (?, ?, ?)');
  $stmt->bind_param('sss', $modelName, $username, $centroids);
  $stmt->execute();
  $stmt->close();
}
