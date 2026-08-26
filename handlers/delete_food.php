<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| DELETE FOOD — ADMINISTRATOR ONLY
|--------------------------------------------------------------------------
| Deletes a menu item only when it has not already been used by an order.
| This protects historical order records from being broken by a hard delete.
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    ($_SESSION['role'] ?? '') !== 'Administrator'
) {
    $_SESSION['login_message'] =
        'Please log in as an Administrator to delete food items.';
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['food_message'] = [
        'type' => 'error',
        'message' => 'Invalid request. Please use the Delete button.'
    ];
    header('Location: ../pages/food_menu.php');
    exit;
}

require_once '../includes/db_connection.php';

$foodId = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);

if (!$foodId || $foodId < 1) {
    $_SESSION['food_message'] = [
        'type' => 'error',
        'message' => 'Invalid food item selected.'
    ];
    header('Location: ../pages/food_menu.php');
    exit;
}

try {
    $pdo->beginTransaction();

    /* Lock and load the menu item so the delete is safe under concurrency. */
    $stmt = $pdo->prepare('SELECT id, name, image FROM food_menu WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $foodId]);
    $food = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$food) {
        $pdo->rollBack();
        $_SESSION['food_message'] = [
            'type' => 'error',
            'message' => 'The selected food item no longer exists.'
        ];
        header('Location: ../pages/food_menu.php');
        exit;
    }

    /*
     * Never remove a food row that is already part of an order.
     * Keeping the menu row preserves historical order integrity.
     */
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE food_id = :food_id');
    $stmt->execute([':food_id' => $foodId]);
    $orderItemCount = (int) $stmt->fetchColumn();

    if ($orderItemCount > 0) {
        $pdo->rollBack();
        $_SESSION['food_message'] = [
            'type' => 'error',
            'message' => 'This food item cannot be deleted because it has already been used in an order. Set it to Unavailable instead.'
        ];
        header('Location: ../pages/food_menu.php');
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM food_menu WHERE id = :id');
    $stmt->execute([':id' => $foodId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The food item could not be deleted.');
    }

    $pdo->commit();

    /* Delete the image only after the database deletion succeeds. */
    if (!empty($food['image'])) {
        $imageName = basename((string) $food['image']);
        $imagePath = dirname(__DIR__) . '/assets/uploads/' . $imageName;

        if (is_file($imagePath)) {
            @unlink($imagePath);
        }
    }

    $_SESSION['food_message'] = [
        'type' => 'success',
        'message' => 'Food item "' . $food['name'] . '" was deleted successfully.'
    ];

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['food_message'] = [
        'type' => 'error',
        'message' => 'Unable to delete the food item. Please try again.'
    ];
}

header('Location: ../pages/food_menu.php');
exit;