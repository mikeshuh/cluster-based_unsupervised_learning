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

  if (searchUserModel($username, $modelName)) {
    echo '<script>alert("Model name already taken.");</script>';
  } elseif ($inputType == 'file' && $_FILES['trainFile']['error'] === UPLOAD_ERR_NO_FILE) {
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
          insertDBKCluster($modelName, $username, $algo, $centroids);
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
        insertDBKCluster($modelName, $username, $algo, $centroids);
        echo '<script>alert("Model trained.");</script>';
      }
    } else {
      echo '<script>alert("Text input must only consist of numbers separated by commas.");</script>';
    }
  }
}

if (isset($_POST['selectedModel']) && $_FILES['testFile']['error'] === UPLOAD_ERR_NO_FILE) {
  echo '<script>alert("Please select a file to upload.");</script>';
} elseif (isset($_POST['selectedModel']) && $_FILES) {
  $modelName = $_POST['selectedModel'];
  $file = $_FILES['testFile']['name'];
  // Sanitize file name
  $file = strtolower(preg_replace('[^A-Za-z0-9.]', '', $file));

  // Ensure file type is txt
  if ($_FILES['testFile']['type'] == 'text/plain') {
    // Move file from tmp location
    move_uploaded_file($_FILES['testFile']['tmp_name'], $file);

    // Read all file contents
    $content = file_get_contents($file);

    if (validateDataSet($content)) {
      $data = stringToNumbersArray($content);
      $algo = getUserModelType($username, $modelName);
      if ($algo == 'kMeans') {
        $centroids = getKClusterCentroids($username, $modelName);
        $centroids = stringToNumbersArray($centroids);
        $assignments = assignPoints($data, $centroids);
        $clusters = groupPointsByCluster($data, $assignments, sizeof($centroids));

        echo '<h1>Cluster Assignments:</h1>';
        foreach ($clusters as $index => $cluster) {
          echo '<b>Cluster ' . ($index + 1) . ': </b>';
          if (!empty($cluster)) {
            echo implode(', ', $cluster);
          } else {
            echo 'No data points';
          }
          echo '<br>';
        }
      }
    } else {
      echo '<script>alert("File contents must only consist of numbers separated by commas.");</script>';
    }
  } else {
    echo '<script>alert("Invalid file. Must be a txt file.");</script>';
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

function insertDBKCluster($modelName, $username, $modelType, $centroids)
{
  global $conn;
  $stmt = $conn->prepare('INSERT INTO user_models VALUES (?, ?, ?)');
  $stmt->bind_param('sss', $modelName, $username, $modelType);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare('INSERT INTO k_means VALUES (?, ?, ?)');
  $stmt->bind_param('sss', $modelName, $username, $centroids);
  $stmt->execute();
  $stmt->close();
}

function searchUserModel($username, $modelName)
{
  global $conn;
  $stmt = $conn->prepare('SELECT * FROM user_models WHERE username=? AND model_name=?');
  $stmt->bind_param('ss', $username, $modelName);
  $stmt->execute();
  $result = $stmt->get_result();
  $exists = $result->num_rows > 0;
  $stmt->close();
  return $exists;
}

function getUserModelType($username, $modelName)
{
  global $conn;
  $stmt = $conn->prepare('SELECT model_type FROM user_models WHERE username=? AND model_name=?');
  $stmt->bind_param('ss', $username, $modelName);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();
  return $row['model_type'];
}


function getKClusterCentroids($username, $modelName)
{
  global $conn;
  $stmt = $conn->prepare('SELECT centroids FROM k_means WHERE username=? AND model_name=?');
  $stmt->bind_param('ss', $username, $modelName);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();
  return $row['centroids'];
}

function groupPointsByCluster($data, $assignments, $numClusters)
{
  $clusters = [];
  for ($i = 0; $i < $numClusters; $i++) {
    $clusters[$i] = [];
  }
  foreach ($assignments as $index => $clusterIndex) {
    $clusters[$clusterIndex][] = $data[$index];
  }
  return $clusters;
}
