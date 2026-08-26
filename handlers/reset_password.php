<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RESET PASSWORD HANDLER
|--------------------------------------------------------------------------
| Changes the password of the currently logged-in user only.
|
| Security:
| - Requires an authenticated session.
| - Does not accept a user ID from the browser.
| - Verifies the current password with password_verify().
| - Stores the new password with password_hash().
| - Uses a prepared statement.
| - Returns JSON only.
|--------------------------------------------------------------------------
*/

session_start();

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(
    bool $success,
    string $message,
    int $statusCode = 200
): never {

    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    jsonResponse(
        false,
        'Your session has expired. Please log in again.',
        401
    );
}


/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/
$userId = (int)(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

if ($userId <= 0) {
    jsonResponse(
        false,
        'Unable to identify your account. Please log in again.',
        401
    );
}


/*
|--------------------------------------------------------------------------
| READ JSON REQUEST
|--------------------------------------------------------------------------
*/
$rawInput = file_get_contents('php://input');

$data = json_decode(
    $rawInput ?: '',
    true
);

if (!is_array($data)) {
    jsonResponse(
        false,
        'Invalid request.',
        400
    );
}


$currentPassword = (string)(
    $data['current_password'] ?? ''
);

$newPassword = (string)(
    $data['new_password'] ?? ''
);

$confirmPassword = (string)(
    $data['confirm_password'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/
if ($currentPassword === '') {
    jsonResponse(
        false,
        'Enter your current password.',
        422
    );
}

if ($newPassword === '') {
    jsonResponse(
        false,
        'Enter a new password.',
        422
    );
}

if (strlen($newPassword) < 8) {
    jsonResponse(
        false,
        'The new password must be at least 8 characters.',
        422
    );
}

if ($newPassword !== $confirmPassword) {
    jsonResponse(
        false,
        'The new passwords do not match.',
        422
    );
}

if (hash_equals($currentPassword, $newPassword)) {
    jsonResponse(
        false,
        'Your new password must be different from your current password.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/db_connection.php';


try {

    /*
     * Get the password belonging to the logged-in user.
     */
    $stmt = $pdo->prepare("
        SELECT
            id,
            password
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$user) {

        jsonResponse(
            false,
            'User account could not be found.',
            404
        );

    }


    /*
     |--------------------------------------------------------------------------
     | VERIFY CURRENT PASSWORD
     |--------------------------------------------------------------------------
     */
    if (
        !password_verify(
            $currentPassword,
            (string)$user['password']
        )
    ) {

        jsonResponse(
            false,
            'The current password is incorrect.',
            422
        );

    }


    /*
     |--------------------------------------------------------------------------
     | HASH NEW PASSWORD
     |--------------------------------------------------------------------------
     */
    $newPasswordHash =
        password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );


    if ($newPasswordHash === false) {

        jsonResponse(
            false,
            'Unable to securely create the new password.',
            500
        );

    }


    /*
     |--------------------------------------------------------------------------
     | UPDATE
     |--------------------------------------------------------------------------
     */
    $update = $pdo->prepare("
        UPDATE users
        SET password = :password
        WHERE id = :user_id
        LIMIT 1
    ");

    $update->execute([
        ':password' => $newPasswordHash,
        ':user_id' => $userId
    ]);


    /*
     |--------------------------------------------------------------------------
     | SUCCESS
     |--------------------------------------------------------------------------
     */
    jsonResponse(
        true,
        'Your password has been updated successfully.'
    );


} catch (Throwable $e) {

    /*
     * Do not expose database errors or SQL details to the browser.
     */
    error_log(
        'Password reset error: ' .
        $e->getMessage()
    );

    jsonResponse(
        false,
        'Unable to update your password right now. Please try again.',
        500
    );
}