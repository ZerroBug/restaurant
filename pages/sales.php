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
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$allowedPeriods = ['all', 'day', 'week', 'month', 'range'];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'day';
}

/* If both From and To are supplied, treat the request as a custom date range. */
if (validDate($fromDate) && validDate($toDate)) {
    $period = 'range';
}

if (!validDate($selectedDate)) {
    $selectedDate = date('Y-m-d');
}

if (!validDate($fromDate) || !validDate($toDate)) {
    $fromDate = '';
    $toDate = '';
}

$rangeStart = null;
$rangeEnd = null;
$rangeLabel = 'All completed sales';

if ($period === 'range' && $fromDate !== '' && $toDate !== '') {
    if ($fromDate > $toDate) {
        [$fromDate, $toDate] = [$toDate, $fromDate];
    }
    $rangeStart = $fromDate;
    $rangeEnd = $toDate;
    $rangeLabel = date('d M Y', strtotime($rangeStart)) . ' – ' . date('d M Y', strtotime($rangeEnd));
} elseif ($period === 'day') {
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
$where = [];

$params = [];

if ($rangeStart !== null && $rangeEnd !== null) {
    $where[] = "paid.created_at >= :range_start
                AND paid.created_at < DATE_ADD(:range_end, INTERVAL 1 DAY)";

    $params[':range_start'] = $rangeStart;
    $params[':range_end'] = $rangeEnd;
}

if ($categoryId > 0) {
    $where[] = "EXISTS (
        SELECT 1
        FROM order_items ocf
        INNER JOIN food_menu fcf ON fcf.id = ocf.food_id
        WHERE ocf.order_id = o.id
          AND fcf.category_id = :category_id
    )";
    $params[':category_id'] = $categoryId;
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
            INNER JOIN food_menu fsi ON fsi.id = osi.food_id
            LEFT JOIN categories csi ON csi.id = fsi.category_id
            WHERE osi.order_id = o.id
              AND (fsi.name LIKE :search_food OR COALESCE(csi.name, '') LIKE :search_category)
        )
    )";

    $searchValue = '%' . $search . '%';

    $params[':search_order'] = $searchValue;
    $params[':search_id'] = $searchValue;
    $params[':search_name'] = $searchValue;
    $params[':search_username'] = $searchValue;
    $params[':search_payment'] = $searchValue;
    $params[':search_food'] = $searchValue;
    $params[':search_category'] = $searchValue;
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
$categorySales = [];
$categories = [];
$reportSales = 0;
$reportOrders = 0;
$reportItems = 0;

try {
    $categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
     | PERIOD REPORT TOTALS (independent of table search/category filter)
     |--------------------------------------------------------------------------
     */
    $reportWhere = ["EXISTS (SELECT 1 FROM payments prc WHERE prc.order_id = orp.id AND prc.status = 'Completed')"];
    $reportParams = [];
    if ($rangeStart !== null && $rangeEnd !== null) {
        $reportWhere[] = "prp.created_at >= :report_start AND prp.created_at < DATE_ADD(:report_end, INTERVAL 1 DAY)";
        $reportParams[':report_start'] = $rangeStart;
        $reportParams[':report_end'] = $rangeEnd;
    }
    $reportWhereSql = 'WHERE ' . implode(' AND ', $reportWhere);
    $reportPaymentSubquery = "SELECT p1.order_id, SUM(p1.amount) AS amount, MAX(p1.created_at) AS created_at FROM payments p1 WHERE p1.status = 'Completed' GROUP BY p1.order_id";
    $reportSql = "SELECT
                    COALESCE(SUM(prp.amount),0) sales_total,
                    COUNT(orp.id) order_total,
                    COALESCE(SUM(ri.total_items),0) item_total
                  FROM orders orp
                  INNER JOIN ($reportPaymentSubquery) prp ON prp.order_id = orp.id
                  LEFT JOIN (
                      SELECT order_id, SUM(quantity) total_items
                      FROM order_items
                      GROUP BY order_id
                  ) ri ON ri.order_id = orp.id
                  $reportWhereSql";
    $stmt = $pdo->prepare($reportSql);
    foreach ($reportParams as $key=>$value) $stmt->bindValue($key,$value,PDO::PARAM_STR);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $reportSales = (float)($report['sales_total'] ?? 0);
    $reportOrders = (int)($report['order_total'] ?? 0);
    $reportItems = (int)($report['item_total'] ?? 0);

    /*
     |--------------------------------------------------------------------------
     | SALES BY CATEGORY FOR SELECTED PERIOD
     |--------------------------------------------------------------------------
     */
    $categorySales = [];
    $categoryPaymentSubquery = "
        SELECT order_id, MAX(created_at) AS created_at
        FROM payments
        WHERE status = 'Completed'
        GROUP BY order_id
    ";

    $categorySql = "
        SELECT
            c.id,
            c.name,
            COALESCE(SUM(oi.quantity), 0) AS quantity,
            COALESCE(SUM(oi.subtotal), 0) AS sales
        FROM categories c
        LEFT JOIN food_menu fm ON fm.category_id = c.id
        LEFT JOIN order_items oi ON oi.food_id = fm.id
        LEFT JOIN orders oc ON oc.id = oi.order_id
        LEFT JOIN ($categoryPaymentSubquery) pcat ON pcat.order_id = oc.id
        WHERE c.status = 'Active'
    ";

    if ($rangeStart !== null && $rangeEnd !== null) {
        $categorySql .= "
          AND (
              (pcat.created_at >= :cat_start AND pcat.created_at < DATE_ADD(:cat_end, INTERVAL 1 DAY))
              OR oi.id IS NULL
          )
        ";
    }

    $categorySql .= "
        GROUP BY c.id, c.name
        ORDER BY sales DESC, c.name ASC
    ";

    $stmt = $pdo->prepare($categorySql);
    if ($rangeStart !== null && $rangeEnd !== null) {
        $stmt->bindValue(':cat_start', $rangeStart, PDO::PARAM_STR);
        $stmt->bindValue(':cat_end', $rangeEnd, PDO::PARAM_STR);
    }
    $stmt->execute();
    $categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        INNER JOIN ($paymentSubquery) paid
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
            COALESCE(SUM(CASE WHEN :summary_category_id = 0 THEN paid.amount ELSE COALESCE(cat_items.category_sales, 0) END), 0) AS sales_total,
            COALESCE(SUM(CASE WHEN :summary_category_id = 0 THEN items.total_items ELSE COALESCE(cat_items.category_items, 0) END), 0) AS item_total,
            COALESCE(SUM(paid.amount), 0) AS collected_total
        FROM orders o
        LEFT JOIN users u
            ON u.id = o.user_id
        INNER JOIN ($paymentSubquery) paid
            ON paid.order_id = o.id
        LEFT JOIN (
            SELECT order_id, SUM(quantity) AS total_items
            FROM order_items
            GROUP BY order_id
        ) items ON items.order_id = o.id
        LEFT JOIN (
            SELECT oi.order_id, SUM(oi.subtotal) AS category_sales, SUM(oi.quantity) AS category_items
            FROM order_items oi
            INNER JOIN food_menu fm ON fm.id = oi.food_id
            WHERE fm.category_id = :summary_category_id
            GROUP BY oi.order_id
        ) cat_items ON cat_items.order_id = o.id
        $whereSql
    ";

    $stmt = $pdo->prepare($summarySql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':summary_category_id', $categoryId, PDO::PARAM_INT);

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
        INNER JOIN ($paymentSubquery) paid
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
                CASE
                    WHEN :record_category_id = 0 THEN paid.amount
                    ELSE SUM(oi.subtotal)
                END,
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
            ) AS item_count,

            COALESCE(
                GROUP_CONCAT(
                    DISTINCT c.name
                    ORDER BY c.name
                    SEPARATOR ', '
                ),
                'Uncategorised'
            ) AS categories

        FROM orders o

        LEFT JOIN users u
            ON u.id = o.user_id

        INNER JOIN ($paymentSubquery) paid
            ON paid.order_id = o.id

        LEFT JOIN order_items oi
            ON oi.order_id = o.id
            AND (
                :record_category_id = 0
                OR EXISTS (
                    SELECT 1
                    FROM food_menu fmx
                    WHERE fmx.id = oi.food_id
                      AND fmx.category_id = :record_category_id
                )
            )

        LEFT JOIN food_menu fm
            ON fm.id = oi.food_id

        LEFT JOIN categories c
            ON c.id = fm.category_id

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
    $stmt->bindValue(':record_category_id', $categoryId, PDO::PARAM_INT);

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
        'from'   => $_GET['from'] ?? '',
        'to'     => $_GET['to'] ?? '',
        'category_id' => $_GET['category_id'] ?? '',
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
        grid-template-columns: minmax(280px, 1.2fr) minmax(150px, .7fr) minmax(280px, 1.2fr) minmax(170px, .8fr) minmax(220px, 1fr) auto;
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

    /* -------------------------------------------------------------------------
       SALES REPORT / CATEGORY CARDS
       ------------------------------------------------------------------------- */
    .date-range-fields .range-inputs {
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .range-inputs input {
        width: 100%;
        height: 42px;
        padding: 0 11px;
        border: 1px solid #e3e7ec;
        border-radius: 10px;
        color: #344054;
        font-size: 11px;
        outline: none;
        background: #fff;
    }

    .range-inputs input:focus {
        border-color: #6f8cff;
        box-shadow: 0 0 0 3px rgba(111, 140, 255, .10);
    }

    .range-inputs span {
        color: #98a2b3;
        font-size: 10px;
        font-weight: 700;
    }

    .filter-field select {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        color: #344054;
        font-size: 11px;
        font-weight: 600;
    }

    .filter-actions {
        display: flex;
        gap: 7px;
    }

    .report-section {
        margin-bottom: 20px;
        border: 1px solid #e7eaf0;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 14px 40px rgba(16, 24, 40, .07);
        overflow: hidden;
    }

    .report-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        padding: 21px 22px;
        border-bottom: 1px solid #edf0f4;
        background: linear-gradient(180deg, #ffffff, #fbfcff);
    }

    .report-eyebrow {
        color: #f58220;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.4px;
    }

    .report-heading h2 {
        margin: 4px 0 0;
        font-size: 19px;
        font-weight: 800;
        color: #182230;
        letter-spacing: -.3px;
    }

    .report-heading p {
        margin: 5px 0 0;
        color: #8b95a5;
        font-size: 10px;
    }

    .report-total {
        min-width: 210px;
        padding: 13px 16px;
        border-radius: 14px;
        background: linear-gradient(135deg, #fff4e8, #fff9f4);
        text-align: right;
        border: 1px solid #ffe2ca;
    }

    .report-total span,
    .report-total small {
        display: block;
        color: #8b95a5;
        font-size: 9px;
    }

    .report-total strong {
        display: block;
        margin: 2px 0;
        color: #d95e08;
        font-size: 22px;
        font-weight: 800;
    }

    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 13px;
        padding: 18px 20px 20px;
    }

    .category-card {
        position: relative;
        overflow: hidden;
        min-height: 132px;
        padding: 15px;
        border: 0;
        border-radius: 16px;
        color: #fff;
        transition: transform .18s ease, box-shadow .18s ease;
        box-shadow: 0 10px 24px rgba(16, 24, 40, .10);
    }

    .category-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(16, 24, 40, .15);
    }

    .category-card::after {
        content: "";
        position: absolute;
        width: 100px;
        height: 100px;
        right: -35px;
        bottom: -50px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .12);
    }

    .category-card-top {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .category-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        color: #fff;
        background: rgba(255, 255, 255, .18);
        font-size: 12px;
    }

    .category-card-top span {
        color: rgba(255, 255, 255, .82);
        font-size: 9px;
        font-weight: 800;
    }

    .category-name {
        position: relative;
        z-index: 1;
        color: rgba(255, 255, 255, .86);
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .45px;
    }

    .category-card strong {
        position: relative;
        z-index: 1;
        display: block;
        margin-top: 3px;
        color: #fff;
        font-size: 21px;
        font-weight: 800;
        letter-spacing: -.4px;
    }

    .category-card small {
        position: relative;
        z-index: 1;
        display: block;
        margin-top: 3px;
        color: rgba(255, 255, 255, .76);
        font-size: 9px;
    }

    .category-bar {
        position: relative;
        z-index: 1;
        height: 5px;
        margin-top: 11px;
        border-radius: 99px;
        background: rgba(255, 255, 255, .18);
        overflow: hidden;
    }

    .category-bar span {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: #fff;
    }

    .summary-card .category-icon {
        color: #fff;
        background: rgba(255, 255, 255, .18);
    }

    .total-sales-card {
        background: linear-gradient(135deg, #ff8a2a, #e95d08);
    }

    .total-orders-card {
        background: linear-gradient(135deg, #1fb978, #0d8053);
    }

    .category-grid .category-card:nth-child(4n+3) {
        background: linear-gradient(135deg, #7d5bd1, #5b3ca4);
    }

    .category-grid .category-card:nth-child(4n+4) {
        background: linear-gradient(135deg, #4d83df, #315eb1);
    }

    .category-grid .category-card:nth-child(4n+5) {
        background: linear-gradient(135deg, #e2a51a, #c47d00);
    }

    .category-grid .category-card:nth-child(4n+6) {
        background: linear-gradient(135deg, #e45b76, #c83e5b);
    }

    .category-empty {
        grid-column: 1/-1;
        padding: 35px;
        text-align: center;
        color: #98a2b3;
        font-size: 11px;
    }

    .category-list-cell {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        max-width: 210px;
    }

    .category-badge {
        display: inline-flex;
        padding: 5px 8px;
        border-radius: 20px;
        color: #a4510b;
        background: #fff1e6;
        font-size: 8px;
        font-weight: 800;
        white-space: nowrap;
    }

    /* Payment cards: colourful but still subordinate to the main report. */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .payment-card {
        position: relative;
        overflow: hidden;
        padding: 14px 16px;
        border: 1px solid #e9e4df;
        border-radius: 14px;
        color: #3d3833;
        background: #fff;
        box-shadow: 0 5px 16px rgba(16, 24, 40, .055);
    }

    .payment-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(16, 24, 40, .08);
    }

    .payment-label {
        color: #6f675f;
        font-size: 10px;
        font-weight: 700;
    }

    .payment-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #a9a19a !important;
    }

    .payment-card:nth-child(1) .payment-dot {
        background: #6f675f !important;
    }

    .payment-card:nth-child(2) .payment-dot {
        background: #8c837a !important;
    }

    .payment-card:nth-child(3) .payment-dot {
        background: #aaa19a !important;
    }

    .payment-amount {
        margin-top: 8px;
        color: #2f2a26;
        font-size: 18px;
        font-weight: 800;
    }

    .payment-progress {
        background: #eee9e5;
    }

    .payment-progress span {
        background: #817870 !important;
    }

    .payment-card small {
        color: #9a928b;
        font-size: 8px;
    }

    @media (max-width: 1200px) {
        .filter-body {
            grid-template-columns: 1fr 1fr;
        }

        .filter-actions {
            width: 100%;
        }

        .filter-actions>* {
            flex: 1;
        }

        .report-heading {
            align-items: flex-start;
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

    /* Compact report cards */
    .category-grid {
        grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
        gap: 9px;
        padding: 12px 14px 14px;
    }

    .category-card {
        min-height: 88px;
        padding: 10px 11px;
        border-radius: 11px;
        box-shadow: 0 6px 16px rgba(16, 24, 40, .08);
    }

    .category-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 9px 20px rgba(16, 24, 40, .11);
    }

    .category-card-top {
        margin-bottom: 6px;
    }

    .category-icon {
        width: 25px;
        height: 25px;
        border-radius: 7px;
        font-size: 9px;
    }

    .category-card-top span {
        font-size: 7px;
    }

    .category-name {
        font-size: 8px;
        letter-spacing: .25px;
    }

    .category-card strong {
        margin-top: 2px;
        font-size: 15px;
        letter-spacing: -.2px;
    }

    .category-card small {
        margin-top: 2px;
        font-size: 7px;
    }

    .category-bar {
        height: 3px;
        margin-top: 7px;
    }

    .report-total {
        min-width: 155px;
        padding: 9px 11px;
        border-radius: 10px;
    }

    .report-total strong {
        font-size: 17px;
    }

    .report-total span,
    .report-total small {
        font-size: 7px;
    }

    .export-pdf-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 36px;
        padding: 0 12px;
        border: 0;
        border-radius: 9px;
        color: #fff;
        background: linear-gradient(135deg, #d94141, #b92323);
        font-size: 9px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 6px 14px rgba(185, 35, 35, .18);
    }

    .export-pdf-btn:hover {
        filter: brightness(.96);
        transform: translateY(-1px);
    }

    @media print {

        .sidebar,
        .filter-panel,
        .new-order-btn,
        .export-pdf-btn,
        .payment-grid,
        .pagination-wrap {
            display: none !important;
        }

        .main {
            margin-left: 0 !important;
        }

        .content {
            padding: 0 !important;
        }

        .report-section,
        .sales-panel {
            box-shadow: none !important;
        }
    }

    /* TABLE ACTIONS / EXPORT */
    .sales-panel-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap
    }

    .table-export-btn,
    .delete-order-btn {
        border: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border-radius: 8px;
        font-family: inherit;
        font-weight: 700;
        cursor: pointer;
        transition: .18s ease
    }

    .table-export-btn {
        padding: 8px 12px;
        color: #fff;
        background: linear-gradient(135deg, #dc650e, #f58220);
        font-size: 10px;
        box-shadow: 0 5px 14px rgba(245, 130, 32, .18)
    }

    .table-export-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 18px rgba(245, 130, 32, .25)
    }

    .delete-order-btn {
        padding: 6px 9px;
        color: #b42318;
        background: #fff1f0;
        border: 1px solid #ffd2ce;
        font-size: 8px
    }

    .delete-order-btn:hover {
        color: #fff;
        background: #d94b4b;
        border-color: #d94b4b
    }

    .table-filter-total {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 10px;
        border-radius: 9px;
        background: #f5faf7;
        border: 1px solid #dcefe4;
        color: #28724e;
        font-size: 9px;
        font-weight: 700
    }

    .table-filter-total strong {
        font-size: 12px;
        color: #165c3d;
        letter-spacing: -.2px
    }

    .delete-confirm-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(24, 20, 16, .5);
        backdrop-filter: blur(4px)
    }

    .delete-confirm-overlay.show {
        display: flex
    }

    .delete-confirm-modal {
        width: min(390px, 100%);
        padding: 24px;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .2);
        text-align: center
    }

    .delete-confirm-icon {
        width: 50px;
        height: 50px;
        margin: 0 auto 12px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        color: #c62828;
        background: #fff0ef;
        font-size: 19px
    }

    .delete-confirm-modal h3 {
        margin: 0;
        color: #2f2925;
        font-size: 17px;
        font-weight: 800
    }

    .delete-confirm-modal p {
        margin: 8px 0 18px;
        color: #81776f;
        font-size: 10px;
        line-height: 1.6
    }

    .delete-confirm-actions {
        display: flex;
        gap: 8px;
        justify-content: center
    }

    .delete-cancel,
    .delete-submit {
        border: 0;
        border-radius: 9px;
        padding: 9px 15px;
        font-family: inherit;
        font-size: 9px;
        font-weight: 800;
        cursor: pointer
    }

    .delete-cancel {
        color: #665e57;
        background: #f3f0ed
    }

    .delete-submit {
        color: #fff;
        background: #d94b4b
    }

    .delete-submit:disabled {
        opacity: .6;
        cursor: not-allowed
    }

    @media print {
        body {
            background: #fff !important
        }

        .app>.main {
            margin-left: 0 !important
        }

        .content>*:not(.sales-panel) {
            display: none !important
        }

        .sales-panel {
            display: block !important;
            box-shadow: none !important;
            border: 1px solid #ddd !important
        }

        .sales-panel-head {
            border-bottom: 1px solid #ddd !important
        }

        .sales-panel-actions,
        .pagination-wrap,
        .delete-order-btn {
            display: none !important
        }

        .sales-table {
            min-width: 0 !important
        }

        .sales-table th,
        .sales-table td {
            font-size: 9px !important;
            padding: 8px !important
        }

        .sales-panel-head h2 {
            font-size: 16px !important
        }

        .sales-panel-head p {
            font-size: 9px !important
        }

        .table-filter-total {
            display: flex !important
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

                    <div style="display:flex;align-items:center;gap:8px;">
                        <a href="orders.php" class="new-order-btn">
                            <i class="fa-solid fa-plus"></i>
                            New Order
                        </a>
                    </div>
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
                            <span class="field-label">Report Period</span>
                            <div class="period-buttons">
                                <?php foreach (['day'=>'Day','week'=>'Week','month'=>'Month','all'=>'All','range'=>'Custom'] as $value=>$label): ?>
                                <button type="submit" name="period" value="<?= $value ?>"
                                    class="period-btn <?= $period === $value ? 'active' : '' ?>">
                                    <?= $label ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <span class="field-label">Date / Month</span>
                            <div class="filter-field">
                                <i class="fa-regular fa-calendar"></i>
                                <input type="<?= $period === 'month' ? 'month' : 'date' ?>" name="date"
                                    value="<?= e($period === 'month' ? date('Y-m', strtotime($selectedDate)) : $selectedDate) ?>">
                            </div>
                        </div>

                        <div class="date-range-fields">
                            <span class="field-label">Between Dates</span>
                            <div class="range-inputs">
                                <input type="date" name="from" value="<?= e($fromDate) ?>" aria-label="From date">
                                <span>to</span>
                                <input type="date" name="to" value="<?= e($toDate) ?>" aria-label="To date">
                            </div>
                        </div>

                        <div>
                            <span class="field-label">Category</span>
                            <div class="filter-field">
                                <i class="fa-solid fa-layer-group"></i>
                                <select name="category_id">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>"
                                        <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                                        <?= e($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <span class="field-label">Search Sales</span>
                            <div class="filter-field">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" name="search" value="<?= e($search) ?>"
                                    placeholder="Search order, food, category, staff or payment...">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="filter-submit"><i class="fa-solid fa-magnifying-glass"></i>
                                Search</button>
                            <a href="sales.php" class="clear-filter"><i class="fa-solid fa-rotate-left"></i> Clear</a>
                        </div>

                    </form>
                </section>

                <!-- SALES REPORT -->
                <section class="report-section">
                    <div class="report-heading">
                        <div>
                            <span class="report-eyebrow">SALES REPORT</span>
                            <h2>Total Sales by Category</h2>
                            <p><?= e($rangeLabel) ?> · Completed payments only</p>
                        </div>
                        <div class="report-total">
                            <span>Total Sales</span>
                            <strong><?= ghMoney($reportSales) ?></strong>
                            <small><?= number_format($reportOrders) ?> orders · <?= number_format($reportItems) ?>
                                items</small>
                        </div>
                    </div>

                    <div class="category-grid">
                        <article class="category-card summary-card total-sales-card">
                            <div class="category-card-top">
                                <div class="category-icon"><i class="fa-solid fa-coins"></i></div>
                                <span>ALL CATEGORIES</span>
                            </div>
                            <div class="category-name">TOTAL SALES</div>
                            <strong><?= ghMoney($reportSales) ?></strong>
                            <small><?= e($rangeLabel) ?></small>
                            <div class="category-bar"><span style="width:100%"></span></div>
                        </article>

                        <article class="category-card summary-card total-orders-card">
                            <div class="category-card-top">
                                <div class="category-icon"><i class="fa-solid fa-receipt"></i></div>
                                <span>COMPLETED</span>
                            </div>
                            <div class="category-name">TOTAL ORDERS</div>
                            <strong><?= number_format($reportOrders) ?></strong>
                            <small>Completed orders in <?= e($rangeLabel) ?></small>
                            <div class="category-bar"><span style="width:100%"></span></div>
                        </article>

                        <?php if ($categorySales): ?>
                        <?php foreach ($categorySales as $cat): ?>
                        <?php $catPercent = $reportSales > 0 ? ((float)$cat['sales'] / $reportSales) * 100 : 0; ?>
                        <article class="category-card">
                            <div class="category-card-top">
                                <div class="category-icon"><i class="fa-solid fa-utensils"></i></div>
                                <span><?= number_format($catPercent, 1) ?>%</span>
                            </div>
                            <div class="category-name"><?= e($cat['name']) ?></div>
                            <strong><?= ghMoney($cat['sales']) ?></strong>
                            <small><?= number_format((int)$cat['quantity']) ?> items sold</small>
                            <div class="category-bar"><span style="width:<?= min(100, $catPercent) ?>%"></span></div>
                        </article>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="category-empty">No category sales found for <?= e($rangeLabel) ?>.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- PAYMENT SUMMARY -->
                <section class="payment-grid">

                    <article class="payment-card">
                        <div class="payment-label">
                            <span class="payment-dot cash"></span>
                            Cash
                        </div>

                        <strong class="payment-amount">
                            <?= ghMoney($cashSales) ?>
                        </strong>

                        <div class="payment-progress">
                            <span style="width:<?= min(100, $cashPercent) ?>%;"></span>
                        </div>

                        <small>
                            <?= number_format($cashPercent, 1) ?>% of filtered sales
                        </small>
                    </article>

                    <article class="payment-card">
                        <div class="payment-label">
                            <span class="payment-dot momo"></span>
                            Mobile Money
                        </div>

                        <strong class="payment-amount">
                            <?= ghMoney($momoSales) ?>
                        </strong>

                        <div class="payment-progress">
                            <span style="width:<?= min(100, $momoPercent) ?>%;"></span>
                        </div>

                        <small>
                            <?= number_format($momoPercent, 1) ?>% of filtered sales
                        </small>
                    </article>

                    <article class="payment-card">
                        <div class="payment-label">
                            <span class="payment-dot card"></span>
                            Card
                        </div>

                        <strong class="payment-amount">
                            <?= ghMoney($cardSales) ?>
                        </strong>

                        <div class="payment-progress">
                            <span style="width:<?= min(100, $cardPercent) ?>%;"></span>
                        </div>

                        <small>
                            <?= number_format($cardPercent, 1) ?>% of filtered sales
                        </small>
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

                        <div class="sales-panel-actions">
                            <div class="table-filter-total">
                                <span>Search Total</span>
                                <strong><?= ghMoney($filteredSales) ?></strong>
                                <span>· <?= number_format($totalRecords) ?> matching records</span>
                            </div>
                            <button type="button" class="table-export-btn" onclick="exportSalesTable()">
                                <i class="fa-solid fa-file-pdf"></i> Export PDF
                            </button>
                        </div>

                    </div>

                    <div class="table-wrap">

                        <table class="sales-table">

                            <thead>
                                <tr>
                                    <th>ORDER</th>
                                    <th>ITEMS</th>
                                    <th>CATEGORY</th>
                                    <th>SALESPERSON</th>
                                    <th>TYPE</th>
                                    <th>PAYMENT</th>
                                    <th>TOTAL</th>
                                    <th>DATE & TIME</th>
                                    <th>ACTION</th>
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
                                        <div class="category-list-cell">
                                            <?php foreach (array_filter(array_map('trim', explode(',', (string)$record['categories']))) as $recordCategory): ?>
                                            <span class="category-badge"><?= e($recordCategory) ?></span>
                                            <?php endforeach; ?>
                                        </div>
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

                                    <td>
                                        <button type="button" class="delete-order-btn"
                                            data-order-id="<?= (int)$record['id'] ?>"
                                            data-order-number="<?= e($record['order_number'] ?: ('#' . $record['id'])) ?>"
                                            title="Delete order">
                                            <i class="fa-solid fa-trash"></i>
                                            Delete
                                        </button>
                                    </td>

                                </tr>

                                <?php endforeach; ?>

                                <?php else: ?>

                                <tr>
                                    <td colspan="9" class="empty-sales">

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


    <div class="delete-confirm-overlay" id="deleteOrderOverlay" aria-hidden="true">
        <div class="delete-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="deleteOrderTitle">
            <div class="delete-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
            <h3 id="deleteOrderTitle">Delete Order?</h3>
            <p>This will permanently delete <strong id="deleteOrderNumber"></strong>, including its order items and
                payment record. This cannot be undone.</p>
            <div class="delete-confirm-actions">
                <button type="button" class="delete-cancel" id="cancelDeleteOrder">Cancel</button>
                <button type="button" class="delete-submit" id="confirmDeleteOrder"><i
                        class="fa-solid fa-trash me-1"></i> Delete Order</button>
            </div>
        </div>
    </div>

    <script>
    function exportSalesTable() {
        const table = document.querySelector('.sales-table');
        if (!table) return;
        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        if (!printWindow) return;
        const clone = table.cloneNode(true);
        clone.querySelectorAll('tr').forEach(row => {
            if (row.lastElementChild) row.lastElementChild.remove();
        });
        const title = <?= json_encode('Sales Transactions — ' . $rangeLabel) ?>;
        const total = <?= json_encode(ghMoney($filteredSales)) ?>;
        printWindow.document.write(
            `<!doctype html><html><head><title>${title}</title><style>body{font-family:Arial,sans-serif;padding:28px;color:#222}h1{font-size:20px;margin:0 0 4px}p{font-size:11px;color:#666;margin:0 0 16px}.total{display:inline-block;padding:8px 12px;background:#f2f8f4;border:1px solid #dcefe4;border-radius:8px;font-weight:700;margin-bottom:18px}table{width:100%;border-collapse:collapse;font-size:10px}th{background:#f4f4f4;text-align:left;padding:8px;border-bottom:1px solid #ccc}td{padding:8px;border-bottom:1px solid #e5e5e5} .category-badge{display:inline-block;margin:2px;padding:3px 6px;background:#f5f5f5;border-radius:5px}</style></head><body><h1>Sales Transactions</h1><p>${title}</p><div class="total">Filtered Table Total: ${total}</div>${clone.outerHTML}</body></html>`
            );
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    document.addEventListener("DOMContentLoaded", function() {

        const periodButtons = document.querySelectorAll(".period-btn");
        const dateInput = document.querySelector('input[name="date"]');
        const filterForm = document.querySelector(".filter-body");

        periodButtons.forEach(button => {

            button.addEventListener("click", function() {

                const selectedPeriod = this.value;

                /* Fixed period buttons clear custom dates so they do not
                   accidentally override Day/Week/Month/All. */
                if (selectedPeriod !== "range") {
                    document.querySelector('input[name="from"]')?.setAttribute("value", "");
                    document.querySelector('input[name="to"]')?.setAttribute("value", "");
                    const fromInput = document.querySelector('input[name="from"]');
                    const toInput = document.querySelector('input[name="to"]');
                    if (fromInput) fromInput.value = "";
                    if (toInput) toInput.value = "";
                }

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


        const deleteOverlay = document.getElementById('deleteOrderOverlay');
        const deleteOrderNumber = document.getElementById('deleteOrderNumber');
        const cancelDeleteOrder = document.getElementById('cancelDeleteOrder');
        const confirmDeleteOrder = document.getElementById('confirmDeleteOrder');
        let deleteOrderId = null;

        document.querySelectorAll('.delete-order-btn').forEach(button => {
            button.addEventListener('click', () => {
                deleteOrderId = button.dataset.orderId;
                deleteOrderNumber.textContent = button.dataset.orderNumber || 'this order';
                deleteOverlay.classList.add('show');
                deleteOverlay.setAttribute('aria-hidden', 'false');
            });
        });

        function closeDeleteModal() {
            deleteOrderId = null;
            deleteOverlay.classList.remove('show');
            deleteOverlay.setAttribute('aria-hidden', 'true');
        }

        cancelDeleteOrder?.addEventListener('click', closeDeleteModal);
        deleteOverlay?.addEventListener('click', e => {
            if (e.target === deleteOverlay) closeDeleteModal();
        });

        confirmDeleteOrder?.addEventListener('click', async () => {
            if (!deleteOrderId) return;
            const id = deleteOrderId;
            confirmDeleteOrder.disabled = true;
            confirmDeleteOrder.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin me-1"></i> Deleting...';
            try {
                const response = await fetch('../handlers/delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
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
                    throw new Error('The server returned an invalid response.');
                }
                if (result.permission_denied) throw new Error(result.message ||
                    'You do not have permission to delete orders.');
                if (!response.ok || !result.success) throw new Error(result.message ||
                    'Unable to delete order.');
                closeDeleteModal();
                window.location.reload();
            } catch (error) {
                alert(error.message || 'Unable to delete order.');
                confirmDeleteOrder.disabled = false;
                confirmDeleteOrder.innerHTML =
                '<i class="fa-solid fa-trash me-1"></i> Delete Order';
            }
        });

    });
    </script>

</body>

</html>