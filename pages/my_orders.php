<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| MY ORDERS — SALES PERSON
|--------------------------------------------------------------------------
| Sales Person can only view orders belonging to their own account.
| No administration/order-management actions are exposed here.
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !in_array($_SESSION['role'] ?? '', ['Salesperson', 'Sales Person'], true)
) {
    $_SESSION['login_message'] =
        'You do not have permission to access My Orders.';
    header('Location: ../index.php');
    exit;
}

require_once "../includes/db_connection.php";

$username = $_SESSION['username'] ?? 'Sales Person';
$fullName = $_SESSION['full_name'] ?? $username;
$role = $_SESSION['role'] ?? 'Sales Person';
$avatar = strtoupper(substr(trim($fullName), 0, 1));

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return 'GH₵ ' . number_format($amount, 2);
}

function statusClass($status): string
{
    return strtolower(trim((string)$status));
}

$userId = (int)(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

$orderDate = trim((string)($_GET['order_date'] ?? ''));
if ($orderDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate)) {
    $orderDate = '';
}

$orders = [];
$totalOrders = 0;
$completedOrders = 0;
$totalSales = 0.00;
$totalPages = 1;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$expandedOrderId = (int)($_GET['order'] ?? 0);

try {
    /*
     * Summary cards are always restricted to this Sales Person.
     */
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_orders,
            COUNT(*) AS completed_orders,
            COALESCE(SUM(total), 0) AS total_sales,
            COALESCE(SUM(
                CASE
                    WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                     AND created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
                    THEN total
                    ELSE 0
                END
            ), 0) AS weekly_sales
        FROM orders
        WHERE user_id = :user_id
          AND status = 'Completed'
    ");
    $stmt->execute([':user_id' => $userId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalOrders = (int)($summary['total_orders'] ?? 0);
    $completedOrders = (int)($summary['completed_orders'] ?? 0);
    $totalSales = (float)($summary['total_sales'] ?? 0);
    $weeklySales = (float)($summary['weekly_sales'] ?? 0);

    /*
     * Build the filtered order query.
     */
    $where = ["o.user_id = :user_id", "o.status = 'Completed'"];
    $params = [':user_id' => $userId];

    if ($orderDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate)) {
        $where[] = 'DATE(o.created_at) = :order_date';
        $params[':order_date'] = $orderDate;
    }

    $whereSql = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM orders o
        WHERE {$whereSql}
    ");
    $stmt->execute($params);
    $filteredCount = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($filteredCount / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_number,
            o.order_type,
            o.status,
            o.payment_status,
            o.total,
            o.created_at,
            COALESCE(SUM(oi.quantity), 0) AS item_quantity
        FROM orders o
        LEFT JOIN order_items oi
            ON oi.order_id = o.id
        WHERE {$whereSql}
        GROUP BY
            o.id,
            o.order_number,
            o.order_type,
            o.status,
            o.payment_status,
            o.total,
            o.created_at
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Load the selected order's items only when requested.
     * The user_id condition prevents viewing another salesperson's order.
     */
    $selectedOrder = null;
    $selectedItems = [];

    if ($expandedOrderId > 0) {
        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.order_number,
                o.order_type,
                o.status,
                o.payment_status,
                o.total,
                o.created_at
            FROM orders o
            WHERE o.id = :order_id
              AND o.user_id = :user_id
              AND o.status = 'Completed'
            LIMIT 1
        ");
        $stmt->execute([
            ':order_id' => $expandedOrderId,
            ':user_id' => $userId
        ]);
        $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($selectedOrder) {
            $stmt = $pdo->prepare("
                SELECT
                    f.name,
                    f.image,
                    oi.quantity,
                    oi.unit_price,
                    oi.subtotal
                FROM order_items oi
                INNER JOIN food_menu f
                    ON f.id = oi.food_id
                WHERE oi.order_id = :order_id
                ORDER BY oi.id ASC
            ");
            $stmt->execute([':order_id' => $expandedOrderId]);
            $selectedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    // Keep the page usable if a non-critical query fails.
    $orders = [];
    $totalPages = 1;
}

function queryUrl(array $changes = []): string
{
    $query = $_GET;

    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    return '?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Better End Food Point</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: 'Poppins', sans-serif;
    }

    .orders-content {
        padding-bottom: 40px;
    }

    .orders-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 22px;
    }

    .orders-hero h2 {
        margin: 0 0 6px;
        font-size: 24px;
        font-weight: 700;
    }

    .orders-hero p {
        margin: 0;
        color: var(--muted, #8a817a);
        font-size: 13px;
    }

    .primary-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 0;
        border-radius: 10px;
        padding: 12px 16px;
        text-decoration: none;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: .4px;
        background: #f58220;
        color: #fff;
        box-shadow: 0 8px 18px rgba(245, 130, 32, .18);
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .summary-card {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .05);
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 7px 24px rgba(0, 0, 0, .04);
    }

    .summary-card .label {
        font-size: 11px;
        color: #8d837b;
        margin-bottom: 7px;
    }

    .summary-card strong {
        display: block;
        font-size: 21px;
        line-height: 1.15;
    }

    .summary-card .icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        float: right;
        background: #fff3e8;
        color: #f58220;
    }

    .toolbar {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .05);
        border-radius: 14px;
        padding: 14px;
        margin-bottom: 18px;
        box-shadow: 0 7px 24px rgba(0, 0, 0, .04);
    }

    .filter-form {
        display: grid;
        grid-template-columns: minmax(220px, 1.7fr) repeat(2, minmax(150px, .8fr)) auto auto;
        gap: 10px;
        align-items: center;
    }

    .filter-input,
    .filter-select {
        width: 100%;
        min-height: 42px;
        box-sizing: border-box;
        border: 1px solid #e5dfda;
        background: #fff;
        color: #3e3935;
        border-radius: 9px;
        padding: 0 12px;
        font-family: inherit;
        font-size: 12px;
        outline: none;
    }

    .filter-input:focus,
    .filter-select:focus {
        border-color: #f58220;
        box-shadow: 0 0 0 3px rgba(245, 130, 32, .10);
    }

    .filter-button,
    .clear-button {
        min-height: 42px;
        padding: 0 14px;
        border-radius: 9px;
        font-family: inherit;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        white-space: nowrap;
    }

    .filter-button {
        border: 0;
        background: #f58220;
        color: #fff;
    }

    .clear-button {
        border: 1px solid #e5dfda;
        background: #fff;
        color: #625a54;
    }

    .orders-panel {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .05);
        border-radius: 14px;
        box-shadow: 0 7px 24px rgba(0, 0, 0, .04);
        overflow: hidden;
    }

    .panel-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        padding: 19px 20px;
        border-bottom: 1px solid #eee8e3;
    }

    .panel-heading h3 {
        margin: 0 0 4px;
        font-size: 15px;
    }

    .panel-heading p {
        margin: 0;
        color: #958b84;
        font-size: 11px;
    }

    .result-count {
        color: #8a817a;
        font-size: 11px;
        white-space: nowrap;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }

    th {
        text-align: left;
        padding: 12px 18px;
        background: #faf8f6;
        color: #8c827b;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .7px;
        text-transform: uppercase;
    }

    td {
        padding: 14px 18px;
        border-top: 1px solid #f0ece9;
        vertical-align: middle;
        font-size: 11px;
        color: #514b46;
    }

    tbody tr:hover {
        background: #fffaf6;
    }

    .order-main {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .order-number {
        font-weight: 700;
        color: #3c3631;
    }

    .order-date {
        color: #9a9089;
        font-size: 9px;
    }

    .status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 700;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .status.pending,
    .status.preparing,
    .status.ready {
        background: #fff4df;
        color: #a76a00;
    }

    .status.completed,
    .status.paid {
        background: #eaf8ef;
        color: #21834a;
    }

    .status.cancelled,
    .status.failed,
    .status.refunded {
        background: #fdeceb;
        color: #b53d37;
    }

    .status.default {
        background: #f1efed;
        color: #6f6761;
    }

    .amount {
        font-weight: 700;
        color: #342f2b;
        white-space: nowrap;
    }

    .view-button {
        width: 32px;
        height: 32px;
        border: 1px solid #e8e2dd;
        background: #fff;
        color: #6e655e;
        border-radius: 8px;
        cursor: pointer;
        display: inline-grid;
        place-items: center;
        text-decoration: none;
    }

    .view-button:hover {
        color: #f58220;
        border-color: #f58220;
    }

    .empty {
        padding: 65px 20px;
        text-align: center;
        color: #999;
        font-size: 11px;
    }

    .empty i {
        display: block;
        font-size: 28px;
        margin-bottom: 12px;
        color: #c9c1bb;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        padding: 18px;
        border-top: 1px solid #eee8e3;
    }

    .page-link {
        min-width: 32px;
        height: 32px;
        padding: 0 9px;
        border: 1px solid #e7e1dc;
        background: #fff;
        color: #655d57;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 10px;
        font-weight: 600;
    }

    .page-link.active {
        background: #f58220;
        color: #fff;
        border-color: #f58220;
    }

    .page-link.disabled {
        opacity: .45;
        pointer-events: none;
    }

    .order-details {
        background: #fffaf6;
        border-top: 1px solid #f0e6df;
        padding: 18px 20px 20px;
    }

    .details-head {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        align-items: flex-start;
        margin-bottom: 14px;
    }

    .details-head h4 {
        margin: 0 0 4px;
        font-size: 14px;
    }

    .details-meta {
        color: #8d837c;
        font-size: 10px;
    }

    .details-grid {
        display: grid;
        grid-template-columns: 1.4fr .7fr;
        gap: 14px;
    }

    .items-box,
    .order-summary-box {
        background: #fff;
        border: 1px solid #eee5df;
        border-radius: 10px;
        padding: 13px;
    }

    .item-line {
        display: grid;
        grid-template-columns: 42px 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f1ece8;
    }

    .item-line:last-child {
        border-bottom: 0;
    }

    .item-image {
        width: 42px;
        height: 42px;
        border-radius: 9px;
        overflow: hidden;
        background: #f4f1ee;
    }

    .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .item-name {
        font-size: 10px;
        font-weight: 700;
    }

    .item-qty {
        color: #918780;
        font-size: 9px;
    }

    .item-price {
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 10px;
        padding: 6px 0;
    }

    .summary-row strong {
        color: #38322e;
    }

    @media (max-width: 1100px) {
        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .filter-form {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 760px) {
        .orders-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .summary-grid {
            grid-template-columns: 1fr 1fr;
        }

        .filter-form {
            grid-template-columns: 1fr;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .panel-heading {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    /* =========================================================
       COMPLETED ORDERS — DATE SEARCH TOOLBAR
       ========================================================= */

    .date-toolbar {
        padding: 14px 16px !important;
        background: linear-gradient(135deg, #ffffff 0%, #fffaf6 100%) !important;
    }

    .date-search-form {
        display: grid !important;
        grid-template-columns: minmax(260px, 1fr) 230px auto auto !important;
        align-items: center !important;
        gap: 12px !important;
    }

    .date-search-title {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 0;
    }

    .date-search-title>i {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: #fff0e3;
        color: #f58220;
        font-size: 14px;
    }

    .date-search-title strong {
        display: block;
        color: #3c3631;
        font-size: 11px;
        font-weight: 800;
    }

    .date-search-title small {
        display: block;
        margin-top: 3px;
        color: #958b84;
        font-size: 8px;
        line-height: 1.4;
    }

    .date-search-field {
        position: relative;
    }

    .date-search-field>i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #f58220;
        pointer-events: none;
        z-index: 2;
    }

    .date-search-field .filter-input {
        padding-left: 38px !important;
        cursor: pointer;
    }

    .date-search-form .filter-button,
    .date-search-form .clear-button {
        min-height: 42px;
        padding: 0 16px;
        border-radius: 9px;
        font-family: inherit;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    .date-search-form .filter-button {
        min-width: 110px;
    }

    .date-search-form .clear-button {
        min-width: 105px;
    }

    .bootstrap-pagination {
        padding: 18px 20px;
        border-top: 1px solid #eee8e3;
        background: #fff;
    }

    .bootstrap-pagination .pagination {
        gap: 4px;
    }

    .bootstrap-pagination .page-link {
        min-width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 9px;
        border: 1px solid #e7e1dc;
        border-radius: 8px !important;
        color: #655d57;
        background: #fff;
        font-size: 10px;
        font-weight: 700;
        box-shadow: none;
    }

    .bootstrap-pagination .page-item.active .page-link {
        color: #fff;
        background: #f58220;
        border-color: #f58220;
    }

    .bootstrap-pagination .page-link:hover {
        color: #f58220;
        background: #fff7f0;
        border-color: #f4c79f;
    }

    .bootstrap-pagination .page-item.disabled .page-link {
        color: #b7afa8;
        background: #f8f6f4;
        border-color: #e7e1dc;
    }

    @media (max-width: 1000px) {
        .date-search-form {
            grid-template-columns: 1fr 220px auto auto !important;
        }

        .date-search-title small {
            display: none;
        }
    }

    @media (max-width: 760px) {
        .date-search-form {
            grid-template-columns: 1fr !important;
        }

        .date-search-title small {
            display: block;
        }

        .date-search-form .filter-button,
        .date-search-form .clear-button {
            width: 100%;
        }
    }

    /* =========================================================
       SIDEBAR ISOLATION
       Keep Bootstrap pagination styles from affecting the shared
       role-based sidebar. Only the real current page gets active.
       ========================================================= */
    .sidebar .sidebar-nav a,
    .sidebar .sidebar-nav a:hover,
    .sidebar .sidebar-nav a:focus,
    .sidebar .sidebar-nav a:active {
        text-decoration: none !important;
    }

    .sidebar .sidebar-nav a.active {
        text-decoration: none !important;
    }

    .sidebar .sidebar-nav a.active::before,
    .sidebar .sidebar-nav a.active::after {
        text-decoration: none !important;
    }

    /* Bootstrap pagination is scoped strictly to its own container. */
    .bootstrap-pagination .page-link {
        text-decoration: none !important;
    }
    </style>
</head>

<body>
    <div class="app">

        <?php include "../includes/sidebar.php"; ?>

        <main class="main">

            <header class="topbar">
                <div class="page-title">
                    <h1>My Orders</h1>
                    <p>View and track orders placed using your Sales Person account</p>
                </div>

                <div class="profile">
                    <button type="button" class="profile-button" id="profileButton">
                        <div class="avatar"><?= e($avatar) ?></div>
                        <div class="profile-info">
                            <strong><?= e($fullName) ?></strong>
                            <small>Sales Person</small>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size:9px;color:#948a82"></i>
                    </button>

                    <div class="profile-menu" id="profileMenu">
                        <div class="profile-menu-head">
                            <strong><?= e($fullName) ?></strong>
                            <small>@<?= e($username) ?> · Sales Person</small>
                        </div>

                        <a href="sales_dashboard.php">
                            <i class="fa-solid fa-gauge-high"></i>
                            Dashboard
                        </a>

                        <a href="orders.php">
                            <i class="fa-solid fa-cart-plus"></i>
                            Create New Order
                        </a>

                        <a href="my_orders.php">
                            <i class="fa-solid fa-receipt"></i>
                            My Orders
                        </a>

                        <a href="../handlers/logout.php">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Sign Out
                        </a>
                    </div>
                </div>
            </header>

            <section class="content orders-content">

                <div class="orders-hero">
                    <div>
                        <h2>My Orders</h2>
                        <p>View your completed orders and quickly find orders by date.</p>
                    </div>

                    <a href="orders.php" class="primary-button">
                        <i class="fa-solid fa-plus"></i>
                        CREATE NEW ORDER
                    </a>
                </div>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="icon"><i class="fa-solid fa-receipt"></i></div>
                        <div class="label">Total Orders</div>
                        <strong><?= number_format($totalOrders) ?></strong>
                    </div>

                    <div class="summary-card">
                        <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="label">Completed Orders</div>
                        <strong><?= number_format($completedOrders) ?></strong>
                    </div>

                    <div class="summary-card">
                        <div class="icon"><i class="fa-solid fa-calendar-week"></i></div>
                        <div class="label">Weekly Sales</div>
                        <strong><?= money($weeklySales) ?></strong>
                    </div>

                    <div class="summary-card">
                        <div class="icon"><i class="fa-solid fa-coins"></i></div>
                        <div class="label">Completed Sales</div>
                        <strong><?= money($totalSales) ?></strong>
                    </div>
                </div>

                <section class="toolbar date-toolbar">
                    <form class="date-search-form" method="get" action="my_orders.php">

                        <div class="date-search-title">
                            <i class="fa-regular fa-calendar-check"></i>
                            <div>
                                <strong>Find Orders by Date</strong>
                                <small>Select a date to display all completed orders from that day.</small>
                            </div>
                        </div>

                        <div class="date-search-field">
                            <i class="fa-regular fa-calendar"></i>
                            <input class="filter-input" type="date" name="order_date" value="<?= e($orderDate) ?>"
                                aria-label="Select order date">
                        </div>

                        <button class="filter-button" type="submit">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            SEARCH
                        </button>

                        <a class="clear-button" href="my_orders.php">
                            <i class="fa-solid fa-list"></i>
                            SHOW ALL
                        </a>

                    </form>
                </section>

                <section class="orders-panel">

                    <div class="panel-heading">
                        <div>
                            <h3>Order History</h3>
                            <p>Your latest orders appear first.</p>
                        </div>

                        <span class="result-count">
                            <?= number_format($filteredCount ?? 0) ?>
                            result<?= (($filteredCount ?? 0) === 1 ? '' : 's') ?>
                        </span>
                    </div>

                    <?php if ($orders): ?>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Type</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>View</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <?php
                                    $status = (string)$order['status'];
                                    $isExpanded = $expandedOrderId === (int)$order['id'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="order-main">
                                            <span class="order-number">
                                                <?= e($order['order_number']) ?>
                                            </span>
                                            <span class="order-date">
                                                <?= e(date('d M Y, h:i A', strtotime((string)$order['created_at']))) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td><?= e($order['order_type']) ?></td>

                                    <td><?= number_format((int)$order['item_quantity']) ?></td>

                                    <td>
                                        <span class="status completed">Completed</span>
                                    </td>

                                    <td class="amount">
                                        <?= money((float)$order['total']) ?>
                                    </td>

                                    <td>
                                        <?php if ($isExpanded): ?>
                                        <a class="view-button" href="<?= e(queryUrl(['order' => null])) ?>"
                                            title="Close order details">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </a>
                                        <?php else: ?>
                                        <a class="view-button" href="<?= e(queryUrl(['order' => (int)$order['id']])) ?>"
                                            title="View order details">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($isExpanded && $selectedOrder): ?>
                                <tr>
                                    <td colspan="6" style="padding:0;">
                                        <div class="order-details">

                                            <div class="details-head">
                                                <div>
                                                    <h4>
                                                        Order <?= e($selectedOrder['order_number']) ?>
                                                    </h4>
                                                    <div class="details-meta">
                                                        <?= e($selectedOrder['order_type']) ?>
                                                        ·
                                                        <?= e(date('d M Y, h:i A', strtotime((string)$selectedOrder['created_at']))) ?>
                                                    </div>
                                                </div>

                                                <div>
                                                    <span
                                                        class="status <?= e(statusClass($selectedOrder['status'])) ?>">
                                                        <?= e($selectedOrder['status']) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="details-grid">

                                                <div class="items-box">
                                                    <?php if ($selectedItems): ?>
                                                    <?php foreach ($selectedItems as $item): ?>
                                                    <div class="item-line">
                                                        <div class="item-image">
                                                            <?php if (!empty($item['image'])): ?>
                                                            <img src="../assets/uploads/<?= e($item['image']) ?>"
                                                                alt="<?= e($item['name']) ?>"
                                                                onerror="this.style.display='none';">
                                                            <?php endif; ?>
                                                        </div>

                                                        <div>
                                                            <div class="item-name">
                                                                <?= e($item['name']) ?>
                                                            </div>
                                                            <div class="item-qty">
                                                                Qty: <?= number_format((int)$item['quantity']) ?>
                                                                ·
                                                                <?= money((float)$item['unit_price']) ?> each
                                                            </div>
                                                        </div>

                                                        <div class="item-price">
                                                            <?= money((float)$item['subtotal']) ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <div class="empty" style="padding:25px 10px;">
                                                        No item details available.
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="order-summary-box">
                                                    <div class="summary-row">
                                                        <span>Order status</span>
                                                        <strong><?= e($selectedOrder['status']) ?></strong>
                                                    </div>

                                                    <div class="summary-row">
                                                        <span>Payment</span>
                                                        <strong><?= e($selectedOrder['payment_status']) ?></strong>
                                                    </div>

                                                    <div class="summary-row">
                                                        <span>Order type</span>
                                                        <strong><?= e($selectedOrder['order_type']) ?></strong>
                                                    </div>

                                                    <div class="summary-row"
                                                        style="border-top:1px solid #eee5df;margin-top:7px;padding-top:11px;">
                                                        <span>Total</span>
                                                        <strong><?= money((float)$selectedOrder['total']) ?></strong>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav class="bootstrap-pagination" aria-label="Completed orders pagination">
                        <ul class="pagination justify-content-center align-items-center mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="<?= e(queryUrl(['page' => max(1, $page - 1), 'order' => null])) ?>"
                                    aria-label="Previous">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>

                            <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                ?>

                            <?php if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= e(queryUrl(['page' => 1, 'order' => null])) ?>">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(queryUrl(['page' => $p, 'order' => null])) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="<?= e(queryUrl(['page' => $totalPages, 'order' => null])) ?>">
                                    <?= $totalPages ?>
                                </a>
                            </li>
                            <?php endif; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="<?= e(queryUrl(['page' => min($totalPages, $page + 1), 'order' => null])) ?>"
                                    aria-label="Next">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>

                        </ul>
                    </nav>
                    <?php endif; ?>

                    <?php else: ?>

                    <div class="empty">
                        <i class="fa-solid fa-receipt"></i>

                        <?php if ($orderDate): ?>
                        No completed orders were found for the selected date.
                        <br>
                        <a href="my_orders.php"
                            style="display:inline-block;margin-top:10px;color:#f58220;font-weight:600;text-decoration:none;">
                            Clear date
                        </a>
                        <?php else: ?>
                        You have not placed any orders from this account yet.
                        <br>
                        <a href="orders.php"
                            style="display:inline-block;margin-top:10px;color:#f58220;font-weight:600;text-decoration:none;">
                            Create your first order
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>

                </section>

            </section>
        </main>
    </div>

    <script>
    const profileButton = document.getElementById('profileButton');
    const profileMenu = document.getElementById('profileMenu');

    profileButton?.addEventListener('click', function(event) {
        event.stopPropagation();
        profileMenu?.classList.toggle('show');
    });

    document.addEventListener('click', function(event) {
        if (!event.target.closest('.profile')) {
            profileMenu?.classList.remove('show');
        }
    });
    </script>

</body>

</html>