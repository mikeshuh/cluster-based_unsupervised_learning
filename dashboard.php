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
if (isset($_SESSION['check'])
    && $_SESSION['check'] != hash('ripemd128', $_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT'])) different_user();

if (!authenticateUser()) {
    $conn->close();
    header('Location: login_signup.php');
    exit();
}

// check log out
if (isset($_POST['logout'])) {
    // destroy session
    destroy_session_and_data();
    $conn->close();

    // redirect to second page
    header('Location: login_signup.php');
    exit();
}

$username = $_SESSION['username'];

echo <<<_END
  <html>
  <head>
    <title>Dashboard</title>
    <style>
      .signup {
        border: 1px solid #999999;
        font: normal 14px helvetica;
        color: #444444;
      }
    </style>
  </head>
  <body>
    <!-- log out -->
    <form action="dashboard.php" method="post">
      <input type="submit" name="logout" id="logout" value="Log Out" />
    </form>
    <h1>Hello $username!</h1>
    <h2>Train Model</h2>
    <!-- search advisor -->
    <form method="post" action="dashboard.php" enctype="multipart/form-data">
      <b>Model Name: </b><input type="text" name="modelName" id="modelName"><br>
      <b>Choose Algorithm: </b>
      <select name="algo" id="algo"> 
        <option value="kMeans">K-Means Clustering</option> 
        <option value="eMax">Expectation Maximization</option> 
      </select><br>
      <b>Upload File or Text Box :</b>
      <select name="inputType" id="inputType" onchange="showInput()"> 
        <option value="none">Select...</option>
        <option value="file">File Upload</option>
        <option value="text">Text Box</option>
      </select>
      <div id="fileInput" style="display:none;">
            <input type="file" name="file">
        </div>
        <div id="textInput" style="display:none;">
            <textarea name="text" rows="4" cols="50"></textarea>
        </div>
    </form>
    <script>
      function showInput() {
        var inputType = document.getElementById("inputType").value;
        var fileInput = document.getElementById("fileInput");
        var textInput = document.getElementById("textInput");
    
        fileInput.style.display = (inputType === 'file') ? "block" : "none";
        textInput.style.display = (inputType === 'text') ? "block" : "none";
      }  
    </script>
  </body>
  </html>
_END;

$conn->close();

// authenticate user by checking if session var is set
function authenticateUser() {
    return isset($_SESSION['username']);
}

// sanatize strings
function sanitizeString($var) {
    $var = stripslashes($var);
    $var = htmlentities($var);
    $var = strip_tags($var);
    return $var;
}

// destroy session data
function destroy_session_and_data() {
    $_SESSION = array();
    setcookie(session_name(), '', time() - 2592000, '/');
    session_destroy();
}

// if user tries to hijack session, destroy session data and redirect to second page
function different_user() {
    global $conn;
    destroy_session_and_data();
    $conn->close();
    echo 'Techniacal error during signup/login. Please <a href="login_signup.php">click here</a> to sign up/log in.';
}
?>