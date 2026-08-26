<?php

session_start();

require_once "../includes/db_connection.php";


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/categories.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get form values
|--------------------------------------------------------------------------
*/

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status      = trim($_POST['status'] ?? '');


/*
|--------------------------------------------------------------------------
| Error handler
|--------------------------------------------------------------------------
*/

function addCategoryError(string $message): never
{
    $_SESSION['category_message'] = [
        'type' => 'error',
        'message' => $message
    ];

    header('Location: ../pages/categories.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate required fields
|--------------------------------------------------------------------------
*/

if ($name === '' || $status === '') {
    addCategoryError(
        'Please complete all the required fields.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate category status
|--------------------------------------------------------------------------
*/

$allowed_statuses = [
    'Active',
    'Inactive'
];

if (!in_array($status, $allowed_statuses, true)) {
    addCategoryError(
        'Please select a valid category status.'
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | Check category name
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE name = :name
        LIMIT 1
    ");

    $stmt->execute([
        ':name' => $name
    ]);

    if ($stmt->fetch()) {
        addCategoryError(
            'The category "' . $name . '" already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert category
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO categories (
            name,
            description,
            status
        )
        VALUES (
            :name,
            :description,
            :status
        )
    ");

    $stmt->execute([
        ':name'        => $name,
        ':description' => $description !== '' ? $description : null,
        ':status'      => $status
    ]);


    /*
    |--------------------------------------------------------------------------
    | Success notification
    |--------------------------------------------------------------------------
    */

    $_SESSION['category_message'] = [
        'type' => 'success',
        'message' => 'Category "' . $name . '" was added successfully.'
    ];


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Database error
    |--------------------------------------------------------------------------
    */

    $_SESSION['category_message'] = [
        'type' => 'error',
        'message' => 'Unable to add the category. Please try again.'
    ];
}


/*
|--------------------------------------------------------------------------
| Return to Categories page
|--------------------------------------------------------------------------
*/

header('Location: ../pages/category.php');
exit;