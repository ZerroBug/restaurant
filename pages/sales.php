<?php
session_start();
require_once "../includes/db_connection.php";

/*
|--------------------------------------------------------------------------
| ADMIN SALES RECORDS
|--------------------------------------------------------------------------
| This is the administrator's historical sales page.
|
| Sales are based on completed payments, because the application records
| completed payments when an order is successfully paid.
|
| Search modes:
|   - Day
|   - Week
|   - Month
|   - All dates
|
| The search can also match:
|   - Order number / ID
|   - Salesperson / user
|   - Payment method
|   - Food item
|
| Bootstrap pagination is used for the records table.
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    ($_SESSION['role'] ?? '') !== 'Administrator'
) {
    $_SESSION['login_message'] =
        'Please log in as an Administrator to access the Sales page.';
    header('Location: ../index.php');
    exit;
}

$username  = $_SESSION['username'] ?? 'Administrator';
$full_name = $_SESSION['full_name'] ?? $username;
$role      = $_SESSION['role'] ?? 'Administrator';
$avatar    = strtoupper(substr(trim($full_name), 0, 1));

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ghMoney($amount): string
{
    return 'GH₵' . number_format((float)$amount, 2);
}

function validDate(string $date): bool
{
    if ($date === '') {
        return false;
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);

    return $d !== false && $d->format('Y-m-d') === $date;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$period = $_GET['period'] ?? 'day';
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$allowedPeriods = ['all', 'day', 'week', 'month'];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'day';
}

if (!validDate($selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$rangeStart = null;
$rangeEnd = null;
$rangeLabel = 'All completed sales';

if ($period === 'day') {
    $rangeStart = $selectedDate;
    $rangeEnd = $selectedDate;
    $rangeLabel = date('d M Y', strtotime($selectedDate));
} elseif ($period === 'week') {
    $dateObject = new DateTime($selectedDate);
    $dateObject->modify('monday this week');

    $rangeStart = $dateObject->format('Y-m-d');

    $dateObject->modify('+6 days');
    $rangeEnd = $dateObject->format('Y-m-d');

    $rangeLabel =
        date('d M', strtotime($rangeStart)) .
        ' – ' .
        date('d M Y', strtotime($rangeEnd));
} elseif ($period === 'month') {
    $dateObject = new DateTime($selectedDate);

    $rangeStart = $dateObject->format('Y-m-01');
    $rangeEnd = $dateObject->format('Y-m-t');

    $rangeLabel = date('F Y', strtotime($selectedDate));
}

/*
|--------------------------------------------------------------------------
| BUILD FILTER
|--------------------------------------------------------------------------
*/
$where = [
    "EXISTS (
        SELECT 1
        FROM payments pc
        WHERE pc.order_id = o.id
          AND pc.status = 'Completed'
    )"
];

$params = [];

if ($rangeStart !== null && $rangeEnd !== null) {
    $where[] = "DATE(COALESCE(paid.created_at, o.created_at))
                BETWEEN :range_start AND :range_end";

    $params[':range_start'] = $rangeStart;
    $params[':range_end'] = $rangeEnd;
}

if ($search !== '') {
    $where[] = "(
        o.order_number LIKE :search_order
        OR CAST(o.id AS CHAR) LIKE :search_id
        OR COALESCE(u.full_name, '') LIKE :search_name
        OR COALESCE(u.username, '') LIKE :search_username
        OR COALESCE(paid.payment_method, '') LIKE :search_payment
        OR EXISTS (
            SELECT 1
            FROM order_items osi
            INNER JOIN food_menu fsi
                ON fsi.id = osi.food_id
            WHERE osi.order_id = o.id
              AND fsi.name LIKE :search_food
        )
    )";

    $searchValue = '%' . $search . '%';

    $params[':search_order'] = $searchValue;
    $params[':search_id'] = $searchValue;
    $params[':search_name'] = $searchValue;
    $params[':search_username'] = $searchValue;
    $params[':search_payment'] = $searchValue;
    $params[':search_food'] = $searchValue;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$error = null;
$records = [];
$totalRecords = 0;
$filteredSales = 0;
$filteredItems = 0;
$averageOrder = 0;
$cashSales = 0;
$cardSales = 0;
$momoSales = 0;

try {
    /*
     * Completed payment totals are grouped per order so a sale is never
     * accidentally duplicated if an order has more than one payment row.
     */
    $paymentSubquery = "
        SELECT
            p1.order_id,
            MAX(p1.created_at) AS created_at,
            MAX(p1.payment_method) AS payment_method,
            SUM(p1.amount) AS amount
        FROM payments p1
        WHERE p1.status = 'Completed'
        GROUP BY p1.order_id
    ";

    /*
     |--------------------------------------------------------------------------
     | COUNT
     |--------------------------------------------------------------------------
     */
    $countSql = "
        SELECT COUNT(*)
        FROM orders o
        LEFT JOIN users u
            ON u.id = o.user_id
        LEFT JOIN ($paymentSubquery) paid
            ON paid.order_id = o.id
        $whereSql
    ";

    $stmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $totalRecords = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalRecords / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    /*
     |--------------------------------------------------------------------------
     | FILTERED SUMMARY
     |--------------------------------------------------------------------------
     */
    $summarySql = "
        SELECT
            COALESCE(SUM(paid.amount), 0) AS sales_total,
            COALESCE(SUM(items.total_items), 0) AS item_total,
            COALESCE(SUM(paid.amount), 0) AS collected_total
        FROM orders o
        LEFT JOIN users u
            ON u.id = o.user_id
        LEFT JOIN ($paymentSubquery) paid
            ON paid.order_id = o.id
        LEFT JOIN (
            SELECT
                order_id,
                SUM(quantity) AS total_items
            FROM order_items
            GROUP BY order_id
        ) items
            ON items.order_id = o.id
        $whereSql
    ";

    $stmt = $pdo->prepare($summarySql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $filteredSales = (float)($summary['sales_total'] ?? 0);
    $filteredItems = (int)($summary['item_total'] ?? 0);

    $averageOrder = $totalRecords > 0
        ? $filteredSales / $totalRecords
        : 0;

    /*
     |--------------------------------------------------------------------------
     | PAYMENT METHOD SUMMARY
     |--------------------------------------------------------------------------
     */
    $paymentSummarySql = "
        SELECT
            paid.payment_method,
            COALESCE(SUM(paid.amount), 0) AS amount
        FROM orders o
        LEFT JOIN users u
            ON u.id = o.user_id
        LEFT JOIN ($paymentSubquery) paid
            ON paid.order_id = o.id
        $whereSql
        GROUP BY paid.payment_method
    ";

    $stmt = $pdo->prepare($paymentSummarySql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $method = trim((string)($row['payment_method'] ?? ''));
        $amount = (float)$row['amount'];

        if ($method === 'Cash') {
            $cashSales += $amount;
        } elseif ($method === 'Card') {
            $cardSales += $amount;
        } elseif ($method === 'Mobile Money') {
            $momoSales += $amount;
        }
    }

    /*
     |--------------------------------------------------------------------------
     | SALES BY FOOD CATEGORY
     |--------------------------------------------------------------------------
     | Category totals follow the same selected period as the sales summary.
     */
    $categorySales = [];
    $categoryWhere = ["EXISTS (SELECT 1 FROM payments pc_cat WHERE pc_cat.order_id = o_cat.id AND pc_cat.status = 'Completed')"];
    $categoryParams = [];

    if ($rangeStart !== null && $rangeEnd !== null) {
        $categoryWhere[] = "DATE(COALESCE(paid_cat.created_at, o_cat.created_at)) BETWEEN :cat_range_start AND :cat_range_end";
        $categoryParams[':cat_range_start'] = $rangeStart;
        $categoryParams[':cat_range_end'] = $rangeEnd;
    }

    if ($search !== '') {
        $categoryWhere[] = "(
            o_cat.order_number LIKE :cat_search_order
            OR CAST(o_cat.id AS CHAR) LIKE :cat_search_id
            OR COALESCE(u_cat.full_name, '') LIKE :cat_search_name
            OR COALESCE(u_cat.username, '') LIKE :cat_search_username
            OR COALESCE(paid_cat.payment_method, '') LIKE :cat_search_payment
            OR f_cat.name LIKE :cat_search_food
            OR c_cat.name LIKE :cat_search_category
        )";
        $categorySearchValue = '%' . $search . '%';
        foreach (['order','id','name','username','payment','food','category'] as $key) {
            $categoryParams[":cat_search_$key"] = $categorySearchValue;
        }
    }

    $categoryWhereSql = 'WHERE ' . implode(' AND ', $categoryWhere);
    $categorySql = "
        SELECT c_cat.id, c_cat.name,
               COALESCE(SUM(oi_cat.subtotal), 0) AS sales,
               COALESCE(SUM(oi_cat.quantity), 0) AS quantity
        FROM order_items oi_cat
        INNER JOIN orders o_cat ON o_cat.id = oi_cat.order_id
        LEFT JOIN users u_cat ON u_cat.id = o_cat.user_id
        INNER JOIN food_menu f_cat ON f_cat.id = oi_cat.food_id
        INNER JOIN categories c_cat ON c_cat.id = f_cat.category_id
        LEFT JOIN ($paymentSubquery) paid_cat ON paid_cat.order_id = o_cat.id
        $categoryWhereSql
        GROUP BY c_cat.id, c_cat.name
        ORDER BY sales DESC, c_cat.name ASC
    ";
    $stmt = $pdo->prepare($categorySql);
    foreach ($categoryParams as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
    $stmt->execute();
    $categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     |--------------------------------------------------------------------------
     | SALES RECORDS
     |--------------------------------------------------------------------------
     */
    $recordsSql = "
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

            COALESCE(
                u.full_name,
                u.username,
                'Unknown User'
            ) AS salesperson,

            COALESCE(
                paid.amount,
                o.total,
                0
            ) AS paid_amount,

            COALESCE(
                paid.payment_method,
                'Not recorded'
            ) AS payment_method,

            COALESCE(
                paid.created_at,
                o.created_at
            ) AS paid_at,

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
                SUM(oi.quantity),
                0
            ) AS item_count

        FROM orders o

        LEFT JOIN users u
            ON u.id = o.user_id

        LEFT JOIN ($paymentSubquery) paid
            ON paid.order_id = o.id

        LEFT JOIN order_items oi
            ON oi.order_id = o.id

        LEFT JOIN food_menu fm
            ON fm.id = oi.food_id

        $whereSql

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
            u.username,
            paid.amount,
            paid.payment_method,
            paid.created_at

        ORDER BY
            paid_at DESC,
            o.id DESC

        LIMIT :limit_value
        OFFSET :offset_value
    ";

    $stmt = $pdo->prepare($recordsSql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit_value', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset_value', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Unable to load the sales records. Please check the database connection.';
    $totalPages = 1;
}

/*
|--------------------------------------------------------------------------
| QUERY STRING FOR PAGINATION
|--------------------------------------------------------------------------
*/
function salesQuery(array $overrides = []): string
{
    $query = [
        'period' => $_GET['period'] ?? 'day',
        'date'   => $_GET['date'] ?? date('Y-m-d'),
        'search' => $_GET['search'] ?? ''
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}

$todayLabel = date('l, d M Y');

$paymentGrand = $cashSales + $cardSales + $momoSales;

$cashPercent = $paymentGrand > 0
    ? ($cashSales / $paymentGrand) * 100
    : 0;

$momoPercent = $paymentGrand > 0
    ? ($momoSales / $paymentGrand) * 100
    : 0;

$cardPercent = $paymentGrand > 0
    ? ($cardSales / $paymentGrand) * 100
    : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales Records | Better End Food Point</title>

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
        --red: #d94b4b;
        --sidebar-width: 250px;
    }

    * {
        box-sizing: border-box;
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
        text-decoration: none;
    }

    button,
    input,
    select {
        font-family: inherit;
    }

    .main {
        margin-left: var(--sidebar-width);
        min-height: 100vh;
    }

    /*
        |--------------------------------------------------------------------------
        | TOPBAR STACKING
        |--------------------------------------------------------------------------
        */
    .main>.topbar,
    .main>header,
    .main>.navbar {
        position: relative;
        z-index: 5000;
    }

    .profile-dropdown,
    #profileDropdown {
        z-index: 6000 !important;
    }

    /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */
    .content {
        max-width: 1600px;
        margin: 0 auto;
        padding: 25px 30px 48px;
    }

    /*
        |--------------------------------------------------------------------------
        | PAGE HEADING
        |--------------------------------------------------------------------------
        */
    .sales-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 17px;
    }

    .sales-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .title-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        color: #fff;
        background: linear-gradient(135deg,
                var(--orange),
                var(--orange-dark));
        box-shadow: 0 9px 22px rgba(245, 130, 32, .20);
        font-size: 18px;
    }

    .eyebrow {
        display: block;
        margin-bottom: 4px;
        color: var(--orange);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1px;
    }

    .sales-heading h1 {
        margin: 0;
        font-size: 23px;
        line-height: 1.15;
        font-weight: 800;
        letter-spacing: -.6px;
    }

    .sales-heading p {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 10px;
    }

    .new-order-btn {
        height: 40px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 13px;
        border: 0;
        border-radius: 8px;
        color: #fff;
        background: var(--orange);
        font-size: 9px;
        font-weight: 800;
        box-shadow: 0 7px 17px rgba(245, 130, 32, .16);
    }

    .new-order-btn:hover {
        color: #fff;
        background: var(--orange-dark);
        transform: translateY(-1px);
    }

    /*
        |--------------------------------------------------------------------------
        | FILTER PANEL
        |--------------------------------------------------------------------------
        */
    .filter-panel {
        margin-bottom: 17px;
        border: 1px solid var(--border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 8px 25px rgba(45, 32, 23, .045);
    }

    .filter-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 15px 18px;
        border-bottom: 1px solid #eee9e5;
    }

    .filter-head-left {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .filter-head-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        color: var(--orange);
        background: var(--orange-light);
        font-size: 11px;
    }

    .filter-head strong {
        display: block;
        color: #322d28;
        font-size: 11px;
        font-weight: 800;
    }

    .filter-head small {
        display: block;
        margin-top: 2px;
        color: #9a928b;
        font-size: 7px;
    }

    .range-label {
        padding: 6px 9px;
        border-radius: 20px;
        color: var(--orange-dark);
        background: var(--orange-light);
        font-size: 7px;
        font-weight: 800;
        white-space: nowrap;
    }

    .filter-body {
        display: grid;
        grid-template-columns: auto minmax(170px, 1fr) minmax(220px, 1.4fr) auto;
        align-items: end;
        gap: 9px;
        padding: 14px 18px 16px;
    }

    .period-buttons {
        display: flex;
        align-items: center;
        gap: 5px;
        height: 39px;
    }

    .period-btn {
        height: 39px;
        padding: 0 11px;
        border: 1px solid #e4ded8;
        border-radius: 8px;
        color: #756d65;
        background: #fff;
        font-size: 8px;
        font-weight: 800;
        cursor: pointer;
    }

    .period-btn:hover,
    .period-btn.active {
        border-color: var(--orange);
        color: #fff;
        background: var(--orange);
    }

    .field-label {
        display: block;
        margin-bottom: 5px;
        color: #7e756d;
        font-size: 7px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .45px;
    }

    .filter-field {
        height: 39px;
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 0 10px;
        border: 1px solid #e4ded8;
        border-radius: 8px;
        background: #fff;
    }

    .filter-field:focus-within {
        border-color: #efa267;
        box-shadow: 0 0 0 3px rgba(245, 130, 32, .08);
    }

    .filter-field i {
        color: #aaa19a;
        font-size: 10px;
    }

    .filter-field input {
        width: 100%;
        min-width: 0;
        height: 100%;
        border: 0;
        outline: 0;
        color: #3e3833;
        background: transparent;
        font-size: 9px;
        font-weight: 600;
    }

    .filter-submit {
        height: 39px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 15px;
        border: 0;
        border-radius: 8px;
        color: #fff;
        background: var(--orange);
        font-size: 8px;
        font-weight: 800;
        cursor: pointer;
    }

    .filter-submit:hover {
        background: var(--orange-dark);
    }

    .clear-filter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 39px;
        padding: 0 11px;
        border: 1px solid #e2dbd5;
        border-radius: 8px;
        color: #756c64;
        background: #fff;
        font-size: 8px;
        font-weight: 800;
    }

    .clear-filter:hover {
        color: var(--orange-dark);
        background: #fffaf6;
    }

    /*
        |--------------------------------------------------------------------------
        | KPI CARDS
        |--------------------------------------------------------------------------
        */
    .metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 11px;
        margin-bottom: 17px;
    }

    .metric {
        position: relative;
        overflow: hidden;
        min-height: 105px;
        padding: 13px 14px;
        border-radius: 12px;
        color: #fff;
        box-shadow: 0 8px 22px rgba(39, 29, 22, .10);
    }

    .metric:nth-child(1) {
        background: linear-gradient(135deg, #f58220, #e96b12);
    }

    .metric:nth-child(2) {
        background: linear-gradient(135deg, #1fa463, #168552);
    }

    .metric:nth-child(3) {
        background: linear-gradient(135deg, #7655b5, #5f4397);
    }

    .metric:nth-child(4) {
        background: linear-gradient(135deg, #4c7ed2, #315eae);
    }

    .metric::before {
        content: "";
        position: absolute;
        width: 115px;
        height: 115px;
        right: -45px;
        bottom: -63px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .12);
    }

    .metric-top {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 7px;
        color: rgba(255, 255, 255, .82);
        font-size: 8px;
        font-weight: 700;
    }

    .metric-icon {
        width: 27px;
        height: 27px;
        display: grid;
        place-items: center;
        border-radius: 7px;
        background: rgba(255, 255, 255, .14);
        font-size: 10px;
    }

    .metric strong {
        position: relative;
        z-index: 1;
        display: block;
        margin-top: 11px;
        font-size: 18px;
        font-weight: 800;
    }

    .metric small {
        position: relative;
        z-index: 1;
        display: block;
        margin-top: 2px;
        color: rgba(255, 255, 255, .65);
        font-size: 7px;
    }

    /*
        |--------------------------------------------------------------------------
        | PAYMENT SNAPSHOT
        |--------------------------------------------------------------------------
        */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 17px;
    }

    .payment-card {
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 11px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(45, 32, 23, .035);
    }

    .payment-label {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #625a53;
        font-size: 8px;
        font-weight: 800;
    }

    .payment-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .payment-dot.cash {
        background: var(--orange);
    }

    .payment-dot.momo {
        background: #e2a300;
    }

    .payment-dot.card {
        background: var(--purple);
    }

    .payment-amount {
        display: block;
        margin-top: 7px;
        color: #332d28;
        font-size: 13px;
        font-weight: 800;
    }

    .payment-progress {
        height: 5px;
        margin-top: 7px;
        overflow: hidden;
        border-radius: 20px;
        background: #eeeae7;
    }

    .payment-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
    }

    .payment-card:nth-child(1) .payment-progress span {
        background: linear-gradient(90deg, var(--orange), #ffb277);
    }

    .payment-card:nth-child(2) .payment-progress span {
        background: linear-gradient(90deg, #dfa000, #f4c65c);
    }

    .payment-card:nth-child(3) .payment-progress span {
        background: linear-gradient(90deg, #7655b5, #a487dc);
    }

    .payment-card small {
        display: block;
        margin-top: 5px;
        color: #aaa19a;
        font-size: 7px;
    }

    /*
        |--------------------------------------------------------------------------
        | SALES TABLE
        |--------------------------------------------------------------------------
        */
    .sales-panel {
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 30px rgba(40, 30, 22, .055);
    }

    .sales-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 17px 19px;
        border-bottom: 1px solid #eee9e5;
    }

    .sales-panel-title {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .sales-panel-icon {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        color: var(--orange);
        background: var(--orange-light);
        font-size: 11px;
    }

    .sales-panel-head h2 {
        margin: 0;
        color: #302a25;
        font-size: 14px;
        font-weight: 800;
    }

    .sales-panel-head p {
        margin: 3px 0 0;
        color: #9a928b;
        font-size: 8px;
    }

    .record-count {
        padding: 6px 9px;
        border-radius: 20px;
        color: var(--orange-dark);
        background: var(--orange-light);
        font-size: 7px;
        font-weight: 800;
        white-space: nowrap;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .sales-table {
        width: 100%;
        min-width: 1050px;
        border-collapse: collapse;
    }

    .sales-table th {
        padding: 13px 15px;
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

    .sales-table td {
        padding: 13px 15px;
        border-bottom: 1px solid #f1ece8;
        color: #514a44;
        font-size: 8px;
        vertical-align: middle;
    }

    .sales-table tbody tr:hover {
        background: #fffaf6;
    }

    .sales-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .order-cell {
        min-width: 150px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .order-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        color: var(--orange);
        background: var(--orange-light);
        font-size: 10px;
    }

    .order-cell strong {
        display: block;
        color: #302a25;
        font-size: 8.5px;
        font-weight: 800;
    }

    .order-cell small {
        display: block;
        margin-top: 3px;
        color: #aaa19a;
        font-size: 6.5px;
    }

    .items-cell {
        max-width: 280px;
        line-height: 1.5;
    }

    .salesperson-cell strong {
        display: block;
        color: #403933;
        font-size: 8px;
        font-weight: 800;
    }

    .salesperson-cell small {
        display: block;
        margin-top: 3px;
        color: #aaa19a;
        font-size: 6.5px;
    }

    .type-badge,
    .paid-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 8px;
        border-radius: 20px;
        white-space: nowrap;
        font-size: 7px;
        font-weight: 800;
    }

    .type-badge {
        color: #756c64;
        background: #f5f2ef;
    }

    .paid-badge {
        color: #167448;
        background: #e9f8f0;
    }

    .payment-method {
        display: block;
        margin-top: 4px;
        color: #999089;
        font-size: 6.5px;
    }

    .sale-total {
        color: var(--orange-dark);
        font-size: 9px;
        font-weight: 800;
        white-space: nowrap;
    }

    .sale-date {
        display: block;
        color: #514a44;
        font-size: 7.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    .sale-date small {
        display: block;
        margin-top: 3px;
        color: #aaa19a;
        font-size: 6.5px;
        font-weight: 500;
    }

    .empty-sales {
        padding: 60px 20px !important;
        text-align: center !important;
    }

    .empty-sales-icon {
        width: 58px;
        height: 58px;
        display: grid;
        place-items: center;
        margin: 0 auto 11px;
        border-radius: 17px;
        color: var(--orange);
        background: var(--orange-light);
        font-size: 21px;
    }

    .empty-sales strong {
        display: block;
        color: #4b443e;
        font-size: 10px;
    }

    .empty-sales span {
        display: block;
        max-width: 380px;
        margin: 5px auto 0;
        color: #aaa19a;
        font-size: 8px;
        line-height: 1.6;
    }

    /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
    .pagination-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 13px 17px;
        border-top: 1px solid #eee9e5;
        background: #fff;
    }

    .pagination-info {
        color: #999089;
        font-size: 7px;
        font-weight: 600;
    }

    .pagination-info strong {
        color: #5b534c;
    }

    .pagination {
        margin: 0;
        gap: 3px;
    }

    .pagination .page-link {
        min-width: 28px;
        height: 28px;
        display: grid;
        place-items: center;
        padding: 0 7px;
        border: 1px solid #e4ded8;
        border-radius: 7px !important;
        color: #756c64;
        background: #fff;
        font-size: 7px;
        font-weight: 800;
    }

    .pagination .page-link:hover {
        color: var(--orange-dark);
        background: var(--orange-light);
        border-color: #f3c39e;
    }

    .pagination .page-item.active .page-link {
        color: #fff;
        background: var(--orange);
        border-color: var(--orange);
    }

    .pagination .page-item.disabled .page-link {
        color: #c2bbb5;
        background: #faf9f7;
    }

    /*
        |--------------------------------------------------------------------------
        | NOTICE
        |--------------------------------------------------------------------------
        */
    .sales-error {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-bottom: 15px;
        padding: 11px 13px;
        border: 1px solid #f0c8c8;
        border-radius: 9px;
        color: #8f3333;
        background: #fff4f4;
        font-size: 8px;
    }

    .sales-error i {
        margin-top: 2px;
    }

    /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */
    @media (max-width: 1200px) {
        .filter-body {
            grid-template-columns: 1fr 1fr;
        }

        .period-buttons {
            grid-column: 1 / -1;
        }

        .filter-submit,
        .clear-filter {
            width: 100%;
        }

        .metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 992px) {
        .main {
            margin-left: 0;
        }

        .content {
            padding: 22px 20px 40px;
        }
    }

    @media (max-width: 700px) {
        .content {
            padding: 18px 12px 35px;
        }

        .sales-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .new-order-btn {
            width: 100%;
            justify-content: center;
        }

        .filter-body {
            grid-template-columns: 1fr;
        }

        .period-buttons {
            grid-column: auto;
            width: 100%;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }

        .period-btn {
            padding: 0 5px;
            font-size: 7px;
        }

        .metrics {
            grid-template-columns: 1fr;
        }

        .payment-grid {
            grid-template-columns: 1fr;
        }

        .sales-panel-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .pagination-wrap {
            align-items: flex-start;
            flex-direction: column;
        }

        .pagination {
            width: 100%;
            justify-content: center;
        }

        .title-icon {
            width: 43px;
            height: 43px;
            flex-basis: 43px;
            font-size: 16px;
        }

        .sales-heading h1 {
            font-size: 21px;
        }
    }
    </style>

    <style>
    /* ===== FINAL SALES PAGE POLISH ===== */
    :root {
        --sales-green: #0f6b2e;
        --sales-green-dark: #0a4d20;
        --sales-yellow: #f4c430;
        --sales-bg: #f6f8f7
    }

    body {
        background: var(--sales-bg);
        font-size: 14px
    }

    .content {
        max-width: 1500px;
        margin: 0 auto;
        padding-bottom: 42px
    }

    .sales-heading {
        background: linear-gradient(135deg, #fff 0%, #f8fbf9 100%);
        border: 1px solid #e4ebe6;
        border-radius: 18px;
        padding: 22px 24px;
        margin-bottom: 18px;
        box-shadow: 0 8px 25px rgba(15, 107, 46, .06)
    }

    .sales-title {
        gap: 15px
    }

    .title-icon {
        background: linear-gradient(135deg, var(--sales-green), #17883d) !important;
        box-shadow: 0 8px 18px rgba(15, 107, 46, .18)
    }

    .sales-heading h1 {
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.6px
    }

    .sales-heading p {
        font-size: 13px;
        color: #68736d
    }

    .new-order-btn {
        background: var(--sales-green) !important;
        border-color: var(--sales-green) !important;
        border-radius: 10px !important;
        padding: 11px 16px !important;
        font-weight: 700 !important
    }

    .filter-panel,
    .sales-panel,
    .payment-section {
        border: 1px solid #e2e9e4 !important;
        border-radius: 16px !important;
        background: #fff !important;
        box-shadow: 0 7px 22px rgba(25, 48, 35, .06) !important
    }

    .filter-panel {
        margin-bottom: 18px !important
    }

    .filter-head {
        padding: 16px 19px !important;
        border-bottom: 1px solid #edf1ee !important
    }

    .filter-head-left strong {
        font-size: 14px
    }

    .filter-head-left small {
        font-size: 11px
    }

    .filter-body {
        padding: 17px 19px !important
    }

    .field-label {
        font-size: 10px !important;
        font-weight: 800 !important;
        letter-spacing: .5px !important
    }

    .period-btn {
        height: 38px !important;
        border-radius: 9px !important;
        font-size: 11px !important;
        font-weight: 700 !important
    }

    .period-btn.active {
        background: var(--sales-green) !important;
        color: #fff !important
    }

    .metrics {
        gap: 10px !important;
        margin: 0 0 18px !important
    }

    .metric {
        min-height: 84px !important;
        padding: 11px 13px !important;
        border-radius: 12px !important;
        box-shadow: 0 5px 15px rgba(25, 48, 35, .07) !important
    }

    .metric-top {
        font-size: 10px !important
    }

    .metric-icon {
        width: 28px !important;
        height: 28px !important;
        flex-basis: 28px !important;
        font-size: 11px !important
    }

    .metric>strong {
        font-size: 21px !important;
        margin-top: 7px !important
    }

    .metric>small {
        font-size: 8px !important
    }

    .payment-grid {
        gap: 12px !important
    }

    .payment-card {
        border: 1px solid #e5ebe7 !important;
        border-radius: 13px !important;
        padding: 15px !important;
        box-shadow: none !important
    }

    .payment-amount {
        font-size: 21px !important
    }

    .payment-label {
        font-size: 11px !important
    }

    .payment-progress {
        height: 5px !important
    }

    .sales-panel-head {
        padding: 17px 19px !important;
        border-bottom: 1px solid #edf1ee !important
    }

    .sales-panel-title h2 {
        font-size: 17px !important;
        font-weight: 800 !important
    }

    .sales-panel-title p {
        font-size: 10px !important
    }

    .record-count {
        font-size: 10px !important;
        background: #eef7f0 !important;
        color: var(--sales-green) !important;
        padding: 7px 10px !important;
        border-radius: 999px !important
    }

    .table-wrap {
        overflow: auto
    }

    .sales-table {
        min-width: 980px !important
    }

    .sales-table th {
        background: #f7faf8 !important;
        color: #65716a !important;
        font-size: 9px !important;
        letter-spacing: .8px !important;
        padding: 12px 15px !important
    }

    .sales-table td {
        padding: 13px 15px !important;
        font-size: 11px !important;
        border-bottom: 1px solid #edf1ee !important
    }

    .sales-table tbody tr:hover {
        background: #fbfdfb !important
    }

    .category-badge,
    .type-badge,
    .status-badge {
        border-radius: 999px !important;
        font-size: 9px !important;
        padding: 5px 9px !important;
        font-weight: 700 !important
    }

    .pagination-wrap {
        padding: 14px 18px !important
    }

    .pagination a,
    .pagination span {
        min-width: 30px !important;
        height: 30px !important;
        font-size: 10px !important;
        border-radius: 7px !important
    }

    @media(max-width:900px) {
        .sales-heading {
            padding: 18px
        }

        .sales-heading h1 {
            font-size: 24px
        }

        .metrics {
            grid-template-columns: repeat(2, 1fr) !important
        }
    }

    @media(max-width:560px) {
        .content {
            padding: 12px
        }

        .sales-heading h1 {
            font-size: 21px
        }

        .metrics {
            grid-template-columns: 1fr !important
        }

        .filter-body {
            padding: 13px !important
        }
    }

    .category-sales-section {
        margin: 18px 0;
        border: 1px solid #e1e9e4;
        border-radius: 16px;
        background: #fff;
        padding: 18px;
        box-shadow: 0 7px 22px rgba(25, 48, 35, .06)
    }

    .category-sales-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 14px
    }

    .section-eyebrow {
        display: block;
        color: #0f6b2e;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1.3px;
        margin-bottom: 3px
    }

    .category-sales-head h2 {
        margin: 0;
        color: #24352b;
        font-size: 17px;
        font-weight: 800
    }

    .category-sales-head p {
        margin: 4px 0 0;
        color: #7b8981;
        font-size: 10px
    }

    .category-sales-total {
        text-align: right
    }

    .category-sales-total span {
        display: block;
        color: #8a958e;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .7px
    }

    .category-sales-total strong {
        display: block;
        margin-top: 2px;
        color: #0f6b2e;
        font-size: 18px;
        font-weight: 900
    }

    .category-sales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px
    }

    .category-sales-card {
        min-width: 0;
        padding: 12px;
        border: 1px solid #e6ece8;
        border-radius: 12px;
        background: linear-gradient(145deg, #fff, #f8fbf9);
        transition: .18s
    }

    .category-sales-card:hover {
        transform: translateY(-2px);
        border-color: #b9d6c1;
        box-shadow: 0 8px 20px rgba(25, 48, 35, .08)
    }

    .category-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between
    }

    .category-card-icon {
        width: 27px;
        height: 27px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: #eaf5ed;
        color: #0f6b2e;
        font-size: 10px;
        font-weight: 900
    }

    .category-card-rank {
        color: #9aa59f;
        font-size: 8px;
        font-weight: 800
    }

    .category-card-name {
        margin-top: 9px;
        color: #56645c;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .category-card-amount {
        display: block;
        margin-top: 4px;
        color: #26372d;
        font-size: 18px;
        font-weight: 900;
        letter-spacing: -.3px
    }

    .category-card-meta {
        display: flex;
        justify-content: space-between;
        gap: 5px;
        margin-top: 7px;
        color: #8a958e;
        font-size: 8px;
        font-weight: 700
    }

    .category-card-meta span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }

    .category-card-meta i {
        color: #0f6b2e;
        margin-right: 2px
    }

    .category-card-progress {
        height: 4px;
        margin-top: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: #e8eee9
    }

    .category-card-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: #0f6b2e
    }

    .category-empty {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 18px;
        border: 1px dashed #d5dfd8;
        border-radius: 12px;
        background: #fafcfb;
        color: #87938c
    }

    .category-empty>i {
        color: #0f6b2e;
        font-size: 18px
    }

    .category-empty strong,
    .category-empty span {
        display: block
    }

    .category-empty strong {
        color: #4d5c53;
        font-size: 11px
    }

    .category-empty span {
        margin-top: 2px;
        font-size: 9px
    }

    @media(max-width:700px) {
        .category-sales-section {
            padding: 13px
        }

        .category-sales-head {
            align-items: flex-start;
            flex-direction: column
        }

        .category-sales-total {
            text-align: left
        }

        .category-sales-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:430px) {
        .category-sales-grid {
            grid-template-columns: 1fr
        }
    }
    </style>

</head>

<body>

    <div class="app">

        <?php include '../includes/sidebar.php'; ?>

        <main class="main">



            <div class="content">

                <?php if ($error): ?>
                <div class="sales-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= e($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- PAGE HEADING -->
                <div class="sales-heading">
                    <div class="sales-title">
                        <div class="title-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>

                        <div>
                            <span class="eyebrow">SALES RECORDS</span>
                            <h1>All Sales</h1>
                            <p>
                                Search and review every completed order recorded by the restaurant.
                            </p>
                        </div>
                    </div>

                    <a href="orders.php" class="new-order-btn">
                        <i class="fa-solid fa-plus"></i>
                        New Order
                    </a>
                </div>

                <!-- FILTERS -->
                <section class="filter-panel">

                    <div class="filter-head">
                        <div class="filter-head-left">
                            <div class="filter-head-icon">
                                <i class="fa-solid fa-filter"></i>
                            </div>

                            <div>
                                <strong>Find Sales</strong>
                                <small>
                                    Filter completed sales by day, week or month.
                                </small>
                            </div>
                        </div>

                        <span class="range-label">
                            <?= e($rangeLabel) ?>
                        </span>
                    </div>

                    <form method="GET" class="filter-body">

                        <div>
                            <span class="field-label">Period</span>

                            <div class="period-buttons">

                                <button type="submit" name="period" value="day"
                                    class="period-btn <?= $period === 'day' ? 'active' : '' ?>">
                                    Day
                                </button>

                                <button type="submit" name="period" value="week"
                                    class="period-btn <?= $period === 'week' ? 'active' : '' ?>">
                                    Week
                                </button>

                                <button type="submit" name="period" value="month"
                                    class="period-btn <?= $period === 'month' ? 'active' : '' ?>">
                                    Month
                                </button>

                                <button type="submit" name="period" value="all"
                                    class="period-btn <?= $period === 'all' ? 'active' : '' ?>">
                                    All
                                </button>

                            </div>
                        </div>

                        <div>
                            <span class="field-label">
                                <?= $period === 'month' ? 'Select Month' : 'Select Date' ?>
                            </span>

                            <div class="filter-field">
                                <i class="fa-regular fa-calendar"></i>

                                <input type="<?= $period === 'month' ? 'month' : 'date' ?>" name="date" value="<?= e(
                                    $period === 'month'
                                        ? date('Y-m', strtotime($selectedDate))
                                        : $selectedDate
                                ) ?>">
                            </div>
                        </div>

                        <div>
                            <span class="field-label">Search Records</span>

                            <div class="filter-field">
                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input type="text" name="search" value="<?= e($search) ?>"
                                    placeholder="Search order, salesperson, payment, food or category...">
                            </div>
                        </div>

                        <div style="display:flex;gap:6px;">
                            <button type="submit" class="filter-submit">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                Search
                            </button>

                            <a href="sales.php" class="clear-filter">
                                <i class="fa-solid fa-rotate-left"></i>
                                Clear
                            </a>
                        </div>

                    </form>
                </section>

                <!-- SUMMARY -->
                <section class="metrics">

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon">
                                <i class="fa-solid fa-coins"></i>
                            </div>
                            Filtered Sales
                        </div>

                        <strong><?= ghMoney($filteredSales) ?></strong>

                        <small>
                            <?= e($rangeLabel) ?>
                        </small>
                    </article>

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            Orders
                        </div>

                        <strong><?= number_format($totalRecords) ?></strong>

                        <small>
                            Completed orders in this result
                        </small>
                    </article>

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon">
                                <i class="fa-solid fa-utensils"></i>
                            </div>
                            Items Sold
                        </div>

                        <strong><?= number_format($filteredItems) ?></strong>

                        <small>
                            Total food quantities sold
                        </small>
                    </article>

                    <article class="metric">
                        <div class="metric-top">
                            <div class="metric-icon">
                                <i class="fa-solid fa-chart-simple"></i>
                            </div>
                            Average Order
                        </div>

                        <strong><?= ghMoney($averageOrder) ?></strong>

                        <small>
                            Average completed order
                        </small>
                    </article>

                </section>

                <!-- SALES BY CATEGORY -->
                <section class="category-sales-section">
                    <div class="category-sales-head">
                        <div>
                            <span class="section-eyebrow">CATEGORY PERFORMANCE</span>
                            <h2>Sales by Category</h2>
                            <p>Revenue generated by each food category for <?= e($rangeLabel) ?>.</p>
                        </div>
                        <div class="category-sales-total">
                            <span>Total Sales</span>
                            <strong><?= ghMoney($filteredSales) ?></strong>
                        </div>
                    </div>
                    <?php if ($categorySales): ?>
                    <div class="category-sales-grid">
                        <?php foreach ($categorySales as $index => $category): ?>
                        <?php
                                $categoryAmount = (float)($category['sales'] ?? 0);
                                $categoryQty = (int)($category['quantity'] ?? 0);
                                $categoryPercent = $filteredSales > 0 ? ($categoryAmount / $filteredSales) * 100 : 0;
                                $categoryInitials = strtoupper(mb_substr(trim((string)$category['name']), 0, 1));
                            ?>
                        <article class="category-sales-card">
                            <div class="category-card-top">
                                <div class="category-card-icon"><?= e($categoryInitials) ?></div>
                                <span class="category-card-rank">#<?= $index + 1 ?></span>
                            </div>
                            <div class="category-card-name"><?= e($category['name']) ?></div>
                            <strong class="category-card-amount"><?= ghMoney($categoryAmount) ?></strong>
                            <div class="category-card-meta">
                                <span><i class="fa-solid fa-bowl-food"></i> <?= number_format($categoryQty) ?>
                                    items</span>
                                <span><?= number_format($categoryPercent, 1) ?>%</span>
                            </div>
                            <div class="category-card-progress"><span
                                    style="width:<?= min(100, max(0, $categoryPercent)) ?>%;"></span></div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="category-empty">
                        <i class="fa-solid fa-chart-pie"></i>
                        <div><strong>No category sales found</strong><span>There are no completed category sales for
                                this period.</span></div>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- PAYMENT SUMMARY -->
                <section class="payment-grid">
                    <article class="payment-card">
                        <div class="payment-label"><span class="payment-dot cash"></span>Cash</div><strong
                            class="payment-amount"><?= ghMoney($cashSales) ?></strong>
                        <div class="payment-progress"><span style="width:<?= min(100, $cashPercent) ?>%;"></span></div>
                        <small><?= number_format($cashPercent, 1) ?>% of filtered sales</small>
                    </article>
                    <article class="payment-card">
                        <div class="payment-label"><span class="payment-dot momo"></span>Mobile Money</div><strong
                            class="payment-amount"><?= ghMoney($momoSales) ?></strong>
                        <div class="payment-progress"><span style="width:<?= min(100, $momoPercent) ?>%;"></span></div>
                        <small><?= number_format($momoPercent, 1) ?>% of filtered sales</small>
                    </article>
                    <article class="payment-card">
                        <div class="payment-label"><span class="payment-dot card"></span>Card</div><strong
                            class="payment-amount"><?= ghMoney($cardSales) ?></strong>
                        <div class="payment-progress"><span style="width:<?= min(100, $cardPercent) ?>%;"></span></div>
                        <small><?= number_format($cardPercent, 1) ?>% of filtered sales</small>
                    </article>
                </section>

                <!-- SALES RECORDS -->
                <section class="sales-panel">

                    <div class="sales-panel-head">

                        <div class="sales-panel-title">

                            <div class="sales-panel-icon">
                                <i class="fa-solid fa-table-list"></i>
                            </div>

                            <div>
                                <h2>Sales Records</h2>

                                <p>
                                    Completed orders and their payment details.
                                </p>
                            </div>

                        </div>

                        <span class="record-count">
                            <?= number_format($totalRecords) ?>
                            <?= $totalRecords === 1 ? 'record' : 'records' ?>
                        </span>

                    </div>

                    <div class="table-wrap">

                        <table class="sales-table">

                            <thead>
                                <tr>
                                    <th>ORDER</th>
                                    <th>ITEMS</th>
                                    <th>SALESPERSON</th>
                                    <th>TYPE</th>
                                    <th>PAYMENT</th>
                                    <th>TOTAL</th>
                                    <th>DATE & TIME</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($records): ?>

                                <?php foreach ($records as $record): ?>

                                <tr>

                                    <td>
                                        <div class="order-cell">

                                            <div class="order-icon">
                                                <i class="fa-solid fa-receipt"></i>
                                            </div>

                                            <div>
                                                <strong>
                                                    <?= e(
                                                        $record['order_number']
                                                            ?: '#' . $record['id']
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    Order #<?= (int)$record['id'] ?>
                                                </small>
                                            </div>

                                        </div>
                                    </td>

                                    <td>
                                        <div class="items-cell">
                                            <?= e($record['items']) ?>
                                        </div>

                                        <small style="display:block;margin-top:4px;color:#aaa19a;font-size:6.5px;">
                                            <?= number_format((int)$record['item_count']) ?>
                                            <?= (int)$record['item_count'] === 1 ? 'item' : 'items' ?>
                                        </small>
                                    </td>

                                    <td>
                                        <div class="salesperson-cell">
                                            <strong>
                                                <?= e($record['salesperson']) ?>
                                            </strong>

                                            <small>
                                                <?= e(
                                                    $_SESSION['role'] ?? 'User'
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="type-badge">
                                            <i class="fa-solid <?= $record['order_type'] === 'Dine In'
                                                ? 'fa-chair'
                                                : 'fa-bag-shopping' ?>"></i>

                                            <?= e($record['order_type']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="paid-badge">
                                            <i class="fa-solid fa-circle-check"></i>
                                            Completed
                                        </span>

                                        <span class="payment-method">
                                            <?= e($record['payment_method']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="sale-total">
                                            <?= ghMoney($record['paid_amount']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="sale-date">
                                            <?= e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime($record['paid_at'])
                                                )
                                            ) ?>
                                        </span>

                                        <small>
                                            <?= e($record['salesperson']) ?>
                                        </small>
                                    </td>

                                </tr>

                                <?php endforeach; ?>

                                <?php else: ?>

                                <tr>
                                    <td colspan="7" class="empty-sales">

                                        <div class="empty-sales-icon">
                                            <i class="fa-solid fa-receipt"></i>
                                        </div>

                                        <strong>
                                            No completed sales found
                                        </strong>

                                        <span>
                                            No completed orders match the selected
                                            period or search criteria.
                                        </span>

                                    </td>
                                </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                    <!-- BOOTSTRAP PAGINATION -->
                    <?php if ($totalRecords > 0 && $totalPages > 1): ?>

                    <div class="pagination-wrap">

                        <div class="pagination-info">

                            Showing

                            <strong>
                                <?= number_format($offset + 1) ?>
                            </strong>

                            to

                            <strong>
                                <?= number_format(
                                    min($offset + $perPage, $totalRecords)
                                ) ?>
                            </strong>

                            of

                            <strong>
                                <?= number_format($totalRecords) ?>
                            </strong>

                            records

                        </div>

                        <nav aria-label="Sales records pagination">

                            <ul class="pagination">

                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                                    <?php if ($page > 1): ?>

                                    <a class="page-link" href="?<?= e(
                                                salesQuery(['page' => $page - 1])
                                            ) ?>" aria-label="Previous">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>

                                    <?php else: ?>

                                    <span class="page-link">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </span>

                                    <?php endif; ?>

                                </li>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                ?>

                                <?php if ($startPage > 1): ?>

                                <li class="page-item">
                                    <a class="page-link" href="?<?= e(
                                                salesQuery(['page' => 1])
                                            ) ?>">
                                        1
                                    </a>
                                </li>

                                <?php if ($startPage > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">…</span>
                                </li>
                                <?php endif; ?>

                                <?php endif; ?>

                                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>

                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">

                                    <a class="page-link" href="?<?= e(
                                                salesQuery(['page' => $p])
                                            ) ?>">
                                        <?= $p ?>
                                    </a>

                                </li>

                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>

                                <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">…</span>
                                </li>
                                <?php endif; ?>

                                <li class="page-item">

                                    <a class="page-link" href="?<?= e(
                                                salesQuery(['page' => $totalPages])
                                            ) ?>">
                                        <?= $totalPages ?>
                                    </a>

                                </li>

                                <?php endif; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                                    <?php if ($page < $totalPages): ?>

                                    <a class="page-link" href="?<?= e(
                                                salesQuery(['page' => $page + 1])
                                            ) ?>" aria-label="Next">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>

                                    <?php else: ?>

                                    <span class="page-link">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </span>

                                    <?php endif; ?>

                                </li>

                            </ul>

                        </nav>

                    </div>

                    <?php endif; ?>

                </section>

            </div>

        </main>

    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {

        const periodButtons = document.querySelectorAll(".period-btn");
        const dateInput = document.querySelector('input[name="date"]');
        const filterForm = document.querySelector(".filter-body");

        periodButtons.forEach(button => {

            button.addEventListener("click", function() {

                const selectedPeriod = this.value;

                /*
                 * Buttons submit immediately, but update the date field's
                 * input type first so Week/Month selection behaves correctly.
                 */
                if (dateInput) {

                    if (selectedPeriod === "month") {

                        dateInput.type = "month";

                        if (dateInput.value.length === 10) {
                            dateInput.value = dateInput.value.substring(0, 7);
                        }

                    } else {

                        dateInput.type = "date";

                        if (!dateInput.value) {
                            const now = new Date();
                            const year = now.getFullYear();
                            const month = String(now.getMonth() + 1).padStart(2, "0");
                            const day = String(now.getDate()).padStart(2, "0");

                            dateInput.value = `${year}-${month}-${day}`;
                        }
                    }
                }
            });

        });

        /*
         * Month input arrives as YYYY-MM. Convert it to the first day of
         * the selected month before submitting, so PHP can use one date
         * format for all periods.
         */
        if (filterForm && dateInput) {

            filterForm.addEventListener("submit", function() {

                const activePeriod =
                    document.querySelector(".period-btn.active")?.value || "day";

                if (
                    activePeriod === "month" &&
                    /^\d{4}-\d{2}$/.test(dateInput.value)
                ) {
                    dateInput.type = "hidden";
                    dateInput.value += "-01";
                }

            });

        }

    });
    </script>

</body>

</html>