<?php
require_once 'login.php';
require 'ml_algos/k_means.php';
require 'ml_algos/e_max.php';

// connect to db
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) die($conn->connect_error);

// start session
session_start();

// prevent session fixation
if (!isset($_SESSION['initiated'])) { // if session var is not set, regen session id
  session_regenerate_id();
  $_SESSION['initiated'] = true;
}

// prevent session hijacking
if (
  isset($_SESSION['check'])
  && $_SESSION['check'] != hash('ripemd128', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'])
) different_user();

// if user is not authenticated, redirect to sign up/log in
if (!authenticateUser()) {
  $conn->close();
  header('Location: signup_login.php');
  exit();
}

// check log out
if (isset($_POST['logout'])) {
  // destroy session
  destroy_session_and_data();

  // close db conn
  $conn->close();

  // redirect to sign up/log in
  header('Location: signup_login.php');
  exit();
}

// set username var
$username = $_SESSION['username'];

// check train model form
if (isset($_POST['modelName']) && isset($_POST['algo']) && isset($_POST['numClusters']) && isset($_POST['inputType'])) {
  // sanitize model name
  $modelName = sanitizeString($_POST['modelName']);
  $algo = $_POST['algo'];
  $numClusters = $_POST['numClusters'];
  $inputType = $_POST['inputType'];

  // check if model name is already taken for that user
  if (searchUserModel($username, $modelName)) { // already in use
    echo '<script>alert("Model name already in use.");</script>';
  } elseif ($inputType == 'file') { // if input type is file
    if ($_FILES['trainFile']['error'] === UPLOAD_ERR_NO_FILE) { // check if no file uploaded
      echo '<script>alert("Please select a file to upload.");</script>';
    } elseif ($_FILES) { // file uploaded
      $file = $_FILES['trainFile']['name'];

      // sanitize file name
      $file = strtolower(preg_replace('[^A-Za-z0-9.]', '', $file));

      // ensure file type is txt
      if ($_FILES['trainFile']['type'] == 'text/plain') { // valid txt
        // move file from tmp location
        move_uploaded_file($_FILES['trainFile']['tmp_name'], $file);

        // read all file contents
        $content = file_get_contents($file);

        // validate file content
        if (validateDataSet($content)) { // valid file content
          // convert data string to num array
          $data = stringToNumbersArray($content);

          // which clustering algo to use
          if ($algo == 'kMeans') {
            $centroids = kMeans($data, $numClusters); // get centriods of data set
            $centroids = implode(',', $centroids); // convert num array to string

            // insert trained model to db
            insertDBKCluster($modelName, $username, $algo, $centroids);
            echo '<script>alert("Model trained.");</script>';
          }
          if ($algo == 'eMax') {
            $clusters = emAlgorithm($data, $numClusters); // get cluster properties

            if ($clusters == 'Invalid input parameters') { // numCluster > score count
              echo '<script>alert("Number of clusters cannot be greater than number of scores.");</script>';
            } else { // valid input
              // convert num arrays to strings
              $means = implode(',', $clusters['means']);
              $variances = implode(',', $clusters['variances']);
              $mixingCoefficients = implode(',', $clusters['mixingCoefficients']);

              // insert trained model to db
              insertDBEM($modelName, $username, $algo, $means, $variances, $mixingCoefficients);
              echo '<script>alert("Model trained.");</script>';
            }
          }
        } else echo '<script>alert("File contents must only consist of numbers separated by commas.");</script>'; // invalid file content
      } else echo '<script>alert("Invalid file. Must be a txt file.");</script>'; // invalid file. not txt
    }
  } elseif ($inputType == 'text') { // if input type is text
    if (!isset($_POST['text'])) { // check if no text is set
      echo '<script>alert("Please enter text for training.");</script>';
    } elseif (isset($_POST['text'])) { // text is provided
      $content = $_POST['text'];

      // validate text data set
      if (validateDataSet($content)) { // valid text content
        // convert data string to num array
        $data = stringToNumbersArray($content);

        // which clustering algo to use
        if ($algo == 'kMeans') {
          $centroids = kMeans($data, $numClusters); // get centriods of data set
          $centroids = implode(',', $centroids); // convert num array to string

          // insert trained model to db
          insertDBKCluster($modelName, $username, $algo, $centroids);
          echo '<script>alert("Model trained.");</script>';
        }
        if ($algo == 'eMax') {
          $clusters = emAlgorithm($data, $numClusters); // get cluster properties

          if ($clusters == 'Invalid input parameters') { // numCluster > score count
            echo '<script>alert("Number of clusters cannot be greater than number of scores.");</script>';
          } else { // valid input
            // convert num arrays to strings
            $means = implode(',', $clusters['means']);
            $variances = implode(',', $clusters['variances']);
            $mixingCoefficients = implode(',', $clusters['mixingCoefficients']);

            // insert trained model to db
            insertDBEM($modelName, $username, $algo, $means, $variances, $mixingCoefficients);
            echo '<script>alert("Model trained.");</script>';
          }
        }
      } else echo '<script>alert("Text input must only consist of numbers separated by commas.");</script>'; // invalid text content
    }
  }
}

// check test model form
if (isset($_POST['selectedModel'])) { // if model selected
  if ($_FILES['testFile']['error'] === UPLOAD_ERR_NO_FILE) { // if no file uploaded
    echo '<script>alert("Please select a file to upload.");</script>';
  } elseif ($_FILES) { // if file uploaded
    $modelName = $_POST['selectedModel'];
    $file = $_FILES['testFile']['name'];

    // sanitize file name
    $file = strtolower(preg_replace('[^A-Za-z0-9.]', '', $file));

    // ensure file type is txt
    if ($_FILES['testFile']['type'] == 'text/plain') {
      // move file from tmp location
      move_uploaded_file($_FILES['testFile']['tmp_name'], $file);

      // read all file contents
      $content = file_get_contents($file);

      // validate file content
      if (validateDataSet($content)) {
        $data = stringToNumbersArray($content); // convert data string to num array
        $algo = getUserModelType($username, $modelName); // get type of user model (kMeans or EM)
        sort($data); // sort data asc

        // which clustering algo to test
        if ($algo == 'kMeans') {
          $centroids = getKClusterCentroids($username, $modelName); // get centroids of model
          $centroids = stringToNumbersArray($centroids); // convert centriod string to num array
          $assignments = assignPoints($data, $centroids); // assign data to centriods
          $clusters = groupPointsByCluster($data, $assignments, sizeof($centroids)); // group data by centriods

          // display cluster lists
          echo '<h1>K-Means Cluster Assignments</h1>';
          foreach ($clusters as $index => $cluster) {
            echo '<b>Cluster ' . ($index + 1) . ':</b><br><i>Centroid: ' . $centroids[$index] . '</i><br>Scores: ';
            if (!empty($cluster)) {
              echo implode(', ', $cluster);
            } else {
              echo 'No data points';
            }
            echo '<br><br>';
          }
        }
        if ($algo == 'eMax') {
          $clusters = getEMaxClusterProperties($username, $modelName);

          echo '<h1>Expectation Maximization</h1>';
          echo '<h2>Cluster Properties</h2>';
          for ($i = 0; $i < count($clusters[0]); ++$i) {
            echo '<b>Cluster ' . ($i + 1) . ':</b><br>';
            for ($j = 0; $j < 3; ++$j) {
              if ($j == 0) echo '<i>Mean: ' . $clusters[$j][$i] . '</i><br>';
              if ($j == 1) echo '<i>Variance: ' . $clusters[$j][$i] . '</i><br>';
              if ($j == 2) echo '<i>Mixing Coefficient: ' . $clusters[$j][$i] . '</i><br>';
            }
            echo '<br>';
          }

          echo '<h2>Score Cluster Probabilities</h2>';
          $means = $clusters[0];
          $variances = $clusters[1];
          $mixingCoeffs = $clusters[2];

          $res = calculateClusterProbabilities($data, $means, $variances, $mixingCoeffs);
          foreach ($res as $dataProbs) {
            echo '<b>Score: ' . $dataProbs['dataPoint'] . '</b><br>';
            foreach ($dataProbs['probabilities'] as $cluster => $prob) {
              echo 'Cluster ' . ($cluster + 1) . ': ' . round($prob * 100, 2) . '%<br>';
            }
            echo '<br>';
          }
        }
      } else echo '<script>alert("File contents must only consist of numbers separated by commas.");</script>'; // invalid file content
    } else echo '<script>alert("Invalid file. Must be a txt file.");</script>'; // invalid file. not txt
  }
}

// include webpage
include('private_html/dashboard.html');

// close db conn
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

// server side validation of data set
function validateDataSet($content)
{
  $pattern = '/^\d+(,\d+)*$/';
  return preg_match($pattern, $content) ? true : false;
}

// convert number string with commas to num array
function stringToNumbersArray($numbersString)
{
  $numberArray = explode(',', $numbersString);
  $numberArray = array_map('floatval', $numberArray);
  return $numberArray;
}

// return boolean on if user model alr exists
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

// return type of model (kMeans or EM) based on user model
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
