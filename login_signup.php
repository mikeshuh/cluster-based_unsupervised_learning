<?php
require_once 'login.php';

// connect to db
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) die($conn->connect_error);

echo file_get_contents('frontend/login_signup.html');

// check sign up input
if (isset($_POST['sName']) && isset($_POST['sStudentId']) && isset($_POST['sEmail']) && isset($_POST['sPassword'])) {
    // sanitize inputs
    $name = sanitizeString($_POST['sName']);
    $studentId = sanitizeString($_POST['sStudentId']);
    $email = sanitizeString($_POST['sEmail']);
    $password = $_POST['sPassword'];

    // check if studentID exists
    if (searchStudentID($studentId)) { // studentID alr exists
        echo '<script>alert("Student ID is already taken.");</script>';
    }
    else { // studentID valid
        // server side form input validation
        if (verifyValidation($name, $studentId, $email, $password)) {
            // add password salts
            $salt1 = 'qm&h*';
            $salt2 = 'pg!@';
            // get token with hash
            $token = hash('ripemd128', "$salt1$password$salt2");
            
            // add to db
            insertDB($name, $studentId, $email, $token);
            
            // start session, set session vars
            session_start();
            $_SESSION['initiated'] = true;
            $_SESSION['check'] = hash('ripemd128', $_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
            $_SESSION['studentId'] = $studentId;
            $conn->close();

            // redirect to first page
            header('Location: dashboard.php');
            exit();
        }
    }
}

// check log in input
if (isset($_POST['lStudentId']) && isset($_POST['lPassword'])) {
    //sanitize inputs
    $studentId = sanitizeString($_POST['lStudentId']);
    $password = $_POST['lPassword'];

    // add password salts
    $salt1 = 'qm&h*';
    $salt2 = 'pg!@';
    // get token with hash
    $token = hash('ripemd128', "$salt1$password$salt2");

    // query for user credentials
    $stmt = $conn->prepare('SELECT * FROM credentials WHERE student_id=? AND token=?');
    $stmt->bind_param('is', $studentId, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_array(MYSQLI_NUM);
    $stmt->close();
    
    // check password
    if ($token == $row[3]) { // if tokens match
        // start session, set session vars
        session_start();
        $_SESSION['initiated'] = true;
        $_SESSION['check'] = hash('ripemd128', $_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
        $_SESSION['studentId'] = $studentId;
        $conn->close();

        // redirect to first page
        header('Location: dashboard.php');
        exit();
    }
    else echo '<script>alert("Incorrect Log In.");</script>';
}

$conn->close();

// add to db function
function insertDB($name, $studentId, $email, $token) {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO credentials VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $studentId, $name, $email, $token);
    $stmt->execute();
    $stmt->close();
}

// return if studentID alr in db
function searchStudentID($studentId) {
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM credentials WHERE student_id=?');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// server side form input validation
function verifyValidation($name, $studentId, $email, $password) {
    if ($name == '')
        return false;
    elseif ($studentId == '' || !is_numeric($studentId) || $studentId < 0)
        return false;
    elseif ($email == '' || !(strpos($email, '.') > 0 && strpos($email, '@') > 0) || preg_match('/[^a-zA-Z0-9.@_-]/', $email))
        return false;
    elseif ($password == '' || strlen($password) < 6 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password))
        return false;
    return true;
}

// sanatize strings
function sanitizeString($var) {
    $var = stripslashes($var);
    $var = strip_tags($var);
    $var = htmlentities($var);
    return $var;
}
?>