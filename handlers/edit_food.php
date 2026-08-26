<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| EDIT FOOD HANDLER
|--------------------------------------------------------------------------
| Location:
|   /handlers/edit_food.php
|
| Used by:
|   food_menu.php Edit Food popup
|
| Updates:
|   - Food name
|   - Category
|   - Price
|   - Description
|   - Status
|   - Optional food image
|
| After successful update:
|   Redirects directly to:
|       ../pages/food_menu.php
|--------------------------------------------------------------------------
*/

session_start();

require_once __DIR__ . '/../includes/db_connection.php';


/*
|--------------------------------------------------------------------------
| REDIRECT WITH FOOD MENU NOTIFICATION
|--------------------------------------------------------------------------
*/
function redirectWithMessage(
    string $message,
    string $type = 'error'
): never {

    $_SESSION['food_message'] = [
        'type'    => $type,
        'message' => $message
    ];

    /*
     * Redirect directly to Food Menu page.
     *
     * 303 = Post/Redirect/Get
     */
    header(
        'Location: ../pages/food_menu.php',
        true,
        303
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CLEAN TEXT
|--------------------------------------------------------------------------
*/
function cleanText(mixed $value): string
{
    return trim((string) $value);
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {

    $_SESSION['login_message'] =
        'Please log in to edit food items.';

    header(
        'Location: ../index.php',
        true,
        303
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ADMINISTRATOR ONLY
|--------------------------------------------------------------------------
*/
if (
    ($_SESSION['role'] ?? '') !== 'Administrator'
) {

    redirectWithMessage(
        'You do not have permission to edit food items.'
    );
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    redirectWithMessage(
        'Invalid request. Please use the Edit Food form.'
    );
}


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$foodId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

$name = cleanText(
    $_POST['name'] ?? ''
);

$categoryId = filter_input(
    INPUT_POST,
    'category_id',
    FILTER_VALIDATE_INT
);

$priceRaw = cleanText(
    $_POST['price'] ?? ''
);

$description = cleanText(
    $_POST['description'] ?? ''
);

$status = cleanText(
    $_POST['status'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDATE FOOD ID
|--------------------------------------------------------------------------
*/
if (
    !$foodId ||
    $foodId <= 0
) {

    redirectWithMessage(
        'Invalid food item selected.'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE FOOD NAME
|--------------------------------------------------------------------------
*/
if ($name === '') {

    redirectWithMessage(
        'Food name is required.'
    );
}

if (
    mb_strlen($name) > 150
) {

    redirectWithMessage(
        'Food name cannot exceed 150 characters.'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE CATEGORY
|--------------------------------------------------------------------------
*/
if (
    $categoryId === false ||
    $categoryId <= 0
) {

    redirectWithMessage(
        'Please select a valid food category.'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE PRICE
|--------------------------------------------------------------------------
*/
if (
    $priceRaw === '' ||
    !is_numeric($priceRaw)
) {

    redirectWithMessage(
        'Please enter a valid food price.'
    );
}

$price = (float) $priceRaw;

if ($price < 0) {

    redirectWithMessage(
        'Food price cannot be negative.'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE DESCRIPTION
|--------------------------------------------------------------------------
*/
if (
    mb_strlen($description) > 500
) {

    redirectWithMessage(
        'Food description cannot exceed 500 characters.'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'Available',
    'Unavailable'
];

if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    redirectWithMessage(
        'Invalid food status selected.'
    );
}


/*
|--------------------------------------------------------------------------
| IMAGE VARIABLES
|--------------------------------------------------------------------------
*/
$imageWasUploaded = (
    isset($_FILES['image']) &&
    is_array($_FILES['image']) &&
    (
        $_FILES['image']['error']
        ?? UPLOAD_ERR_NO_FILE
    ) !== UPLOAD_ERR_NO_FILE
);

$newImageName = null;
$newImagePath = null;


/*
|--------------------------------------------------------------------------
| PROCESS NEW IMAGE
|--------------------------------------------------------------------------
*/
if ($imageWasUploaded) {

    $imageError = (int) (
        $_FILES['image']['error']
        ?? UPLOAD_ERR_NO_FILE
    );


    /*
     * PHP upload errors
     */
    if (
        $imageError !== UPLOAD_ERR_OK
    ) {

        $uploadErrors = [

            UPLOAD_ERR_INI_SIZE =>
                'The uploaded image is too large.',

            UPLOAD_ERR_FORM_SIZE =>
                'The uploaded image is too large.',

            UPLOAD_ERR_PARTIAL =>
                'The image upload was incomplete.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'The server image upload folder is missing.',

            UPLOAD_ERR_CANT_WRITE =>
                'The server could not save the uploaded image.',

            UPLOAD_ERR_EXTENSION =>
                'The image upload was blocked by the server.'
        ];

        redirectWithMessage(
            $uploadErrors[$imageError]
            ?? 'Unable to upload the new food image.'
        );
    }


    /*
     * Temporary uploaded file
     */
    $tmpName =
        $_FILES['image']['tmp_name']
        ?? '';


    if (
        $tmpName === '' ||
        !is_uploaded_file($tmpName)
    ) {

        redirectWithMessage(
            'Invalid image upload.'
        );
    }


    /*
     * Maximum image size: 5 MB
     */
    $maxImageSize =
        5 * 1024 * 1024;


    if (
        (int) (
            $_FILES['image']['size']
            ?? 0
        ) > $maxImageSize
    ) {

        redirectWithMessage(
            'Food image must not exceed 5 MB.'
        );
    }


    /*
     * Detect real MIME type.
     */
    $finfo = new finfo(
        FILEINFO_MIME_TYPE
    );

    $mimeType =
        $finfo->file($tmpName);


    /*
     * Allowed image formats.
     */
    $allowedMimeTypes = [

        'image/jpeg' =>
            'jpg',

        'image/png' =>
            'png',

        'image/webp' =>
            'webp'
    ];


    if (
        !isset(
            $allowedMimeTypes[$mimeType]
        )
    ) {

        redirectWithMessage(
            'Only JPG, PNG, and WEBP food images are allowed.'
        );
    }


    /*
     * Verify that the file is actually an image.
     */
    if (
        @getimagesize($tmpName) === false
    ) {

        redirectWithMessage(
            'The uploaded file is not a valid image.'
        );
    }


    /*
     * Generate unique image filename.
     */
    $extension =
        $allowedMimeTypes[$mimeType];


    $newImageName =
        'food_' .
        $foodId .
        '_' .
        bin2hex(
            random_bytes(8)
        ) .
        '.' .
        $extension;


    /*
     * Food image directory.
     */
    $uploadDirectory =
        __DIR__ .
        '/../assets/uploads/';


    /*
     * Create directory if necessary.
     */
    if (
        !is_dir($uploadDirectory)
    ) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
        ) {

            redirectWithMessage(
                'Unable to create the food image upload folder.'
            );
        }
    }


    /*
     * Check directory permissions.
     */
    if (
        !is_writable($uploadDirectory)
    ) {

        redirectWithMessage(
            'The food image upload folder is not writable.'
        );
    }


    /*
     * Complete image path.
     */
    $newImagePath =
        $uploadDirectory .
        $newImageName;


    /*
     * Move uploaded image.
     */
    if (
        !move_uploaded_file(
            $tmpName,
            $newImagePath
        )
    ) {

        redirectWithMessage(
            'Unable to save the new food image.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE UPDATE
|--------------------------------------------------------------------------
*/
try {

    /*
     * Get existing food item.
     */
    $foodStmt = $pdo->prepare("
        SELECT
            id,
            image
        FROM food_menu
        WHERE id = :id
        LIMIT 1
    ");

    $foodStmt->execute([
        ':id' => $foodId
    ]);

    $food =
        $foodStmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
     * Food does not exist.
     */
    if (!$food) {

        if (
            $newImagePath &&
            is_file($newImagePath)
        ) {

            @unlink(
                $newImagePath
            );
        }

        redirectWithMessage(
            'The selected food item could not be found.'
        );
    }


    /*
     * Verify category exists.
     */
    $categoryStmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id = :category_id
        LIMIT 1
    ");

    $categoryStmt->execute([
        ':category_id' =>
            $categoryId
    ]);


    if (
        !$categoryStmt->fetchColumn()
    ) {

        if (
            $newImagePath &&
            is_file($newImagePath)
        ) {

            @unlink(
                $newImagePath
            );
        }

        redirectWithMessage(
            'The selected food category does not exist.'
        );
    }


    /*
     * Start transaction.
     */
    $pdo->beginTransaction();


    /*
     * Update including new image.
     */
    if (
        $newImageName !== null
    ) {

        $updateStmt = $pdo->prepare("
            UPDATE food_menu
            SET
                name = :name,
                category_id = :category_id,
                price = :price,
                description = :description,
                status = :status,
                image = :image
            WHERE id = :id
            LIMIT 1
        ");

        $updateStmt->execute([

            ':name' =>
                $name,

            ':category_id' =>
                $categoryId,

            ':price' =>
                $price,

            ':description' =>
                $description,

            ':status' =>
                $status,

            ':image' =>
                $newImageName,

            ':id' =>
                $foodId
        ]);

    } else {

        /*
         * Update without changing image.
         */
        $updateStmt = $pdo->prepare("
            UPDATE food_menu
            SET
                name = :name,
                category_id = :category_id,
                price = :price,
                description = :description,
                status = :status
            WHERE id = :id
            LIMIT 1
        ");

        $updateStmt->execute([

            ':name' =>
                $name,

            ':category_id' =>
                $categoryId,

            ':price' =>
                $price,

            ':description' =>
                $description,

            ':status' =>
                $status,

            ':id' =>
                $foodId
        ]);
    }


    /*
     * Commit database changes.
     */
    $pdo->commit();


    /*
     * Delete old image only after successful
     * database update.
     */
    if (
        $newImageName !== null
    ) {

        $oldImage =
            trim(
                (string) (
                    $food['image']
                    ?? ''
                )
            );


        if (
            $oldImage !== ''
        ) {

            /*
             * Prevent path traversal.
             */
            $oldImageBase =
                basename(
                    $oldImage
                );


            if (
                $oldImageBase !== '' &&
                $oldImageBase !== '.' &&
                $oldImageBase !== '..'
            ) {

                $oldImagePath =
                    __DIR__ .
                    '/../assets/uploads/' .
                    $oldImageBase;


                if (
                    is_file(
                        $oldImagePath
                    )
                ) {

                    /*
                     * Never delete the newly uploaded image.
                     */
                    $newRealPath =
                        realpath(
                            $newImagePath
                        );

                    $oldRealPath =
                        realpath(
                            $oldImagePath
                        );


                    if (
                        $oldRealPath === false ||
                        $newRealPath === false ||
                        $oldRealPath !== $newRealPath
                    ) {

                        @unlink(
                            $oldImagePath
                        );
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    |
    | Save the success notification in the same structure
    | used by food_menu.php.
    |
    */
    $_SESSION['food_message'] = [
        'type' =>
            'success',

        'message' =>
            'Food item updated successfully.'
    ];


    /*
     * DIRECTLY LOAD FOOD MENU PAGE
     */
    header(
        'Location: ../pages/food_menu.php',
        true,
        303
    );

    exit;


} catch (PDOException $e) {

    /*
     * Roll back database transaction.
     */
    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    /*
     * Remove newly uploaded image
     * because database update failed.
     */
    if (
        $newImagePath &&
        is_file($newImagePath)
    ) {

        @unlink(
            $newImagePath
        );
    }


    /*
     * Show friendly error.
     */
    redirectWithMessage(
        'Unable to update the food item. Please try again.'
    );


} catch (Throwable $e) {

    /*
     * Roll back transaction.
     */
    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    /*
     * Remove unused new image.
     */
    if (
        $newImagePath &&
        is_file($newImagePath)
    ) {

        @unlink(
            $newImagePath
        );
    }


    /*
     * Friendly error message.
     */
    redirectWithMessage(
        'An unexpected error occurred while updating the food item.'
    );
}

?>