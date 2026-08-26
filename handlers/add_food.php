<?php

session_start();

require_once "../includes/db_connection.php";


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ../pages/food_menu.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Only allow logged-in Administrators
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Administrator'
) {

    $_SESSION['login_message'] =
        'Please log in as an Administrator to add food items.';

    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get form values
|--------------------------------------------------------------------------
*/

$name        = trim($_POST['name'] ?? '');
$category_id = trim($_POST['category_id'] ?? '');
$price       = trim($_POST['price'] ?? '');
$description = trim($_POST['description'] ?? '');
$status      = trim($_POST['status'] ?? '');


/*
|--------------------------------------------------------------------------
| Error handler
|--------------------------------------------------------------------------
*/

function addFoodError(string $message): never
{
    $_SESSION['food_message'] = [
        'type' => 'error',
        'message' => $message
    ];

    header('Location: ../pages/food_menu.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate required fields
|--------------------------------------------------------------------------
*/

if (
    $name === '' ||
    $category_id === '' ||
    $price === '' ||
    $status === ''
) {

    addFoodError(
        'Please complete all the required food fields.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate food name
|--------------------------------------------------------------------------
*/

if (mb_strlen($name) > 150) {

    addFoodError(
        'The food name cannot exceed 150 characters.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate category ID
|--------------------------------------------------------------------------
*/

if (!ctype_digit($category_id)) {

    addFoodError(
        'Please select a valid food category.'
    );
}

$category_id = (int) $category_id;


/*
|--------------------------------------------------------------------------
| Validate price
|--------------------------------------------------------------------------
*/

if (!is_numeric($price)) {

    addFoodError(
        'Please enter a valid food price.'
    );
}


$price = (float) $price;


if ($price < 0) {

    addFoodError(
        'Food price cannot be negative.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate status
|--------------------------------------------------------------------------
*/

$allowed_statuses = [
    'Available',
    'Unavailable'
];


if (!in_array($status, $allowed_statuses, true)) {

    addFoodError(
        'Please select a valid food status.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate description
|--------------------------------------------------------------------------
*/

if (mb_strlen($description) > 500) {

    addFoodError(
        'The food description cannot exceed 500 characters.'
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | Check category
    |--------------------------------------------------------------------------
    |
    | Only active categories can receive new food items.
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name
        FROM categories
        WHERE id = :category_id
        AND status = 'Active'
        LIMIT 1
    ");

    $stmt->execute([
        ':category_id' => $category_id
    ]);

    $category = $stmt->fetch();


    if (!$category) {

        addFoodError(
            'The selected category does not exist or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check duplicate food + price combination
    |--------------------------------------------------------------------------
    |
    | The same food name is allowed more than once.
    | What makes a menu item a duplicate is the combination of:
    |
    |   - Food name
    |   - Price
    |
    | Therefore:
    |
    |   Jollof Rice - 20.00  -> allowed
    |   Jollof Rice - 25.00  -> allowed
    |   Jollof Rice - 20.00  -> duplicate
    |
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM food_menu
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
          AND price = :price
        LIMIT 1
    ");

    $stmt->execute([
        ':name'  => $name,
        ':price' => $price
    ]);


    if ($stmt->fetch()) {

        addFoodError(
            'The food item "' .
            $name .
            '" with a price of ' .
            number_format($price, 2) .
            ' already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | IMAGE UPLOAD
    |--------------------------------------------------------------------------
    */

    $imageName = null;


    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {


        /*
        |--------------------------------------------------------------------------
        | Check upload error
        |--------------------------------------------------------------------------
        */

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

            addFoodError(
                'There was a problem uploading the food image.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check file size
        |--------------------------------------------------------------------------
        |
        | Maximum: 5MB
        |
        */

        $maxFileSize = 5 * 1024 * 1024;


        if ($_FILES['image']['size'] > $maxFileSize) {

            addFoodError(
                'The food image must not be larger than 5MB.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Validate image
        |--------------------------------------------------------------------------
        */

        $tmpFile = $_FILES['image']['tmp_name'];


        $imageInfo = @getimagesize($tmpFile);


        if ($imageInfo === false) {

            addFoodError(
                'The uploaded file is not a valid image.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Allowed image types
        |--------------------------------------------------------------------------
        */

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];


        $mimeType = $imageInfo['mime'] ?? '';


        if (!isset($allowedMimeTypes[$mimeType])) {

            addFoodError(
                'Only JPG, PNG and WEBP images are allowed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Generate safe unique filename
        |--------------------------------------------------------------------------
        */

        $extension = $allowedMimeTypes[$mimeType];


        $imageName =
            'food_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $extension;


        /*
        |--------------------------------------------------------------------------
        | Upload directory
        |--------------------------------------------------------------------------
        |
        | This points to:
        |
        | assets/uploads/
        |
        */

        $uploadDirectory =
            __DIR__ .
            '/../assets/uploads/';


        /*
        |--------------------------------------------------------------------------
        | Create upload directory if it doesn't exist
        |--------------------------------------------------------------------------
        */

        if (!is_dir($uploadDirectory)) {

            if (!mkdir(
                $uploadDirectory,
                0755,
                true
            )) {

                addFoodError(
                    'Unable to create the image upload folder.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Make sure directory is writable
        |--------------------------------------------------------------------------
        */

        if (!is_writable($uploadDirectory)) {

            addFoodError(
                'The image upload folder is not writable.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Final image path
        |--------------------------------------------------------------------------
        */

        $imagePath =
            $uploadDirectory .
            $imageName;


        /*
        |--------------------------------------------------------------------------
        | Move uploaded image
        |--------------------------------------------------------------------------
        */

        if (
            !move_uploaded_file(
                $tmpFile,
                $imagePath
            )
        ) {

            addFoodError(
                'Unable to save the uploaded food image.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert food
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO food_menu (
            category_id,
            name,
            description,
            price,
            image,
            status
        )
        VALUES (
            :category_id,
            :name,
            :description,
            :price,
            :image,
            :status
        )
    ");


    $stmt->execute([

        ':category_id' => $category_id,

        ':name' =>
            $name,

        ':description' =>
            $description !== ''
                ? $description
                : null,

        ':price' =>
            $price,

        ':image' =>
            $imageName,

        ':status' =>
            $status
    ]);


    /*
    |--------------------------------------------------------------------------
    | Success notification
    |--------------------------------------------------------------------------
    */

    $_SESSION['food_message'] = [

        'type' =>
            'success',

        'message' =>
            'Food "' .
            $name .
            '" was added successfully.'
    ];


} catch (PDOException $e) {


    /*
    |--------------------------------------------------------------------------
    | Delete uploaded image if database insertion failed
    |--------------------------------------------------------------------------
    |
    | This prevents unused images from remaining in assets/uploads/.
    |
    */

    if (
        !empty($imageName) &&
        isset($imagePath) &&
        file_exists($imagePath)
    ) {

        @unlink($imagePath);
    }


    /*
    |--------------------------------------------------------------------------
    | Database error
    |--------------------------------------------------------------------------
    */

    $_SESSION['food_message'] = [

        'type' =>
            'error',

        'message' =>
            'Unable to add the food item. Please try again.'
    ];
}


/*
|--------------------------------------------------------------------------
| Return to Food Menu page
|--------------------------------------------------------------------------
*/

header('Location: ../pages/food_menu.php');
exit;