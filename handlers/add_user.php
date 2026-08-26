<?php

session_start();

require_once"../includes/db_connection.php";


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get form values
|--------------------------------------------------------------------------
*/

$full_name = trim($_POST['full_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$role      = trim($_POST['role'] ?? '');


/*
|--------------------------------------------------------------------------
| Error handler
|--------------------------------------------------------------------------
*/

function addUserError(string $message): never
{
    $_SESSION['user_message'] = [
        'type' => 'error',
        'message' => $message
    ];

    header('Location: ../pages/users.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate required fields
|--------------------------------------------------------------------------
*/

if (
    $full_name === '' ||
    $username === '' ||
    $email === '' ||
    $phone === '' ||
    $role === ''
) {
    addUserError('Please complete all the required fields.');
}


/*
|--------------------------------------------------------------------------
| Validate email
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    addUserError('Please enter a valid email address.');
}


/*
|--------------------------------------------------------------------------
| Validate role
|--------------------------------------------------------------------------
*/

$allowed_roles = [
    'Administrator',
    'Salesperson'
];

if (!in_array($role, $allowed_roles, true)) {
    addUserError('Please select a valid user role.');
}


try {

    /*
    |--------------------------------------------------------------------------
    | Check username
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username
    ]);

    if ($stmt->fetch()) {
        addUserError(
            'The username "' . $username . '" already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check email
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    if ($stmt->fetch()) {
        addUserError(
            'The email address "' . $email . '" is already registered.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate initial password
    |--------------------------------------------------------------------------
    |
    | The username becomes the initial password.
    |
    | Example:
    |
    | Username: eric
    | Initial password: eric
    |
    | Only the hashed password is stored.
    |
    */

    $passwordHash = password_hash(
        $username,
        PASSWORD_DEFAULT
    );


    /*
    |--------------------------------------------------------------------------
    | Insert user
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO users (
            full_name,
            username,
            email,
            phone,
            password,
            role
        )
        VALUES (
            :full_name,
            :username,
            :email,
            :phone,
            :password,
            :role
        )
    ");

    $stmt->execute([
        ':full_name' => $full_name,
        ':username'  => $username,
        ':email'     => $email,
        ':phone'     => $phone,
        ':password'  => $passwordHash,
        ':role'      => $role
    ]);


    /*
    |--------------------------------------------------------------------------
    | Success notification
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_message'] = [
        'type' => 'success',
        'message' => 'User "' . $full_name . '" was added successfully.'
    ];


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Database error
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_message'] = [
        'type' => 'error',
        'message' => 'Unable to add the user. Please try again.'
    ];
}


/*
|--------------------------------------------------------------------------
| Return to Users page
|--------------------------------------------------------------------------
*/

header('Location: ../pages/users.php');
exit;