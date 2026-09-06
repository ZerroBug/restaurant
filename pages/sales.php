<?php
session_start();
require_once "../includes/db_connection.php";

/* Keep prepared statements compatible with the mixed SQL expressions used on this page. */
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
} catch (Throwable $e) {
    // The connection file may already configure these attributes.
}

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
| FILTERS + REPORT DATA
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
if (!in_array($period, $allowedPeriods, true)) $period = 'day';
if (!validDate($selectedDate)) $selectedDate = date('Y-m-d');

/* A valid From + To selection always means a custom range, even if the
   user forgets to press the Custom period button. */
if (validDate($fromDate) && validDate($toDate)) {
    if ($fromDate > $toDate) {
        [$fromDate, $toDate] = [$toDate, $fromDate];
    }
    $period = 'range';
}

$rangeStart = null;
$rangeEnd = null;
$rangeLabel = 'All completed sales';

if ($period === 'day') {
    $rangeStart = $selectedDate;
    $rangeEnd = $selectedDate;
    $rangeLabel = date('d M Y', strtotime($selectedDate));
} elseif ($period === 'week') {
    $d = new DateTime($selectedDate);
    $d->modify('monday this week');
    $rangeStart = $d->format('Y-m-d');
    $d->modify('+6 days');
    $rangeEnd = $d->format('Y-m-d');
    $rangeLabel = date('d M', strtotime($rangeStart)) . ' – ' . date('d M Y', strtotime($rangeEnd));
} elseif ($period === 'month') {
    $d = new DateTime($selectedDate);
    $rangeStart = $d->format('Y-m-01');
    $rangeEnd = $d->format('Y-m-t');
    $rangeLabel = date('F Y', strtotime($selectedDate));
} elseif ($period === 'range' && validDate($fromDate) && validDate($toDate)) {
    $rangeStart = $fromDate;
    $rangeEnd = $toDate;
    $rangeLabel = date('d M Y', strtotime($fromDate)) . ' – ' . date('d M Y', strtotime($toDate));
} elseif ($period === 'range') {
    /* Custom was selected without a complete pair of dates. Keep the page
       usable instead of producing an empty/invalid query. */
    $period = 'all';
    $rangeLabel = 'All completed sales';
}

$error = null;
$records = [];
$totalRecords = 0;
$totalPages = 1;
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
    $categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'Active' ORDER BY name ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    /* One completed-payment row per order. */
    $paidSql = "
        SELECT p.order_id,
               SUM(p.amount) AS amount,
               MAX(p.created_at) AS created_at,
               MAX(p.payment_method) AS payment_method
        FROM payments p
        WHERE p.status = 'Completed'
        GROUP BY p.order_id
    ";

    /* Base period condition used by report totals. */
    $reportWhere = [];
    $reportParams = [];
    if ($rangeStart !== null && $rangeEnd !== null) {
        $reportWhere[] = "p.created_at >= :r_start AND p.created_at < DATE_ADD(:r_end, INTERVAL 1 DAY)";
        $reportParams[':r_start'] = $rangeStart;
        $reportParams[':r_end'] = $rangeEnd;
    }
    $reportWhereSql = $reportWhere ? 'AND ' . implode(' AND ', $reportWhere) : '';

    /* Overall total for the selected period. */
    $reportSql = "
        SELECT COALESCE(SUM(p.amount),0) AS sales_total,
               COUNT(DISTINCT p.order_id) AS order_total
        FROM payments p
        WHERE p.status = 'Completed' $reportWhereSql
    ";
    $stmt = $pdo->prepare($reportSql);
    foreach ($reportParams as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $reportSales = (float)($report['sales_total'] ?? 0);
    $reportOrders = (int)($report['order_total'] ?? 0);

    /* Category totals for the selected period. Only completed orders count. */
    $catWhere = ["c.status = 'Active'", "p.order_id IS NOT NULL"];
    $catParams = [];
    if ($rangeStart !== null && $rangeEnd !== null) {
        $catWhere[] = "p.created_at >= :c_start AND p.created_at < DATE_ADD(:c_end, INTERVAL 1 DAY)";
        $catParams[':c_start'] = $rangeStart;
        $catParams[':c_end'] = $rangeEnd;
    }
    $categorySql = "
        SELECT c.id, c.name,
               COALESCE(SUM(oi.quantity),0) AS quantity,
               COALESCE(SUM(oi.subtotal),0) AS sales
        FROM categories c
        LEFT JOIN food_menu fm ON fm.category_id = c.id
        LEFT JOIN order_items oi ON oi.food_id = fm.id
        LEFT JOIN orders o ON o.id = oi.order_id
        LEFT JOIN ($paidSql) p ON p.order_id = o.id
        WHERE " . implode(' AND ', $catWhere) . "
        GROUP BY c.id, c.name
        ORDER BY sales DESC, c.name ASC
    ";
    $stmt = $pdo->prepare($categorySql);
    foreach ($catParams as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->execute();
    $categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Build table filters with unique parameter names for every statement. */
    $where = ["paid.order_id IS NOT NULL"];
    $params = [];
    if ($rangeStart !== null && $rangeEnd !== null) {
        $where[] = "paid.created_at >= :t_start AND paid.created_at < DATE_ADD(:t_end, INTERVAL 1 DAY)";
        $params[':t_start'] = $rangeStart;
        $params[':t_end'] = $rangeEnd;
    }
    if ($categoryId > 0) {
        $where[] = "EXISTS (
            SELECT 1 FROM order_items foci
            INNER JOIN food_menu focm ON focm.id = foci.food_id
            WHERE foci.order_id = o.id AND focm.category_id = :t_category
        )";
        $params[':t_category'] = $categoryId;
    }
    if ($search !== '') {
        $where[] = "(
            o.order_number LIKE :s_order
            OR CAST(o.id AS CHAR) LIKE :s_id
            OR COALESCE(u.full_name,'') LIKE :s_name
            OR COALESCE(u.username,'') LIKE :s_user
            OR COALESCE(paid.payment_method,'') LIKE :s_payment
            OR EXISTS (
                SELECT 1
                FROM order_items si
                INNER JOIN food_menu sf ON sf.id = si.food_id
                LEFT JOIN categories sc ON sc.id = sf.category_id
                WHERE si.order_id = o.id
                  AND (sf.name LIKE :s_food OR COALESCE(sc.name,'') LIKE :s_cat)
            )
        )";
        $sv = '%' . $search . '%';
        $params[':s_order']=$sv; $params[':s_id']=$sv; $params[':s_name']=$sv;
        $params[':s_user']=$sv; $params[':s_payment']=$sv; $params[':s_food']=$sv; $params[':s_cat']=$sv;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    /* Count matching orders. */
    $countSql = "
        SELECT COUNT(*) FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        INNER JOIN ($paidSql) paid ON paid.order_id = o.id
        $whereSql
    ";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v,PDO::PARAM_STR);
    $stmt->execute();
    $totalRecords = (int)$stmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    /* Filtered total: when a category is selected, sum only that category's items. */
    $summarySql = "
        SELECT COALESCE(SUM(CASE
                    WHEN :summary_cat = 0 THEN paid.amount
                    ELSE COALESCE(ci.category_sales,0)
               END),0) AS sales_total,
               COALESCE(SUM(CASE
                    WHEN :summary_cat_items = 0 THEN COALESCE(it.total_items,0)
                    ELSE COALESCE(ci.category_items,0)
               END),0) AS item_total
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        INNER JOIN ($paidSql) paid ON paid.order_id = o.id
        LEFT JOIN (
            SELECT order_id, SUM(quantity) AS total_items
            FROM order_items GROUP BY order_id
        ) it ON it.order_id = o.id
        LEFT JOIN (
            SELECT oi.order_id, SUM(oi.subtotal) AS category_sales, SUM(oi.quantity) AS category_items
            FROM order_items oi
            INNER JOIN food_menu fm ON fm.id = oi.food_id
            WHERE fm.category_id = :summary_cat_items_for_join
            GROUP BY oi.order_id
        ) ci ON ci.order_id = o.id
        $whereSql
    ";
    $stmt = $pdo->prepare($summarySql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v,PDO::PARAM_STR);
    $stmt->bindValue(':summary_cat',$categoryId,PDO::PARAM_INT);
    $stmt->bindValue(':summary_cat_items',$categoryId,PDO::PARAM_INT);
    $stmt->bindValue(':summary_cat_items_for_join',$categoryId,PDO::PARAM_INT);
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $filteredSales = (float)($summary['sales_total'] ?? 0);
    $filteredItems = (int)($summary['item_total'] ?? 0);
    $averageOrder = $totalRecords > 0 ? $filteredSales / $totalRecords : 0;

    /* Payment method totals follow the same table filters. */
    $paymentSummarySql = "
        SELECT paid.payment_method, COALESCE(SUM(CASE WHEN :pay_cat = 0 THEN paid.amount ELSE COALESCE(ci.category_sales,0) END),0) amount
        FROM orders o
        LEFT JOIN users u ON u.id=o.user_id
        INNER JOIN ($paidSql) paid ON paid.order_id=o.id
        LEFT JOIN (
            SELECT oi.order_id, SUM(oi.subtotal) category_sales
            FROM order_items oi INNER JOIN food_menu fm ON fm.id=oi.food_id
            WHERE fm.category_id=:pay_cat_join GROUP BY oi.order_id
        ) ci ON ci.order_id=o.id
        $whereSql
        GROUP BY paid.payment_method
    ";
    $stmt=$pdo->prepare($paymentSummarySql);
    foreach($params as $k=>$v) $stmt->bindValue($k,$v,PDO::PARAM_STR);
    $stmt->bindValue(':pay_cat',$categoryId,PDO::PARAM_INT);
    $stmt->bindValue(':pay_cat_join',$categoryId,PDO::PARAM_INT);
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $m=trim((string)$r['payment_method']); $a=(float)$r['amount'];
        if($m==='Cash') $cashSales=$a;
        elseif($m==='Card') $cardSales=$a;
        elseif($m==='Mobile Money') $momoSales=$a;
    }

    /* Records. Category selected => show only items from that category and sum only those items. */
    $itemJoin = $categoryId > 0
        ? "INNER JOIN order_items oi ON oi.order_id=o.id
           INNER JOIN food_menu fm ON fm.id=oi.food_id AND fm.category_id=:r_cat_join
           LEFT JOIN categories c ON c.id=fm.category_id"
        : "LEFT JOIN order_items oi ON oi.order_id=o.id
           LEFT JOIN food_menu fm ON fm.id=oi.food_id
           LEFT JOIN categories c ON c.id=fm.category_id";

    $recordsSql = "
        SELECT o.id,o.order_number,o.order_type,o.status,o.subtotal,o.discount,o.tax,o.total,o.payment_status,o.user_id,o.created_at,
               COALESCE(u.full_name,u.username,'Unknown User') salesperson,
               CASE WHEN :r_cat_case=0 THEN paid.amount ELSE COALESCE(SUM(oi.subtotal),0) END paid_amount,
               COALESCE(paid.payment_method,'Not recorded') payment_method,
               paid.created_at paid_at,
               COALESCE(GROUP_CONCAT(DISTINCT CONCAT(fm.name,' × ',oi.quantity) ORDER BY fm.name SEPARATOR ', '),'No items') items,
               COALESCE(SUM(oi.quantity),0) item_count,
               COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),'Uncategorised') categories
        FROM orders o
        LEFT JOIN users u ON u.id=o.user_id
        INNER JOIN ($paidSql) paid ON paid.order_id=o.id
        $itemJoin
        $whereSql
        GROUP BY o.id,o.order_number,o.order_type,o.status,o.subtotal,o.discount,o.tax,o.total,o.payment_status,o.user_id,o.created_at,
                 u.full_name,u.username,paid.amount,paid.payment_method,paid.created_at
        ORDER BY paid.created_at DESC,o.id DESC
        LIMIT :r_limit OFFSET :r_offset
    ";
    $stmt=$pdo->prepare($recordsSql);
    foreach($params as $k=>$v) $stmt->bindValue($k,$v,PDO::PARAM_STR);
    if($categoryId>0) $stmt->bindValue(':r_cat_join',$categoryId,PDO::PARAM_INT);
    $stmt->bindValue(':r_cat_case',$categoryId,PDO::PARAM_INT);
    $stmt->bindValue(':r_limit',$perPage,PDO::PARAM_INT);
    $stmt->bindValue(':r_offset',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $records=$stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Sales page SQL error: '.$e->getMessage());
    $error='Unable to load the sales records. Database query failed. Please check the SQL/database configuration.';
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
        'date' => $_GET['date'] ?? date('Y-m-d'),
        'from' => $_GET['from'] ?? '',
        'to' => $_GET['to'] ?? '',
        'category_id' => $_GET['category_id'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    foreach ($overrides as $key=>$value) $query[$key]=$value;
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

    /* =====================================================================
       SALES PAGE — TRUE MOBILE RESPONSIVE LAYOUT
       ===================================================================== */

    html,
    body {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden !important;
    }

    .app {
        width: 100%;
        min-width: 0;
    }

    .main {
        width: calc(100% - var(--sidebar-width));
        min-width: 0;
    }

    .content {
        width: 100%;
        max-width: 1600px;
        min-width: 0;
    }

    /* Tablet: collapse the fixed sidebar instead of squeezing the page. */
    @media (max-width: 1100px) {
        .main {
            width: 100%;
            margin-left: 0 !important;
        }

        .content {
            padding: 20px 18px 35px;
        }

        .sales-heading {
            align-items: flex-start;
        }

        .filter-body {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        .filter-body>div:first-child {
            grid-column: 1 / -1;
        }

        .period-buttons {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            height: auto;
        }

        .period-btn {
            width: 100%;
            min-width: 0;
        }

        .metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* Phone */
    @media (max-width: 700px) {
        .content {
            padding: 14px 10px 28px !important;
        }

        .sales-heading {
            width: 100%;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 12px;
        }

        .sales-title {
            width: 100%;
            align-items: flex-start;
            gap: 9px;
        }

        .title-icon {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
        }

        .sales-heading h1 {
            font-size: 20px !important;
        }

        .sales-heading p {
            font-size: 9px !important;
            line-height: 1.5;
        }

        .sales-heading>div:last-child {
            width: 100%;
        }

        .new-order-btn {
            width: 100%;
            min-height: 42px;
            justify-content: center;
        }

        /* Filters become a clean vertical form. */
        .filter-panel {
            width: 100%;
            border-radius: 12px;
        }

        .filter-head {
            padding: 13px;
            flex-direction: column;
            align-items: flex-start;
        }

        .filter-head-left {
            width: 100%;
        }

        .range-label {
            white-space: normal;
            line-height: 1.4;
        }

        .filter-body {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 10px !important;
            padding: 13px !important;
        }

        .filter-body>div:first-child {
            grid-column: auto !important;
        }

        .period-buttons {
            display: grid !important;
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            width: 100%;
            height: auto;
            gap: 4px;
        }

        .period-btn {
            min-width: 0;
            height: 38px;
            padding: 0 2px;
            font-size: 8px;
            white-space: nowrap;
        }

        .filter-field {
            width: 100%;
            height: 42px;
            min-width: 0;
        }

        .filter-field input,
        .filter-field select {
            min-width: 0;
            font-size: 11px;
        }

        .date-range-fields {
            width: 100%;
        }

        .range-inputs {
            width: 100%;
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 7px !important;
        }

        .range-inputs span {
            display: none;
        }

        .range-inputs input {
            width: 100%;
            min-width: 0;
            height: 42px;
        }

        .filter-actions {
            width: 100%;
            display: grid !important;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .filter-submit,
        .clear-filter {
            width: 100%;
            min-height: 42px;
        }

        /* KPI cards */
        .metrics {
            grid-template-columns: 1fr !important;
            gap: 9px;
        }

        .metric {
            min-height: 96px;
        }

        /* Payment cards */
        .payment-grid {
            grid-template-columns: 1fr !important;
            gap: 9px;
        }

        /* Category report */
        .report-section {
            width: 100%;
            border-radius: 13px;
        }

        .report-heading {
            padding: 15px 13px;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .report-heading h2 {
            font-size: 17px;
        }

        .report-heading p {
            font-size: 9px;
        }

        .report-total {
            width: 100%;
            min-width: 0 !important;
            text-align: left;
        }

        .category-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px;
            padding: 10px;
        }

        .category-card {
            min-width: 0;
            min-height: 100px;
            padding: 10px;
        }

        .category-name {
            overflow-wrap: anywhere;
            font-size: 8px;
        }

        .category-card strong {
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .category-card small {
            line-height: 1.4;
        }

        /* Sales records header */
        .sales-panel {
            width: 100%;
            min-width: 0;
            border-radius: 13px;
        }

        .sales-panel-head {
            padding: 13px;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .sales-panel-title {
            width: 100%;
        }

        .sales-panel-actions {
            width: 100%;
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 7px;
        }

        .table-filter-total,
        .table-export-btn {
            width: 100%;
        }

        .table-filter-total {
            justify-content: center;
        }

        .table-export-btn {
            min-height: 40px;
        }

        /*
         * IMPORTANT:
         * On phones the table is converted into stacked record cards.
         * This removes the 980px/1050px table width that was making the
         * whole Sales page overflow horizontally.
         */
        .table-wrap {
            width: 100%;
            overflow: visible !important;
        }

        .sales-table {
            width: 100% !important;
            min-width: 0 !important;
            display: block;
            border-collapse: separate;
        }

        .sales-table thead {
            display: none;
        }

        .sales-table tbody,
        .sales-table tr {
            display: block;
            width: 100%;
        }

        .sales-table tbody tr {
            margin: 0;
            padding: 10px 12px;
            border-bottom: 1px solid #eee8e2;
            background: #fff;
        }

        .sales-table tbody tr:last-child {
            border-bottom: 0;
        }

        .sales-table td {
            width: 100%;
            min-width: 0;
            display: flex !important;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 0 !important;
            border-bottom: 1px solid #f3efeb;
            text-align: right;
            font-size: 10px;
            overflow-wrap: anywhere;
        }

        .sales-table td:last-child {
            border-bottom: 0;
        }

        .sales-table td::before {
            flex: 0 0 82px;
            color: #938a82;
            font-size: 8px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .sales-table td:nth-child(1)::before {
            content: "Order";
        }

        .sales-table td:nth-child(2)::before {
            content: "Items";
        }

        .sales-table td:nth-child(3)::before {
            content: "Category";
        }

        .sales-table td:nth-child(4)::before {
            content: "Salesperson";
        }

        .sales-table td:nth-child(5)::before {
            content: "Type";
        }

        .sales-table td:nth-child(6)::before {
            content: "Payment";
        }

        .sales-table td:nth-child(7)::before {
            content: "Total";
        }

        .sales-table td:nth-child(8)::before {
            content: "Date";
        }

        .sales-table td:nth-child(9)::before {
            content: "Action";
        }

        .sales-table td>* {
            max-width: calc(100% - 94px);
        }

        .order-cell,
        .items-cell,
        .category-list-cell,
        .salesperson-cell {
            max-width: calc(100% - 94px);
            min-width: 0;
            text-align: right;
            justify-content: flex-end;
        }

        .order-cell {
            gap: 7px;
        }

        .order-icon {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
        }

        .items-cell {
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .category-list-cell {
            justify-content: flex-end;
        }

        .category-badge {
            font-size: 8px;
        }

        .type-badge,
        .paid-badge {
            font-size: 8px;
        }

        .sale-total {
            font-size: 11px;
        }

        .sale-date {
            white-space: normal;
            font-size: 9px;
        }

        .delete-order-btn {
            min-height: 36px;
        }

        /* Empty state must not inherit the card-row layout. */
        .sales-table td.empty-sales {
            display: block !important;
            padding: 35px 10px !important;
            text-align: center !important;
        }

        .sales-table td.empty-sales::before {
            display: none;
        }

        .sales-table td.empty-sales>* {
            max-width: none;
        }

        /* Pagination */
        .pagination-wrap {
            width: 100%;
            padding: 12px;
            flex-direction: column;
            align-items: stretch;
            gap: 9px;
        }

        .pagination-info {
            text-align: center;
            font-size: 8px;
        }

        .pagination {
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }
    }

    @media (max-width: 420px) {
        .content {
            padding-left: 8px !important;
            padding-right: 8px !important;
        }

        .period-buttons {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }

        .period-btn {
            font-size: 8px;
        }

        .category-grid {
            grid-template-columns: 1fr !important;
        }

        .filter-actions {
            grid-template-columns: 1fr !important;
        }

        .sales-table td::before {
            flex-basis: 70px;
        }

        .sales-table td>*,
        .order-cell,
        .items-cell,
        .category-list-cell,
        .salesperson-cell {
            max-width: calc(100% - 80px);
        }
    }

    @media (max-width: 360px) {
        .sales-table td {
            gap: 8px;
            font-size: 9px;
        }

        .sales-table td::before {
            flex-basis: 62px;
            font-size: 7px;
        }

        .sales-table td>*,
        .order-cell,
        .items-cell,
        .category-list-cell,
        .salesperson-cell {
            max-width: calc(100% - 70px);
        }
    }


    /* Mobile navigation button */
    .sales-mobile-menu,
    .sales-mobile-overlay {
        display: none;
    }

    @media (max-width: 1100px) {
        .sales-mobile-menu {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 30000;
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border: 1px solid #e5ded7;
            border-radius: 10px;
            color: #3b342f;
            background: #fff;
            box-shadow: 0 5px 18px rgba(30, 25, 20, .10);
            cursor: pointer;
        }

        .sales-mobile-overlay {
            position: fixed;
            inset: 0;
            z-index: 29998;
            background: rgba(25, 20, 16, .42);
            backdrop-filter: blur(2px);
        }

        .sales-mobile-overlay.show {
            display: block;
        }

        /* Support the common shared-sidebar class names used by this system. */
        .sidebar,
        .pos-sidebar {
            z-index: 29999 !important;
        }

        .sidebar.show,
        .pos-sidebar.show {
            transform: translateX(0) !important;
        }
    }

    @media (max-width: 700px) {
        .sales-mobile-menu {
            top: 9px;
            left: 9px;
            width: 40px;
            height: 40px;
        }
    }
    </style>
</head>


<body>

    <button type="button" class="sales-mobile-menu" id="salesMobileMenu" aria-label="Open navigation menu"
        aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="sales-mobile-overlay" id="salesMobileOverlay"></div>

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
                `<!doctype html><html><head><title>${title}</title><style>body{font-family:Arial,sans-serif;padding:28px;color:#222}h1{font-size:20px;margin:0 0 4px}p{font-size:11px;color:#666;margin:0 0 16px}.total{display:inline-block;padding:8px 12px;background:#f2f8f4;border:1px solid #dcefe4;border-radius:8px;font-weight:700;margin-bottom:18px}table{width:100%;border-collapse:collapse;font-size:10px}th{background:#f4f4f4;text-align:left;padding:8px;border-bottom:1px solid #ccc}td{padding:8px;border-bottom:1px solid #e5e5e5} .category-badge{display:inline-block;margin:2px;padding:3px 6px;background:#f5f5f5;border-radius:5px}</style></head><body><h1>Sales Transactions</h1><p>${title}</p><div class="total">Filtered Table Total: ${total}</div>${clone.outerHTML}
<script>
(function () {
    const menu = document.getElementById('salesMobileMenu');
    const overlay = document.getElementById('salesMobileOverlay');

    if (!menu) return;

    function getSidebar() {
        return document.querySelector('.sidebar, .pos-sidebar');
    }

    function setOpen(open) {
        const sidebar = getSidebar();

        if (sidebar) {
            sidebar.classList.toggle('show', open);
        }

        if (overlay) {
            overlay.classList.toggle('show', open);
        }

        menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.innerHTML = open
            ? '<i class="fa-solid fa-xmark"></i>'
            : '<i class="fa-solid fa-bars"></i>';
    }

    menu.addEventListener('click', function () {
        const sidebar = getSidebar();
        const open = sidebar ? sidebar.classList.contains('show') : false;
        setOpen(!open);
    });

    if (overlay) {
        overlay.addEventListener('click', function () {
            setOpen(false);
        });
    }

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 1100) return;

        const sidebar = getSidebar();
        if (!sidebar) return;

        if (
            sidebar.classList.contains('show') &&
            !sidebar.contains(event.target) &&
            event.target !== menu &&
            !menu.contains(event.target)
        ) {
            setOpen(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1100) {
            setOpen(false);
        }
    });
})();
    </script>

</body>

</html>`
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