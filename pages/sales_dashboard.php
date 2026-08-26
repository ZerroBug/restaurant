<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| SALES PERSON DASHBOARD
|--------------------------------------------------------------------------
| Salesperson has access ONLY to:
| - Sales dashboard
| - New order
| - Own orders
| - Own sales summary
| - Own password reset
|
| No administration functions are exposed here.
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !in_array($_SESSION['role'] ?? '', ['Salesperson', 'Sales Person'], true)
) {
    $_SESSION['login_message'] =
        'You do not have permission to access the Sales Person dashboard.';
    header('Location: ../index.php');
    exit;
}

require_once "../includes/db_connection.php";

$username = $_SESSION['username'] ?? 'Sales Person';
$fullName = $_SESSION['full_name'] ?? $username;
$role = $_SESSION['role'] ?? 'Salesperson';

$avatar = strtoupper(substr(trim($fullName), 0, 1));

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return 'GH₵ ' . number_format($amount, 2);
}

/* Use the same food-image path rules as the Administrator dashboard. */
function foodImagePath($image): string
{
    if (!$image) {
        return '../assets/uploads/default-food.jpg';
    }

    $image = str_replace('\\', '/', trim((string)$image));
    $image = ltrim($image, '/');

    if (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0) {
        return $image;
    }

    if (strpos($image, 'assets/') === 0) {
        return '../' . $image;
    }

    if (strpos($image, 'uploads/') === 0) {
        return '../assets/' . $image;
    }

    return '../assets/uploads/' . $image;
}

$userId = (int)(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

$todaySales = 0.00;
$todayOrders = 0;
$pendingOrders = 0;
$completedOrders = 0;
$monthSales = 0.00;
$monthOrders = 0;
$recentOrders = [];
$topItems = [];

try {

    /*
     * Today's personal sales.
     */
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'Completed'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS today_sales,

            COUNT(*) AS today_orders,

            SUM(
                CASE
                    WHEN status IN ('Pending','Preparing','Ready')
                    THEN 1
                    ELSE 0
                END
            ) AS pending_orders,

            SUM(
                CASE
                    WHEN status = 'Completed'
                    THEN 1
                    ELSE 0
                END
            ) AS completed_orders

        FROM orders

        WHERE user_id = :user_id
          AND DATE(created_at) = CURDATE()
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $todaySales = (float)($row['today_sales'] ?? 0);
    $todayOrders = (int)($row['today_orders'] ?? 0);
    $pendingOrders = (int)($row['pending_orders'] ?? 0);
    $completedOrders = (int)($row['completed_orders'] ?? 0);


    /*
     * Current month.
     */
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'Completed'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS month_sales,

            COUNT(*) AS month_orders

        FROM orders

        WHERE user_id = :user_id
          AND YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $monthSales = (float)($row['month_sales'] ?? 0);
    $monthOrders = (int)($row['month_orders'] ?? 0);


    /*
     * Recent orders belonging to this salesperson only.
     */
    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_number,
            o.order_type,
            o.status,
            o.payment_status,
            o.total,
            o.created_at,

            COALESCE(
                SUM(oi.quantity),
                0
            ) AS item_quantity

        FROM orders o

        LEFT JOIN order_items oi
            ON oi.order_id = o.id

        WHERE o.user_id = :user_id

        GROUP BY
            o.id,
            o.order_number,
            o.order_type,
            o.status,
            o.payment_status,
            o.total,
            o.created_at

        ORDER BY o.created_at DESC

        LIMIT 8
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
     * Most ordered foods by this salesperson.
     */
    $stmt = $pdo->prepare("
        SELECT
            f.id,
            f.name,
            f.image,

            SUM(oi.quantity) AS quantity_sold,

            SUM(oi.subtotal) AS sales_amount

        FROM order_items oi

        INNER JOIN orders o
            ON o.id = oi.order_id

        INNER JOIN food_menu f
            ON f.id = oi.food_id

        WHERE o.user_id = :user_id

        GROUP BY
            f.id,
            f.name,
            f.image

        ORDER BY
            quantity_sold DESC,
            sales_amount DESC

        LIMIT 5
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    /*
     * Keep dashboard usable if a non-critical query fails.
     */
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Sales Dashboard | Better End Food Point
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: 'Poppins', sans-serif;
    }

    .food-image {
        width: 58px;
        height: 58px;
        min-width: 58px;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        background: #f3f4f6;
    }

    .food-image img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
    }

    .food-fallback {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        color: #999;
        font-size: 20px;
    }
    </style>

</head>


<body>

    <div class="app">

        <?php include "../includes/sidebar.php"; ?>


        <main class="main">


            <!-- =====================================================
             TOP BAR
             ===================================================== -->

            <header class="topbar">

                <div class="page-title">

                    <h1>
                        Sales Dashboard
                    </h1>

                    <p>
                        Orders, sales and customer service at a glance
                    </p>

                </div>


                <div class="profile">

                    <button type="button" class="profile-button" id="profileButton">

                        <div class="avatar">
                            <?= e($avatar) ?>
                        </div>

                        <div class="profile-info">

                            <strong>
                                <?= e($fullName) ?>
                            </strong>

                            <small>
                                Sales Person
                            </small>

                        </div>

                        <i class="fa-solid fa-chevron-down" style="font-size:9px;color:#948a82"></i>

                    </button>


                    <div class="profile-menu" id="profileMenu">

                        <div class="profile-menu-head">

                            <strong>
                                <?= e($fullName) ?>
                            </strong>

                            <small>
                                @<?= e($username) ?>
                                · Sales Person
                            </small>

                        </div>


                        <a href="orders.php">

                            <i class="fa-solid fa-cart-plus"></i>

                            Create New Order

                        </a>


                        <a href="my_orders.php">

                            <i class="fa-solid fa-receipt"></i>

                            My Orders

                        </a>


                        <button type="button" id="openPassword">

                            <i class="fa-solid fa-key"></i>

                            Reset Password

                        </button>


                        <a href="../handlers/logout.php">

                            <i class="fa-solid fa-right-from-bracket"></i>

                            Sign Out

                        </a>

                    </div>

                </div>

            </header>


            <!-- =====================================================
             DASHBOARD CONTENT
             ===================================================== -->

            <section class="content">


                <div class="welcome">

                    <div>

                        <h2>
                            Welcome, <?= e($fullName) ?>.
                        </h2>

                        <p>
                            Your sales workspace is ready.
                            Take orders, monitor your sales and manage your own account.
                        </p>

                    </div>


                    <a href="orders.php" class="primary-button">

                        <i class="fa-solid fa-plus"></i>

                        CREATE NEW ORDER

                    </a>

                </div>


                <!-- =================================================
                 FOUR COLOURED CARDS
                 ================================================= -->

                <div class="metric-grid">


                    <div class="metric-card sales">

                        <div class="metric-inner">

                            <div>

                                <div class="metric-label">
                                    Today's Sales
                                </div>

                                <strong class="metric-value">
                                    <?= money($todaySales) ?>
                                </strong>

                                <div class="metric-description">
                                    Completed orders today
                                </div>

                            </div>

                            <div class="metric-icon">
                                <i class="fa-solid fa-coins"></i>
                            </div>

                        </div>

                    </div>


                    <div class="metric-card orders">

                        <div class="metric-inner">

                            <div>

                                <div class="metric-label">
                                    Today's Orders
                                </div>

                                <strong class="metric-value">
                                    <?= number_format($todayOrders) ?>
                                </strong>

                                <div class="metric-description">
                                    Orders you recorded today
                                </div>

                            </div>

                            <div class="metric-icon">
                                <i class="fa-solid fa-receipt"></i>
                            </div>

                        </div>

                    </div>


                    <div class="metric-card pending">

                        <div class="metric-inner">

                            <div>

                                <div class="metric-label">
                                    Pending Orders
                                </div>

                                <strong class="metric-value">
                                    <?= number_format($pendingOrders) ?>
                                </strong>

                                <div class="metric-description">
                                    Still being processed
                                </div>

                            </div>

                            <div class="metric-icon">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>

                        </div>

                    </div>


                    <div class="metric-card completed">

                        <div class="metric-inner">

                            <div>

                                <div class="metric-label">
                                    Completed Orders
                                </div>

                                <strong class="metric-value">
                                    <?= number_format($completedOrders) ?>
                                </strong>

                                <div class="metric-description">
                                    Successfully completed today
                                </div>

                            </div>

                            <div class="metric-icon">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                 QUICK ACTIONS
                 ================================================= -->

                <div class="quick-grid">


                    <section class="panel panel-padding">

                        <div class="panel-heading">

                            <div>

                                <h3>
                                    Quick Actions
                                </h3>

                                <p>
                                    Tools available to your Sales Person account.
                                </p>

                            </div>

                        </div>


                        <div class="quick-actions">


                            <a href="orders.php" class="quick-action">

                                <div class="quick-action-icon">
                                    <i class="fa-solid fa-cart-plus"></i>
                                </div>

                                <strong>
                                    New Order
                                </strong>

                                <span>
                                    Take a customer order and process payment.
                                </span>

                            </a>


                            <a href="my_orders.php" class="quick-action">

                                <div class="quick-action-icon">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>

                                <strong>
                                    My Orders
                                </strong>

                                <span>
                                    View orders placed from your account.
                                </span>

                            </a>


                            <button type="button" class="quick-action" id="quickPassword">

                                <div class="quick-action-icon">
                                    <i class="fa-solid fa-key"></i>
                                </div>

                                <strong>
                                    Reset Password
                                </strong>

                                <span>
                                    Change your account password securely.
                                </span>

                            </button>


                        </div>

                    </section>


                    <section class="panel panel-padding">

                        <div class="panel-heading">

                            <div>

                                <h3>
                                    This Month
                                </h3>

                                <p>
                                    Your personal sales activity
                                </p>

                            </div>

                        </div>


                        <div class="month-summary">

                            <div>

                                <small>
                                    Completed Sales
                                </small>

                                <strong>
                                    <?= money($monthSales) ?>
                                </strong>

                                <p>
                                    <?= number_format($monthOrders) ?>
                                    orders recorded this month
                                </p>

                            </div>


                            <div class="month-icon">

                                <i class="fa-solid fa-chart-line"></i>

                            </div>

                        </div>

                    </section>

                </div>


                <!-- =================================================
                 RECENT ORDERS + POPULAR FOOD
                 ================================================= -->

                <div class="lower-grid">


                    <section class="panel">

                        <div class="table-heading">

                            <div>

                                <h3>
                                    My Recent Orders
                                </h3>

                                <p>
                                    Latest orders placed using your account
                                </p>

                            </div>

                            <a href="my_orders.php">

                                VIEW ALL

                                <i class="fa-solid fa-arrow-right"></i>

                            </a>

                        </div>


                        <?php if ($recentOrders): ?>

                        <div class="table-wrap">

                            <table>

                                <thead>

                                    <tr>

                                        <th>
                                            Order
                                        </th>

                                        <th>
                                            Type
                                        </th>

                                        <th>
                                            Items
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Payment
                                        </th>

                                        <th>
                                            Total
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($recentOrders as $order): ?>

                                    <?php

                                    $statusClass =
                                        strtolower(
                                            (string)$order['status']
                                        );

                                    $paymentClass =
                                        strtolower(
                                            (string)$order['payment_status']
                                        );

                                    ?>

                                    <tr>

                                        <td>

                                            <span class="order-number">
                                                <?= e($order['order_number']) ?>
                                            </span>

                                            <span class="order-date">
                                                <?= date(
                                                    'd M Y, h:i A',
                                                    strtotime(
                                                        $order['created_at']
                                                    )
                                                ) ?>
                                            </span>

                                        </td>


                                        <td>
                                            <?= e($order['order_type']) ?>
                                        </td>


                                        <td>
                                            <?= number_format(
                                                (int)$order['item_quantity']
                                            ) ?>
                                        </td>


                                        <td>

                                            <span class="status <?= e($statusClass) ?>">
                                                <?= e($order['status']) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <span class="status <?= e($paymentClass) ?>">
                                                <?= e($order['payment_status']) ?>
                                            </span>

                                        </td>


                                        <td class="amount">

                                            <?= money(
                                                (float)$order['total']
                                            ) ?>

                                        </td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <?php else: ?>

                        <div class="empty">

                            <i class="fa-solid fa-receipt"></i>

                            No orders have been placed from your account yet.

                        </div>

                        <?php endif; ?>

                    </section>


                    <section class="panel">

                        <div class="table-heading">

                            <div>

                                <h3>
                                    Popular Items
                                </h3>

                                <p>
                                    Foods you have ordered most frequently
                                </p>

                            </div>

                        </div>


                        <?php if ($topItems): ?>

                        <div class="food-list">

                            <?php foreach ($topItems as $item): ?>

                            <div class="food-row">
                                <div class="food-image">

                                    <?php $imageUrl = foodImagePath($item['image'] ?? ''); ?>

                                    <?php if ($imageUrl !== ''): ?>

                                    <img src="<?= e($imageUrl) ?>" alt="<?= e($item['name']) ?>" loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">

                                    <div class="food-fallback" style="display:none;">
                                        <i class="fa-solid fa-utensils"></i>
                                    </div>

                                    <?php else: ?>

                                    <div class="food-fallback">
                                        <i class="fa-solid fa-utensils"></i>
                                    </div>

                                    <?php endif; ?>

                                </div>


                                <div class="food-info">

                                    <strong>
                                        <?= e($item['name']) ?>
                                    </strong>

                                    <small>
                                        <?= number_format(
                                                (int)$item['quantity_sold']
                                            ) ?>
                                        units ordered
                                    </small>

                                </div>


                                <div class="food-total">

                                    <strong>
                                        <?= money(
                                                (float)$item['sales_amount']
                                            ) ?>
                                    </strong>

                                    <small>
                                        sales
                                    </small>

                                </div>

                            </div>

                            <?php endforeach; ?>

                        </div>

                        <?php else: ?>

                        <div class="empty">

                            <i class="fa-solid fa-bowl-food"></i>

                            Your popular items will appear here after you place orders.

                        </div>

                        <?php endif; ?>

                    </section>


                </div>


                <!-- =================================================
                 PERMISSION SUMMARY
                 ================================================= -->

                <section class="permission-panel">

                    <div class="panel-heading">

                        <div>

                            <h3>
                                Your Account Access
                            </h3>

                            <p>
                                Your account is limited to sales operations.
                            </p>

                        </div>

                    </div>


                    <div class="permission-badges">

                        <span class="permission-badge">
                            <i class="fa-solid fa-check"></i>
                            Place Orders
                        </span>

                        <span class="permission-badge">
                            <i class="fa-solid fa-check"></i>
                            View My Orders
                        </span>

                        <span class="permission-badge">
                            <i class="fa-solid fa-check"></i>
                            View My Sales
                        </span>

                        <span class="permission-badge">
                            <i class="fa-solid fa-key"></i>
                            Reset Own Password
                        </span>

                        <span class="permission-badge locked">
                            <i class="fa-solid fa-lock"></i>
                            Administration Restricted
                        </span>

                    </div>

                </section>


            </section>

        </main>

    </div>


    <!-- =========================================================
     PASSWORD MODAL
     ========================================================= -->

    <div class="password-modal" id="passwordModal" aria-hidden="true">

        <div class="password-box">

            <h3>
                Reset Your Password
            </h3>

            <p>
                Enter your current password and choose a new password.
                This only changes your own Sales Person account.
            </p>


            <div class="password-message" id="passwordMessage"></div>


            <form id="passwordForm">

                <div class="field">

                    <label>
                        Current Password
                    </label>

                    <input type="password" name="current_password" autocomplete="current-password" required>

                </div>


                <div class="field">

                    <label>
                        New Password
                    </label>

                    <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>

                </div>


                <div class="field">

                    <label>
                        Confirm New Password
                    </label>

                    <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>

                </div>


                <div class="modal-actions">

                    <button type="button" class="cancel-button" id="cancelPassword">
                        Cancel
                    </button>

                    <button type="submit" class="save-button" id="savePassword">
                        <i class="fa-solid fa-lock"></i>
                        Update Password
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script>
    /* =========================================================
   PROFILE MENU
   ========================================================= */

    const profileButton =
        document.getElementById('profileButton');

    const profileMenu =
        document.getElementById('profileMenu');

    profileButton?.addEventListener(
        'click',
        function(event) {

            event.stopPropagation();

            profileMenu.classList.toggle('show');

        }
    );

    document.addEventListener(
        'click',
        function(event) {

            if (!event.target.closest('.profile')) {

                profileMenu?.classList.remove('show');

            }

        }
    );


    /* =========================================================
       PASSWORD MODAL
       ========================================================= */

    const passwordModal =
        document.getElementById('passwordModal');

    const passwordForm =
        document.getElementById('passwordForm');

    const passwordMessage =
        document.getElementById('passwordMessage');

    const savePassword =
        document.getElementById('savePassword');


    function openPasswordModal(event) {

        if (event) {
            event.preventDefault();
        }

        profileMenu?.classList.remove('show');

        passwordForm.reset();

        passwordMessage.className =
            'password-message';

        passwordMessage.textContent = '';

        passwordModal.classList.add('show');

        passwordModal.setAttribute(
            'aria-hidden',
            'false'
        );
    }


    function closePasswordModal() {

        passwordModal.classList.remove('show');

        passwordModal.setAttribute(
            'aria-hidden',
            'true'
        );
    }


    document
        .getElementById('openPassword')
        ?.addEventListener(
            'click',
            openPasswordModal
        );


    document
        .getElementById('openPasswordFromNav')
        ?.addEventListener(
            'click',
            openPasswordModal
        );


    document
        .getElementById('quickPassword')
        ?.addEventListener(
            'click',
            openPasswordModal
        );


    document
        .getElementById('cancelPassword')
        ?.addEventListener(
            'click',
            closePasswordModal
        );


    passwordModal?.addEventListener(
        'click',
        function(event) {

            if (event.target === passwordModal) {

                closePasswordModal();

            }

        }
    );


    /* =========================================================
       PASSWORD SUBMISSION
       ========================================================= */

    passwordForm?.addEventListener(
        'submit',
        async function(event) {

            event.preventDefault();

            const data =
                Object.fromEntries(
                    new FormData(passwordForm).entries()
                );


            if (
                data.new_password !==
                data.confirm_password
            ) {

                passwordMessage.className =
                    'password-message error show';

                passwordMessage.textContent =
                    'The new passwords do not match.';

                return;
            }


            savePassword.disabled = true;

            savePassword.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';


            try {

                const response =
                    await fetch(
                        '../handlers/sales_reset_password.php', {
                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json',

                                'Accept': 'application/json'
                            },

                            body: JSON.stringify(data)
                        }
                    );


                const text =
                    await response.text();


                let result;

                try {

                    result =
                        JSON.parse(text);

                } catch {

                    throw new Error(
                        'The server returned an invalid response.'
                    );

                }


                if (
                    !response.ok ||
                    !result.success
                ) {

                    throw new Error(
                        result.message ||
                        'Unable to update password.'
                    );

                }


                passwordMessage.className =
                    'password-message success show';

                passwordMessage.textContent =
                    'Your password has been updated successfully.';


                passwordForm.reset();


                setTimeout(
                    closePasswordModal,
                    1300
                );


            } catch (error) {

                passwordMessage.className =
                    'password-message error show';

                passwordMessage.textContent =
                    error.message;


            } finally {

                savePassword.disabled = false;

                savePassword.innerHTML =
                    '<i class="fa-solid fa-lock"></i> Update Password';

            }

        }
    );
    </script>

</body>

</html>