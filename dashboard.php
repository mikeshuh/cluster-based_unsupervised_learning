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

// check seach advisor input
if (isset($_POST['name']) && isset($_POST['studentId'])) {
    // sanitize inputs
    $name = sanitizeString($_POST['name']);
    $studentId = sanitizeString($_POST['studentId']);

    // no entry for name or student ID
    if($name == '' || $studentId =='') echo 'Enter a Name and Student ID.';
    // check if student exists
    else if (searchStudent($name, $studentId)) { // student exists
        // query for assocaited advisor
        $advisorInfo = searchAdvisor($studentId);
        
        if (empty($advisorInfo)) { // no query results
            echo 'No listed advisor.';
        }
        else { // output advisor info
            echo <<<_END
            <p>
                <b><u>Advisor</u></b><br>
                <b>Name: </b>$advisorInfo[0]<br>
                <b>Email: </b>$advisorInfo[1]<br>
                <b>Phone: </b>$advisorInfo[2]
            </p>
_END;
        }
    }
    else echo 'No such student exists.';
}

echo <<<_END
  <html>
  <head>
    <title>Search Advisor</title>
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
      <input type="submit" name="logout" value="Log Out" />
    </form>
    <!-- search advisor -->
    <form method="post" action="dashboard.php">
      <table border="0" cellpadding="2" cellspacing="5" bgcolor="#eeeeee">
        <th colspan="2" align="center">Search Advisor</th>
        <tr>
          <td>Name</td>
          <td><input type="text" maxlength="255" name="name" /></td>
        </tr>
        <tr>
          <td>Student ID</td>
          <td><input type="text" maxlength="9" name="studentId" /></td>
        </tr>
        <tr>
          <td colspan="2" align="center">
            <input type="submit" value="Search" />
          </td>
        </tr>
      </table>
    </form>
  </body>
  </html>
_END;

$conn->close();

// query for associated advisor given studentID
function searchAdvisor($studentId) {
    global $conn;
    $stmt = $conn->prepare('SELECT name, email, phone_number FROM advisors_info WHERE lower_bound_id <= ? AND upper_bound_id >= ?');
    $stmt->bind_param('ii', $studentId, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_array(MYSQLI_NUM);
}

// return if student exists in db given name and studentID
function searchStudent($name, $studentId) {
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM credentials WHERE name=? AND student_id=?');
    $stmt->bind_param('si', $name, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// authenticate user by checking if session var is set
function authenticateUser() {
    return isset($_SESSION['studentId']);
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