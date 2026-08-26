<?php

/*
|--------------------------------------------------------------------------
| handlers/add_order.php
|--------------------------------------------------------------------------
| Creates:
|   1. orders
|   2. order_items
|   3. payments
|
| Payment is linked to the order with payments.order_id.
|--------------------------------------------------------------------------
*/

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once "../includes/db_connection.php";


function respond(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
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
    respond(false, 'Please log in before placing an order.', [], 401);
}


/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    respond(false, 'Your login session is invalid. Please log in again.', [], 401);
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', [], 405);
}


/*
|--------------------------------------------------------------------------
| READ JSON
|--------------------------------------------------------------------------
*/

$raw = file_get_contents('php://input');

if ($raw === false || trim($raw) === '') {
    respond(false, 'No order data was received.', [], 400);
}

$data = json_decode($raw, true);

if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    respond(false, 'Invalid order data received.', [], 400);
}


/*
|--------------------------------------------------------------------------
| ORDER TYPE
|--------------------------------------------------------------------------
*/

$orderType = trim((string)($data['order_type'] ?? ''));

if (!in_array($orderType, ['Dine In', 'Takeaway'], true)) {
    respond(false, 'Please select a valid order type.', [], 422);
}


/*
|--------------------------------------------------------------------------
| PAYMENT METHOD
|--------------------------------------------------------------------------
*/

$paymentMethod = trim((string)($data['payment_method'] ?? ''));

if (!in_array($paymentMethod, ['Cash', 'Card', 'Mobile Money'], true)) {
    respond(false, 'Please select a valid payment method.', [], 422);
}


/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

$items = $data['items'] ?? null;

if (!is_array($items) || count($items) === 0) {
    respond(false, 'Please add at least one food item.', [], 422);
}

if (count($items) > 100) {
    respond(false, 'Too many different food items were submitted.', [], 422);
}


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | LOAD FOOD FROM DATABASE
    |--------------------------------------------------------------------------
    | Prices are NEVER trusted from JavaScript.
    |--------------------------------------------------------------------------
    */

    $foodStmt = $pdo->prepare("
        SELECT id, name, price, status
        FROM food_menu
        WHERE id = :id
        LIMIT 1
    ");

    $validatedItems = [];
    $subtotal = 0.00;


    foreach ($items as $item) {

        $foodId = (int)($item['food_id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);

        if ($foodId <= 0) {
            throw new Exception('Invalid food item selected.');
        }

        if ($quantity <= 0) {
            throw new Exception('Food quantity must be at least 1.');
        }

        if ($quantity > 1000) {
            throw new Exception('Food quantity is too large.');
        }

        $foodStmt->execute([
            ':id' => $foodId
        ]);

        $food = $foodStmt->fetch(PDO::FETCH_ASSOC);

        if (!$food) {
            throw new Exception('One of the selected food items does not exist.');
        }

        if ($food['status'] !== 'Available') {
            throw new Exception(
                '"' . $food['name'] . '" is currently unavailable.'
            );
        }

        $unitPrice = round((float)$food['price'], 2);

        if ($unitPrice < 0) {
            throw new Exception(
                'Invalid price for "' . $food['name'] . '".'
            );
        }

        $itemSubtotal = round($unitPrice * $quantity, 2);

        $subtotal += $itemSubtotal;

        $validatedItems[] = [
            'food_id' => $foodId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $itemSubtotal
        ];
    }


    $subtotal = round($subtotal, 2);

    /*
    |--------------------------------------------------------------------------
    | CURRENT TAX / DISCOUNT
    |--------------------------------------------------------------------------
    */

    $discount = 0.00;
    $tax = 0.00;

    $total = round(
        $subtotal - $discount + $tax,
        2
    );

    if ($total <= 0) {
        throw new Exception('The order total must be greater than zero.');
    }


    /*
    |--------------------------------------------------------------------------
    | ORDER NUMBER
    |--------------------------------------------------------------------------
    */

    $orderNumber = 'ORD-' .
        date('YmdHis') .
        '-' .
        random_int(100, 999);


    /*
    |--------------------------------------------------------------------------
    | INSERT ORDER
    |--------------------------------------------------------------------------
    */

    $orderStmt = $pdo->prepare("
        INSERT INTO orders
        (
            order_number,
            user_id,
            order_type,
            status,
            subtotal,
            discount,
            tax,
            total,
            payment_status
        )
        VALUES
        (
            :order_number,
            :user_id,
            :order_type,
            :status,
            :subtotal,
            :discount,
            :tax,
            :total,
            :payment_status
        )
    ");

    $orderStmt->execute([
        ':order_number' => $orderNumber,
        ':user_id' => $userId,
        ':order_type' => $orderType,
        ':status' => 'Completed',
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':tax' => $tax,
        ':total' => $total,
        ':payment_status' => 'Paid'
    ]);


    $orderId = (int)$pdo->lastInsertId();

    if ($orderId <= 0) {
        throw new Exception('Failed to create the order.');
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items
        (
            order_id,
            food_id,
            quantity,
            unit_price,
            subtotal
        )
        VALUES
        (
            :order_id,
            :food_id,
            :quantity,
            :unit_price,
            :subtotal
        )
    ");


    foreach ($validatedItems as $item) {

        $itemStmt->execute([
            ':order_id' => $orderId,
            ':food_id' => $item['food_id'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price'],
            ':subtotal' => $item['subtotal']
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT
    |--------------------------------------------------------------------------
    */

    $reference = 'PAY-' .
        date('YmdHis') .
        '-' .
        random_int(1000, 9999);


    $paymentStmt = $pdo->prepare("
        INSERT INTO payments
        (
            order_id,
            amount,
            payment_method,
            reference,
            status
        )
        VALUES
        (
            :order_id,
            :amount,
            :payment_method,
            :reference,
            :status
        )
    ");


    $paymentStmt->execute([
        ':order_id' => $orderId,
        ':amount' => $total,
        ':payment_method' => $paymentMethod,
        ':reference' => $reference,
        ':status' => 'Completed'
    ]);


    $paymentId = (int)$pdo->lastInsertId();

    if ($paymentId <= 0) {
        throw new Exception('Failed to record the payment.');
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Order placed successfully.',
        [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'payment_id' => $paymentId,
            'payment_method' => $paymentMethod,
            'payment_reference' => $reference,
            'payment_status' => 'Completed',
            'order_payment_status' => 'Paid',
            'order_status' => 'Completed'
        ]
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | SERVER ERROR
    |--------------------------------------------------------------------------
    |
    | Return JSON even when PHP/database fails.
    |--------------------------------------------------------------------------
    */

    respond(
        false,
        $e->getMessage(),
        [],
        500
    );
}