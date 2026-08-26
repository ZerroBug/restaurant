<?php

session_start();
require_once "../includes/db_connection.php";

/*
|--------------------------------------------------------------------------
| Dashboard Access Protection
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Administrator'
) {
    $_SESSION['login_message'] =
        'Please log in as an Administrator to access the dashboard.';
    header('Location: ../index.php');
    exit;
}

$username  = $_SESSION['username'] ?? 'Administrator';
$full_name = $_SESSION['full_name'] ?? $username;
$role      = $_SESSION['role'] ?? 'Administrator';
$avatar    = strtoupper(substr(trim($full_name), 0, 1));

/* Currency helpers */
function ghMoney($amount): string
{
    return 'GH₵' . number_format((float)$amount, 2);
}

function ghCompact($amount): string
{
    $amount = (float)$amount;
    if ($amount >= 1000000) return 'GH₵' . number_format($amount / 1000000, 2) . 'M';
    if ($amount >= 1000) return 'GH₵' . number_format($amount / 1000, 1) . 'K';
    return 'GH₵' . number_format($amount, 0);
}

function foodImagePath($image): string
{
    if (!$image) return '../assets/uploads/default-food.jpg';
    if (strpos($image, 'assets/') === 0) return '../' . ltrim($image, '/');
    if (strpos($image, 'uploads/') === 0) return '../assets/' . ltrim($image, '/');
    return '../assets/uploads/' . ltrim($image, '/');
}

/* Defaults */
$todaySales = 0;
$totalOrders = 0;
$totalItemsOrdered = 0;
$completedOrders = 0;
$pendingOrders = 0;
$paidOrders = 0;
$takeawayOrders = 0;
$dineInOrders = 0;
$monthlySales = 0;
$monthlyOrders = 0;

$popularFoods = [];
$recentOrders = [];
$monthlySalesData = [];

$paymentStats = [
    'Cash' => 0,
    'Card' => 0,
    'Mobile Money' => 0
];

$paymentCounts = [
    'Cash' => 0,
    'Card' => 0,
    'Mobile Money' => 0
];

$paymentStatusCounts = [
    'Pending' => 0,
    'Completed' => 0,
    'Failed' => 0,
    'Refunded' => 0
];

$totalCollected = 0;
$chartTotal = 0;
$chartBest = 0;
$chartBestLabel = 'No Data';
$chartAverage = 0;

$dashboardError = null;

try {

    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    */

    $totalOrders = (int)$pdo->query("
        SELECT COUNT(id)
        FROM orders
    ")->fetchColumn();

    $completedOrders = (int)$pdo->query("
        SELECT COUNT(id)
        FROM orders
        WHERE status = 'Completed'
    ")->fetchColumn();

    $pendingOrders = (int)$pdo->query("
        SELECT COUNT(id)
        FROM orders
        WHERE status IN ('Pending', 'Preparing', 'Ready')
    ")->fetchColumn();

    $paidOrders = (int)$pdo->query("
        SELECT COUNT(id)
        FROM orders
        WHERE payment_status = 'Paid'
    ")->fetchColumn();

    $takeawayOrders = (int)$pdo->query("
        SELECT COUNT(id)
        FROM orders
        WHERE order_type = 'Takeaway'
    ")->fetchColumn();

    $dineInOrders = (int)$pdo->query("
        SELECT COUNT(id)
        FROM orders
        WHERE order_type = 'Dine In'
    ")->fetchColumn();

    $totalItemsOrdered = (int)$pdo->query("
        SELECT COALESCE(SUM(quantity), 0)
        FROM order_items
    ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | SALES
    |--------------------------------------------------------------------------
    |
    | Sales are taken from completed payments, because the current
    | add_order.php creates the order as Pending but records the payment
    | as Completed and orders.payment_status as Paid.
    |
    */

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(p.amount), 0)
        FROM payments p
        INNER JOIN orders o ON o.id = p.order_id
        WHERE p.status = 'Completed'
          AND DATE(p.created_at) = CURDATE()
    ");

    $todaySales = (float)$stmt->fetchColumn();


    /* Current month completed payment collection */
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(p.amount), 0)
        FROM payments p
        INNER JOIN orders o ON o.id = p.order_id
        WHERE p.status = 'Completed'
          AND YEAR(p.created_at) = YEAR(CURDATE())
          AND MONTH(p.created_at) = MONTH(CURDATE())
    ");

    $monthlySales = (float)$stmt->fetchColumn();


    /* Current month paid orders */
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT p.order_id)
        FROM payments p
        WHERE p.status = 'Completed'
          AND YEAR(p.created_at) = YEAR(CURDATE())
          AND MONTH(p.created_at) = MONTH(CURDATE())
    ");

    $monthlyOrders = (int)$stmt->fetchColumn();

    $averageOrder = $monthlyOrders > 0
        ? $monthlySales / $monthlyOrders
        : 0;


    /*
    |--------------------------------------------------------------------------
    | POPULAR FOOD
    |--------------------------------------------------------------------------
    |
    | Highest quantity first, then highest sales.
    | Uses payment records so newly placed paid orders appear even while
    | their order status is still Pending.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            fm.id,
            fm.name,
            fm.image,
            COALESCE(SUM(oi.quantity), 0) AS total_quantity,
            COALESCE(SUM(oi.subtotal), 0) AS total_sales
        FROM order_items oi
        INNER JOIN orders o
            ON o.id = oi.order_id
        INNER JOIN food_menu fm
            ON fm.id = oi.food_id
        INNER JOIN payments p
            ON p.order_id = o.id
           AND p.status = 'Completed'
        WHERE DATE(p.created_at) = CURDATE()
        GROUP BY fm.id, fm.name, fm.image
        ORDER BY total_quantity DESC, total_sales DESC
        LIMIT 4
    ");

    $popularFoods = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* If there are no paid sales today, show overall popular paid foods. */
    if (!$popularFoods) {

        $stmt = $pdo->query("
            SELECT
                fm.id,
                fm.name,
                fm.image,
                COALESCE(SUM(oi.quantity), 0) AS total_quantity,
                COALESCE(SUM(oi.subtotal), 0) AS total_sales
            FROM order_items oi
            INNER JOIN orders o
                ON o.id = oi.order_id
            INNER JOIN food_menu fm
                ON fm.id = oi.food_id
            INNER JOIN payments p
                ON p.order_id = o.id
               AND p.status = 'Completed'
            GROUP BY fm.id, fm.name, fm.image
            ORDER BY total_quantity DESC, total_sales DESC
            LIMIT 4
        ");

        $popularFoods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
    |--------------------------------------------------------------------------
    | LAST 12 MONTHS
    |--------------------------------------------------------------------------
    */

    for ($i = 11; $i >= 0; $i--) {

        $ts = strtotime("-$i months");
        $key = date('Y-m', $ts);

        $monthlySalesData[$key] = [
            'month' => date('M', $ts),
            'sales' => 0
        ];
    }


    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(p.created_at, '%Y-%m') AS month_key,
            COALESCE(SUM(p.amount), 0) AS sales
        FROM payments p
        INNER JOIN orders o
            ON o.id = p.order_id
        WHERE p.status = 'Completed'
          AND p.created_at >= DATE_SUB(
                DATE_FORMAT(CURDATE(), '%Y-%m-01'),
                INTERVAL 11 MONTH
              )
        GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
        ORDER BY month_key ASC
    ");


    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        if (isset($monthlySalesData[$row['month_key']])) {

            $monthlySalesData[$row['month_key']]['sales'] =
                (float)$row['sales'];
        }
    }


    foreach ($monthlySalesData as $month) {

        $chartTotal += $month['sales'];

        if ($month['sales'] > $chartBest) {

            $chartBest = $month['sales'];
            $chartBestLabel = $month['month'];
        }
    }


    $chartAverage = count($monthlySalesData) > 0
        ? $chartTotal / count($monthlySalesData)
        : 0;


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHODS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            payment_method,
            COALESCE(SUM(amount), 0) AS amount,
            COUNT(id) AS payment_count
        FROM payments
        WHERE status = 'Completed'
          AND YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
        GROUP BY payment_method
    ");


    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        if (isset($paymentStats[$row['payment_method']])) {

            $paymentStats[$row['payment_method']] =
                (float)$row['amount'];

            $paymentCounts[$row['payment_method']] =
                (int)$row['payment_count'];
        }
    }


    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
        WHERE status = 'Completed'
          AND YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ");

    $totalCollected = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            status,
            COUNT(id) AS total
        FROM payments
        GROUP BY status
    ");


    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        if (isset($paymentStatusCounts[$row['status']])) {

            $paymentStatusCounts[$row['status']] =
                (int)$row['total'];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RECENT ORDERS / ORDER SEARCH + PAGINATION
    |--------------------------------------------------------------------------
    | Supports:
    |   ?order_date=YYYY-MM-DD
    |   ?order_search=order-number
    |   ?order_page=1
    */

    $orderDate = trim($_GET['order_date'] ?? '');
    $orderSearch = trim($_GET['order_search'] ?? '');
    $orderPage = max(1, (int)($_GET['order_page'] ?? 1));
    $ordersPerPage = 10;

    /* Only accept a real YYYY-MM-DD date. */
    if ($orderDate !== '') {
        $dateObject = DateTime::createFromFormat('Y-m-d', $orderDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $orderDate) {
            $orderDate = '';
        }
    }

    $orderWhere = [];
    $orderParams = [];

    if ($orderDate !== '') {
        $orderWhere[] = 'DATE(o.created_at) = :order_date';
        $orderParams[':order_date'] = $orderDate;
    }

    if ($orderSearch !== '') {
        $orderWhere[] = '(o.order_number LIKE :order_search OR CAST(o.id AS CHAR) LIKE :order_search_id)';
        $orderParams[':order_search'] = '%' . $orderSearch . '%';
        $orderParams[':order_search_id'] = '%' . $orderSearch . '%';
    }

    $orderWhereSql = $orderWhere
        ? 'WHERE ' . implode(' AND ', $orderWhere)
        : '';

    /* Count matching orders before applying LIMIT/OFFSET. */
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM orders o
        $orderWhereSql
    ");

    foreach ($orderParams as $param => $value) {
        $countStmt->bindValue($param, $value, PDO::PARAM_STR);
    }

    $countStmt->execute();
    $filteredOrderCount = (int)$countStmt->fetchColumn();

    /*
     * FILTERED ORDER SUMMARY
     * These values always use the same date/order-number filters as the
     * Recent Orders table, so the totals change immediately after Search.
     */
    $filteredOrderSales = 0.0;
    $filteredOrderItems = 0;

    $summaryStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(o.total), 0) AS filtered_sales,
            COALESCE(SUM(
                (
                    SELECT COALESCE(SUM(oi2.quantity), 0)
                    FROM order_items oi2
                    WHERE oi2.order_id = o.id
                )
            ), 0) AS filtered_items
        FROM orders o
        $orderWhereSql
    ");

    foreach ($orderParams as $param => $value) {
        $summaryStmt->bindValue($param, $value, PDO::PARAM_STR);
    }

    $summaryStmt->execute();
    $filteredSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $filteredOrderSales = (float)($filteredSummary['filtered_sales'] ?? 0);
    $filteredOrderItems = (int)($filteredSummary['filtered_items'] ?? 0);

    $orderTotalPages = max(1, (int)ceil($filteredOrderCount / $ordersPerPage));

    if ($orderPage > $orderTotalPages) {
        $orderPage = $orderTotalPages;
    }

    $orderOffset = ($orderPage - 1) * $ordersPerPage;

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_number,
            o.order_type,
            o.status,
            o.subtotal,
            o.discount,
            o.tax,
            o.total,
            o.payment_status,
            o.user_id,
            o.created_at,
            COALESCE(u.full_name, u.username, 'Unknown User') AS created_by_name,
            COALESCE(u.username, '') AS created_by_username,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        fm.name,
                        ' × ',
                        oi.quantity
                    )
                    ORDER BY fm.name
                    SEPARATOR ', '
                ),
                'No items'
            ) AS items,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT p.payment_method
                    ORDER BY p.id DESC
                    SEPARATOR ', '
                ),
                'Not recorded'
            ) AS payment_method
        FROM orders o
        LEFT JOIN users u
            ON u.id = o.user_id
        LEFT JOIN order_items oi
            ON oi.order_id = o.id
        LEFT JOIN food_menu fm
            ON fm.id = oi.food_id
        LEFT JOIN payments p
            ON p.order_id = o.id
        $orderWhereSql
        GROUP BY
            o.id,
            o.order_number,
            o.order_type,
            o.status,
            o.subtotal,
            o.discount,
            o.tax,
            o.total,
            o.payment_status,
            o.user_id,
            o.created_at,
            u.full_name,
            u.username
        ORDER BY o.id DESC
        LIMIT :orders_limit OFFSET :orders_offset
    ");

    foreach ($orderParams as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':orders_limit', $ordersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':orders_offset', $orderOffset, PDO::PARAM_INT);
    $stmt->execute();

    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $dashboardError = $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| CHART ARRAYS
|--------------------------------------------------------------------------
*/

$chartLabels = [];
$chartValues = [];

foreach ($monthlySalesData as $month) {

    $chartLabels[] = $month['month'];
    $chartValues[] = (float)$month['sales'];
}


$paymentGrandTotal = array_sum($paymentStats);

$cashPercent = $paymentGrandTotal > 0
    ? ($paymentStats['Cash'] / $paymentGrandTotal) * 100
    : 0;

$momoPercent = $paymentGrandTotal > 0
    ? ($paymentStats['Mobile Money'] / $paymentGrandTotal) * 100
    : 0;

$cardPercent = $paymentGrandTotal > 0
    ? ($paymentStats['Card'] / $paymentGrandTotal) * 100
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Better End — Restaurant Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
    :root {
        --orange: #f58220;
        --orange-dark: #dc650e;
        --orange-light: #fff1e6;
        --cream: #fffaf6;
        --bg: #f7f6f4;
        --white: #fff;
        --text: #2d2925;
        --muted: #958d85;
        --border: #eae5e0;
        --green: #1fa463;
        --purple: #7655b5;
        --blue: #4c7ed2;
        --yellow: #e5a600;
        --sidebar-width: 250px;
    }

    * {
        box-sizing: border-box
    }

    body {
        margin: 0;
        min-height: 100vh;
        background: var(--bg);
        color: var(--text);
        font-family: "Poppins", sans-serif;
        font-size: 14px;
    }

    a {
        text-decoration: none
    }

    button,
    input {
        font-family: inherit
    }

    .app {
        min-height: 100vh
    }

    /* Existing sidebar is included from ../includes/sidebar.php */
    .main {
        margin-left: var(--sidebar-width);
        min-height: 100vh;
    }

    /* Header */
    .header {
        height: 82px;
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 0 30px;
        background: rgba(255, 255, 255, .98);
        border-bottom: 1px solid var(--border);
        box-shadow: 0 2px 18px rgba(43, 34, 27, .035);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .mobile-toggle {
        display: none;
        width: 40px;
        height: 40px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #fff;
        color: var(--text);
    }

    .search {
        flex: 1;
        max-width: 650px;
        height: 44px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-left: 14px;
        border: 1px solid #e8e2dc;
        border-radius: 8px;
        background: #faf9f7;
    }

    .search>i {
        color: #a49b93;
        font-size: 13px
    }

    .search input {
        min-width: 0;
        flex: 1;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--text);
        font-size: 11px;
    }

    .search button {
        height: 36px;
        margin-right: 4px;
        padding: 0 17px;
        border: 0;
        border-radius: 6px;
        color: #fff;
        background: var(--orange);
        font-size: 10px;
        font-weight: 700;
    }

    .header-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .header-action {
        min-height: 40px;
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 0 10px;
        border-radius: 7px;
        color: #655e58;
        font-size: 10px;
        font-weight: 600;
    }

    .header-action:hover {
        background: var(--orange-light);
        color: var(--orange-dark)
    }

    .cart-badge span:last-child {
        min-width: 19px;
        height: 19px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: var(--orange);
        font-size: 8px;
    }

    .profile-dropdown-wrap {
        position: relative
    }

    .profile-trigger {
        min-height: 48px;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 4px 8px 4px 5px;
        border: 1px solid transparent;
        border-radius: 9px;
        background: #fff;
        color: var(--text);
    }

    .profile-trigger:hover,
    .profile-trigger.open {
        border-color: #eee4dc;
        background: #fffaf6;
    }

    .profile-avatar {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: linear-gradient(135deg, var(--orange), #ed6c14);
        font-size: 11px;
        font-weight: 800;
    }

    .profile-avatar.large {
        width: 43px;
        height: 43px
    }

    .profile-copy {
        display: flex;
        flex-direction: column;
        align-items: flex-start
    }

    .profile-copy strong {
        font-size: 10px
    }

    .profile-copy small {
        margin-top: 2px;
        color: var(--muted);
        font-size: 8px
    }

    .profile-chevron {
        color: #999;
        font-size: 8px;
        transition: .2s
    }

    .profile-trigger.open .profile-chevron {
        transform: rotate(180deg)
    }

    .profile-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 235px;
        display: none;
        padding: 8px;
        border: 1px solid var(--border);
        border-radius: 11px;
        background: #fff;
        box-shadow: 0 20px 45px rgba(42, 31, 22, .13);
    }

    .profile-dropdown.show {
        display: block
    }

    .profile-dropdown-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px
    }

    .profile-dropdown-head strong {
        display: block;
        font-size: 10px
    }

    .profile-dropdown-head small {
        display: block;
        margin-top: 3px;
        color: var(--muted);
        font-size: 8px
    }

    .profile-divider {
        height: 1px;
        margin: 5px 2px;
        background: #f0ece8
    }

    .profile-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 38px;
        padding: 0 10px;
        border-radius: 7px;
        color: #665e57;
        font-size: 9px;
        font-weight: 600;
    }

    .profile-menu-item i {
        width: 17px;
        color: #999;
        text-align: center
    }

    .profile-menu-item:hover {
        color: var(--orange-dark);
        background: var(--orange-light)
    }

    .logout-item {
        color: #d64747
    }

    /* Content */
    .content {
        max-width: 1600px;
        margin: 0 auto;
        padding: 25px 30px 48px;
    }

    .dashboard-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
    }

    .top-heading {
        margin-bottom: 14px
    }

    .eyebrow {
        display: block;
        margin-bottom: 4px;
        color: var(--orange);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1px;
    }

    .dashboard-heading h2 {
        margin: 0;
        font-size: 23px;
        font-weight: 800;
        letter-spacing: -.6px;
    }

    .dashboard-heading p {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 11px;
    }

    .heading-actions {
        display: flex;
        gap: 8px
    }

    .date-filter,
    .new-order-btn {
        height: 40px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 12px;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: #fff;
        color: #625b54;
        font-size: 9px;
        font-weight: 700;
    }

    .new-order-btn {
        border-color: var(--orange);
        color: #fff;
        background: var(--orange);
    }

    /* Sales cards */
    .metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }

    /* Colorful modern KPI cards */
    .metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .metric {
        position: relative;
        overflow: hidden;
        min-height: 116px;
        padding: 15px 16px 14px;
        border: 0;
        border-radius: 13px;
        color: #fff;
        box-shadow: 0 8px 22px rgba(39, 29, 22, .10);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .metric:hover {
        transform: translateY(-3px);
        box-shadow: 0 13px 28px rgba(39, 29, 22, .15);
    }

    .metric::before {
        content: "";
        position: absolute;
        width: 125px;
        height: 125px;
        right: -48px;
        bottom: -68px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .13);
    }

    .metric::after {
        content: "";
        position: absolute;
        width: 75px;
        height: 75px;
        right: 22px;
        top: -43px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
    }

    /* Individual card colors */
    .metric:nth-child(1) {
        background: linear-gradient(135deg, #f58220 0%, #ed6a12 100%);
    }

    .metric:nth-child(2) {
        background: linear-gradient(135deg, #21a66a 0%, #168652 100%);
    }

    .metric:nth-child(3) {
        background: linear-gradient(135deg, #7655b5 0%, #5d4096 100%);
    }

    .metric:nth-child(4) {
        background: linear-gradient(135deg, #4c7ed2 0%, #315eae 100%);
    }

    .metric-top {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, .90);
        font-size: 9px;
        font-weight: 700;
    }

    .metric-icon {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        flex: 0 0 32px;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 8px;
        color: #fff;
        background: rgba(255, 255, 255, .15);
        font-size: 11px;
        backdrop-filter: blur(4px);
    }

    .metric-icon.green,
    .metric-icon.purple,
    .metric-icon.blue {
        color: #fff;
        background: rgba(255, 255, 255, .15);
    }

    .metric>strong {
        position: relative;
        z-index: 2;
        display: block;
        margin-top: 12px;
        color: #fff;
        font-size: 21px;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.5px;
    }

    .metric>small {
        position: relative;
        z-index: 2;
        display: block;
        margin-top: 5px;
        color: rgba(255, 255, 255, .78);
        font-size: 7.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .metric>small i {
        color: #fff;
        margin-right: 2px;
    }

    @media(max-width:1200px) {
        .metrics {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    @media(max-width:700px) {
        .metrics {
            grid-template-columns: 1fr
        }

        .metric {
            min-height: 108px
        }
    }

    /* Hero + popular food */
    .hero-food-row {
        display: grid;
        grid-template-columns: minmax(0, 1.48fr) minmax(360px, .82fr);
        gap: 18px;
        margin-top: 4px;
        margin-bottom: 21px;
    }

    .hero {
        position: relative;
        min-height: 385px;
        overflow: hidden;
        border-radius: 13px;
        background:
            linear-gradient(90deg, rgba(238, 105, 16, .96) 0%, rgba(245, 130, 32, .78) 37%, rgba(245, 130, 32, .08) 76%),
            url("https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1400&q=88") center/cover;
        box-shadow: 0 14px 34px rgba(45, 32, 23, .08);
    }

    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 560px;
        padding: 54px 48px;
        color: #fff;
    }

    .hero-kicker {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1.2px;
        opacity: .9;
    }

    .hero h1 {
        margin: 12px 0 15px;
        font-size: 43px;
        line-height: 1.06;
        font-weight: 800;
        letter-spacing: -1.4px;
    }

    .hero p {
        max-width: 475px;
        margin: 0;
        font-size: 11px;
        line-height: 1.75;
        opacity: .92;
    }

    .hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 24px;
        padding: 12px 18px;
        border-radius: 6px;
        color: #2d2925;
        background: #fff;
        font-size: 9px;
        font-weight: 800;
        box-shadow: 0 8px 20px rgba(0, 0, 0, .1);
    }

    /* Popular food lives directly to the right of hero */
    .popular-panel {
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 9px 28px rgba(40, 30, 22, .055);
    }

    .popular-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        padding: 18px 18px 13px;
        border-bottom: 1px solid #f0ece8;
    }

    .popular-head h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
    }

    .popular-head p {
        margin: 4px 0 0;
        color: var(--muted);
        font-size: 8px;
    }

    .popular-head a {
        color: var(--orange-dark);
        font-size: 8px;
        font-weight: 700;
    }

    .popular-list {
        padding: 10px
    }

    .popular-item {
        display: grid;
        grid-template-columns: 76px minmax(0, 1fr) auto;
        align-items: center;
        gap: 11px;
        padding: 9px;
        border-radius: 9px;
        transition: .18s;
    }

    .popular-item:hover {
        background: var(--cream)
    }

    .popular-image {
        width: 76px;
        height: 68px;
        border-radius: 8px;
        background-position: center;
        background-size: cover;
    }

    .popular-image.jollof {
        background-image: url("https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=500&q=85");
    }

    .popular-image.fried {
        background-image: url("https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=500&q=85");
    }

    .popular-image.chicken {
        background-image: url("https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=500&q=85");
    }

    .popular-image.tilapia {
        background-image: url("https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?auto=format&fit=crop&w=500&q=85");
    }

    .popular-info strong {
        display: block;
        color: #39332e;
        font-size: 10px;
        font-weight: 800;
    }

    .popular-info small {
        display: block;
        margin-top: 4px;
        color: #9b938c;
        font-size: 7px;
        line-height: 1.45;
    }

    .popular-rating {
        margin-top: 6px;
        color: #e7a019;
        font-size: 7px;
    }

    .popular-sales {
        text-align: right;
    }

    .popular-sales strong {
        display: block;
        color: var(--orange-dark);
        font-size: 10px;
    }

    .popular-sales small {
        display: block;
        margin-top: 3px;
        color: #aaa29b;
        font-size: 7px;
    }

    /* Analytics */
    .dashboard-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(330px, .75fr);
        gap: 15px;
        margin-bottom: 23px;
    }

    .panel {
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 11px;
        background: #fff;
        box-shadow: 0 7px 23px rgba(40, 30, 22, .04);
    }

    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 17px 19px 12px;
        border-bottom: 1px solid #f0ece8;
    }

    .panel-header h3 {
        margin: 0;
        font-size: 14px;
        font-weight: 800
    }

    .panel-header small {
        display: block;
        margin-top: 4px;
        color: var(--muted);
        font-size: 8px
    }

    .panel-header>span {
        color: var(--orange-dark);
        font-size: 10px;
        font-weight: 800
    }

    .chart-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        padding: 13px 18px;
    }

    .chart-summary>div {
        padding: 9px 10px;
        border: 1px solid #f0e9e3;
        border-radius: 7px;
        background: #fffaf6;
    }

    .chart-summary small {
        display: block;
        color: #a39a92;
        font-size: 7px
    }

    .chart-summary strong {
        display: block;
        margin-top: 3px;
        font-size: 9px
    }

    .sales-chart {
        padding: 0 18px 15px
    }

    .chart-area {
        position: relative;
        height: 190px
    }

    .chart-svg {
        width: 100%;
        height: 165px;
        display: block
    }

    .sales-fill {
        opacity: .95
    }

    .sales-line {
        fill: none;
        stroke: var(--orange);
        stroke-width: 4;
        stroke-linecap: round
    }

    .chart-axis {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        color: #a8a098;
        font-size: 7px;
        text-align: center;
    }

    /* Payment panel */
    .payment-total {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 16px 18px;
        padding: 14px;
        border: 1px solid #f0e5dc;
        border-radius: 8px;
        background: linear-gradient(135deg, #fff8f2, #fff);
    }

    .payment-total small {
        display: block;
        color: var(--muted);
        font-size: 8px
    }

    .payment-total strong {
        display: block;
        margin-top: 3px;
        font-size: 20px
    }

    .growth {
        padding: 5px 8px;
        border-radius: 20px;
        color: var(--green);
        background: #eaf8f0;
        font-size: 8px;
        font-weight: 800;
    }

    .payment-bars {
        padding: 0 18px
    }

    .payment-row {
        margin-bottom: 15px
    }

    .payment-label {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 6px
    }

    .payment-label strong {
        font-size: 9px
    }

    .payment-label small {
        margin-left: auto;
        color: #aaa19a;
        font-size: 7px
    }

    .payment-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%
    }

    .cash {
        background: var(--orange)
    }

    .momo {
        background: #e2a300
    }

    .card {
        background: var(--purple)
    }

    .payment-track {
        height: 6px;
        border-radius: 20px;
        background: #eeeae7;
        overflow: hidden
    }

    .payment-track span {
        display: block;
        height: 100%;
        border-radius: 20px;
        background: linear-gradient(90deg, var(--orange), #ffb277)
    }

    .payment-row:nth-child(2) .payment-track span {
        background: linear-gradient(90deg, #dfa000, #f4c65c)
    }

    .payment-row:nth-child(3) .payment-track span {
        background: linear-gradient(90deg, #7655b5, #a487dc)
    }

    .payment-row>b {
        display: block;
        margin-top: 4px;
        text-align: right;
        font-size: 8px;
        color: #544d47
    }

    .payment-note {
        margin: 5px 18px 17px;
        padding-top: 12px;
        border-top: 1px solid #f0ece8;
        color: #918981;
        font-size: 8px;
    }

    .payment-note i {
        margin-right: 5px;
        color: var(--green)
    }

    /* Snapshot */
    .snapshot {
        margin-bottom: 23px
    }

    .snapshot-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .snapshot-heading h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800
    }

    .period {
        padding: 6px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #8f8780;
        background: #fff;
        font-size: 8px;
        font-weight: 700;
    }

    .snapshot-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 13px;
    }

    .snapshot-card {
        display: flex;
        align-items: center;
        gap: 11px;
        min-height: 86px;
        padding: 14px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(45, 32, 23, .035);
    }

    .snapshot-icon {
        width: 39px;
        height: 39px;
        flex: 0 0 39px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        font-size: 12px;
    }

    .snapshot-icon.orange {
        color: var(--orange);
        background: var(--orange-light)
    }

    .snapshot-icon.green {
        color: var(--green);
        background: #eaf8f0
    }

    .snapshot-icon.purple {
        color: var(--purple);
        background: #f2edff
    }

    .snapshot-icon.blue {
        color: var(--blue);
        background: #edf4ff
    }

    .snapshot-card small {
        display: block;
        color: #9c948d;
        font-size: 8px
    }

    .snapshot-card strong {
        display: block;
        margin-top: 3px;
        font-size: 13px
    }

    .positive {
        display: block;
        margin-top: 3px;
        color: var(--green);
        font-size: 7px;
        font-weight: 800
    }

    /* Recent Orders */
    .recent-orders-section {
        margin-bottom: 23px;
    }

    .recent-orders-panel {
        width: 100%;
    }

    .recent-view-link {
        color: var(--orange-dark);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .recent-orders-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .recent-orders-table {
        width: 100%;
        min-width: 930px;
        border-collapse: collapse;
    }

    .recent-orders-table th {
        padding: 13px 16px;
        text-align: left;
        border-bottom: 1px solid #eee8e2;
        color: #938a82;
        background: #fffaf6;
        font-size: 7px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .55px;
        white-space: nowrap;
    }

    .recent-orders-table td {
        padding: 13px 16px;
        border-bottom: 1px solid #f1ece8;
        color: #514a44;
        font-size: 8px;
        vertical-align: middle;
    }

    .recent-orders-table tbody tr:hover {
        background: #fffaf6;
    }

    .recent-orders-table td:first-child strong {
        display: block;
        color: #2f2924;
        font-size: 9px;
        font-weight: 800;
    }

    .recent-orders-table td small {
        display: block;
        margin-top: 3px;
        color: #aaa19a;
        font-size: 6.5px;
    }

    .items-cell {
        max-width: 250px;
        line-height: 1.45;
    }

    .type-badge,
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 7px;
        border-radius: 20px;
        white-space: nowrap;
        font-size: 7px;
        font-weight: 800;
    }

    .type-badge {
        color: #756c64;
        background: #f5f2ef;
    }

    .status-badge.pending {
        color: #a56a00;
        background: #fff5d9;
    }

    .status-badge.preparing {
        color: #8a5a00;
        background: #fff0cc;
    }

    .status-badge.ready {
        color: #176d9b;
        background: #e8f5fc;
    }

    .status-badge.completed,
    .status-badge.paid {
        color: #167448;
        background: #e9f8f0;
    }

    .status-badge.unpaid {
        color: #a64242;
        background: #fff0f0;
    }

    .payment-method {
        margin-top: 4px;
        color: #999089;
        font-size: 6.5px;
    }

    .order-total {
        display: block;
        color: var(--orange-dark);
        font-size: 9px;
        font-weight: 800;
    }

    .order-date {
        display: block;
        color: #514a44;
        white-space: nowrap;
        font-size: 7.5px;
        font-weight: 700;
    }

    .empty-orders {
        padding: 45px 20px !important;
        text-align: center !important;
    }

    .empty-orders i {
        display: block;
        margin-bottom: 10px;
        color: var(--orange);
        font-size: 24px;
    }

    .empty-orders strong {
        display: block;
        color: #4b443e;
        font-size: 10px;
    }

    .empty-orders span {
        display: block;
        margin-top: 5px;
        color: #9d958e;
        font-size: 7px;
    }

    /* Responsive */
    @media(max-width:1200px) {
        .hero-food-row {
            grid-template-columns: 1fr
        }

        .popular-panel {
            min-height: auto
        }

        .popular-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr)
        }

        .metrics {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    @media(max-width:992px) {
        .main {
            margin-left: 0
        }

        .mobile-toggle {
            display: grid;
            place-items: center
        }

        .header {
            padding: 0 18px
        }

        .header-actions .header-action span:not(:last-child) {
            display: none
        }

        .dashboard-grid {
            grid-template-columns: 1fr
        }

        .snapshot-grid {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    @media(max-width:700px) {
        .content {
            padding: 21px 15px 40px
        }

        .recent-orders-heading {
            align-items: flex-start;
        }

        .recent-order-tools {
            width: 100%;
            align-items: stretch;
            justify-content: flex-start;
        }

        .order-filter-form {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
        }

        .order-search-box,
        .order-date-box {
            width: auto;
        }

        .order-filter-btn {
            justify-content: center;
        }

        .recent-order-tools>.recent-view-link {
            display: none;
        }

        .orders-pagination {
            align-items: flex-start;
            flex-direction: column;
        }

        .pagination-controls {
            width: 100%;
            justify-content: center;
        }

        .header {
            height: 72px
        }

        .search {
            display: none
        }

        .header-actions {
            margin-left: auto
        }

        .top-heading {
            align-items: flex-start;
            flex-direction: column
        }

        .heading-actions {
            width: 100%
        }

        .date-filter,
        .new-order-btn {
            flex: 1;
            justify-content: center
        }

        .metrics {
            grid-template-columns: 1fr
        }

        .hero-content {
            padding: 42px 27px
        }

        .hero h1 {
            font-size: 34px
        }

        .popular-list {
            grid-template-columns: 1fr
        }

        .chart-summary {
            grid-template-columns: repeat(2, 1fr)
        }

        .snapshot-grid {
            grid-template-columns: 1fr
        }

        .chart-axis {
            font-size: 6px
        }
    }

    /* =========================================
           PREMIUM DARK RESTAURANT OVERVIEW
        ========================================= */
    .top-heading {
        position: relative;
        overflow: hidden;
        min-height: 145px;
        margin-bottom: 20px;
        padding: 30px 32px;
        align-items: center;

        background:
            radial-gradient(circle at 84% 12%, rgba(245, 130, 32, .18), transparent 28%),
            radial-gradient(circle at 100% 100%, rgba(245, 130, 32, .08), transparent 32%),
            linear-gradient(135deg, #070707 0%, #141414 58%, #0a0a0a 100%);

        border: 1px solid #272727;
        border-radius: 14px;

        box-shadow:
            0 14px 34px rgba(0, 0, 0, .15),
            inset 0 1px 0 rgba(255, 255, 255, .04);
    }

    .top-heading::before {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        right: 10%;
        top: -170px;
        border-radius: 50%;
        background: rgba(245, 130, 32, .09);
        filter: blur(50px);
        pointer-events: none;
    }

    .top-heading::after {
        content: "";
        position: absolute;
        left: 32px;
        right: 32px;
        bottom: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(245, 130, 32, .38), transparent);
        pointer-events: none;
    }

    .top-heading>* {
        position: relative;
        z-index: 2;
    }

    .top-heading .eyebrow {
        margin-bottom: 7px;
        color: #f58220;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.6px;
    }

    .top-heading h2 {
        margin: 0;
        color: #fff;
        font-size: 30px;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.9px;
    }

    .top-heading p {
        margin: 8px 0 0;
        color: #a9a9a9;
        font-size: 11px;
        line-height: 1.6;
    }

    .top-heading .heading-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .top-heading .date-filter {
        height: 46px;
        padding: 0 17px;
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        color: #ededed;
        background: #1b1b1b;
        font-size: 10px;
        font-weight: 700;
        transition: .2s ease;
    }

    .top-heading .date-filter:hover {
        border-color: #f58220;
        background: #242424;
        color: #fff;
    }

    .top-heading .new-order-btn {
        height: 46px;
        padding: 0 19px;
        border: 1px solid #f58220;
        border-radius: 8px;
        color: #fff;
        background: #f58220;
        font-size: 10px;
        font-weight: 800;
        box-shadow: 0 8px 20px rgba(245, 130, 32, .24);
        transition: .2s ease;
    }

    .top-heading .new-order-btn:hover {
        border-color: #ff9138;
        background: #ff9138;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 11px 25px rgba(245, 130, 32, .34);
    }

    .top-heading .date-filter i,
    .top-heading .new-order-btn i {
        font-size: 10px;
    }

    @media(max-width:700px) {
        .top-heading {
            min-height: auto;
            padding: 24px 21px;
            align-items: flex-start;
            flex-direction: column;
            gap: 20px;
        }

        .top-heading h2 {
            font-size: 25px
        }

        .top-heading p {
            font-size: 10px
        }

        .top-heading .heading-actions {
            width: 100%
        }

        .top-heading .date-filter,
        .top-heading .new-order-btn {
            flex: 1;
            justify-content: center;
        }
    }

    /* =========================================================
       PROFESSIONAL TYPOGRAPHY UPGRADE
       Keeps the existing dashboard design but improves
       readability of Recent Orders and This Month at a Glance.
       ========================================================= */

    body {
        font-size: 15px;
    }

    .dashboard-heading p {
        font-size: 13px;
    }

    .eyebrow {
        font-size: 11px;
        letter-spacing: 1.5px;
    }

    /* KPI cards */
    .metric-top {
        font-size: 11px;
    }

    .metric>strong {
        font-size: 25px;
    }

    .metric>small {
        font-size: 10px;
    }

    /* Popular food */
    .popular-head h3 {
        font-size: 17px;
    }

    .popular-head p {
        font-size: 10px;
    }

    .popular-head a {
        font-size: 10px;
    }

    .popular-info strong {
        font-size: 12px;
    }

    .popular-info small {
        font-size: 9px;
    }

    .popular-rating {
        font-size: 9px;
    }

    .popular-sales strong {
        font-size: 12px;
    }

    .popular-sales small {
        font-size: 9px;
    }

    /* Monthly Sales Performance */
    .panel-header h3 {
        font-size: 16px;
    }

    .panel-header small {
        font-size: 10px;
    }

    .panel-header>span {
        font-size: 12px;
    }

    .chart-summary small {
        font-size: 9px;
    }

    .chart-summary strong {
        font-size: 11px;
    }

    .chart-axis {
        font-size: 9px;
    }

    /* Payment Methods */
    .payment-total small {
        font-size: 10px;
    }

    .payment-total strong {
        font-size: 22px;
    }

    .growth {
        font-size: 9px;
    }

    .payment-label strong {
        font-size: 11px;
    }

    .payment-label small {
        font-size: 9px;
    }

    .payment-row>b {
        font-size: 10px;
    }

    .payment-note {
        font-size: 9px;
    }

    /* Section headings */
    .snapshot-heading h2 {
        font-size: 21px;
        letter-spacing: -.3px;
    }

    .period {
        font-size: 10px;
    }

    /* =========================================================
       RECENT ORDERS — larger, clearer, professional table
       ========================================================= */

    .recent-orders-section {
        margin-bottom: 28px;
    }

    .recent-orders-table-wrap {
        padding: 2px 0;
    }

    .recent-orders-table {
        min-width: 1080px;
    }

    .recent-orders-table th {
        padding: 15px 18px;
        font-size: 10px;
        letter-spacing: .7px;
    }

    .recent-orders-table td {
        padding: 16px 18px;
        font-size: 11px;
        line-height: 1.5;
    }

    .recent-orders-table td:first-child strong {
        font-size: 12px;
    }

    .recent-orders-table td small {
        margin-top: 4px;
        font-size: 9px;
    }

    .items-cell {
        max-width: 290px;
        font-size: 11px;
        line-height: 1.55;
    }

    .type-badge,
    .status-badge {
        padding: 7px 10px;
        font-size: 9px;
    }

    .payment-method {
        font-size: 9px;
    }

    .order-total {
        font-size: 12px;
    }

    .order-date {
        font-size: 10px;
    }

    /* =========================================================
       RECENT ORDERS — SEARCH, DATE FILTER & PAGINATION
       ========================================================= */

    .recent-orders-heading {
        align-items: center;
        gap: 22px;
    }

    .recent-results {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 10px;
    }

    .recent-order-tools {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 9px;
        flex-wrap: wrap;
    }

    .order-filter-form {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
    }

    .order-search-box,
    .order-date-box {
        height: 43px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 12px;
        border: 1px solid #e4ded8;
        border-radius: 9px;
        background: #fff;
        transition: .2s ease;
    }

    .order-search-box {
        width: 220px;
    }

    .order-date-box {
        width: 165px;
    }

    .order-search-box:focus-within,
    .order-date-box:focus-within {
        border-color: var(--orange);
        box-shadow: 0 0 0 3px rgba(245, 130, 32, .09);
    }

    .order-search-box i,
    .order-date-box i {
        color: #a29a92;
        font-size: 11px;
    }

    .order-search-box input,
    .order-date-box input {
        width: 100%;
        min-width: 0;
        border: 0;
        outline: 0;
        background: transparent;
        color: #4b443e;
        font-family: inherit;
        font-size: 10px;
        font-weight: 600;
    }

    .order-date-box input {
        cursor: pointer;
    }

    .order-filter-btn {
        height: 43px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 0 14px;
        border: 1px solid var(--orange);
        border-radius: 9px;
        color: #fff;
        background: var(--orange);
        font-family: inherit;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
        transition: .2s ease;
    }

    .order-filter-btn:hover {
        background: var(--orange-dark);
        border-color: var(--orange-dark);
        transform: translateY(-1px);
    }

    .order-clear-btn {
        width: 43px;
        height: 43px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e4ded8;
        border-radius: 9px;
        color: #81786f;
        background: #fff;
        font-size: 11px;
        transition: .2s ease;
    }

    .order-clear-btn:hover {
        color: var(--orange-dark);
        border-color: #f2b37d;
        background: var(--orange-light);
    }

    .filtered-order-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        padding: 14px 18px 4px;
        background: #fff;
    }

    .filtered-summary-card {
        display: flex;
        align-items: center;
        gap: 11px;
        min-height: 64px;
        padding: 10px 12px;
        border: 1px solid #eee7e1;
        border-radius: 10px;
        background: linear-gradient(135deg, #fffaf6, #fff);
    }

    .filtered-summary-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        color: var(--orange-dark);
        background: var(--orange-light);
        font-size: 12px;
    }

    .filtered-summary-card:nth-child(2) .filtered-summary-icon {
        color: var(--green);
        background: #eaf8f0;
    }

    .filtered-summary-card:nth-child(3) .filtered-summary-icon {
        color: var(--purple);
        background: #f2edff;
    }

    .filtered-summary-card small {
        display: block;
        color: #999089;
        font-size: 8px;
        font-weight: 600;
    }

    .filtered-summary-card strong {
        display: block;
        margin-top: 2px;
        color: #302a25;
        font-size: 16px;
        line-height: 1.15;
        font-weight: 800;
    }

    .filtered-summary-card.sales strong {
        color: var(--orange-dark);
    }

    @media(max-width:700px) {
        .filtered-order-summary {
            grid-template-columns: 1fr;
            padding: 12px 12px 4px;
        }
    }

    .orders-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 15px 18px;
        border-top: 1px solid #eee8e2;
        background: #fff;
    }

    .pagination-summary {
        color: #938a82;
        font-size: 10px;
    }

    .pagination-summary strong {
        color: #514a44;
        font-weight: 800;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .page-btn {
        min-width: 34px;
        height: 34px;
        padding: 0 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e5dfd9;
        border-radius: 8px;
        color: #6d655e;
        background: #fff;
        font-size: 10px;
        font-weight: 800;
        text-decoration: none;
        transition: .18s ease;
    }

    .page-btn:hover {
        color: var(--orange-dark);
        border-color: #f2b37d;
        background: var(--orange-light);
    }

    .page-btn.active {
        color: #fff;
        border-color: var(--orange);
        background: var(--orange);
        box-shadow: 0 5px 13px rgba(245, 130, 32, .20);
    }

    .page-btn.disabled {
        opacity: .38;
        cursor: not-allowed;
        pointer-events: none;
    }

    .page-arrow {
        font-size: 9px;
    }

    /* =========================================================
       THIS MONTH AT A GLANCE — larger and easier to scan
       ========================================================= */

    .snapshot {
        margin-bottom: 30px;
    }

    .snapshot-heading {
        margin-bottom: 15px;
    }

    .snapshot-card {
        min-height: 105px;
        padding: 17px;
        gap: 14px;
    }

    .snapshot-icon {
        width: 46px;
        height: 46px;
        flex-basis: 46px;
        font-size: 14px;
    }

    .snapshot-card small {
        font-size: 10px;
    }

    .snapshot-card strong {
        margin-top: 4px;
        font-size: 17px;
    }

    .positive {
        margin-top: 4px;
        font-size: 9px;
    }

    /* General panel spacing/readability */
    .panel-header {
        padding: 19px 21px 14px;
    }

    .chart-summary {
        padding: 15px 20px;
        gap: 10px;
    }

    .chart-summary>div {
        padding: 11px 12px;
    }

    /* Make the dashboard feel less cramped on desktop */
    @media (min-width: 1201px) {
        .content {
            padding-left: 34px;
            padding-right: 34px;
        }

        .recent-orders-table {
            min-width: 100%;
        }
    }

    @media (max-width: 700px) {
        .recent-orders-table th {
            font-size: 9px;
        }

        .recent-orders-table td {
            font-size: 10px;
        }

        .snapshot-heading h2 {
            font-size: 19px;
        }

        .snapshot-card strong {
            font-size: 16px;
        }
    }


    .snapshot-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .snapshot-card {
        width: 100%;
        min-height: 118px;
        padding: 20px 22px;
        gap: 16px;
    }

    .snapshot-icon {
        width: 52px;
        height: 52px;
        flex-basis: 52px;
        font-size: 16px;
        border-radius: 14px;
    }

    .snapshot-card small {
        font-size: 11px;
    }

    .snapshot-card strong {
        margin-top: 5px;
        font-size: 20px;
    }

    .positive {
        margin-top: 5px;
        font-size: 10px;
    }

    @media (max-width: 900px) {
        .snapshot-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px) {
        .snapshot-grid {
            grid-template-columns: 1fr;
        }
    }


    .order-action {
        text-align: center;
        white-space: nowrap;
    }

    .delete-order-btn {
        height: 34px;
        padding: 0 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1px solid #f0caca;
        border-radius: 8px;
        background: #fff;
        color: #b42318;
        font-family: inherit;
        font-size: 9px;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
    }

    .delete-order-btn:hover {
        color: #fff;
        border-color: #b42318;
        background: #b42318;
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(180, 35, 24, .18);
    }

    .delete-confirm-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(25, 20, 16, .55);
        backdrop-filter: blur(4px);
    }

    .delete-confirm-overlay.show {
        display: flex;
    }

    .delete-confirm-modal {
        width: min(410px, 100%);
        background: #fff;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 25px 70px rgba(0, 0, 0, .25);
        text-align: center;
    }

    .delete-confirm-icon {
        width: 58px;
        height: 58px;
        margin: 0 auto 14px;
        display: grid;
        place-items: center;
        border-radius: 17px;
        background: #fff0ef;
        color: #b42318;
        font-size: 21px;
    }

    .delete-confirm-modal h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
    }

    .delete-confirm-modal p {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 10px;
        line-height: 1.6;
    }

    .delete-confirm-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
        margin-top: 20px;
    }

    .delete-confirm-actions button {
        height: 43px;
        border-radius: 9px;
        font-family: inherit;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
    }

    .delete-cancel {
        border: 1px solid var(--border);
        background: #fff;
        color: #756c64;
    }

    .delete-submit {
        border: 0;
        background: #b42318;
        color: #fff;
    }

    .delete-submit:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    @media(max-width:700px) {
        .delete-order-btn span {
            display: none;
        }

        .delete-order-btn {
            width: 32px;
            padding: 0;
        }
    }


    /* =========================================================
       DASHBOARD PERMISSION NOTICE
       ========================================================= */
    .dashboard-notice {
        position: fixed;
        top: 86px;
        right: 24px;
        z-index: 12000;
        width: min(390px, calc(100vw - 32px));
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 15px 16px;
        border: 1px solid #f1d7b9;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 15px 40px rgba(40, 30, 22, .16);
        animation: noticeIn .2s ease;
    }

    .dashboard-notice.warning .notice-icon {
        background: #fff4e8;
        color: var(--orange-dark);
    }

    .notice-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: grid;
        place-items: center;
        border-radius: 10px;
    }

    .notice-content strong {
        display: block;
        color: var(--text);
        font-size: 11px;
        font-weight: 800;
    }

    .notice-content span {
        display: block;
        margin-top: 3px;
        color: var(--muted);
        font-size: 9px;
        line-height: 1.5;
    }

    .notice-close {
        margin-left: auto;
        border: 0;
        background: transparent;
        color: #988f87;
        cursor: pointer;
        font-size: 13px;
    }

    @keyframes noticeIn {
        from {
            opacity: 0;
            transform: translateY(-8px) translateX(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0) translateX(0);
        }
    }

    @media (max-width: 600px) {
        .dashboard-notice {
            top: 72px;
            right: 16px;
        }
    }


    /* =========================================================
       CUSTOM ORDER DELETION NOTICE
       ========================================================= */
    .custom-delete-notice {
        position: fixed;
        top: 88px;
        right: 24px;
        z-index: 15000;
        width: min(410px, calc(100vw - 32px));
        display: none;
        align-items: flex-start;
        gap: 13px;
        padding: 16px;
        border: 1px solid #eadfd6;
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 18px 45px rgba(40, 30, 22, .16);
    }

    .custom-delete-notice.show {
        display: flex;
        animation: deleteNoticeIn .22s ease;
    }

    .custom-delete-notice.success {
        border-color: #e4e0d9;
    }

    .custom-delete-notice.warning {
        border-color: #f1d5b4;
    }

    .delete-notice-icon {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: var(--orange-light);
        color: var(--orange-dark);
    }

    .custom-delete-notice.warning .delete-notice-icon {
        background: #fff4e8;
    }

    .delete-notice-text strong {
        display: block;
        font-size: 11px;
        font-weight: 800;
        color: var(--text);
    }

    .delete-notice-text span {
        display: block;
        margin-top: 4px;
        font-size: 9px;
        line-height: 1.55;
        color: var(--muted);
    }

    .delete-notice-close {
        margin-left: auto;
        border: 0;
        background: transparent;
        color: #988f87;
        cursor: pointer;
        font-size: 13px;
    }

    @keyframes deleteNoticeIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }


    /* =========================================================
       MONTHLY SALES PERFORMANCE
       ========================================================= */
    .monthly-sales-row,
    .sales-month-row {
        min-height: 54px;
        border-bottom: 1px solid #f0ebe6;
        transition: background .16s ease, transform .16s ease;
    }

    .monthly-sales-row:hover,
    .sales-month-row:hover {
        background: #fffaf6;
    }

    .monthly-sales-row td,
    .sales-month-row td {
        padding: 13px 15px;
        font-size: 11px;
        vertical-align: middle;
    }

    .monthly-sales-row td:first-child,
    .sales-month-row td:first-child {
        font-weight: 800;
        color: var(--text);
    }

    .monthly-sales-row td:last-child,
    .sales-month-row td:last-child {
        font-weight: 800;
        color: var(--orange-dark);
        text-align: right;
    }

    .sales-month-label {
        display: flex;
        align-items: center;
        gap: 9px;
        font-weight: 800;
    }

    .sales-month-dot {
        width: 8px;
        height: 8px;
        flex: 0 0 8px;
        border-radius: 50%;
        background: var(--orange);
        box-shadow: 0 0 0 4px var(--orange-light);
    }

    .sales-amount {
        font-size: 12px;
        font-weight: 800;
        color: var(--orange-dark);
    }

    .sales-progress {
        height: 7px;
        width: min(180px, 100%);
        overflow: hidden;
        border-radius: 999px;
        background: #f2eee9;
    }

    .sales-progress>span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--orange), var(--orange-dark));
    }

    .sales-table-wrap {
        overflow-x: auto;
    }

    .sales-table-wrap table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .sales-table-wrap thead th {
        padding: 11px 15px;
        background: #faf8f6;
        color: #827970;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #e9e3dd;
    }

    .sales-table-wrap thead th:last-child {
        text-align: right;
    }


    /* =========================================================
       MONTHLY SALES PERFORMANCE — CLEAN 12-MONTH VIEW
       ========================================================= */
    .monthly-sales-section {
        margin-top: 24px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 12px 34px rgba(40, 30, 22, .055);
    }

    .monthly-sales-section .section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px;
        border-bottom: 1px solid #eeeae6;
    }

    .monthly-sales-section .section-head h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: var(--text);
    }

    .monthly-sales-section .section-head p {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 10px;
    }

    .monthly-sales-total {
        padding: 8px 12px;
        border-radius: 999px;
        background: var(--orange-light);
        color: var(--orange-dark);
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    .monthly-sales-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .monthly-sales-table thead th {
        padding: 12px 18px;
        background: #faf8f6;
        border-bottom: 1px solid #e9e3dd;
        color: #827970;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        text-align: left;
    }

    .monthly-sales-table thead th:last-child {
        text-align: right;
    }

    .monthly-sales-table tbody tr {
        transition: background .16s ease;
    }

    .monthly-sales-table tbody tr:hover {
        background: #fffaf6;
    }

    .monthly-sales-table tbody td {
        padding: 14px 18px;
        border-bottom: 1px solid #f0ebe6;
        vertical-align: middle;
        font-size: 11px;
    }

    .monthly-sales-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .monthly-sales-month {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        color: var(--text);
    }

    .monthly-sales-dot {
        width: 8px;
        height: 8px;
        flex: 0 0 8px;
        border-radius: 50%;
        background: var(--orange);
        box-shadow: 0 0 0 4px var(--orange-light);
    }

    .monthly-sales-bar {
        width: 100%;
        max-width: 360px;
        height: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: #f1ece7;
    }

    .monthly-sales-bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--orange), var(--orange-dark));
    }

    .monthly-sales-amount {
        text-align: right;
        color: var(--orange-dark);
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    @media (max-width: 700px) {
        .monthly-sales-section .section-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .monthly-sales-table thead th,
        .monthly-sales-table tbody td {
            padding: 11px 12px;
        }

        .monthly-sales-table th:nth-child(2),
        .monthly-sales-table td:nth-child(2) {
            display: none;
        }
    }

    /* =========================================================
       ADMIN DASHBOARD FINAL POLISH
       Recent-order creator + compact four-card KPI row
       ========================================================= */

    /* Keep all four colored KPI cards on one row on desktop. */
    .metrics {
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 11px !important;
        margin-bottom: 19px !important;
    }

    .metric {
        min-width: 0 !important;
        min-height: 108px !important;
        padding: 13px 14px 12px !important;
        border-radius: 12px !important;
    }

    .metric-top {
        gap: 7px !important;
        font-size: 8.5px !important;
    }

    .metric-icon {
        width: 29px !important;
        height: 29px !important;
        flex-basis: 29px !important;
        border-radius: 7px !important;
        font-size: 10px !important;
    }

    .metric>strong {
        margin-top: 9px !important;
        font-size: 19px !important;
    }

    .metric>small {
        margin-top: 4px !important;
        font-size: 7px !important;
    }

    /* Recent Orders table */
    .recent-orders-table {
        min-width: 1160px !important;
    }

    .recent-orders-table th {
        padding: 14px 15px !important;
    }

    .recent-orders-table td {
        padding: 14px 15px !important;
    }

    .order-user-cell {
        min-width: 190px;
    }

    .order-user {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
    }

    .order-user-avatar {
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: linear-gradient(135deg, var(--orange), #e96d15);
        font-size: 10px;
        font-weight: 800;
        box-shadow: 0 4px 10px rgba(245, 130, 32, .18);
    }

    .order-user-copy {
        min-width: 0;
    }

    .order-user-copy strong {
        display: block;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #39332e;
        font-size: 9px;
        font-weight: 800;
    }

    .order-user-copy small {
        display: block;
        max-width: 150px;
        margin-top: 3px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #9b938c;
        font-size: 7px;
    }

    @media (max-width: 1100px) {
        .metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 600px) {
        .metrics {
            grid-template-columns: 1fr !important;
        }

        .order-user-copy strong,
        .order-user-copy small {
            max-width: 125px;
        }
    }
    </style>
</head>

<body>
    <div class="app">

        <!-- EXISTING SIDEBAR -->
        <?php  include '../includes/sidebar.php'; ?>

        <main class="main">

            <!-- HEADER -->

            <?php  include '../includes/topbar.php'; ?>
            <div class="content">

                <?php if ($dashboardError): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-3" role="alert"
                    style="font-size:11px;border-radius:10px;">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    Some dashboard data could not be loaded. Please check the database connection.
                </div>
                <?php endif; ?>

                <!-- PAGE HEADING -->
                <div class="dashboard-heading top-heading">
                    <div>
                        <span class="eyebrow">TODAY'S PERFORMANCE</span>
                        <h2>Restaurant Overview</h2>
                        <p>Everything happening at your food point today.</p>
                    </div>

                    <div class="heading-actions">
                        <button class="date-filter">
                            <i class="fa-regular fa-calendar"></i>
                            Today
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>

                        <a href="orders.php" class="new-order-btn">
                            <i class="fa-solid fa-plus"></i>
                            New Order
                        </a>
                    </div>
                </div>

                <!-- SALES CARDS -->
                <section class="metrics">
                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon"><i class="fa-solid fa-coins"></i></div>
                            <span>Today's Sales</span>
                        </div>
                        <strong><?= ghMoney($todaySales) ?></strong>
                        <small><i class="fa-solid fa-database"></i> Live from completed payments</small>
                    </article>

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon green"><i class="fa-solid fa-receipt"></i></div>
                            <span>Total Orders</span>
                        </div>
                        <strong><?= number_format($totalOrders) ?></strong>
                        <small><?= number_format($totalItemsOrdered) ?> food items ordered</small>
                    </article>

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon purple"><i class="fa-solid fa-circle-check"></i></div>
                            <span>Paid Orders</span>
                        </div>
                        <strong><?= number_format($paidOrders) ?></strong>
                        <small><?= number_format($pendingOrders) ?> orders still in service</small>
                    </article>

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon blue"><i class="fa-solid fa-bag-shopping"></i></div>
                            <span>Takeaway Orders</span>
                        </div>
                        <strong><?= number_format($takeawayOrders) ?></strong>
                        <small><?= number_format($dineInOrders) ?> dine-in orders</small>
                    </article>
                </section>

                <!-- HERO + POPULAR FOOD ON RIGHT -->
                <section class="hero-food-row">

                    <article class="hero">
                        <div class="hero-content">
                            <span class="hero-kicker">BETTER END FOOD POINT</span>

                            <h1>
                                Fresh Food.<br>
                                Fast Service.
                            </h1>

                            <p>
                                Record customer orders quickly, send receipts to the kitchen,
                                serve takeaway customers and keep every sale documented.
                            </p>

                            <a href="orders.php" class="hero-btn">
                                <i class="fa-solid fa-cart-plus"></i>
                                CREATE NEW ORDER
                            </a>
                        </div>
                    </article>

                    <!-- POPULAR FOOD IS NOW DIRECTLY BESIDE HERO -->
                    <aside class="popular-panel">

                        <div class="popular-head">
                            <div>
                                <h3>Popular Food</h3>
                                <p>Top ordered meals today</p>
                            </div>
                            <a href="food_menu.php">View menu <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <div class="popular-list">

                            <?php if (!empty($popularFoods)): ?>
                            <?php foreach ($popularFoods as $food): ?>
                            <article class="popular-item">
                                <div class="popular-image"
                                    style="background-image:url('<?= htmlspecialchars(foodImagePath($food['image']), ENT_QUOTES, 'UTF-8') ?>');">
                                </div>

                                <div class="popular-info">
                                    <strong><?= htmlspecialchars($food['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= number_format((int)$food['total_quantity']) ?> items ordered</small>
                                    <div class="popular-rating">
                                        <i class="fa-solid fa-chart-line"></i>
                                        <?= number_format((int)$food['total_quantity']) ?> orders
                                    </div>
                                </div>

                                <div class="popular-sales">
                                    <strong><?= ghCompact($food['total_sales']) ?></strong>
                                    <small>Sales</small>
                                </div>
                            </article>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div style="padding:35px 15px;text-align:center;color:#999;font-size:9px;">
                                No completed food sales yet.
                            </div>
                            <?php endif; ?>

                        </div>
                    </aside>

                </section>

                <!-- MONTHLY SALES + PAYMENTS -->
                <section class="dashboard-grid">

                    <article class="panel">
                        <div class="panel-header">
                            <div>
                                <h3>Monthly Sales Performance</h3>
                                <small>Revenue movement across the last 12 months</small>
                            </div>
                            <span><?= ghCompact($chartTotal) ?></span>
                        </div>

                        <div class="chart-summary">
                            <div><small>Best Month</small><strong><?= htmlspecialchars($chartBestLabel) ?> ·
                                    <?= ghCompact($chartBest) ?></strong></div>

                            <div><small>Current Month</small><strong><?= ghCompact($monthlySales) ?></strong></div>
                            <div><small>Year to Date</small><strong><?= ghCompact($chartTotal) ?></strong></div>
                        </div>

                        <div class="sales-chart">
                            <div class="chart-area">
                                <svg class="chart-svg" viewBox="0 0 800 180" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="orangeFill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#f58220" stop-opacity=".24" />
                                            <stop offset="100%" stop-color="#f58220" stop-opacity="0" />
                                        </linearGradient>
                                    </defs>

                                    <?php
                                    $points = [];
                                    $count = count($chartValues);
                                    $max = $count ? max($chartValues) : 1;
                                    if ($max <= 0) $max = 1;
                                    foreach ($chartValues as $i => $value) {
                                        $x = $count <= 1 ? 400 : ($i / ($count - 1)) * 800;
                                        $y = 145 - (($value / $max) * 120);
                                        $points[] = round($x) . ',' . round($y);
                                    }
                                    $pointString = implode(' ', $points);
                                    ?>

                                    <?php if ($pointString): ?>
                                    <polyline points="<?= htmlspecialchars($pointString, ENT_QUOTES, 'UTF-8') ?>"
                                        fill="none" stroke="#f58220" stroke-width="4" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <?php endif; ?>
                                </svg>

                                <div class="chart-axis">
                                    <?php foreach ($chartLabels as $label): ?>
                                    <span><?= htmlspecialchars($label) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel-header">
                            <div>
                                <h3>Payment Methods</h3>
                                <small>Monthly completed payment collection</small>
                            </div>
                            <span><?= ghCompact($totalCollected) ?></span>
                        </div>

                        <div class="payment-total">
                            <div>
                                <small>Total Collected</small>
                                <strong><?= ghMoney($totalCollected) ?></strong>
                            </div>
                            <span class="growth"><i class="fa-solid fa-circle-check"></i> Completed</span>
                        </div>

                        <div class="payment-bars">
                            <div class="payment-row">
                                <div class="payment-label">
                                    <span class="payment-dot cash"></span>
                                    <strong>Cash</strong>
                                    <small><?= number_format($cashPercent, 1) ?>%</small>
                                </div>
                                <div class="payment-track"><span style="width:<?= min(100, $cashPercent) ?>%"></span>
                                </div>
                                <b><?= ghCompact($paymentStats['Cash']) ?></b>
                            </div>

                            <div class="payment-row">
                                <div class="payment-label">
                                    <span class="payment-dot momo"></span>
                                    <strong>Mobile Money</strong>
                                    <small><?= number_format($momoPercent, 1) ?>%</small>
                                </div>
                                <div class="payment-track"><span style="width:<?= min(100, $momoPercent) ?>%"></span>
                                </div>
                                <b><?= ghCompact($paymentStats['Mobile Money']) ?></b>
                            </div>

                        </div>

                        <div class="payment-note">
                            <i class="fa-solid fa-circle-check"></i>
                            Payment figures are loaded directly from the payments table.
                        </div>
                    </article>

                </section>

                <!-- RECENT ORDERS -->
                <section class="recent-orders-section">
                    <div class="snapshot-heading recent-orders-heading">
                        <div>
                            <span class="eyebrow">ORDER ACTIVITY</span>
                            <h2>Recent Orders</h2>
                            <p class="recent-results">
                                <?= number_format($filteredOrderCount) ?> matching
                                <?= $orderDate ? 'orders on ' . htmlspecialchars(date('F j, Y', strtotime($orderDate)), ENT_QUOTES, 'UTF-8') : 'orders' ?>
                            </p>
                        </div>

                        <div class="recent-order-tools">
                            <form method="GET" class="order-filter-form">
                                <div class="order-search-box">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="search" name="order_search"
                                        value="<?= htmlspecialchars($orderSearch, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Search order number..." autocomplete="off">
                                </div>

                                <div class="order-date-box">
                                    <i class="fa-regular fa-calendar"></i>
                                    <input type="date" name="order_date"
                                        value="<?= htmlspecialchars($orderDate, ENT_QUOTES, 'UTF-8') ?>"
                                        title="Filter orders by date">
                                </div>

                                <button type="submit" class="order-filter-btn">
                                    <i class="fa-solid fa-filter"></i>
                                    Search
                                </button>

                                <?php if ($orderDate !== '' || $orderSearch !== ''): ?>
                                <a href="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>#recent-orders"
                                    class="order-clear-btn" title="Clear order filters">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                                <?php endif; ?>
                            </form>

                            <a href="orders.php" class="period recent-view-link">
                                New Order <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="filtered-order-summary">
                        <div class="filtered-summary-card">
                            <div class="filtered-summary-icon">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div>
                                <small><?= ($orderDate !== '' || $orderSearch !== '') ? 'Filtered Orders' : 'Total Orders' ?></small>
                                <strong><?= number_format($filteredOrderCount) ?></strong>
                            </div>
                        </div>

                        <div class="filtered-summary-card sales">
                            <div class="filtered-summary-icon">
                                <i class="fa-solid fa-coins"></i>
                            </div>
                            <div>
                                <small><?= ($orderDate !== '' || $orderSearch !== '') ? 'Filtered Sales' : 'Total Sales' ?></small>
                                <strong><?= ghMoney($filteredOrderSales) ?></strong>
                            </div>
                        </div>

                        <div class="filtered-summary-card">
                            <div class="filtered-summary-icon">
                                <i class="fa-solid fa-bowl-food"></i>
                            </div>
                            <div>
                                <small>Food Items</small>
                                <strong><?= number_format($filteredOrderItems) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="panel recent-orders-panel" id="recent-orders">
                        <div class="recent-orders-table-wrap">
                            <table class="recent-orders-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>User</th>
                                        <th>Items</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentOrders)): ?>
                                    <?php foreach ($recentOrders as $recent): ?>
                                    <?php
                                            $status = $recent['status'] ?? 'Pending';
                                            $paymentStatus = $recent['payment_status'] ?? 'Unpaid';

                                            $statusClass = 'pending';
                                            if ($status === 'Completed') {
                                                $statusClass = 'completed';
                                            } elseif ($status === 'Preparing') {
                                                $statusClass = 'preparing';
                                            } elseif ($status === 'Ready') {
                                                $statusClass = 'ready';
                                            }

                                            $paymentClass = $paymentStatus === 'Paid'
                                                ? 'paid'
                                                : 'unpaid';
                                        ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($recent['order_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small>#<?= (int)$recent['id'] ?></small>
                                        </td>

                                        <td class="order-user-cell">
                                            <div class="order-user">
                                                <span class="order-user-avatar">
                                                    <?= htmlspecialchars(
                                                        strtoupper(substr(trim($recent['created_by_name'] ?? 'U'), 0, 1)),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>
                                                <div class="order-user-copy">
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            $recent['created_by_name'] ?? 'Unknown User',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </strong>
                                                    <?php if (!empty($recent['created_by_username'])): ?>
                                                    <small>
                                                        @<?= htmlspecialchars(
                                                            $recent['created_by_username'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </small>
                                                    <?php else: ?>
                                                    <small>Cashier / Salesperson</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="items-cell">
                                            <?= htmlspecialchars($recent['items'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td>
                                            <span class="type-badge">
                                                <i
                                                    class="fa-solid <?= $recent['order_type'] === 'Takeaway' ? 'fa-bag-shopping' : 'fa-utensils' ?>"></i>
                                                <?= htmlspecialchars($recent['order_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= $paymentClass ?>">
                                                <?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <small class="payment-method">
                                                <?= htmlspecialchars($recent['payment_method'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </td>

                                        <td>
                                            <strong class="order-total">
                                                <?= ghMoney($recent['total']) ?>
                                            </strong>
                                            <small>
                                                Subtotal <?= ghMoney($recent['subtotal']) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="order-date">
                                                <?= date('d M Y', strtotime($recent['created_at'])) ?>
                                            </span>
                                            <small>
                                                <?= date('h:i A', strtotime($recent['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td class="order-action">
                                            <button type="button" class="delete-order-btn"
                                                data-order-id="<?= (int)$recent['id'] ?>"
                                                data-order-number="<?= htmlspecialchars($recent['order_number'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Delete order">
                                                <i class="fa-solid fa-trash"></i>
                                                <span>Delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="empty-orders">
                                            <i class="fa-solid fa-receipt"></i>
                                            <strong>No orders recorded yet.</strong>
                                            <span>Create an order from the order page and it will appear here.</span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($orderTotalPages > 1): ?>
                        <div class="orders-pagination">
                            <div class="pagination-summary">
                                Showing
                                <strong><?= number_format($filteredOrderCount ? $orderOffset + 1 : 0) ?></strong>
                                –
                                <strong><?= number_format(min($orderOffset + $ordersPerPage, $filteredOrderCount)) ?></strong>
                                of
                                <strong><?= number_format($filteredOrderCount) ?></strong>
                                orders
                            </div>

                            <div class="pagination-controls">
                                <?php
                                $paginationBase = [];
                                if ($orderDate !== '') $paginationBase['order_date'] = $orderDate;
                                if ($orderSearch !== '') $paginationBase['order_search'] = $orderSearch;

                                $pageUrl = function (int $page) use ($paginationBase): string {
                                    return '?' . http_build_query(array_merge($paginationBase, ['order_page' => $page])) . '#recent-orders';
                                };

                                $startPage = max(1, $orderPage - 2);
                                $endPage = min($orderTotalPages, $orderPage + 2);
                                ?>

                                <?php if ($orderPage > 1): ?>
                                <a class="page-btn page-arrow"
                                    href="<?= htmlspecialchars($pageUrl($orderPage - 1), ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Previous page">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                                <?php else: ?>
                                <span class="page-btn page-arrow disabled"><i
                                        class="fa-solid fa-chevron-left"></i></span>
                                <?php endif; ?>

                                <?php for ($pageNo = $startPage; $pageNo <= $endPage; $pageNo++): ?>
                                <a class="page-btn <?= $pageNo === $orderPage ? 'active' : '' ?>"
                                    href="<?= htmlspecialchars($pageUrl($pageNo), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $pageNo ?>
                                </a>
                                <?php endfor; ?>

                                <?php if ($orderPage < $orderTotalPages): ?>
                                <a class="page-btn page-arrow"
                                    href="<?= htmlspecialchars($pageUrl($orderPage + 1), ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Next page">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                                <?php else: ?>
                                <span class="page-btn page-arrow disabled"><i
                                        class="fa-solid fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- MONTHLY SNAPSHOT -->
                <section class="snapshot">

                    <div class="snapshot-heading">
                        <div>
                            <span class="eyebrow">BUSINESS SNAPSHOT</span>
                            <h2>This Month at a Glance</h2>
                        </div>
                        <span class="period"><?= date('F Y') ?></span>
                    </div>

                    <div class="snapshot-grid">
                        <div class="snapshot-card">
                            <div class="snapshot-icon orange"><i class="fa-solid fa-coins"></i></div>
                            <div>
                                <small>Gross Sales</small>
                                <strong><?= ghMoney($monthlySales) ?></strong>
                                <span class="positive">Live database total</span>
                            </div>
                        </div>

                        <div class="snapshot-card">
                            <div class="snapshot-icon green"><i class="fa-solid fa-receipt"></i></div>
                            <div>
                                <small>Orders</small>
                                <strong><?= number_format($monthlyOrders) ?></strong>
                                <span class="positive">This month</span>
                            </div>
                        </div>

                        <div class="snapshot-card">
                            <div class="snapshot-icon purple"><i class="fa-solid fa-utensils"></i></div>
                            <div>
                                <small>Items Ordered</small>
                                <strong><?= number_format($totalItemsOrdered) ?></strong>
                                <span class="positive">All recorded items</span>
                            </div>
                        </div>


                    </div>
                </section>

            </div>
        </main>
    </div>

    <div class="delete-confirm-overlay" id="deleteOrderOverlay" aria-hidden="true">
        <div class="delete-confirm-modal" role="dialog" aria-modal="true">
            <div class="delete-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
            <h3>Delete Order?</h3>
            <p>This will permanently delete <strong id="deleteOrderNumber"></strong>,
                including its order items and payment record. This cannot be undone.</p>
            <div class="delete-confirm-actions">
                <button type="button" class="delete-cancel" id="cancelDeleteOrder">Cancel</button>
                <button type="button" class="delete-submit" id="confirmDeleteOrder">
                    <i class="fa-solid fa-trash me-1"></i> Delete Order
                </button>
            </div>
        </div>
    </div>


    <div id="dashboardNotice" class="dashboard-notice warning" style="display:none;" role="status">
        <div class="notice-icon">
            <i class="fa-solid fa-lock"></i>
        </div>
        <div class="notice-content">
            <strong id="dashboardNoticeTitle">Permission denied</strong>
            <span id="dashboardNoticeMessage"></span>
        </div>
        <button type="button" class="notice-close" id="dashboardNoticeClose" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>


    <div id="customDeleteNotice" class="custom-delete-notice" role="status" aria-live="polite">
        <div class="delete-notice-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="delete-notice-text">
            <strong id="deleteNoticeTitle">Order deleted</strong>
            <span id="deleteNoticeMessage"></span>
        </div>
        <button type="button" class="delete-notice-close" id="deleteNoticeClose" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>


    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    const toggle = document.getElementById("mobileToggle");
    const sidebar = document.getElementById("sidebar");

    if (toggle && sidebar) {
        toggle.addEventListener("click", () => {
            sidebar.classList.toggle("open");
        });

        document.addEventListener("click", (e) => {
            if (
                window.innerWidth <= 992 &&
                sidebar.classList.contains("open") &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)
            ) {
                sidebar.classList.remove("open");
            }
        });
    }

    const trigger = document.getElementById("profileTrigger");
    const dropdown = document.getElementById("profileDropdown");

    if (trigger && dropdown) {
        trigger.addEventListener("click", function(event) {
            event.stopPropagation();
            const open = dropdown.classList.toggle("show");
            trigger.classList.toggle("open", open);
            trigger.setAttribute("aria-expanded", open ? "true" : "false");
        });

        document.addEventListener("click", function() {
            dropdown.classList.remove("show");
            trigger.classList.remove("open");
            trigger.setAttribute("aria-expanded", "false");
        });

        dropdown.addEventListener("click", function(event) {
            event.stopPropagation();
        });
    }

    const deleteOverlay = document.getElementById("deleteOrderOverlay");
    const deleteOrderNumber = document.getElementById("deleteOrderNumber");
    const cancelDeleteOrder = document.getElementById("cancelDeleteOrder");
    const confirmDeleteOrder = document.getElementById("confirmDeleteOrder");
    let deleteOrderId = null;

    document.querySelectorAll(".delete-order-btn").forEach(button => {
        button.addEventListener("click", () => {
            deleteOrderId = button.dataset.orderId;
            deleteOrderNumber.textContent = button.dataset.orderNumber || "this order";
            deleteOverlay.classList.add("show");
            deleteOverlay.setAttribute("aria-hidden", "false");
        });
    });

    function closeDeleteModal() {
        deleteOrderId = null;
        deleteOverlay.classList.remove("show");
        deleteOverlay.setAttribute("aria-hidden", "true");
    }

    cancelDeleteOrder.addEventListener("click", closeDeleteModal);
    deleteOverlay.addEventListener("click", e => {
        if (e.target === deleteOverlay) closeDeleteModal();
    });

    confirmDeleteOrder.addEventListener("click", async () => {
        if (!deleteOrderId) return;
        const id = deleteOrderId;
        confirmDeleteOrder.disabled = true;
        confirmDeleteOrder.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-1"></i>Deleting...';

        try {
            const response = await fetch("../handlers/delete_order.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    order_id: id
                })
            });
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error("The server returned an invalid response.");
            }

            if (result.permission_denied) {
                closeDeleteModal();

                showDashboardNotice(
                    "Permission denied",
                    result.message ||
                    "You do not have permission to delete orders.",
                    "warning"
                );

                return;
            }

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to delete order.");
            }

            closeDeleteModal();

            showCustomDeleteNotice(
                "Order deleted successfully",
                "Order " + (deleteOrderNumber.textContent || "") +
                " and its related payment and item records were removed.",
                "success"
            );

            setTimeout(() => {
                window.location.reload();
            }, 900);
        } catch (error) {
            showDashboardNotice(
                "Delete failed",
                error.message || "Unable to delete order.",
                "warning"
            );

            confirmDeleteOrder.disabled = false;
            confirmDeleteOrder.innerHTML =
                '<i class="fa-solid fa-trash me-1"></i> Delete Order';
        }
    });


    function showDashboardNotice(title, message, type = "warning") {
        const notice = document.getElementById("dashboardNotice");
        const titleEl = document.getElementById("dashboardNoticeTitle");
        const messageEl = document.getElementById("dashboardNoticeMessage");

        if (!notice || !titleEl || !messageEl) {
            return;
        }

        titleEl.textContent = title;
        messageEl.textContent = message;

        notice.className = "dashboard-notice " + type;
        notice.style.display = "flex";

        clearTimeout(window.dashboardNoticeTimer);

        window.dashboardNoticeTimer = setTimeout(() => {
            notice.style.display = "none";
        }, 5000);
    }

    document.getElementById("dashboardNoticeClose")?.addEventListener(
        "click",
        () => {
            document.getElementById("dashboardNotice").style.display = "none";
        }
    );


    function showCustomDeleteNotice(title, message, type = "success") {
        const notice = document.getElementById("customDeleteNotice");
        const titleEl = document.getElementById("deleteNoticeTitle");
        const messageEl = document.getElementById("deleteNoticeMessage");

        if (!notice || !titleEl || !messageEl) return;

        titleEl.textContent = title;
        messageEl.textContent = message;

        notice.className = "custom-delete-notice " + type;

        const icon = notice.querySelector(".delete-notice-icon i");
        if (icon) {
            icon.className = type === "success" ?
                "fa-solid fa-circle-check" :
                "fa-solid fa-lock";
        }

        notice.classList.add("show");

        clearTimeout(window.customDeleteNoticeTimer);
        window.customDeleteNoticeTimer = setTimeout(() => {
            notice.classList.remove("show");
        }, 5000);
    }

    document.getElementById("deleteNoticeClose")?.addEventListener("click", () => {
        document.getElementById("customDeleteNotice")?.classList.remove("show");
    });
    </script>

</body>

</html>