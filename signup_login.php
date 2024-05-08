<?php
require_once 'login.php';

// connect to db
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) die($conn->connect_error);

// check sign up form
if (isset($_POST['sUsername']) && isset($_POST['sEmail']) && isset($_POST['sPassword'])) {
    // sanitize inputs
    $username = sanitizeString($_POST['sUsername']);
    $email = sanitizeString($_POST['sEmail']);
    $password = $_POST['sPassword'];

    // check if username or email exists
    if (searchUsername($username) || searchEmail($email)) { // username or email alr exists
        echo '<script>alert("Username or email is already taken.");</script>';
    } else { // username and email valid
        // server side input validation
        if (validateUserData($username, $email, $password)) {
            // hash password to get token. auto salting
            $token = password_hash($password, PASSWORD_DEFAULT);

            // insert user to db
            insertUserDB($username, $email, $token);

            // start session and set session vars
            session_start();
            $_SESSION['initiated'] = true;
            $_SESSION['check'] = hash('ripemd128', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
            $_SESSION['username'] = $username;

            // close db conn
            $conn->close();

            // direct to dashboard page
            header('Location: dashboard.php');
            exit();
        }
    }
}

// check log in form
if (isset($_POST['lUsername']) && isset($_POST['lPassword'])) {
    // sanitize inputs
    $username = sanitizeString($_POST['lUsername']);
    $password = $_POST['lPassword'];

    // query for user credentials
    $stmt = $conn->prepare('SELECT * FROM user_accounts WHERE username=?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_array(MYSQLI_NUM);
    $stmt->close();

    // verify password
    if (password_verify($password, $row[2])) { // if tokens match
        // start session, set session vars
        session_start();
        $_SESSION['initiated'] = true;
        $_SESSION['check'] = hash('ripemd128', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
        $_SESSION['username'] = $username;

        // close db conn
        $conn->close();

        // direct to dashboard page
        header('Location: dashboard.php');
        exit();
    } else echo '<script>alert("Incorrect Log In.");</script>'; // tokens don't match
}

// include webpage
include('private_html/signup_login.html');

// close db conn
$conn->close();

// add user to db
function insertUserDB($username, $email, $token)
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO user_accounts VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $username, $email, $token);
    $stmt->execute();
    $stmt->close();
}

// return boolean on if username alr exists
function searchUsername($username)
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM user_accounts WHERE username=?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// return boolean on if email alr exists
function searchEmail($email)
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM user_accounts WHERE email=?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// server side form input validation
function validateUserData($username, $email, $password)
{
    // validate username
    if ($username === '' || preg_match('/[^a-zA-Z0-9_-]/', $username)) {
        echo '<script>alert("Invalid Username");</script>';
        return false;
    }
    // validate email
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<script>alert("Invalid Email");</script>';
        return false;
    }
    // validate password
    if ($password === '' || strlen($password) < 8 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        echo '<script>alert("Invalid Password");</script>';
        return false;
    }
    return true;
}

// sanatize strings
function sanitizeString($var)
{
    $var = stripslashes($var);
    $var = strip_tags($var);
    $var = htmlentities($var);
    return $var;
}
