<?php
require_once 'login.php';

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
