<?php
session_start();
require_once "../includes/db_connection.php";

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Salesperson'], true)
) {
    http_response_code(403);
    exit('Access denied.');
}

$username = $_SESSION['full_name'] ?? 'Administrator';

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order.');
}

try {
    $orderStmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.order_type,
            o.status,
            o.subtotal,
            o.discount,
            o.tax,
            o.total,
            o.payment_status,
            o.created_at,
            p.amount AS payment_amount,
            p.payment_method,
            p.reference AS payment_reference,
            p.status AS payment_record_status,
            p.paid_at
        FROM orders o
        LEFT JOIN payments p
            ON p.id = (
                SELECT MAX(p2.id)
                FROM payments p2
                WHERE p2.order_id = o.id
            )
        WHERE o.id = :order_id
        LIMIT 1
    ");

    $orderStmt->execute([':order_id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        exit('Order not found.');
    }

    $itemStmt = $pdo->prepare("
        SELECT
            oi.food_id,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            fm.name
        FROM order_items oi
        INNER JOIN food_menu fm
            ON fm.id = oi.food_id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");

    $itemStmt->execute([':order_id' => $orderId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    exit('Unable to load receipt.');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string {
    return 'GH₵ ' . number_format((float)$value, 2);
}

$businessName = 'BETTER END RESTAURANT';
$businessTagline = 'Fresh Food. Fast Service.';
$receiptDate = $order['created_at']
    ? date('d/m/Y H:i', strtotime($order['created_at']))
    : date('d/m/Y H:i');

$paymentMethod = $order['payment_method'] ?: 'Not recorded';
$paymentReference = $order['payment_reference'] ?: '-';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= h($order['order_number']) ?></title>

    <style>
    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        background: #eeeeee;
        color: #111111;
        font-family: Arial, Helvetica, sans-serif;
    }

    .receipt {
        width: 80mm;
        max-width: 80mm;
        margin: 20px auto;
        padding: 4mm;
        background: #ffffff;
        font-size: 12px;
        line-height: 1.35;
    }

    .center {
        text-align: center;
    }

    .business-name {
        font-size: 18px;
        font-weight: 900;
        letter-spacing: .3px;
    }

    .tagline {
        margin-top: 2px;
        font-size: 10px;
    }

    .divider {
        border-top: 1px dashed #111;
        margin: 10px 0;
    }

    .receipt-title {
        font-size: 14px;
        font-weight: 900;
        margin: 5px 0;
    }

    .meta {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }

    .meta td {
        padding: 2px 0;
        vertical-align: top;
    }

    .meta td:first-child {
        width: 40%;
        font-weight: 700;
    }

    .items {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .items th {
        padding: 4px 0;
        border-bottom: 1px solid #111;
        font-size: 10px;
        text-align: left;
    }

    .items td {
        padding: 5px 0;
        vertical-align: top;
        font-size: 11px;
    }

    .items .name {
        width: 45%;
        word-break: break-word;
    }

    .items .qty {
        width: 12%;
        text-align: center;
    }

    .items .price {
        width: 21%;
        text-align: right;
    }

    .items .amount {
        width: 22%;
        text-align: right;
        font-weight: 700;
    }

    .summary {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }

    .summary td {
        padding: 3px 0;
    }

    .summary td:last-child {
        text-align: right;
    }

    .total-row td {
        border-top: 1px solid #111;
        padding-top: 7px;
        font-size: 15px;
        font-weight: 900;
    }

    .payment-box {
        border: 1px solid #111;
        padding: 7px;
        margin-top: 9px;
    }

    .payment-box div {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin: 2px 0;
    }

    .payment-box strong {
        font-weight: 900;
    }

    .status {
        margin-top: 8px;
        text-align: center;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .footer {
        margin-top: 12px;
        text-align: center;
        font-size: 10px;
    }

    .no-print {
        width: 80mm;
        max-width: 80mm;
        margin: 10px auto 20px;
        display: flex;
        gap: 8px;
    }

    .no-print button {
        flex: 1;
        border: 0;
        border-radius: 6px;
        padding: 10px;
        background: #f58220;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }

    .no-print a {
        flex: 1;
        display: block;
        text-align: center;
        text-decoration: none;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 10px;
        color: #111;
        background: #fff;
        font-weight: 700;
    }

    @page {
        size: 80mm auto;
        margin: 0;
    }

    @media print {

        html,
        body {
            width: 80mm;
            max-width: 80mm;
            background: #fff;
        }

        .receipt {
            width: 80mm;
            max-width: 80mm;
            margin: 0;
            padding: 4mm;
        }

        .no-print {
            display: none !important;
        }
    }
    </style>
</head>

<body>

    <div class="receipt">

        <div class="center">
            <div class="business-name"><?= h($businessName) ?></div>
            <div class="tagline"><?= h($businessTagline) ?></div>

            <div class="divider"></div>

            <div class="receipt-title">SALES RECEIPT</div>
        </div>

        <table class="meta">
            <tr>
                <td>Order No.</td>
                <td><?= h($order['order_number']) ?></td>
            </tr>
            <tr>
                <td>Date</td>
                <td><?= h($receiptDate) ?></td>
            </tr>
            <tr>
                <td>Order Type</td>
                <td><?= h($order['order_type']) ?></td>
            </tr>
            <tr>
                <td>Cashier</td>
                <td><?= h($username) ?></td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="items">
            <thead>
                <tr>
                    <th class="name">ITEM</th>
                    <th class="qty">QTY</th>
                    <th class="price">PRICE</th>
                    <th class="amount">AMOUNT</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="name"><?= h($item['name']) ?></td>
                    <td class="qty"><?= (int)$item['quantity'] ?></td>
                    <td class="price"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="amount"><?= number_format((float)$item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="summary">
            <tr>
                <td>Subtotal</td>
                <td><?= money($order['subtotal']) ?></td>
            </tr>

            <?php if ((float)$order['discount'] > 0): ?>
            <tr>
                <td>Discount</td>
                <td>-<?= money($order['discount']) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ((float)$order['tax'] > 0): ?>
            <tr>
                <td>Tax</td>
                <td><?= money($order['tax']) ?></td>
            </tr>
            <?php endif; ?>

            <tr class="total-row">
                <td>TOTAL</td>
                <td><?= money($order['total']) ?></td>
            </tr>
        </table>

        <div class="payment-box">
            <div>
                <span>Payment</span>
                <strong><?= h($paymentMethod) ?></strong>
            </div>

            <div>
                <span>Payment Status</span>
                <strong><?= h($order['payment_status']) ?></strong>
            </div>

            <div>
                <span>Reference</span>
                <strong><?= h($paymentReference) ?></strong>
            </div>
        </div>

        <div class="status">
            <?= h($order['status']) ?>
        </div>

        <div class="divider"></div>

        <div class="footer">
            Thank you for dining with us.<br>
            Please come again!
        </div>

    </div>

    <div class="no-print">
        <button type="button" onclick="window.print()">
            PRINT RECEIPT
        </button>

        <a href="orders.php">BACK TO ORDER</a>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>