<?php

session_start();

require_once "../includes/db_connection.php";


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $_SESSION['login_message'] =
        'Error: Login handler was accessed incorrectly.';

    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get login credentials
|--------------------------------------------------------------------------
*/

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| Validate submitted fields
|--------------------------------------------------------------------------
*/

if ($username === '') {

    $_SESSION['login_message'] =
        'Username is required.';

    header('Location: ../index.php');
    exit;
}


if ($password === '') {

    $_SESSION['login_message'] =
        'Password is required.';

    header('Location: ../index.php');
    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | Find the user
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | These are the columns you showed me in your users table.
    |
    | id
    | full_name
    | username
    | email
    | phone
    | password
    | role
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            username,
            email,
            phone,
            password,
            role
        FROM users
        WHERE username = :username
        LIMIT 1
    ");


    $stmt->execute([
        ':username' => $username
    ]);


    $user = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Check username
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        $_SESSION['login_message'] =
            'Login failed: The username "' .
            htmlspecialchars($username) .
            '" was not found.';

        header('Location: ../index.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Check password field
    |--------------------------------------------------------------------------
    */

    if (empty($user['password'])) {

        $_SESSION['login_message'] =
            'Login failed: This user does not have a password stored in the database.';

        header('Location: ../index.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify password
    |--------------------------------------------------------------------------
    */

    if (!password_verify($password, $user['password'])) {

        $_SESSION['login_message'] =
            'Login failed: The username is correct, but the password is incorrect.';

        header('Location: ../index.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify role
    |--------------------------------------------------------------------------
    */

    if (
        $user['role'] !== 'Administrator' &&
        $user['role'] !== 'Salesperson'
    ) {

        $_SESSION['login_message'] =
            'Login failed: The user role "' .
            htmlspecialchars($user['role']) .
            '" is not supported.';

        header('Location: ../index.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Login successful
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);


    /*
    |--------------------------------------------------------------------------
    | Store user information in session
    |--------------------------------------------------------------------------
    */

    $_SESSION['logged_in'] = true;


    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'full_name' => $user['full_name'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'phone'     => $user['phone'],
        'role'      => $user['role']
    ];


    /*
    |--------------------------------------------------------------------------
    | Direct session values
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['phone']     = $user['phone'];
    $_SESSION['role']      = $user['role'];


    /*
    |--------------------------------------------------------------------------
    | Redirect based on role
    |--------------------------------------------------------------------------
    */

    if ($user['role'] === 'Administrator') {

        header('Location: ../pages/dashboard.php');
        exit;
    }


    if ($user['role'] === 'Salesperson') {

        header('Location: ../pages/sales_dashboard.php');
        exit;
    }


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | SHOW THE ACTUAL DATABASE ERROR
    |--------------------------------------------------------------------------
    |
    | This is temporary while we are fixing the login.
    |
    */

    $_SESSION['login_message'] =
        'DATABASE ERROR: ' . $e->getMessage();

    header('Location: ../index.php');
    exit;
}