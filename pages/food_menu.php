<?php

session_start();

/*
|--------------------------------------------------------------------------
| FOOD MENU PAGE ACCESS PROTECTION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Administrator'
) {

    $_SESSION['login_message'] =
        'Please log in as an Administrator to access the Food Menu page.';

    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once "../includes/db_connection.php";


/*
|--------------------------------------------------------------------------
| SESSION MESSAGE
|--------------------------------------------------------------------------
*/

$food_message = $_SESSION['food_message'] ?? null;

unset($_SESSION['food_message']);


/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$username  = $_SESSION['username'] ?? 'User';
$full_name = $_SESSION['full_name'] ?? $username;
$role      = $_SESSION['role'] ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| AVATAR
|--------------------------------------------------------------------------
*/

$avatar = strtoupper(
    substr(
        trim($full_name),
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
|
| Categories are needed for the Add Food form.
|
*/

$categories = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name
        FROM categories
        WHERE status = 'Active'
        ORDER BY name ASC
    ");

    $categories = $stmt->fetchAll();

} catch (PDOException $e) {

    $categories = [];

    if (!$food_message) {

        $food_message = [
            'type' => 'error',
            'message' => 'Unable to load food categories.'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| LOAD FOOD MENU
|--------------------------------------------------------------------------
*/

$foods = [];

$searchTerm = trim($_GET['search'] ?? '');

$perPage = 10;

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

$page = ($page && $page > 0)
    ? $page
    : 1;

try {

    $whereSql = '';
    $params = [];

    if ($searchTerm !== '') {

        $whereSql = "
            WHERE
                LOWER(fm.name) LIKE LOWER(:search_name)
                OR LOWER(COALESCE(c.name, '')) LIKE LOWER(:search_category)
        ";

        $searchLike = '%' . $searchTerm . '%';

        $params[':search_name'] = $searchLike;
        $params[':search_category'] = $searchLike;
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL MATCHING FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM food_menu fm
        LEFT JOIN categories c
            ON fm.category_id = c.id
        $whereSql
    ");

    $countStmt->execute($params);

    $totalFoods = (int) $countStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages = max(
        1,
        (int) ceil(
            $totalFoods / $perPage
        )
    );

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset =
        ($page - 1) * $perPage;

    /*
    |--------------------------------------------------------------------------
    | LOAD CURRENT PAGE ONLY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            fm.id,
            fm.category_id,
            fm.name,
            fm.price,
            fm.image,
            fm.status,
            fm.created_at,
            fm.updated_at,
            c.name AS category_name
        FROM food_menu fm
        LEFT JOIN categories c
            ON fm.category_id = c.id
        $whereSql
        ORDER BY COALESCE(c.name, 'Uncategorized') ASC, fm.name ASC
        LIMIT :limit
        OFFSET :offset
    ");

    foreach ($params as $key => $value) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':limit',
        $perPage,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $foods = $stmt->fetchAll();

    $firstItem =
        $totalFoods > 0
            ? $offset + 1
            : 0;

    $lastItem =
        min(
            $offset + $perPage,
            $totalFoods
        );

} catch (PDOException $e) {

    $foods = [];

    $totalFoods = 0;

    $totalPages = 1;

    $page = 1;

    $firstItem = 0;

    $lastItem = 0;

    if (!$food_message) {

        $food_message = [
            'type' => 'error',
            'message' =>
                'Unable to load food menu from the database.'
        ];
    }
}

?>

<?php
/*
|--------------------------------------------------------------------------
| PDF EXPORT
|--------------------------------------------------------------------------
| Complete menu export, grouped by category.
*/
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $pdfStmt = $pdo->query("
        SELECT fm.id, fm.name, fm.price, fm.status,
               c.name AS category_name
        FROM food_menu fm
        LEFT JOIN categories c ON fm.category_id = c.id
        ORDER BY COALESCE(c.name, 'Uncategorized') ASC, fm.name ASC
    ");
    $pdfFoods = $pdfStmt->fetchAll();

    $fpdfCandidates = [
        __DIR__ . '/../includes/fpdf/fpdf.php',
        __DIR__ . '/../includes/fpdf.php',
        __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'
    ];

    foreach ($fpdfCandidates as $fpdfPath) {
        if (is_file($fpdfPath)) {
            require_once $fpdfPath;
            break;
        }
    }

    if (!class_exists('FPDF')) {
        http_response_code(500);
        exit('FPDF was not found. Please install/include FPDF.');
    }

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, 'BETTER END', 0, 1);
    $pdf->SetFont('Arial', 'B', 15);
    $pdf->Cell(0, 9, 'Food Menu Report', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, 'Generated: ' . date('d M Y, H:i'), 0, 1);
    $pdf->Cell(0, 6, 'Total food items: ' . count($pdfFoods), 0, 1);
    $pdf->Ln(5);

    $currentCategory = null;
    $number = 0;

    foreach ($pdfFoods as $food) {
        $category = $food['category_name'] ?? 'Uncategorized';

        if ($category !== $currentCategory) {
            $currentCategory = $category;

            if ($number > 0) {
                $pdf->Ln(4);
            }

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell(0, 8, strtoupper($category), 0, 1, 'L', true);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(12, 7, '#', 1, 0, 'C', true);
            $pdf->Cell(85, 7, 'Food Item', 1, 0, 'L', true);
            $pdf->Cell(35, 7, 'Price (GHC)', 1, 0, 'R', true);
            $pdf->Cell(38, 7, 'Status', 1, 1, 'C', true);
        }

        $number++;
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(12, 7, $number, 1, 0, 'C');
        $pdf->Cell(85, 7, substr((string)$food['name'], 0, 48), 1, 0, 'L');
        $pdf->Cell(35, 7, number_format((float)$food['price'], 2), 1, 0, 'R');
        $pdf->Cell(38, 7, (string)$food['status'], 1, 1, 'C');
    }

    $pdf->Output('D', 'better-end-food-menu-' . date('Y-m-d') . '.pdf');
    exit;
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Food Menu | Better End
    </title>


    <!-- POPPINS -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">


    <!-- FONT AWESOME -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- DASHBOARD CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">

    <!-- BOOTSTRAP 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


    <style>
    /* =========================================================
   GLOBAL
========================================================= */

    :root {

        --orange: #f58220;
        --orange-dark: #df6810;
        --orange-light: #fff1e6;
        --orange-soft: #fff8f2;

        --black: #111111;
        --black-soft: #1c1c1c;

        --text: #292522;
        --muted: #918981;

        --border: #ebe7e3;

        --background: #f6f5f3;

        --white: #ffffff;

        --green: #1fa463;
        --red: #d94b4b;

        --sidebar-width: 250px;
    }


    * {
        box-sizing: border-box;
    }


    html {
        scroll-behavior: smooth;
    }


    body {

        margin: 0;

        min-height: 100vh;

        background: var(--background);

        color: var(--text);

        font-family: "Poppins", sans-serif;

        font-size: 15px;
    }


    button,
    input,
    select,
    textarea {
        font-family: inherit;
    }


    a {
        text-decoration: none;
    }


    /* =========================================================
   MAIN
========================================================= */

    .food-main {
        margin-left: 250px;
        min-height: 100vh;
        position: relative;
    }


    /* =========================================================
   TOP BAR
========================================================= */

    .topbar {

        height: 82px;

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 20px;

        padding: 0 30px;

        background: #ffffff;

        border-bottom: 1px solid var(--border);

        box-shadow:
            0 2px 18px rgba(0, 0, 0, .035);

        position: sticky;

        top: 0;

        z-index: 50;
    }


    .topbar-title {

        display: flex;

        align-items: center;

        gap: 13px;
    }


    .topbar-icon {

        width: 44px;

        height: 44px;

        display: grid;

        place-items: center;

        border-radius: 12px;

        color: #ffffff;

        background:
            linear-gradient(135deg,
                var(--orange),
                #e86b12);

        box-shadow:
            0 7px 18px rgba(245, 130, 32, .20);

        font-size: 16px;
    }


    .topbar-title h1 {

        margin: 0;

        color: #171717;

        font-size: 23px;

        line-height: 1.15;

        font-weight: 800;

        letter-spacing: -.6px;
    }


    .topbar-title p {

        margin: 4px 0 0;

        color: #99918a;

        font-size: 11px;

        font-weight: 500;
    }


    .topbar-right {

        display: flex;

        align-items: center;

        gap: 16px;
    }


    .topbar-link {

        display: flex;

        align-items: center;

        gap: 8px;

        padding: 10px 13px;

        border-radius: 9px;

        color: #4b4540;

        font-size: 13px;

        font-weight: 700;

        transition: .2s ease;
    }


    .topbar-link:hover {

        color: var(--orange);

        background: var(--orange-soft);
    }


    .top-profile {

        display: flex;

        align-items: center;

        gap: 10px;
    }


    .avatar {

        width: 40px;

        height: 40px;

        display: grid;

        place-items: center;

        border-radius: 50%;

        color: #ffffff;

        background:
            linear-gradient(135deg,
                var(--orange),
                #e76b12);

        font-size: 14px;

        font-weight: 800;
    }


    .top-profile strong {

        display: block;

        color: #28231f;

        font-size: 13px;

        font-weight: 800;
    }


    .top-profile small {

        display: block;

        margin-top: 2px;

        color: #99918a;

        font-size: 10px;

        font-weight: 600;
    }


    /* =========================================================
   CONTENT
========================================================= */

    .content {

        max-width: 1550px;

        margin: 0 auto;

        padding: 28px 30px 55px;
    }


    /* =========================================================
   NOTIFICATION
========================================================= */

    .food-message {

        position: relative;

        overflow: hidden;

        display: flex;

        align-items: center;

        gap: 12px;

        min-height: 58px;

        margin-bottom: 20px;

        padding: 10px 16px;

        border: 1px solid;

        border-radius: 12px;

        box-shadow:
            0 8px 25px rgba(30, 25, 20, .08);

        animation:
            foodMessageIn .3s ease both;

        transition:
            opacity .35s ease,
            transform .35s ease;
    }


    .food-message.success {

        color: #176b43;

        background: #f0fbf5;

        border-color: #bfe8d2;
    }


    .food-message.error {

        color: #a93434;

        background: #fff5f5;

        border-color: #f0c3c3;
    }


    .food-message-icon {

        width: 35px;

        height: 35px;

        flex: 0 0 35px;

        display: grid;

        place-items: center;

        border-radius: 50%;

        color: #ffffff;
    }


    .food-message.success .food-message-icon {

        background: var(--green);
    }


    .food-message.error .food-message-icon {

        background: var(--red);
    }


    .food-message-text {

        flex: 1;

        font-size: 12px;

        font-weight: 700;

        line-height: 1.5;
    }


    .food-message-close {

        width: 32px;

        height: 32px;

        display: grid;

        place-items: center;

        border: 0;

        border-radius: 8px;

        color: currentColor;

        background: transparent;

        cursor: pointer;

        opacity: .7;
    }


    .food-message-close:hover {

        opacity: 1;

        background: rgba(0, 0, 0, .05);
    }


    .food-message-progress {

        position: absolute;

        left: 0;

        bottom: 0;

        width: 100%;

        height: 3px;

        transform-origin: left;

        animation:
            foodMessageProgress 5s linear forwards;
    }


    .food-message.success .food-message-progress {

        background: var(--green);
    }


    .food-message.error .food-message-progress {

        background: var(--red);
    }


    .food-message.hide {

        opacity: 0;

        transform: translateY(-8px);
    }


    @keyframes foodMessageIn {

        from {

            opacity: 0;

            transform: translateY(-8px);
        }

        to {

            opacity: 1;

            transform: translateY(0);
        }
    }


    @keyframes foodMessageProgress {

        from {
            transform: scaleX(1);
        }

        to {
            transform: scaleX(0);
        }
    }


    /* =========================================================
   HERO
========================================================= */

    .food-intro {

        position: relative;

        overflow: hidden;

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 20px;

        min-height: 145px;

        margin-bottom: 22px;

        padding: 30px 32px;

        border-radius: 18px;

        color: #ffffff;

        background:

            radial-gradient(circle at 85% 15%,
                rgba(245, 130, 32, .20),
                transparent 28%),

            linear-gradient(135deg,
                #090909,
                #181818 58%,
                #0b0b0b);

        box-shadow:
            0 15px 35px rgba(0, 0, 0, .12);
    }


    .food-intro::after {

        content: "";

        position: absolute;

        width: 190px;

        height: 190px;

        right: 5%;

        top: -115px;

        border-radius: 50%;

        background:
            rgba(245, 130, 32, .10);

        filter: blur(40px);
    }


    .food-intro>* {

        position: relative;

        z-index: 2;
    }


    .eyebrow {

        display: block;

        margin-bottom: 7px;

        color: var(--orange);

        font-size: 10px;

        font-weight: 800;

        letter-spacing: 1.7px;
    }


    .food-intro h2 {

        margin: 0;

        color: #ffffff;

        font-size: 29px;

        font-weight: 800;

        letter-spacing: -.8px;
    }


    .food-intro p {

        margin: 7px 0 0;

        color: #bcb6b0;

        font-size: 12px;

        font-weight: 500;
    }


    .intro-icon {

        width: 68px;

        height: 68px;

        display: grid;

        place-items: center;

        border-radius: 17px;

        color: var(--orange);

        background:
            rgba(255, 255, 255, .055);

        border:
            1px solid rgba(255, 255, 255, .09);

        font-size: 23px;
    }


    /* =========================================================
   FORM + TABLE
========================================================= */

    .food-layout {

        display: grid;

        grid-template-columns:
            390px minmax(0, 1fr);

        gap: 22px;

        align-items: start;
    }


    /* =========================================================
   PANEL
========================================================= */

    .panel {

        overflow: hidden;

        border: 1px solid var(--border);

        border-radius: 20px;

        background: #ffffff;

        box-shadow:
            0 12px 35px rgba(38, 28, 21, .055);
    }


    /* =========================================================
   FORM PANEL
========================================================= */

    .form-panel {

        padding: 10px;

        background: #ffffff;
    }


    .form-card {

        overflow: hidden;

        border-radius: 16px;

        background: #f7f7f8;
    }


    /* =========================================================
   FORM HEADER
========================================================= */

    .form-top {

        padding: 25px 25px 21px;

        text-align: center;

        background: #ffffff;
    }


    .form-top-icon {

        width: 58px;

        height: 58px;

        margin: 0 auto 12px;

        display: grid;

        place-items: center;

        border-radius: 17px;

        color: #ffffff;

        background:
            linear-gradient(135deg,
                var(--orange),
                #e96b12);

        box-shadow:
            0 9px 20px rgba(245, 130, 32, .22);

        font-size: 20px;
    }


    .form-top h3 {

        margin: 0;

        color: #27221e;

        font-size: 19px;

        font-weight: 800;

        letter-spacing: -.3px;
    }


    .form-top p {

        margin: 5px 0 0;

        color: #99918a;

        font-size: 10px;

        font-weight: 500;
    }


    /* =========================================================
   FORM BODY
========================================================= */

    .food-form {

        margin: 0 10px 10px;

        padding: 23px 20px 20px;

        border-radius: 17px;

        background: #f1f1f3;
    }


    .form-group {

        margin-bottom: 18px;
    }


    .form-label {

        display: block;

        margin-bottom: 8px;

        color: #403a35;

        font-size: 12px;

        font-weight: 800;
    }


    .form-label span {

        color: var(--orange);
    }


    /* =========================================================
   FIELD
========================================================= */

    .field {

        min-height: 48px;

        display: flex;

        align-items: center;

        gap: 10px;

        padding: 0 14px;

        border: 1px solid #e4e1de;

        border-radius: 13px;

        background: #ffffff;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .025);

        transition: .2s ease;
    }


    .field:focus-within {

        border-color: #f0a15e;

        box-shadow:
            0 0 0 4px rgba(245, 130, 32, .10);
    }


    .field>i {

        width: 17px;

        flex: 0 0 17px;

        color: #aaa29b;

        text-align: center;

        font-size: 13px;
    }


    .field:focus-within>i {

        color: var(--orange);
    }


    .field input,
    .field select,
    .field textarea {

        width: 100%;

        min-width: 0;

        border: 0;

        outline: 0;

        color: #302b27;

        background: transparent;

        font-size: 12px;

        font-weight: 600;
    }


    .field input::placeholder,
    .field textarea::placeholder {

        color: #aaa39d;

        font-size: 11px;

        font-weight: 500;
    }


    .textarea-field {

        min-height: 100px;

        align-items: flex-start;

        padding-top: 14px;
    }


    .field textarea {

        height: 75px;

        resize: none;

        line-height: 1.5;
    }


    /* =========================================================
   PRICE
========================================================= */

    .price-field {

        position: relative;
    }


    .price-symbol {

        color: var(--orange);

        font-size: 13px;

        font-weight: 800;
    }


    /* =========================================================
   IMAGE FIELD
========================================================= */

    .image-field {

        min-height: 54px;

        padding: 7px 12px;
    }


    .image-field input {

        font-size: 11px;
    }


    /* =========================================================
   FORM NOTE
========================================================= */

    .form-note {

        display: flex;

        align-items: flex-start;

        gap: 8px;

        margin: 2px 0 18px;

        padding: 10px 11px;

        border-radius: 11px;

        color: #8c827a;

        background: #fff8f2;

        border: 1px solid #f2e2d5;

        font-size: 9px;

        line-height: 1.55;
    }


    .form-note i {

        margin-top: 2px;

        color: var(--orange);

        font-size: 10px;
    }


    /* =========================================================
   BUTTONS
========================================================= */

    .form-footer {

        display: grid;

        grid-template-columns:
            105px 1fr;

        gap: 9px;
    }


    .btn-clear,
    .btn-save {

        min-height: 47px;

        border-radius: 12px;

        font-size: 11px;

        font-weight: 800;

        cursor: pointer;

        transition: .2s ease;
    }


    .btn-clear {

        border: 1px solid #ded9d4;

        color: #746c65;

        background: #ffffff;
    }


    .btn-clear:hover {

        background: #faf8f6;

        transform: translateY(-1px);
    }


    .btn-save {

        border: 0;

        color: #ffffff;

        background:
            linear-gradient(135deg,
                var(--orange),
                #e86a12);

        box-shadow:
            0 8px 18px rgba(245, 130, 32, .22);
    }


    .btn-save:hover {

        background:
            linear-gradient(135deg,
                #ff9138,
                #dc610d);

        transform: translateY(-2px);

        box-shadow:
            0 11px 22px rgba(245, 130, 32, .27);
    }


    /* =========================================================
   TABLE PANEL
========================================================= */

    .table-panel {

        overflow: hidden;

        min-height: 560px;

        border: 1px solid #ebe7e3;

        border-radius: 20px;

        background: #ffffff;

        box-shadow:
            0 14px 35px rgba(38, 28, 21, .055);
    }


    /* =========================================================
   TABLE HEADER
========================================================= */

    .panel-header {

        min-height: 82px;

        display: flex;

        align-items: center;

        gap: 13px;

        padding: 18px 22px;

        border-bottom: 1px solid #eeeae6;

        background: #ffffff;
    }


    .panel-header-icon {

        width: 44px;

        height: 44px;

        flex: 0 0 44px;

        display: grid;

        place-items: center;

        border-radius: 13px;

        color: #ffffff;

        background:
            linear-gradient(135deg,
                #171717,
                #2a2a2a);

        box-shadow:
            0 6px 15px rgba(0, 0, 0, .10);

        font-size: 15px;
    }


    .panel-header h3 {

        margin: 0;

        color: #24201d;

        font-size: 16px;

        font-weight: 800;

        letter-spacing: -.25px;
    }


    .panel-header p {

        margin: 4px 0 0;

        color: #9a928b;

        font-size: 10px;

        font-weight: 500;
    }


    /* =========================================================
   FOOD COUNT
========================================================= */

    .table-count {

        margin-left: auto;

        min-width: 82px;

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 5px;

        padding: 8px 12px;

        border-radius: 11px;

        background: #fff5ec;

        border: 1px solid #f7dfca;
    }


    .table-count strong {

        color: #e56d14;

        font-size: 17px;

        font-weight: 800;

        line-height: 1;
    }


    .table-count span {

        color: #9b8170;

        font-size: 9px;

        font-weight: 700;
    }


    /* =========================================================
   TABLE TOOLBAR
========================================================= */

    .table-tools {

        min-height: 68px;

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

        padding: 12px 20px;

        background: #fcfbfa;

        border-bottom: 1px solid #eeeae6;
    }


    .table-tools-info {

        display: flex;

        align-items: center;

        gap: 7px;
    }


    .table-tools strong {

        color: #3c3631;

        font-size: 12px;

        font-weight: 800;
    }


    .table-tools small {

        color: #a29a93;

        font-size: 9px;

        font-weight: 500;
    }


    /* =========================================================
   SEARCH
========================================================= */

    .table-search {

        width: 245px;

        height: 42px;

        display: flex;

        align-items: center;

        gap: 9px;

        padding: 0 13px;

        border: 1px solid #e4dfda;

        border-radius: 11px;

        background: #ffffff;

        transition: .2s ease;
    }


    .table-search:focus-within {

        border-color: #efae73;

        box-shadow:
            0 0 0 4px rgba(245, 130, 32, .08);
    }


    .table-search i {

        color: #aaa29b;

        font-size: 12px;
    }


    .table-search:focus-within i {

        color: var(--orange);
    }


    .table-search input {

        width: 100%;

        min-width: 0;

        border: 0;

        outline: 0;

        background: transparent;

        color: #403a35;

        font-size: 11px;

        font-weight: 600;
    }


    .table-search input::placeholder {

        color: #aaa39d;

        font-size: 10px;

        font-weight: 500;
    }


    /* =========================================================
   TABLE
========================================================= */

    .table-wrap {

        width: 100%;

        overflow-x: auto;

        background: #ffffff;
    }


    .food-table {

        width: 100%;

        min-width: 950px;

        border-collapse: separate;

        border-spacing: 0;
    }


    /* =========================================================
   TABLE HEAD
========================================================= */

    .food-table thead th {

        padding: 15px 16px;

        color: #8b827a;

        background: #faf9f7;

        border-bottom: 1px solid #e9e4df;

        font-size: 9px;

        font-weight: 800;

        letter-spacing: 1px;

        text-align: left;

        white-space: nowrap;
    }


    .food-table thead th:first-child {

        padding-left: 22px;
    }


    .food-table thead th:last-child {

        padding-right: 22px;

        text-align: right;
    }


    /* =========================================================
   TABLE BODY
========================================================= */

    .food-table tbody tr {

        background: #ffffff;

        transition:
            background .18s ease;
    }


    .food-table tbody tr:hover {

        background: #fffaf6;
    }


    .food-table tbody td {

        padding: 14px 16px;

        color: #655d56;

        border-bottom: 1px solid #f0ece8;

        font-size: 11px;

        vertical-align: middle;
    }


    .food-table tbody tr:last-child td {

        border-bottom: 0;
    }


    .food-table tbody td:first-child {

        padding-left: 22px;
    }


    .food-table tbody td:last-child {

        padding-right: 22px;

        text-align: right;
    }


    /* =========================================================
   FOOD CELL
========================================================= */

    .food-cell {

        min-width: 210px;

        display: flex;

        align-items: center;

        gap: 12px;
    }


    .food-image {

        width: 52px;

        height: 52px;

        flex: 0 0 52px;

        overflow: hidden;

        display: grid;

        place-items: center;

        border-radius: 13px;

        color: var(--orange);

        background:
            linear-gradient(145deg,
                #fff3e7,
                #ffead9);

        border: 1px solid #f6dfcc;

        font-size: 16px;
    }


    .food-image img {

        width: 100%;

        height: 100%;

        object-fit: cover;

        display: block;
    }


    .food-cell strong {

        display: block;

        margin-bottom: 3px;

        color: #302b27;

        font-size: 12px;

        font-weight: 800;
    }


    .food-cell small {

        display: block;

        color: #aaa19a;

        font-size: 9px;

        font-weight: 500;
    }


    /* =========================================================
   CATEGORY
========================================================= */

    .food-category {

        display: inline-flex;

        align-items: center;

        gap: 6px;

        padding: 7px 10px;

        border-radius: 9px;

        color: #9a561d;

        background: #fff6ed;

        border: 1px solid #f4dfca;

        font-size: 9px;

        font-weight: 700;

        white-space: nowrap;
    }


    .food-category i {

        color: var(--orange);

        font-size: 9px;
    }


    /* =========================================================
   PRICE
========================================================= */

    .food-price {

        color: #2c2824;

        font-size: 12px;

        font-weight: 800;

        white-space: nowrap;
    }


    .food-price span {

        color: var(--orange);

        margin-right: 2px;
    }


    /* =========================================================
   STATUS
========================================================= */

    .food-status {

        min-width: 88px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        gap: 7px;

        padding: 7px 11px;

        border-radius: 30px;

        font-size: 9px;

        font-weight: 800;
    }


    .food-status::before {

        content: "";

        width: 6px;

        height: 6px;

        flex: 0 0 6px;

        border-radius: 50%;
    }


    .food-status.available {

        color: #087944;

        background: #eaf8f0;

        border: 1px solid #d5f0e1;
    }


    .food-status.available::before {

        background: #1fa463;

        box-shadow:
            0 0 0 3px #dff4e9;
    }


    .food-status.unavailable {

        color: #9b3f3f;

        background: #fff0f0;

        border: 1px solid #f5dada;
    }


    .food-status.unavailable::before {

        background: #d94b4b;

        box-shadow:
            0 0 0 3px #ffe2e2;
    }


    /* =========================================================
   DATE
========================================================= */

    .food-date {

        color: #918981;

        font-size: 10px;

        font-weight: 500;

        white-space: nowrap;
    }


    /* =========================================================
   ACTIONS
========================================================= */

    .actions {

        display: flex;

        align-items: center;

        justify-content: flex-end;

        gap: 7px;
    }


    .action {

        width: 35px;

        height: 35px;

        display: grid;

        place-items: center;

        border: 1px solid #e7e1dc;

        border-radius: 10px;

        color: #817870;

        background: #ffffff;

        font-size: 11px;

        transition:
            color .18s ease,
            background .18s ease,
            border-color .18s ease,
            transform .18s ease;
    }


    .action:hover {

        color: var(--orange);

        background: #fff7f0;

        border-color: #f3c5a1;

        transform: translateY(-2px);
    }


    .action.delete:hover {

        color: var(--red);

        background: #fff4f4;

        border-color: #efc5c5;
    }

    /* Delete action form */
    .delete-food-form {
        margin: 0;
        padding: 0;
        display: inline-flex;
    }

    .delete-food-form .action {
        cursor: pointer;
        font-family: inherit;
    }


    /* =========================================================
   EMPTY STATE
========================================================= */

    .empty-state {

        min-height: 390px;

        display: flex;

        flex-direction: column;

        align-items: center;

        justify-content: center;

        padding: 45px 30px;

        text-align: center;

        background:
            linear-gradient(180deg,
                #ffffff,
                #fcfbfa);
    }


    .empty-icon {

        width: 78px;

        height: 78px;

        margin-bottom: 18px;

        display: grid;

        place-items: center;

        border-radius: 22px;

        color: var(--orange);

        background:
            linear-gradient(145deg,
                #fff3e7,
                #ffead9);

        border: 1px solid #f6dfcc;

        box-shadow:
            0 10px 25px rgba(245, 130, 32, .09);

        font-size: 25px;
    }


    .empty-state strong {

        color: #302b27;

        font-size: 15px;

        font-weight: 800;
    }


    .empty-state p {

        max-width: 400px;

        margin: 7px 0 0;

        color: #a09891;

        font-size: 10px;

        line-height: 1.7;
    }


    /* =========================================================
   RESPONSIVE
========================================================= */

    @media (max-width: 1200px) {

        .food-layout {

            grid-template-columns:
                350px minmax(0, 1fr);
        }
    }


    @media (max-width: 992px) {

        .food-main {

            margin-left: 0;
        }

        .food-layout {

            grid-template-columns: 1fr;
        }

        .form-panel {

            width: 100%;

            max-width: 600px;

            margin: 0 auto;
        }
    }


    @media (max-width: 700px) {

        .content {

            padding:
                20px 15px 40px;
        }

        .topbar {

            height: 72px;

            padding: 0 16px;
        }

        .topbar-title h1 {

            font-size: 19px;
        }

        .topbar-title p {

            display: none;
        }

        .topbar-right .topbar-link {

            display: none;
        }

        .top-profile>div:not(.avatar) {

            display: none;
        }

        .food-intro {

            min-height: auto;

            padding: 23px;

            align-items: flex-start;
        }

        .food-intro h2 {

            font-size: 23px;
        }

        .food-intro p {

            font-size: 10px;
        }

        .intro-icon {

            width: 52px;

            height: 52px;

            flex: 0 0 52px;

            font-size: 18px;
        }

        .form-top {

            padding:
                22px 18px;
        }

        .food-form {

            padding:
                20px 16px;
        }

        .panel-header {

            padding:
                16px;
        }

        .table-count {

            min-width: auto;

            padding:
                7px 9px;
        }

        .table-count span {

            display: none;
        }

        .table-tools {

            align-items: stretch;

            flex-direction: column;

            padding:
                12px 15px;
        }

        .table-tools-info {

            align-items: flex-start;

            flex-direction: column;

            gap: 2px;
        }

        .table-search {

            width: 100%;
        }

        .food-table thead th {

            padding:
                13px 14px;
        }

        .food-table tbody td {

            padding:
                15px 14px;
        }

        .food-table tbody td:first-child,
        .food-table thead th:first-child {

            padding-left: 16px;
        }

        .food-table tbody td:last-child,
        .food-table thead th:last-child {

            padding-right: 16px;
        }
    }


    /* =========================================================
       MENU PAGE — ORDER-PAGE VISUAL SYSTEM
       ========================================================= */
    .food-main .content {
        max-width: 1550px;
        padding: 24px 28px 42px;
    }

    .food-intro {
        min-height: 118px;
        padding: 24px 28px;
        margin-bottom: 18px;
        border-radius: 16px;
    }

    .food-intro h2 {
        font-size: 25px;
        letter-spacing: -.6px;
    }

    .food-intro p {
        font-size: 11px;
    }

    .food-layout {
        grid-template-columns: 335px minmax(0, 1fr);
        gap: 18px;
    }

    .panel,
    .table-panel {
        border-radius: 16px;
    }

    .form-panel {
        padding: 8px;
    }

    .form-top {
        padding: 19px 18px 16px;
    }

    .form-top-icon {
        width: 48px;
        height: 48px;
        margin-bottom: 9px;
        border-radius: 14px;
        font-size: 17px;
    }

    .form-top h3 {
        font-size: 16px;
    }

    .form-top p {
        font-size: 9px;
    }

    .food-form {
        margin: 0 8px 8px;
        padding: 18px 15px 15px;
    }

    .form-group {
        margin-bottom: 13px;
    }

    .form-label {
        margin-bottom: 6px;
        font-size: 10px;
    }

    .field {
        min-height: 42px;
        padding: 0 11px;
        border-radius: 10px;
    }

    .field input,
    .field select,
    .field textarea {
        font-size: 10px;
    }

    .field input::placeholder,
    .field textarea::placeholder {
        font-size: 9px;
    }

    .textarea-field {
        min-height: 82px;
        padding-top: 11px;
    }

    .field textarea {
        height: 60px;
    }

    .image-field {
        min-height: 48px;
    }

    .form-note {
        margin: 0 0 13px;
        padding: 8px 9px;
        font-size: 8px;
    }

    .btn-clear,
    .btn-save {
        min-height: 41px;
        border-radius: 10px;
        font-size: 9px;
    }

    .table-panel {
        min-height: 0;
    }

    .panel-header {
        min-height: 70px;
        padding: 14px 18px;
    }

    .panel-header-icon {
        width: 38px;
        height: 38px;
        flex-basis: 38px;
        border-radius: 11px;
        font-size: 13px;
    }

    .panel-header h3 {
        font-size: 14px;
    }

    .panel-header p {
        font-size: 9px;
    }

    .table-count {
        min-width: 68px;
        padding: 7px 9px;
    }

    .table-count strong {
        font-size: 15px;
    }

    .table-tools {
        min-height: 58px;
        padding: 9px 16px;
    }

    .table-tools strong {
        font-size: 10px;
    }

    .table-tools small {
        font-size: 8px;
    }

    .table-search {
        width: 220px;
        height: 36px;
        border-radius: 9px;
    }

    .table-search input {
        font-size: 9px;
    }

    .food-table {
        min-width: 0;
        table-layout: fixed;
    }

    .food-table thead th {
        padding: 11px 13px;
        font-size: 8px;
    }

    .food-table tbody td {
        padding: 11px 13px;
        font-size: 9px;
    }

    /* =========================================================
       DESKTOP TABLE FIT
       Keep all columns visible without horizontal scrolling.
       Mobile can still use the table wrapper as a safety net.
    ========================================================== */

    .food-table th:nth-child(1),
    .food-table td:nth-child(1) {
        width: 39%;
    }

    .food-table th:nth-child(2),
    .food-table td:nth-child(2) {
        width: 21%;
    }

    .food-table th:nth-child(3),
    .food-table td:nth-child(3) {
        width: 14%;
    }

    .food-table th:nth-child(4),
    .food-table td:nth-child(4) {
        width: 26%;
    }

    .food-table thead th,
    .food-table tbody td {
        overflow: hidden;
    }

    .food-cell {
        min-width: 0;
        width: 100%;
    }

    .food-cell>div:last-child {
        min-width: 0;
        overflow: hidden;
    }

    .food-cell strong,
    .food-cell small {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .food-category {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .food-table .actions {
        flex-wrap: nowrap;
    }

    .food-table .action {
        flex: 0 0 31px;
    }

    @media (min-width: 993px) {

        .food-table thead th {
            padding-left: 9px;
            padding-right: 9px;
        }

        .food-table tbody td {
            padding-left: 9px;
            padding-right: 9px;
        }

        .food-table thead th:first-child,
        .food-table tbody td:first-child {
            padding-left: 16px;
        }

        .food-table thead th:last-child,
        .food-table tbody td:last-child {
            padding-right: 16px;
        }

        .food-image {
            width: 52px;
            height: 52px;
            flex-basis: 52px;
        }

        .food-cell {
            gap: 8px;
        }

        .food-cell strong {
            font-size: 9.5px;
        }

        .food-category {
            padding: 5px 7px;
            font-size: 7.5px;
        }

        .food-price {
            font-size: 9.5px;
        }

        .food-status {
            min-width: 70px;
            padding: 5px 7px;
            font-size: 7.5px;
        }

        .food-date {
            font-size: 7.5px;
        }

        .food-table .action {
            width: 29px;
            height: 29px;
            flex-basis: 29px;
        }
    }


    .food-cell {
        min-width: 190px;
        gap: 9px;
    }

    /* Larger dish thumbnails, following the order page's image-first approach. */
    .food-image {
        width: 58px;
        height: 58px;
        flex-basis: 58px;
        border-radius: 12px;
    }

    .food-cell strong {
        font-size: 10px;
        line-height: 1.35;
    }

    .food-cell small {
        font-size: 8px;
    }

    .food-category {
        padding: 6px 8px;
        font-size: 8px;
    }

    .food-price {
        font-size: 10px;
    }

    .food-status {
        min-width: 78px;
        padding: 6px 9px;
        font-size: 8px;
    }

    .food-date {
        font-size: 8px;
    }

    .action {
        width: 31px;
        height: 31px;
        border-radius: 8px;
        font-size: 9px;
    }

    @media (max-width: 1200px) {
        .food-layout {
            grid-template-columns: 310px minmax(0, 1fr);
        }
    }

    @media (max-width: 992px) {
        .food-main {
            margin-left: 0;
        }

        .food-layout {
            grid-template-columns: 1fr;
        }

        .form-panel {
            max-width: 600px;
            margin: 0 auto;
        }
    }

    @media (max-width: 700px) {
        .food-main .content {
            padding: 18px 12px 30px;
        }

        .food-intro {
            padding: 20px;
        }

        .food-intro h2 {
            font-size: 21px;
        }

        .table-search {
            width: 100%;
        }

        .food-image {
            width: 52px;
            height: 52px;
            flex-basis: 52px;
        }
    }

    /* =========================================================
       EDIT FOOD MODAL
       ========================================================= */
    .edit-food-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(17, 17, 17, .58);
        backdrop-filter: blur(5px);
    }

    .edit-food-modal.show {
        display: flex;
        animation: editFoodFadeIn .18s ease both;
    }

    .edit-food-dialog {
        width: min(560px, 100%);
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        border: 1px solid #ebe7e3;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 25px 70px rgba(0, 0, 0, .24);
    }

    .edit-food-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 18px 20px;
        border-bottom: 1px solid #eeeae6;
    }

    .edit-food-title {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .edit-food-title-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, var(--orange), #e86a12);
        box-shadow: 0 7px 18px rgba(245, 130, 32, .18);
    }

    .edit-food-title h3 {
        margin: 0;
        color: #292522;
        font-size: 16px;
        font-weight: 800;
    }

    .edit-food-title p {
        margin: 3px 0 0;
        color: #99918a;
        font-size: 9px;
    }

    .edit-food-close {
        width: 35px;
        height: 35px;
        display: grid;
        place-items: center;
        border: 0;
        border-radius: 9px;
        color: #817870;
        background: #f7f5f3;
        cursor: pointer;
    }

    .edit-food-close:hover {
        color: var(--red);
        background: #fff1f1;
    }

    .edit-food-form {
        padding: 20px;
    }

    .edit-food-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .edit-food-group.full {
        grid-column: 1 / -1;
    }

    .edit-food-label {
        display: block;
        margin-bottom: 6px;
        color: #403a35;
        font-size: 10px;
        font-weight: 800;
    }

    .edit-food-input {
        width: 100%;
        min-height: 43px;
        padding: 0 11px;
        border: 1px solid #e4e1de;
        border-radius: 10px;
        outline: none;
        color: #302b27;
        background: #fff;
        font-size: 10px;
        font-weight: 600;
        transition: .2s ease;
    }

    .edit-food-input:focus {
        border-color: #efae73;
        box-shadow: 0 0 0 4px rgba(245, 130, 32, .08);
    }

    .edit-food-footer {
        display: flex;
        justify-content: flex-end;
        gap: 9px;
        padding: 15px 20px;
        border-top: 1px solid #eeeae6;
        background: #fcfbfa;
    }

    .edit-food-cancel,
    .edit-food-save {
        min-height: 40px;
        padding: 0 16px;
        border-radius: 9px;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
    }

    .edit-food-cancel {
        border: 1px solid #ddd8d3;
        color: #746c65;
        background: #fff;
    }

    .edit-food-save {
        border: 0;
        color: #fff;
        background: linear-gradient(135deg, var(--orange), #e86a12);
        box-shadow: 0 7px 17px rgba(245, 130, 32, .20);
    }

    .edit-food-save:hover {
        transform: translateY(-1px);
    }

    @keyframes editFoodFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @media (max-width: 600px) {
        .edit-food-grid {
            grid-template-columns: 1fr;
        }

        .edit-food-group.full {
            grid-column: auto;
        }

        .edit-food-form {
            padding: 16px;
        }
    }


    /* =========================================================
       DELETE FOOD CONFIRMATION MODAL
       ========================================================= */
    .delete-food-modal {
        position: fixed;
        inset: 0;
        z-index: 10001;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(17, 17, 17, .58);
        backdrop-filter: blur(5px);
    }

    .delete-food-modal.show {
        display: flex;
        animation: deleteModalFade .18s ease both;
    }

    .delete-food-dialog {
        width: min(430px, 100%);
        overflow: hidden;
        border: 1px solid #eee3df;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 25px 70px rgba(0, 0, 0, .24);
        transform: translateY(0);
    }

    .delete-food-content {
        padding: 26px 24px 20px;
        text-align: center;
    }

    .delete-food-icon {
        width: 58px;
        height: 58px;
        display: grid;
        place-items: center;
        margin: 0 auto 14px;
        border-radius: 16px;
        color: #c83f3f;
        background: #fff1f1;
        border: 1px solid #f5d6d6;
        font-size: 20px;
    }

    .delete-food-content h3 {
        margin: 0;
        color: #292522;
        font-size: 16px;
        font-weight: 800;
    }

    .delete-food-content p {
        margin: 7px auto 0;
        max-width: 340px;
        color: #8f8780;
        font-size: 10px;
        line-height: 1.6;
    }

    .delete-food-name {
        display: inline-block;
        max-width: 100%;
        margin-top: 11px;
        padding: 7px 11px;
        border-radius: 9px;
        color: #9b3f3f;
        background: #fff6f6;
        border: 1px solid #f4dddd;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .delete-food-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
        padding: 15px 20px 20px;
    }

    .delete-food-cancel,
    .delete-food-confirm {
        min-height: 42px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
    }

    .delete-food-cancel {
        border: 1px solid #ddd8d3;
        color: #746c65;
        background: #fff;
    }

    .delete-food-cancel:hover {
        background: #faf8f6;
    }

    .delete-food-confirm {
        border: 0;
        color: #fff;
        background: #d94b4b;
        box-shadow: 0 7px 17px rgba(217, 75, 75, .18);
    }

    .delete-food-confirm:hover {
        background: #c83f3f;
        transform: translateY(-1px);
    }

    @keyframes deleteModalFade {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @media (max-width: 480px) {
        .delete-food-content {
            padding: 22px 17px 16px;
        }

        .delete-food-actions {
            padding: 13px 15px 17px;
        }
    }

    /* =========================================================
       BOOTSTRAP PAGINATION
    ========================================================= */

    .food-pagination-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 18px;
        border-top: 1px solid #eeeae6;
        background: #fcfbfa;
    }

    .food-pagination-info {
        color: #8f8780;
        font-size: 9px;
        font-weight: 600;
        letter-spacing: .1px;
    }

    .food-pagination-info strong {
        color: #403a35;
        font-weight: 800;
    }

    .food-pagination {
        margin: 0;
        gap: 4px;
    }

    .food-pagination .page-link {
        min-width: 34px;
        height: 34px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        padding: 0 9px;

        border: 1px solid #e4dfda;
        border-radius: 8px !important;

        color: #655d56;
        background: #ffffff;

        font-family: "Poppins", sans-serif;
        font-size: 9px;
        font-weight: 800;

        box-shadow: none;

        transition:
            color .18s ease,
            background .18s ease,
            border-color .18s ease,
            transform .18s ease,
            box-shadow .18s ease;
    }

    .food-pagination .page-link:hover {
        color: var(--orange);
        background: #fff7f0;
        border-color: #f1c5a1;
        transform: translateY(-1px);
    }

    .food-pagination .page-item.active .page-link {
        color: #ffffff;
        background:
            linear-gradient(135deg,
                var(--orange),
                #e86a12);
        border-color: var(--orange);
        box-shadow:
            0 5px 13px rgba(245, 130, 32, .20);
    }

    .food-pagination .page-item.disabled .page-link {
        color: #c0b9b2;
        background: #f7f5f3;
        border-color: #ebe7e3;
        opacity: .8;
        transform: none;
    }

    .food-pagination .page-link:focus {
        color: var(--orange);
        background: #fff7f0;
        border-color: #f1c5a1;
        box-shadow:
            0 0 0 3px rgba(245, 130, 32, .10);
    }

    @media (max-width: 700px) {

        .food-pagination-wrap {
            align-items: stretch;
            flex-direction: column;
            padding: 13px 15px;
        }

        .food-pagination-info {
            text-align: center;
        }

        .food-pagination {
            justify-content: center;
            flex-wrap: wrap;
        }
    }

    /* =========================================================
       PROFESSIONAL MENU POLISH
       ========================================================= */
    .food-main .content {
        max-width: 1600px;
        padding: 30px 34px 55px;
    }

    .food-intro {
        min-height: 150px;
        padding: 30px 34px;
        margin-bottom: 24px;
        border-radius: 20px;
    }

    .food-intro h2 {
        font-size: 31px;
    }

    .food-intro p {
        font-size: 14px;
    }

    .eyebrow {
        font-size: 11px;
        letter-spacing: 1.9px;
    }

    .food-layout {
        grid-template-columns: 365px minmax(0, 1fr);
        gap: 24px;
    }

    .panel,
    .table-panel {
        border-radius: 20px;
    }

    .form-top h3 {
        font-size: 20px;
    }

    .form-top p {
        font-size: 12px;
    }

    .form-label {
        font-size: 13px;
    }

    .field {
        min-height: 51px;
        padding: 0 14px;
        border-radius: 12px;
    }

    .field input,
    .field select,
    .field textarea {
        font-size: 13px;
    }

    .field input::placeholder,
    .field textarea::placeholder {
        font-size: 12px;
    }

    .btn-clear,
    .btn-save {
        min-height: 49px;
        font-size: 12px;
    }

    .panel-header {
        min-height: 86px;
        padding: 19px 24px;
    }

    .panel-header h3 {
        font-size: 18px;
    }

    .panel-header p {
        font-size: 11px;
    }

    .table-header-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-export-pdf {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 15px;
        border: 1px solid #e7d4ca;
        border-radius: 10px;
        color: #b33d2e;
        background: #fff7f5;
        font-size: 12px;
        font-weight: 800;
        transition: .2s ease;
    }

    .btn-export-pdf:hover {
        color: #fff;
        background: #c94737;
        border-color: #c94737;
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(201, 71, 55, .18);
    }

    .table-count {
        min-width: 94px;
        padding: 9px 13px;
    }

    .table-count strong {
        font-size: 19px;
    }

    .table-count span {
        font-size: 10px;
    }

    .table-tools {
        min-height: 72px;
        padding: 13px 22px;
    }

    .table-tools strong {
        font-size: 13px;
    }

    .table-tools small {
        font-size: 10px;
    }

    .table-search {
        width: 280px;
        height: 44px;
    }

    .table-search input {
        font-size: 12px;
    }

    .food-table {
        min-width: 0;
        table-layout: fixed;
    }

    .food-table thead th {
        padding: 15px 16px;
        font-size: 10px;
        letter-spacing: 1.1px;
    }

    .food-table tbody td {
        padding: 15px 16px;
        font-size: 12px;
    }

    .food-cell {
        gap: 12px;
    }

    .food-image {
        width: 62px;
        height: 62px;
        flex-basis: 62px;
    }

    .food-cell strong {
        font-size: 13px;
    }

    .food-cell small {
        font-size: 10px;
    }

    .food-category {
        padding: 7px 10px;
        font-size: 10px;
    }

    .food-price {
        font-size: 13px;
    }

    .food-status {
        min-width: 88px;
        padding: 7px 10px;
        font-size: 10px;
    }

    .food-table .action,
    .action {
        width: 35px;
        height: 35px;
        flex-basis: 35px;
        font-size: 11px;
    }

    .food-table tbody .category-divider-row td {
        padding: 0;
        background: #f8f6f3;
        border-bottom: 1px solid #ebe4de;
    }

    .category-divider {
        min-height: 46px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 18px;
        border-left: 4px solid var(--orange);
        background: linear-gradient(90deg, #fff8f2 0%, #faf9f7 100%);
    }

    .category-divider-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #302a26;
        font-size: 13px;
        font-weight: 800;
    }

    .category-divider-icon {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        color: var(--orange);
        background: #fff0e3;
        border: 1px solid #f4d9c2;
    }

    .category-divider-label {
        color: #a18e80;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1.1px;
    }

    .food-pagination-info {
        font-size: 10px;
    }

    .food-pagination .page-link {
        min-width: 36px;
        height: 36px;
        font-size: 10px;
    }

    @media (max-width:1200px) {
        .food-layout {
            grid-template-columns: 330px minmax(0, 1fr);
        }

        .btn-export-pdf span {
            display: none;
        }

        .btn-export-pdf {
            width: 42px;
            padding: 0;
        }
    }

    @media (max-width:992px) {
        .food-main {
            margin-left: 0;
        }

        .food-layout {
            grid-template-columns: 1fr;
        }

        .form-panel {
            max-width: 650px;
            margin: 0 auto;
        }
    }

    @media (max-width:700px) {
        .food-main .content {
            padding: 20px 13px 35px;
        }

        .food-intro {
            padding: 23px;
            min-height: 135px;
        }

        .food-intro h2 {
            font-size: 24px;
        }

        .food-intro p {
            font-size: 11px;
        }

        .panel-header {
            padding: 16px;
        }

        .table-tools {
            align-items: stretch;
            flex-direction: column;
            padding: 12px 15px;
        }

        .table-search {
            width: 100%;
        }

        .food-table {
            min-width: 760px;
            table-layout: auto;
        }
    }
    </style>

</head>


<body>


    <!-- =========================================================
     SIDEBAR
========================================================= -->

    <?php include '../includes/sidebar.php'; ?>


    <!-- =========================================================
     MAIN
========================================================= -->

    <main class="main-content food-main">


        <!-- =====================================================
         TOP BAR
    ====================================================== -->



        <!-- =====================================================
         CONTENT
    ====================================================== -->

        <div class="content">


            <!-- =================================================
             NOTIFICATION
        ================================================== -->

            <?php if ($food_message): ?>

            <div id="foodMessage" class="food-message
                    <?= $food_message['type'] === 'success'
                        ? 'success'
                        : 'error' ?>" role="alert">

                <div class="food-message-icon">

                    <i class="fa-solid
                        <?= $food_message['type'] === 'success'
                            ? 'fa-check'
                            : 'fa-xmark' ?>">
                    </i>

                </div>


                <div class="food-message-text">

                    <?= htmlspecialchars(
                        $food_message['message']
                    ) ?>

                </div>


                <button type="button" class="food-message-close" id="closeFoodMessage" aria-label="Close notification">

                    <i class="fa-solid fa-xmark"></i>

                </button>


                <div class="food-message-progress"></div>

            </div>

            <?php endif; ?>


            <!-- =================================================
             HERO
        ================================================== -->

            <section class="food-intro">

                <div>

                    <span class="eyebrow">
                        FOOD MANAGEMENT
                    </span>

                    <h2>
                        Manage Food Menu
                    </h2>

                    <p>
                        Add, organize and manage the food items
                        available in your restaurant.
                    </p>

                </div>


                <div class="intro-icon">

                    <i class="fa-solid fa-utensils"></i>

                </div>

            </section>


            <!-- =================================================
             FORM + TABLE
        ================================================== -->

            <section class="food-layout">


                <!-- =================================================
                 ADD FOOD FORM
            ================================================= -->

                <article class="panel form-panel">

                    <div class="form-card">


                        <!-- FORM HEADER -->

                        <div class="form-top">

                            <div class="form-top-icon">

                                <i class="fa-solid fa-circle-plus"></i>

                            </div>

                            <h3>
                                Add New Food
                            </h3>

                            <p>
                                Add a new item to your restaurant menu.
                            </p>

                        </div>


                        <!-- FORM -->

                        <form method="POST" action="../handlers/add_food.php" class="food-form"
                            enctype="multipart/form-data" autocomplete="off">


                            <!-- FOOD NAME -->

                            <div class="form-group">

                                <label class="form-label" for="name">
                                    Food Name
                                    <span>*</span>
                                </label>

                                <div class="field">

                                    <i class="fa-solid fa-utensils"></i>

                                    <input type="text" id="name" name="name" placeholder="e.g. Jollof Rice"
                                        maxlength="150" required>

                                </div>

                            </div>


                            <!-- CATEGORY -->

                            <div class="form-group">

                                <label class="form-label" for="category_id">
                                    Category
                                    <span>*</span>
                                </label>

                                <div class="field">

                                    <i class="fa-solid fa-layer-group"></i>

                                    <select id="category_id" name="category_id" required>

                                        <option value="" selected disabled>
                                            Select category
                                        </option>

                                        <?php foreach ($categories as $category): ?>

                                        <option value="<?= (int) $category['id'] ?>">
                                            <?= htmlspecialchars(
                                                $category['name']
                                            ) ?>
                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>


                            <!-- PRICE -->

                            <div class="form-group">

                                <label class="form-label" for="price">
                                    Price
                                    <span>*</span>
                                </label>

                                <div class="field price-field">

                                    <span class="price-symbol">
                                        GHC
                                    </span>

                                    <input type="number" id="price" name="price" placeholder="0.00" min="0" step="0.01"
                                        required>

                                </div>

                            </div>


                            <!-- DESCRIPTION -->

                            <div class="form-group">

                                <label class="form-label" for="description">
                                    Description
                                </label>

                                <div class="field textarea-field">

                                    <i class="fa-regular fa-note-sticky"></i>

                                    <textarea id="description" name="description"
                                        placeholder="Brief description of this food..." maxlength="500"></textarea>

                                </div>

                            </div>


                            <!-- IMAGE -->

                            <div class="form-group">

                                <label class="form-label" for="image">
                                    Food Image
                                </label>

                                <div class="field image-field">

                                    <i class="fa-regular fa-image"></i>

                                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

                                </div>

                            </div>


                            <!-- STATUS -->

                            <div class="form-group">

                                <label class="form-label" for="status">
                                    Status
                                    <span>*</span>
                                </label>

                                <div class="field">

                                    <i class="fa-solid fa-toggle-on"></i>

                                    <select id="status" name="status" required>

                                        <option value="Available" selected>
                                            Available
                                        </option>

                                        <option value="Unavailable">
                                            Unavailable
                                        </option>

                                    </select>

                                </div>

                            </div>


                            <!-- INFORMATION -->

                            <div class="form-note">

                                <i class="fa-solid fa-circle-info"></i>

                                <span>
                                    Select an existing category before
                                    adding a food item. Food prices are
                                    stored as decimal values.
                                </span>

                            </div>


                            <!-- BUTTONS -->

                            <div class="form-footer">

                                <button type="reset" class="btn-clear">

                                    <i class="fa-solid fa-rotate-left"></i>

                                    Clear

                                </button>


                                <button type="submit" class="btn-save">

                                    <i class="fa-solid fa-plus"></i>

                                    Add Food

                                </button>

                            </div>

                        </form>

                    </div>

                </article>


                <!-- =================================================
                 FOOD TABLE
            ================================================== -->

                <article class="panel table-panel">


                    <!-- TABLE HEADER -->

                    <div class="panel-header">

                        <div class="panel-header-icon">

                            <i class="fa-solid fa-list"></i>

                        </div>


                        <div>

                            <h3>
                                Current Food Menu
                            </h3>

                            <p>
                                Food items registered in the system.
                            </p>

                        </div>


                        <div class="table-header-actions">
                            <a class="btn-export-pdf" href="?export=pdf" target="_blank" rel="noopener"
                                title="Export food menu to PDF">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span>Export PDF</span>
                            </a>

                            <div class="table-count">

                                <strong>
                                    <?= (int) $totalFoods ?>
                                </strong>

                                <span>
                                    Foods
                                </span>

                            </div>
                        </div>

                    </div>


                    <!-- TABLE TOOLBAR -->

                    <div class="table-tools">

                        <div class="table-tools-info">

                            <strong>
                                Food List
                            </strong>

                            <small>

                                <?= (int) $totalFoods ?>

                                registered food item<?= count($foods) === 1
                                ? ''
                                : 's' ?>

                            </small>

                        </div>


                        <div class="table-search">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <form method="GET" action=""
                                style="display:flex;align-items:center;gap:9px;width:100%;margin:0;">
                                <input type="text" id="foodSearch" name="search" value="<?= htmlspecialchars(
                                        $searchTerm,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>" placeholder="Search food..." autocomplete="off">

                                <?php if ($searchTerm !== ''): ?>

                                <a href="<?= htmlspecialchars(
                                            strtok($_SERVER['REQUEST_URI'], '?'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>" title="Clear search" aria-label="Clear search" style="color:#aaa29b;">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>

                                <?php endif; ?>

                            </form>

                        </div>

                    </div>


                    <!-- TABLE -->

                    <div class="table-wrap">


                        <?php if (!empty($foods)): ?>

                        <table class="food-table">

                            <thead>

                                <tr>

                                    <th>
                                        FOOD
                                    </th>

                                    <th>
                                        CATEGORY
                                    </th>

                                    <th>
                                        PRICE
                                    </th>



                                    <th>
                                        ACTION
                                    </th>

                                </tr>

                            </thead>


                            <tbody id="foodsBody">

                                <?php
                                $visibleCategory = null;
                                foreach ($foods as $food):
                                    $foodCategory = $food['category_name'] ?? 'Uncategorized';
                                    if ($foodCategory !== $visibleCategory):
                                        $visibleCategory = $foodCategory;
                                ?>
                                <tr class="category-divider-row">
                                    <td colspan="4">
                                        <div class="category-divider">
                                            <div class="category-divider-title">
                                                <span class="category-divider-icon"><i
                                                        class="fa-solid fa-layer-group"></i></span>
                                                <span><?= htmlspecialchars($visibleCategory) ?></span>
                                            </div>
                                            <span class="category-divider-label">CATEGORY</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php$statusClass =
                                        $food['status'] === 'Available'
                                            ? 'available'
                                            : 'unavailable';

                                    ?>


                                <tr>


                                    <!-- FOOD -->

                                    <td>

                                        <div class="food-cell">

                                            <div class="food-image">

                                                <?php if (
                                                        !empty($food['image'])
                                                    ): ?>

                                                <img src="../assets/uploads/<?= htmlspecialchars(
                                                                $food['image']
                                                            ) ?>" alt="<?= htmlspecialchars(
                                                                $food['name']
                                                            ) ?>">

                                                <?php else: ?>

                                                <i class="fa-solid fa-utensils"></i>

                                                <?php endif; ?>

                                            </div>


                                            <div>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                            $food['name']
                                                        ) ?>

                                                </strong>


                                                <small>

                                                    Food
                                                    #<?= (int) $food['id'] ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="food-category">

                                            <i class="fa-solid fa-layer-group"></i>

                                            <?= htmlspecialchars(
                                                    $food['category_name']
                                                        ?? 'Uncategorized'
                                                ) ?>

                                        </span>

                                    </td>


                                    <!-- PRICE -->

                                    <td>

                                        <span class="food-price">

                                            <span>GHC</span>

                                            <?= number_format(
                                                    (float) $food['price'],
                                                    2
                                                ) ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->




                                    <!-- ACTION -->

                                    <td>

                                        <div class="actions">

                                            <button type="button" class="action edit-food-btn" title="Edit food"
                                                aria-label="Edit <?= htmlspecialchars($food['name']) ?>"
                                                data-id="<?= (int) $food['id'] ?>"
                                                data-name="<?= htmlspecialchars($food['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-category-id="<?= (int) $food['category_id'] ?>"
                                                data-price="<?= htmlspecialchars($food['price'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-status="<?= htmlspecialchars($food['status'], ENT_QUOTES, 'UTF-8') ?>">

                                                <i class="fa-solid fa-pen"></i>

                                            </button>


                                            <form method="POST" action="../handlers/delete_food.php"
                                                class="delete-food-form">
                                                <input type="hidden" name="food_id" value="<?= (int) $food['id'] ?>">
                                                <button type="button" class="action delete delete-food-btn"
                                                    title="Delete food"
                                                    aria-label="Delete <?= htmlspecialchars($food['name']) ?>"
                                                    data-id="<?= (int) $food['id'] ?>"
                                                    data-name="<?= htmlspecialchars($food['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>

                                        </div>

                                    </td>

                                </tr>


                                <?php endforeach; ?>

                            </tbody>

                        </table>


                        <?php else: ?>


                        <!-- EMPTY STATE -->

                        <div class="empty-state">

                            <div class="empty-icon">

                                <i class="fa-solid fa-utensils"></i>

                            </div>


                            <strong>
                                No food items yet
                            </strong>


                            <p>
                                Add your first food item using the
                                form. Food items will appear here
                                automatically after they are saved.
                            </p>

                        </div>


                        <?php endif; ?>


                    </div>

                    <!-- =================================================
                         BOOTSTRAP PAGINATION
                    ================================================== -->

                    <?php if ($totalFoods > 0): ?>

                    <div class="food-pagination-wrap">

                        <div class="food-pagination-info">

                            Showing

                            <strong>
                                <?= $firstItem ?>
                            </strong>

                            –

                            <strong>
                                <?= $lastItem ?>
                            </strong>

                            of

                            <strong>
                                <?= $totalFoods ?>
                            </strong>

                            food<?= $totalFoods === 1 ? '' : 's' ?>

                        </div>


                        <?php if ($totalPages > 1): ?>

                        <?php

                        $makePageUrl =
                            function (
                                int $targetPage
                            ) use ($searchTerm): string {

                                $params = [
                                    'page' => $targetPage
                                ];

                                if (
                                    $searchTerm !== ''
                                ) {

                                    $params['search'] =
                                        $searchTerm;
                                }

                                return '?' .
                                    http_build_query(
                                        $params
                                    );
                            };


                        /*
                        |--------------------------------------------------------------------------
                        | PAGE NUMBER WINDOW
                        |--------------------------------------------------------------------------
                        */

                        $paginationPages = [1];

                        $startPage =
                            max(
                                2,
                                $page - 2
                            );

                        $endPage =
                            min(
                                $totalPages - 1,
                                $page + 2
                            );


                        if (
                            $startPage > 2
                        ) {

                            $paginationPages[] =
                                null;
                        }


                        for (
                            $p = $startPage;
                            $p <= $endPage;
                            $p++
                        ) {

                            $paginationPages[] =
                                $p;
                        }


                        if (
                            $endPage <
                            $totalPages - 1
                        ) {

                            $paginationPages[] =
                                null;
                        }


                        if (
                            $totalPages > 1
                        ) {

                            $paginationPages[] =
                                $totalPages;
                        }

                        ?>


                        <nav aria-label="Food menu pagination">

                            <ul class="pagination food-pagination">


                                <!-- PREVIOUS -->

                                <li class="page-item
                                    <?= $page <= 1
                                        ? 'disabled'
                                        : '' ?>">

                                    <?php if (
                                        $page <= 1
                                    ): ?>

                                    <span class="page-link">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </span>

                                    <?php else: ?>

                                    <a class="page-link" href="<?= htmlspecialchars(
                                                $makePageUrl(
                                                    $page - 1
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>" aria-label="Previous page">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>

                                    <?php endif; ?>

                                </li>


                                <!-- PAGE NUMBERS -->

                                <?php foreach (
                                    $paginationPages
                                    as $paginationPage
                                ): ?>

                                <?php if (
                                        $paginationPage === null
                                    ): ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        …
                                    </span>

                                </li>

                                <?php elseif (
                                        $paginationPage === $page
                                    ): ?>

                                <li class="page-item active" aria-current="page">

                                    <span class="page-link">
                                        <?= $paginationPage ?>
                                    </span>

                                </li>

                                <?php else: ?>

                                <li class="page-item">

                                    <a class="page-link" href="<?= htmlspecialchars(
                                                    $makePageUrl(
                                                        (int) $paginationPage
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>">
                                        <?= $paginationPage ?>
                                    </a>

                                </li>

                                <?php endif; ?>

                                <?php endforeach; ?>


                                <!-- NEXT -->

                                <li class="page-item
                                    <?= $page >= $totalPages
                                        ? 'disabled'
                                        : '' ?>">

                                    <?php if (
                                        $page >= $totalPages
                                    ): ?>

                                    <span class="page-link">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </span>

                                    <?php else: ?>

                                    <a class="page-link" href="<?= htmlspecialchars(
                                                $makePageUrl(
                                                    $page + 1
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>" aria-label="Next page">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>

                                    <?php endif; ?>

                                </li>

                            </ul>

                        </nav>

                        <?php endif; ?>

                    </div>

                    <?php endif; ?>

                </article>

            </section>

        </div>

    </main>


    <!-- =========================================================
     JAVASCRIPT
========================================================= -->

    <script>
    document.addEventListener("DOMContentLoaded", function() {

        /* ---------------------------------------------------------
           FOOD NOTIFICATION
        --------------------------------------------------------- */
        const foodMessage = document.getElementById("foodMessage");
        const closeFoodMessageButton =
            document.getElementById("closeFoodMessage");

        function closeFoodMessage() {
            if (!foodMessage) return;

            foodMessage.classList.add("hide");

            setTimeout(function() {
                if (foodMessage && foodMessage.parentNode) {
                    foodMessage.remove();
                }
            }, 350);
        }

        if (closeFoodMessageButton) {
            closeFoodMessageButton.addEventListener("click", function(event) {
                event.preventDefault();
                event.stopPropagation();
                closeFoodMessage();
            });
        }

        if (foodMessage) {
            setTimeout(closeFoodMessage, 5000);
        }


        /* ---------------------------------------------------------
           EDIT FOOD POPUP
        --------------------------------------------------------- */
        const editFoodModal = document.getElementById("editFoodModal");
        const editFoodClose = document.getElementById("editFoodClose");
        const editFoodCancel = document.getElementById("editFoodCancel");

        function closeEditFoodModal() {
            if (!editFoodModal) return;

            editFoodModal.classList.remove("show");
            editFoodModal.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
        }

        function openEditFoodModal(button) {
            if (!editFoodModal) {
                console.error("Edit modal #editFoodModal not found.");
                return;
            }

            const id = button.getAttribute("data-id") || "";
            const name = button.getAttribute("data-name") || "";
            const categoryId =
                button.getAttribute("data-category-id") || "";
            const price = button.getAttribute("data-price") || "";
            const status =
                button.getAttribute("data-status") || "Available";

            const idInput = document.getElementById("editFoodId");
            const nameInput = document.getElementById("editFoodName");
            const categoryInput =
                document.getElementById("editFoodCategory");
            const priceInput = document.getElementById("editFoodPrice");
            const statusInput = document.getElementById("editFoodStatus");
            const imageInput = document.getElementById("editFoodImage");

            if (idInput) idInput.value = id;
            if (nameInput) nameInput.value = name;
            if (categoryInput) categoryInput.value = categoryId;
            if (priceInput) priceInput.value = price;
            if (statusInput) statusInput.value = status;
            if (imageInput) imageInput.value = "";

            editFoodModal.classList.add("show");
            editFoodModal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";

            if (nameInput) {
                setTimeout(function() {
                    nameInput.focus();
                }, 80);
            }
        }

        /*
         * Event delegation is intentional.
         * It also works if the table is rebuilt by pagination/search.
         */
        document.addEventListener("click", function(event) {
            const editButton =
                event.target.closest(".edit-food-btn");

            if (editButton) {
                event.preventDefault();
                event.stopPropagation();
                openEditFoodModal(editButton);
                return;
            }

            const deleteButton =
                event.target.closest(".delete-food-btn");

            if (deleteButton) {
                event.preventDefault();
                event.stopPropagation();
                openDeleteFoodModal(deleteButton);
                return;
            }
        });

        if (editFoodClose) {
            editFoodClose.addEventListener(
                "click",
                closeEditFoodModal
            );
        }

        if (editFoodCancel) {
            editFoodCancel.addEventListener(
                "click",
                closeEditFoodModal
            );
        }

        if (editFoodModal) {
            editFoodModal.addEventListener("click", function(event) {
                if (event.target === editFoodModal) {
                    closeEditFoodModal();
                }
            });
        }


        /* ---------------------------------------------------------
           DELETE FOOD CONFIRMATION
        --------------------------------------------------------- */
        const deleteFoodModal =
            document.getElementById("deleteFoodModal");

        const deleteFoodName =
            document.getElementById("deleteFoodName");

        const deleteFoodCancel =
            document.getElementById("deleteFoodCancel");

        const deleteFoodConfirm =
            document.getElementById("deleteFoodConfirm");

        let deleteFoodForm = null;

        function closeDeleteFoodModal() {
            if (!deleteFoodModal) return;

            deleteFoodModal.classList.remove("show");
            deleteFoodModal.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
            deleteFoodForm = null;

            if (deleteFoodConfirm) {
                deleteFoodConfirm.disabled = false;
                deleteFoodConfirm.innerHTML =
                    '<i class="fa-solid fa-trash"></i> Delete';
            }
        }

        function openDeleteFoodModal(button) {
            if (!deleteFoodModal) {
                console.error(
                    "Delete modal #deleteFoodModal not found."
                );
                return;
            }

            const form =
                button.closest(".delete-food-form");

            if (!form) {
                console.error(
                    "Delete button is missing its delete form."
                );
                return;
            }

            deleteFoodForm = form;

            const foodName =
                button.getAttribute("data-name") ||
                "this food item";

            if (deleteFoodName) {
                deleteFoodName.textContent = foodName;
            }

            deleteFoodModal.classList.add("show");
            deleteFoodModal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        }

        if (deleteFoodCancel) {
            deleteFoodCancel.addEventListener(
                "click",
                function(event) {
                    event.preventDefault();
                    closeDeleteFoodModal();
                }
            );
        }

        if (deleteFoodConfirm) {
            deleteFoodConfirm.addEventListener(
                "click",
                function(event) {
                    event.preventDefault();

                    if (!deleteFoodForm) {
                        closeDeleteFoodModal();
                        return;
                    }

                    deleteFoodConfirm.disabled = true;
                    deleteFoodConfirm.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';

                    /*
                     * Use native submit so a form named "submit" or any
                     * other form control cannot shadow form.submit().
                     */
                    HTMLFormElement.prototype.submit.call(
                        deleteFoodForm
                    );
                }
            );
        }

        if (deleteFoodModal) {
            deleteFoodModal.addEventListener("click", function(event) {
                if (event.target === deleteFoodModal) {
                    closeDeleteFoodModal();
                }
            });
        }


        /* ---------------------------------------------------------
           ESCAPE KEY
        --------------------------------------------------------- */
        document.addEventListener("keydown", function(event) {
            if (event.key !== "Escape") return;

            if (
                editFoodModal &&
                editFoodModal.classList.contains("show")
            ) {
                closeEditFoodModal();
                return;
            }

            if (
                deleteFoodModal &&
                deleteFoodModal.classList.contains("show")
            ) {
                closeDeleteFoodModal();
            }
        });

    });
    </script>



    <!-- =========================================================
         EDIT FOOD MODAL
    ========================================================== -->
    <div class="edit-food-modal" id="editFoodModal" aria-hidden="true">
        <div class="edit-food-dialog" role="dialog" aria-modal="true" aria-labelledby="editFoodModalTitle">

            <div class="edit-food-header">
                <div class="edit-food-title">
                    <div class="edit-food-title-icon">
                        <i class="fa-solid fa-pen"></i>
                    </div>

                    <div>
                        <h3 id="editFoodModalTitle">Edit Food</h3>
                        <p>Update this menu item and save your changes.</p>
                    </div>
                </div>

                <button type="button" class="edit-food-close" id="editFoodClose" aria-label="Close edit food window">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="../handlers/edit_food.php" enctype="multipart/form-data" class="edit-food-form"
                id="editFoodForm">

                <input type="hidden" name="id" id="editFoodId">

                <div class="edit-food-grid">

                    <div class="edit-food-group full">
                        <label class="edit-food-label" for="editFoodName">
                            Food Name
                        </label>

                        <input type="text" class="edit-food-input" id="editFoodName" name="name" maxlength="150"
                            required>
                    </div>

                    <div class="edit-food-group">
                        <label class="edit-food-label" for="editFoodCategory">
                            Category
                        </label>

                        <select class="edit-food-input" id="editFoodCategory" name="category_id" required>

                            <?php foreach ($categories as $category): ?>

                            <option value="<?= (int) $category['id'] ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="edit-food-group">
                        <label class="edit-food-label" for="editFoodPrice">
                            Price
                        </label>

                        <input type="number" class="edit-food-input" id="editFoodPrice" name="price" min="0" step="0.01"
                            required>
                    </div>

                    <div class="edit-food-group">
                        <label class="edit-food-label" for="editFoodStatus">
                            Status
                        </label>

                        <select class="edit-food-input" id="editFoodStatus" name="status" required>

                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>

                        </select>
                    </div>

                    <div class="edit-food-group">
                        <label class="edit-food-label" for="editFoodImage">
                            Replace Image
                        </label>

                        <input type="file" class="edit-food-input" id="editFoodImage" name="image"
                            accept="image/jpeg,image/png,image/webp">
                    </div>

                </div>

                <div class="edit-food-footer">

                    <button type="button" class="edit-food-cancel" id="editFoodCancel">
                        Cancel
                    </button>

                    <button type="submit" class="edit-food-save">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Changes
                    </button>

                </div>

            </form>
        </div>
    </div>


    <!-- =========================================================
         DELETE FOOD CONFIRMATION
    ========================================================== -->
    <div class="delete-food-modal" id="deleteFoodModal" aria-hidden="true">
        <div class="delete-food-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteFoodTitle">

            <div class="delete-food-content">

                <div class="delete-food-icon">
                    <i class="fa-solid fa-trash-can"></i>
                </div>

                <h3 id="deleteFoodTitle">Delete Food Item?</h3>

                <p>
                    You are about to permanently delete this food item.
                    This action cannot be undone.
                </p>

                <div class="delete-food-name" id="deleteFoodName">
                    Food item
                </div>

            </div>

            <div class="delete-food-actions">

                <button type="button" class="delete-food-cancel" id="deleteFoodCancel">
                    Keep Item
                </button>

                <button type="button" class="delete-food-confirm" id="deleteFoodConfirm">
                    <i class="fa-solid fa-trash"></i>
                    Delete
                </button>

            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>