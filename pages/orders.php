<?php
session_start();

require_once "../includes/db_connection.php";
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Administrator', 'Salesperson'], true)
) {
    $_SESSION['login_message'] =
        'You do not have permission to access the Orders page.';
    header('Location: ../index.php');
    exit;
}

$username  = $_SESSION['username'] ?? 'User';
$full_name = $_SESSION['full_name'] ?? $username;
$role      = $_SESSION['role'] ?? 'User';
$avatar    = strtoupper(substr(trim($full_name), 0, 1));


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['login_message'] = 'Please log in to access the Orders page.';
    header('Location: ../index.php');
    exit;
}

$full_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

$foods = [];
$pageError = '';

try {
    $stmt = $pdo->query("
        SELECT id, name, price, image, status
        FROM food_menu
        WHERE status = 'Available'
        ORDER BY name ASC
    ");
    $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pageError = 'Unable to load the food menu.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order | Better End Food Point</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
    :root {
        --orange: #f58220;
        --orange-dark: #dc680d;
        --orange-light: #fff1e6;
        --bg: #f7f6f4;
        --text: #292522;
        --muted: #958c84;
        --border: #e9e3dd;
        --green: #1fa463;
        --red: #d94b4b;
    }

    * {
        box-sizing: border-box
    }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: Poppins, sans-serif
    }



    @media (max-width: 992px) {
        .main {
            margin-left: 0;
        }
    }

    .content {
        padding: 30px 32px 40px;
        max-width: 1680px;
        margin: 0 auto;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        margin-bottom: 26px;
        padding-bottom: 4px;
    }

    .page-header .title {
        display: flex;
        align-items: center;
        gap: 13px
    }

    .title-icon {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--orange), var(--orange-dark));
        color: #fff;
        font-size: 20px;
        box-shadow: 0 10px 24px rgba(245, 130, 32, .22);
    }

    h1 {
        font-size: 28px;
        line-height: 1.15;
        font-weight: 800;
        margin: 0;
        letter-spacing: -.5px;
    }

    .subtitle {
        font-size: 12px;
        color: var(--muted);
        margin: 6px 0 0;
    }

    .date {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 700;
        color: #716961;
        box-shadow: 0 6px 18px rgba(40, 30, 22, .035);
        white-space: nowrap;
    }

    .layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 430px;
        gap: 24px;
        align-items: start;
    }

    .panel {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 12px 34px rgba(40, 30, 22, .055);
    }

    .panel-head {
        padding: 22px 24px;
        border-bottom: 1px solid #eeeae6;
        background: #fff;
    }

    .panel-head-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px
    }

    .panel-head h2 {
        font-size: 19px;
        font-weight: 800;
        margin: 0;
        letter-spacing: -.2px;
    }

    .badge-count {
        padding: 8px 12px;
        border-radius: 999px;
        color: var(--orange-dark);
        background: var(--orange-light);
        font-size: 10px;
        font-weight: 800;
    }

    .menu-search {
        height: 48px;
        border: 1px solid #e3ded9;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 0 15px;
        background: #fcfbfa;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .menu-search:focus-within {
        border-color: #efa267;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(245, 130, 32, .08);
    }

    .menu-search i {
        color: #aaa19a;
        flex: 0 0 auto;
    }

    .menu-search input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        font-size: 12px;
        color: var(--text);
    }

    .food-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(205px, 1fr));
        gap: 14px;
        padding: 18px;
        max-height: calc(100vh - 275px);
        overflow-y: auto;
    }

    .food-card {
        position: relative;
        overflow: hidden;
        border: 1px solid #ebe5df;
        border-radius: 15px;
        background: #fff;
        cursor: pointer;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        box-shadow: 0 4px 14px rgba(40, 30, 22, .035);
    }

    .food-card:hover {
        transform: translateY(-3px);
        border-color: #f0c09a;
        box-shadow: 0 12px 25px rgba(40, 30, 22, .09)
    }

    .food-image {
        height: 125px;
        background: var(--orange-light);
        overflow: hidden;
        position: relative;
    }

    .food-image img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        object-position: center;
        transition: transform .35s ease;
    }

    .food-card:hover img {
        transform: scale(1.06)
    }

    .placeholder {
        height: 100%;
        display: grid;
        place-items: center;
        color: var(--orange);
        font-size: 42px;
    }

    .food-info {
        padding: 10px 12px 12px;
        min-height: 72px;
    }

    .food-name {
        display: -webkit-box;
        padding-right: 40px;
        overflow: hidden;
        text-overflow: ellipsis;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        font-size: 12px;
        line-height: 1.35;
        font-weight: 800;
        min-height: 32px;
    }

    .price {
        margin-top: 4px;
        color: var(--orange-dark);
        font-size: 12px;
        font-weight: 800;
    }

    .add {
        position: absolute;
        right: 9px;
        bottom: 9px;
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 9px;
        background: linear-gradient(135deg, var(--orange), var(--orange-dark));
        color: #fff;
        display: grid;
        place-items: center;
        font-size: 11px;
        box-shadow: 0 6px 13px rgba(245, 130, 32, .22);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .add:hover {
        transform: translateY(-2px) scale(1.04);
        box-shadow: 0 11px 24px rgba(245, 130, 32, .30);
    }

    .cart {
        position: sticky;
        top: 24px;
    }

    .cart-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 22px;
        border-bottom: 1px solid #eeeae6;
    }

    .cart-title {
        display: flex;
        align-items: center;
        gap: 10px
    }

    .cart-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: #181818;
        color: #fff;
        display: grid;
        place-items: center
    }

    .cart-title strong {
        display: block;
        font-size: 16px;
    }

    .cart-title small {
        font-size: 10px;
        color: var(--muted);
    }

    .cart-count {
        min-width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        padding: 0 8px;
        border-radius: 999px;
        background: var(--orange);
        color: #fff;
        font-size: 10px;
        font-weight: 800;
    }

    .cart-items {
        max-height: 370px;
        overflow-y: auto
    }

    .empty {
        min-height: 220px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 30px
    }

    .empty-icon {
        width: 65px;
        height: 65px;
        border-radius: 20px;
        background: #f6f3f0;
        color: #aaa19a;
        display: grid;
        place-items: center;
        font-size: 23px;
        margin-bottom: 13px
    }

    .empty strong {
        font-size: 12px
    }

    .empty p {
        max-width: 230px;
        color: #aaa19a;
        font-size: 9px;
        line-height: 1.6
    }

    .item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        border-bottom: 1px solid #f1ede9;
    }

    .item-img {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        border-radius: 12px;
        overflow: hidden;
        background: var(--orange-light);
        display: grid;
        place-items: center;
        color: var(--orange);
    }

    .item-img img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .item-info {
        flex: 1;
        min-width: 0
    }

    .item-info strong {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 11px;
        font-weight: 800;
    }

    .item-info small {
        font-size: 9px;
        color: var(--muted);
    }

    .qty {
        display: flex;
        align-items: center;
        gap: 4px
    }

    .qty button,
    .remove {
        width: 25px;
        height: 25px;
        border: 1px solid #e4ddd7;
        border-radius: 7px;
        background: #fff;
        color: #645c55
    }

    .qty span {
        min-width: 20px;
        text-align: center;
        font-size: 10px;
        font-weight: 800
    }

    .item-total {
        min-width: 65px;
        text-align: right;
        font-size: 10px;
        font-weight: 800
    }

    .remove {
        border: 0;
        color: #aaa19a
    }

    .remove:hover {
        color: var(--red)
    }

    .details {
        padding: 20px 20px;
        border-top: 1px solid #eeeae6;
    }

    .label {
        display: block;
        margin-bottom: 8px;
        font-size: 10px;
        font-weight: 800;
    }

    .select {
        width: 100%;
        height: 45px;
        border: 1px solid #e3ddd8;
        border-radius: 10px;
        padding: 0 12px;
        background: #fff;
        font-size: 11px;
        font-weight: 600;
        color: var(--text);
        outline: none;
    }

    .select:focus {
        border-color: #efa267;
        box-shadow: 0 0 0 4px rgba(245, 130, 32, .08);
    }

    .summary {
        padding: 19px 20px 20px;
        background: #faf8f6;
        border-top: 1px solid #eeeae6;
    }

    .rowline {
        display: flex;
        justify-content: space-between;
        margin-bottom: 9px;
        color: #837a72;
        font-size: 10px;
    }

    .rowline strong {
        color: #433c36
    }

    .total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px dashed #ddd5ce;
        padding-top: 11px;
        margin-top: 10px
    }

    .total span {
        font-size: 14px;
        font-weight: 800;
    }

    .total strong {
        color: var(--orange-dark);
        font-size: 22px;
    }

    .place {
        width: 100%;
        height: 52px;
        margin-top: 16px;
        border: 0;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--orange), var(--orange-dark));
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        box-shadow: 0 10px 24px rgba(245, 130, 32, .20);
        transition: .18s ease;
    }

    .place:not(:disabled):hover {
        transform: translateY(-1px);
        box-shadow: 0 13px 28px rgba(245, 130, 32, .27);
    }

    .place:disabled {
        opacity: .45
    }

    .clear {
        width: 100%;
        height: 42px;
        margin-top: 9px;
        border: 1px solid #e1dbd5;
        border-radius: 10px;
        background: #fff;
        color: #756c64;
        font-size: 10px;
        font-weight: 700;
        transition: .18s ease;
    }

    .clear:hover {
        background: #f7f4f1;
        border-color: #d8d0c9;
    }

    .alert-box {
        position: fixed;
        top: 25px;
        right: 25px;
        z-index: 9999;
        width: 370px;
        display: none;
        padding: 15px 18px;
        border-radius: 14px;
        background: #ecfff5;
        border: 1px solid #bcebd2;
        color: #1b5e3a;
        box-shadow: 0 15px 40px rgba(0, 0, 0, .12)
    }

    .alert-box.show {
        display: block
    }

    .alert-box.error {
        background: #fff2f2;
        border-color: #f0c5c5;
        color: #8a2727
    }

    .alert-box strong {
        display: block;
        font-size: 12px
    }

    .alert-box span {
        display: block;
        margin-top: 3px;
        font-size: 10px
    }

    @media(max-width:1100px) {
        .main {
            margin-left: 0;
        }

        .layout {
            grid-template-columns: 1fr;
        }

        .cart {
            position: static;
        }

        .food-grid {
            max-height: none;
        }

        .food-image {
            height: 115px;
        }
    }

    @media(max-width:700px) {
        .content {
            padding: 20px 12px 30px;
        }

        .page-header {
            align-items: flex-start;
            flex-direction: column;
        }

        h1 {
            font-size: 24px;
        }

        .date {
            width: 100%;
            text-align: center;
        }

        .food-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 11px;
            padding: 14px;
        }

        .food-image {
            height: 105px;
        }

        .food-info {
            padding: 9px 10px 10px;
        }

        .food-name {
            font-size: 10px;
            line-height: 1.3;
            min-height: 26px;
            -webkit-line-clamp: 2;
        }

        .price {
            font-size: 11px;
        }

        .add {
            width: 29px;
            height: 29px;
            right: 8px;
            bottom: 8px;
        }
    }

    /* =========================================================
       PROFESSIONAL ORDER CONFIRMATION MODAL
       ========================================================= */

    .confirm-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(25, 20, 16, .52);
        backdrop-filter: blur(5px);
    }

    .confirm-overlay.show {
        display: flex;
        animation: confirmFadeIn .18s ease;
    }

    .confirm-modal {
        width: min(430px, 100%);
        background: #fff;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .22);
        transform: translateY(8px) scale(.98);
        animation: confirmModalIn .2s ease forwards;
    }

    .confirm-top {
        padding: 25px 24px 20px;
        text-align: center;
    }

    .confirm-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 14px;
        display: grid;
        place-items: center;
        border-radius: 20px;
        background: var(--orange-light);
        color: var(--orange);
        font-size: 25px;
    }

    .confirm-modal h3 {
        margin: 0;
        font-size: 19px;
        font-weight: 800;
        color: var(--text);
    }

    .confirm-modal p {
        margin: 7px 0 0;
        color: var(--muted);
        font-size: 10px;
        line-height: 1.6;
    }

    .confirm-details {
        margin: 0 24px;
        padding: 14px 15px;
        border: 1px solid var(--border);
        border-radius: 13px;
        background: #faf8f6;
    }

    .confirm-detail {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 5px 0;
        font-size: 10px;
    }

    .confirm-detail span {
        color: var(--muted);
    }

    .confirm-detail strong {
        color: var(--text);
        font-size: 10px;
        text-align: right;
    }

    .confirm-detail.total {
        margin-top: 7px;
        padding-top: 10px;
        border-top: 1px dashed #ddd5ce;
    }

    .confirm-detail.total strong {
        color: var(--orange-dark);
        font-size: 17px;
    }

    .confirm-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        padding: 20px 24px 24px;
    }

    .confirm-btn {
        height: 45px;
        border-radius: 10px;
        border: 0;
        font-family: inherit;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
    }

    .confirm-cancel {
        border: 1px solid var(--border);
        background: #fff;
        color: #756c64;
    }

    .confirm-cancel:hover {
        background: #f7f4f1;
    }

    .confirm-place {
        background: linear-gradient(135deg, var(--orange), var(--orange-dark));
        color: #fff;
        box-shadow: 0 7px 18px rgba(245, 130, 32, .22);
    }

    .confirm-place:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(245, 130, 32, .28);
    }

    .confirm-place:disabled {
        opacity: .55;
        cursor: not-allowed;
        transform: none;
    }

    @keyframes confirmFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes confirmModalIn {
        to {
            transform: translateY(0) scale(1);
        }
    }

    @media (max-width: 480px) {
        .confirm-modal {
            border-radius: 18px;
        }

        .confirm-actions {
            padding: 17px;
        }

        .confirm-details {
            margin: 0 17px;
        }
    }

    /* =========================================================
       ORDER PAGE — SHARED DASHBOARD-STYLE OVERRIDES
       Keeps the sidebar and topbar in their own layout columns.
       Typography is intentionally compact to match Sales Dashboard.
       ========================================================= */

    .main {
        width: calc(100% - 250px);
        margin-left: 250px;
        min-height: 100vh;
        position: relative;
        background: var(--bg);
    }

    /* The included topbar must belong to the main content area,
       never sit underneath/behind the fixed sidebar. */
    .main>.topbar,
    .main>header.topbar,
    .main>.navbar {
        width: 100% !important;
        margin: 0 !important;
        left: auto !important;
        right: auto !important;
        top: 0 !important;
        position: sticky !important;
        z-index: 5000 !important;
        box-sizing: border-box;
    }

    .main>.topbar {
        min-height: 76px;
        height: 76px;
        padding: 0 28px !important;
        background: #fff !important;
        border-bottom: 1px solid #ebe5df !important;
        box-shadow: 0 3px 14px rgba(39, 33, 29, .035);
    }

    /* Compact typography to match the Sales Dashboard. */
    .content {
        padding: 24px 28px 36px !important;
        max-width: 1550px !important;
    }

    .page-header {
        gap: 18px !important;
        margin-bottom: 20px !important;
    }

    .page-header .title {
        gap: 11px !important;
    }

    .title-icon {
        width: 46px !important;
        height: 46px !important;
        flex-basis: 46px !important;
        border-radius: 13px !important;
        font-size: 17px !important;
    }

    h1 {
        font-size: 21px !important;
        letter-spacing: -.35px !important;
    }

    .subtitle {
        font-size: 9px !important;
        margin-top: 4px !important;
    }

    .date {
        padding: 10px 13px !important;
        border-radius: 10px !important;
        font-size: 9px !important;
    }

    .layout {
        grid-template-columns: minmax(0, 1fr) 390px !important;
        gap: 18px !important;
    }

    .panel {
        border-radius: 17px !important;
        box-shadow: 0 10px 28px rgba(40, 30, 22, .055) !important;
    }

    .panel-head {
        padding: 18px 20px !important;
    }

    .panel-head-row {
        margin-bottom: 12px !important;
    }

    .panel-head h2 {
        font-size: 13px !important;
    }

    .badge-count {
        padding: 6px 9px !important;
        font-size: 8px !important;
    }

    .menu-search {
        height: 42px !important;
        border-radius: 10px !important;
        padding: 0 12px !important;
    }

    .menu-search input {
        font-size: 9px !important;
    }

    .food-grid {
        grid-template-columns: repeat(auto-fill, minmax(175px, 1fr)) !important;
        gap: 12px !important;
        padding: 15px !important;
    }

    .food-card {
        border-radius: 13px !important;
    }

    .food-image {
        height: 112px !important;
    }

    .food-info {
        padding: 9px 10px 10px !important;
        min-height: 65px !important;
    }

    .food-name,
    .price {
        font-size: 9px !important;
    }

    .food-name {
        min-height: 27px !important;
    }

    .add {
        width: 27px !important;
        height: 27px !important;
        right: 8px !important;
        bottom: 8px !important;
        font-size: 9px !important;
    }

    .cart {
        top: 18px !important;
    }

    .cart-head {
        padding: 16px 18px !important;
    }

    .cart-icon {
        width: 36px !important;
        height: 36px !important;
        border-radius: 10px !important;
        font-size: 12px !important;
    }

    .cart-title {
        gap: 8px !important;
    }

    .cart-title strong {
        font-size: 12px !important;
    }

    .cart-title small {
        font-size: 8px !important;
    }

    .cart-count {
        min-width: 27px !important;
        height: 27px !important;
        font-size: 8px !important;
    }

    .item {
        gap: 8px !important;
        padding: 11px 13px !important;
    }

    .item-img {
        width: 45px !important;
        height: 45px !important;
        flex-basis: 45px !important;
        border-radius: 10px !important;
    }

    .item-info strong {
        font-size: 9px !important;
    }

    .item-info small,
    .qty span,
    .item-total {
        font-size: 8px !important;
    }

    .qty button,
    .remove {
        width: 23px !important;
        height: 23px !important;
        font-size: 8px !important;
    }

    .details {
        padding: 16px !important;
    }

    .label {
        margin-bottom: 6px !important;
        font-size: 8px !important;
    }

    .select {
        height: 40px !important;
        border-radius: 9px !important;
        font-size: 9px !important;
    }

    .summary {
        padding: 15px 16px 16px !important;
    }

    .rowline {
        margin-bottom: 7px !important;
        font-size: 8px !important;
    }

    .total {
        padding-top: 9px !important;
        margin-top: 8px !important;
    }

    .total span {
        font-size: 11px !important;
    }

    .total strong {
        font-size: 17px !important;
    }

    .place {
        height: 45px !important;
        margin-top: 12px !important;
        border-radius: 10px !important;
        font-size: 9px !important;
    }

    .clear {
        height: 37px !important;
        margin-top: 7px !important;
        border-radius: 9px !important;
        font-size: 8px !important;
    }

    .confirm-modal {
        width: min(400px, 100%) !important;
        border-radius: 18px !important;
    }

    .confirm-top {
        padding: 21px 20px 17px !important;
    }

    .confirm-icon {
        width: 56px !important;
        height: 56px !important;
        border-radius: 17px !important;
        font-size: 21px !important;
    }

    .confirm-modal h3 {
        font-size: 15px !important;
    }

    .confirm-modal p,
    .confirm-detail,
    .confirm-detail strong {
        font-size: 8px !important;
    }

    .confirm-details {
        margin: 0 20px !important;
        padding: 12px 13px !important;
    }

    .confirm-detail.total strong {
        font-size: 14px !important;
    }

    .confirm-actions {
        padding: 17px 20px 20px !important;
    }

    .confirm-btn {
        height: 40px !important;
        font-size: 8px !important;
    }

    @media (max-width: 1100px) {
        .main {
            width: calc(100% - 250px);
            margin-left: 250px;
        }

        .layout {
            grid-template-columns: 1fr !important;
        }

        .cart {
            position: static !important;
        }
    }

    @media (max-width: 800px) {
        .main {
            width: calc(100% - 72px);
            margin-left: 72px;
        }

        .main>.topbar,
        .main>header.topbar,
        .main>.navbar {
            min-height: 68px;
            height: 68px;
            padding: 0 16px !important;
        }

        .content {
            padding: 20px !important;
        }
    }

    @media (max-width: 580px) {
        .main {
            width: calc(100% - 72px);
            margin-left: 72px;
        }

        .content {
            padding: 15px !important;
        }

        .food-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            padding: 12px !important;
        }

        .food-image {
            height: 100px !important;
        }
    }


    /* =========================================================
       DASHBOARD HEADER / TOP BAR — copied from working admin dashboard
       ========================================================= */
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

    /* ============================================
    =============
       ORDER PAGE — ENTERPRISE UI POLISH + TOPBAR LAYER FIX
       ========================================================= */

    html {
        background: #f7f6f4;
    }

    body {
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }

    /* Keep the dashboard topbar above every order-page surface.
       The dropdown is intentionally allowed to escape the topbar box. */
    .main>.topbar,
    .main>header.topbar,
    .main>.navbar {
        position: sticky !important;
        top: 0 !important;
        z-index: 20000 !important;
        overflow: visible !important;
        isolation: isolate;
    }

    .main>.topbar *,
    .main>header.topbar *,
    .main>.navbar * {
        position: relative;
    }

    .main>.topbar .profile-dropdown-wrap,
    .main>header.topbar .profile-dropdown-wrap,
    .main>.navbar .profile-dropdown-wrap {
        z-index: 20020 !important;
    }

    .main>.topbar .profile-dropdown,
    .main>header.topbar .profile-dropdown,
    .main>.navbar .profile-dropdown {
        position: absolute !important;
        z-index: 20050 !important;
        overflow: visible !important;
    }

    /* Never let the page title/content create a competing stacking layer. */
    .content {
        position: relative;
        z-index: 1;
    }

    .page-header {
        min-height: 64px;
        align-items: center;
        margin-bottom: 22px !important;
        padding: 2px 2px 0;
    }

    .page-header .title {
        min-width: 0;
    }

    .page-header .title-icon {
        box-shadow: 0 8px 20px rgba(245, 130, 32, .18);
    }

    .page-header h1 {
        font-size: 23px !important;
        letter-spacing: -.45px !important;
    }

    .page-header .subtitle {
        max-width: 620px;
        line-height: 1.5;
        font-size: 10px !important;
    }

    .date {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        box-shadow: 0 4px 14px rgba(40, 30, 22, .045);
    }

    /* Main workspace */
    .layout {
        grid-template-columns: minmax(0, 1fr) 405px !important;
        gap: 20px !important;
        align-items: start;
    }

    .panel {
        border: 1px solid #e7e0d9;
        border-radius: 16px !important;
        box-shadow: 0 8px 30px rgba(40, 30, 22, .045) !important;
    }

    .panel-head {
        padding: 18px 20px !important;
        background: linear-gradient(180deg, #fff, #fdfcfb);
    }

    .panel-head-row {
        margin-bottom: 13px !important;
    }

    .panel-head h2 {
        font-size: 14px !important;
        letter-spacing: -.15px;
    }

    .badge-count {
        border: 1px solid #f5d9c2;
        box-shadow: 0 3px 10px rgba(245, 130, 32, .06);
    }

    .menu-search {
        height: 43px !important;
        border-radius: 9px !important;
        background: #faf9f7;
    }

    .food-grid {
        grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)) !important;
        gap: 14px !important;
        padding: 16px !important;
        background: #fcfbfa;
    }

    .food-card {
        border-color: #e9e2db;
        border-radius: 14px !important;
        box-shadow: 0 3px 12px rgba(40, 30, 22, .035);
    }

    .food-card:hover {
        transform: translateY(-2px);
        border-color: #edc19f;
        box-shadow: 0 12px 28px rgba(40, 30, 22, .09);
    }

    .food-image {
        height: 118px !important;
    }

    .food-info {
        padding: 11px 12px 12px !important;
    }

    .food-name {
        font-size: 10px !important;
    }

    .price {
        font-size: 10px !important;
        margin-top: 5px;
    }

    .add {
        width: 30px !important;
        height: 30px !important;
        border-radius: 9px !important;
    }

    /* Order summary: visually dominant, but still restrained. */
    .cart {
        top: 94px !important;
        border-color: #e2d9d1;
        box-shadow: 0 12px 36px rgba(40, 30, 22, .075) !important;
    }

    .cart-head {
        min-height: 69px;
        padding: 16px 18px !important;
        background: linear-gradient(180deg, #fff, #fdfcfb);
    }

    .cart-icon {
        background: #1d1b19;
        box-shadow: 0 6px 14px rgba(29, 27, 25, .12);
    }

    .cart-title strong {
        font-size: 13px !important;
    }

    .cart-count {
        box-shadow: 0 4px 12px rgba(245, 130, 32, .18);
    }

    .cart-items {
        max-height: min(360px, 40vh);
    }

    .item {
        padding: 12px 14px !important;
        transition: background .15s ease;
    }

    .item:hover {
        background: #fffcf9;
    }

    .item-img {
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .025);
    }

    .qty button,
    .remove {
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
    }

    .qty button:hover {
        background: var(--orange-light);
        border-color: #efc39f;
        color: var(--orange-dark);
        transform: translateY(-1px);
    }

    .remove:hover {
        background: #fff2f2;
    }

    .details {
        padding: 17px 18px !important;
        background: #fff;
    }

    .select {
        background-color: #fff;
        cursor: pointer;
    }

    .summary {
        padding: 17px 18px 18px !important;
        background: #faf8f6;
    }

    .total strong {
        font-size: 18px !important;
    }

    .place {
        height: 48px !important;
        border-radius: 10px !important;
        font-size: 10px !important;
        letter-spacing: .05px;
    }

    .clear {
        height: 39px !important;
        border-radius: 9px !important;
    }

    /* Cleaner scrollbar treatment for long enterprise menu/order lists. */
    .food-grid,
    .cart-items {
        scrollbar-width: thin;
        scrollbar-color: #d9d0c8 transparent;
    }

    .food-grid::-webkit-scrollbar,
    .cart-items::-webkit-scrollbar {
        width: 7px;
    }

    .food-grid::-webkit-scrollbar-thumb,
    .cart-items::-webkit-scrollbar-thumb {
        background: #d9d0c8;
        border-radius: 999px;
        border: 2px solid transparent;
        background-clip: padding-box;
    }

    /* Make empty/error states feel intentional rather than like dead space. */
    .empty {
        color: #5f5750;
    }

    .empty-icon {
        border: 1px solid #ece5df;
    }

    .alert-box {
        z-index: 30000 !important;
        backdrop-filter: blur(10px);
    }

    /* Responsive workspace */
    @media (max-width: 1200px) {
        .layout {
            grid-template-columns: minmax(0, 1fr) 360px !important;
        }

        .food-grid {
            grid-template-columns: repeat(auto-fill, minmax(165px, 1fr)) !important;
        }
    }

    @media (max-width: 1050px) {
        .layout {
            grid-template-columns: 1fr !important;
        }

        .cart {
            position: static !important;
            top: auto !important;
        }

        .cart-items {
            max-height: 360px;
        }
    }

    @media (max-width: 800px) {
        .page-header {
            min-height: auto;
            padding-top: 0;
        }

        .page-header h1 {
            font-size: 20px !important;
        }

        .page-header .subtitle {
            font-size: 9px !important;
        }

        .content {
            padding: 18px !important;
        }
    }

    @media (max-width: 580px) {
        .page-header {
            align-items: stretch;
        }

        .date {
            width: 100%;
            justify-content: center;
        }

        .food-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            padding: 11px !important;
        }

        .food-image {
            height: 100px !important;
        }

        .panel-head {
            padding: 15px !important;
        }
    }



    /* ===== MENU CARD REFINEMENT ===== */
    .food-grid {
        grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)) !important;
        gap: 12px !important;
        padding: 16px !important;
    }

    .food-card {
        min-width: 0;
        border-radius: 14px !important;
    }

    /* Image occupies about 40% of the card and remains prominent. */
    .food-image {
        height: 118px !important;
        min-height: 118px !important;
    }

    .food-info {
        min-height: 82px !important;
        height: auto !important;
        padding: 11px 12px 13px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
    }

    /* Show the complete food name instead of forcing it into a tiny line. */
    .food-name {
        display: block !important;
        padding-right: 38px !important;
        overflow: visible !important;
        text-overflow: clip !important;
        white-space: normal !important;
        -webkit-line-clamp: unset !important;
        -webkit-box-orient: initial !important;
        min-height: 0 !important;
        line-height: 1.35 !important;
        font-size: 11px !important;
        font-weight: 750 !important;
        word-break: normal !important;
        overflow-wrap: anywhere !important;
    }

    .price {
        margin-top: 7px !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
    }

    .add {
        width: 29px !important;
        height: 29px !important;
        right: 9px !important;
        bottom: 9px !important;
        z-index: 2;
    }

    @media (max-width: 700px) {
        .food-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            padding: 12px !important;
        }

        .food-image {
            height: 105px !important;
            min-height: 105px !important;
        }

        .food-info {
            min-height: 78px !important;
            padding: 10px !important;
        }

        .food-name {
            font-size: 10px !important;
        }

        .price {
            font-size: 10px !important;
        }
    }



    /* ===== FINAL COMPACT MENU CARD SIZE ===== */
    .food-grid {
        grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)) !important;
        gap: 9px !important;
        padding: 12px !important;
    }

    .food-card {
        min-width: 0 !important;
        border-radius: 11px !important;
    }

    /* Keep the card short, but let the image dominate it. */
    .food-image {
        height: 108px !important;
        min-height: 108px !important;
    }

    .food-info {
        min-height: 66px !important;
        padding: 8px 9px 9px !important;
    }

    .food-name {
        padding-right: 32px !important;
        font-size: 9px !important;
        line-height: 1.28 !important;
    }

    .price {
        margin-top: 4px !important;
        font-size: 9px !important;
    }

    .add {
        width: 25px !important;
        height: 25px !important;
        right: 7px !important;
        bottom: 7px !important;
        font-size: 8px !important;
    }

    @media (max-width: 700px) {
        .food-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px !important;
            padding: 10px !important;
        }

        .food-image {
            height: 96px !important;
            min-height: 96px !important;
        }

        .food-info {
            min-height: 62px !important;
            padding: 8px !important;
        }

        .food-name,
        .price {
            font-size: 9px !important;
        }
    }



    /* ===== LARGER DISH IMAGE / COMPACT CARD ===== */
    .food-image {
        height: 124px !important;
        min-height: 124px !important;
    }

    .food-image img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
    }

    .food-info {
        min-height: 60px !important;
        padding: 7px 9px 8px !important;
    }

    .food-name {
        font-size: 9px !important;
        line-height: 1.25 !important;
    }

    .price {
        margin-top: 4px !important;
        font-size: 9px !important;
    }

    @media (max-width: 700px) {
        .food-image {
            height: 110px !important;
            min-height: 110px !important;
        }

        .food-info {
            min-height: 58px !important;
        }
    }

    /* ===== QUICK-PICK MENU LAYOUT =====
   Keep the menu compact so multiple dishes are visible at once.
   The menu itself no longer traps the user inside a scrollable box. */
    .food-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
        gap: 9px !important;
        padding: 12px !important;
        max-height: none !important;
        overflow-y: visible !important;
        align-content: start;
    }

    .food-card {
        min-width: 0 !important;
        border-radius: 12px !important;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
    }

    .food-card:hover {
        transform: translateY(-2px);
    }

    .food-card:active {
        transform: scale(.98);
    }

    .food-image {
        height: 92px !important;
        min-height: 92px !important;
    }

    .food-info {
        min-height: 58px !important;
        padding: 7px 8px 8px !important;
    }

    .food-name {
        padding-right: 30px !important;
        font-size: 9px !important;
        line-height: 1.25 !important;
    }

    .price {
        margin-top: 3px !important;
        font-size: 9px !important;
    }

    .add {
        width: 25px !important;
        height: 25px !important;
        right: 7px !important;
        bottom: 7px !important;
        border-radius: 8px !important;
    }

    .food-card:hover .add {
        transform: scale(1.05);
    }

    @media (max-width: 1350px) {
        .food-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 1200px) {
        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 1050px) {
        .food-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 800px) {
        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 580px) {
        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px !important;
            padding: 9px !important;
        }

        .food-image {
            height: 82px !important;
            min-height: 82px !important;
        }
    }

    /* =========================================================
       FOOD MENU — SALES PAGE CARD STYLE
       Only the food cards are changed.
       ========================================================= */
    .food-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 12px !important;
        padding: 12px !important;
        max-height: none !important;
        overflow: visible !important;
    }

    .food-card {
        position: relative !important;
        min-width: 0 !important;
        overflow: hidden !important;
        border: 1px solid #eadfd7 !important;
        border-radius: 17px !important;
        background: #fff !important;
        box-shadow: 0 4px 12px rgba(40, 30, 22, .035) !important;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease !important;
    }

    .food-card:hover {
        transform: translateY(-2px) !important;
        border-color: #f1b98e !important;
        box-shadow: 0 10px 22px rgba(40, 30, 22, .09) !important;
    }

    /* Large picture exactly like the Sales-page style */
    .food-image {
        width: 100% !important;
        height: 135px !important;
        min-height: 135px !important;
        display: block !important;
        background-position: center !important;
        background-size: cover !important;
        background-repeat: no-repeat !important;
        background-color: #f2ece7 !important;
    }

    .food-image img {
        width: 100% !important;
        height: 100% !important;
        display: block !important;
        object-fit: cover !important;
        object-position: center !important;
    }

    /* Category badge */
    .food-badge {
        position: absolute !important;
        top: 10px !important;
        left: 10px !important;
        z-index: 3 !important;
        padding: 6px 10px !important;
        border: 0 !important;
        border-radius: 8px !important;
        color: #fff !important;
        background: rgba(28, 28, 28, .88) !important;
        font-size: 8px !important;
        line-height: 1 !important;
        font-weight: 800 !important;
        box-shadow: 0 3px 8px rgba(0, 0, 0, .12) !important;
    }

    /* White information area */
    .food-info {
        position: relative !important;
        min-height: 105px !important;
        height: auto !important;
        padding: 14px 15px 16px !important;
        display: block !important;
        background: #fff !important;
    }

    .food-name {
        display: block !important;
        width: calc(100% - 45px) !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        color: #403a35 !important;
        font-size: 13px !important;
        line-height: 1.4 !important;
        font-weight: 800 !important;
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: clip !important;
        word-break: normal !important;
        overflow-wrap: anywhere !important;
        -webkit-line-clamp: unset !important;
    }

    .price {
        display: block !important;
        margin-top: 16px !important;
        color: #e8660d !important;
        font-size: 15px !important;
        line-height: 1 !important;
        font-weight: 800 !important;
    }

    /* Large orange + button */
    .add {
        position: absolute !important;
        right: 12px !important;
        bottom: 14px !important;
        width: 46px !important;
        height: 46px !important;
        display: grid !important;
        place-items: center !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 12px !important;
        color: #fff !important;
        background: #f58220 !important;
        box-shadow: none !important;
        font-size: 17px !important;
        line-height: 1 !important;
        cursor: pointer !important;
        z-index: 4 !important;
        transition: transform .16s ease, background .16s ease !important;
    }

    .add:hover {
        transform: scale(1.04) !important;
        background: #e86b10 !important;
    }

    @media (max-width: 700px) {
        .food-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            padding: 10px !important;
        }

        .food-image {
            height: 110px !important;
            min-height: 110px !important;
        }

        .food-info {
            min-height: 92px !important;
            padding: 11px 12px 13px !important;
        }

        .food-name {
            width: calc(100% - 38px) !important;
            font-size: 10px !important;
        }

        .price {
            margin-top: 12px !important;
            font-size: 12px !important;
        }

        .add {
            right: 9px !important;
            bottom: 10px !important;
            width: 38px !important;
            height: 38px !important;
            border-radius: 10px !important;
            font-size: 14px !important;
        }

        .food-badge {
            top: 8px !important;
            left: 8px !important;
            padding: 5px 8px !important;
            font-size: 7px !important;
        }
    }


    /* =========================================================
       FINAL COMPACT + PROFESSIONAL ORDER MENU
       Keeps food cards compact so more items are visible at once.
       ========================================================= */
    .content {
        padding: 24px 28px 36px;
    }

    .page-header {
        margin-bottom: 18px;
    }

    .title-icon {
        width: 46px;
        height: 46px;
        flex-basis: 46px;
        border-radius: 13px;
        font-size: 17px;
    }

    h1 {
        font-size: 24px;
    }

    .subtitle {
        font-size: 10px;
        margin-top: 4px;
    }

    .date {
        padding: 9px 13px;
        border-radius: 10px;
        font-size: 9px;
    }

    .layout {
        grid-template-columns: minmax(0, 1fr) 390px;
        gap: 18px;
    }

    .panel {
        border-radius: 15px;
        box-shadow: 0 8px 24px rgba(40, 30, 22, .05);
    }

    .panel-head {
        padding: 16px 18px;
    }

    .panel-head-row {
        margin-bottom: 10px;
    }

    .panel-head h2 {
        font-size: 15px;
    }

    .badge-count {
        padding: 6px 9px;
        font-size: 8px;
    }

    .menu-search {
        height: 40px;
        border-radius: 9px;
        gap: 8px;
        padding: 0 11px;
    }

    .menu-search input {
        font-size: 10px;
    }

    .food-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 9px !important;
        padding: 10px !important;
    }

    .food-card {
        border-radius: 11px !important;
        box-shadow: 0 3px 10px rgba(40, 30, 22, .035) !important;
    }

    .food-image {
        height: 88px !important;
        min-height: 88px !important;
    }

    .food-info {
        min-height: 53px !important;
        padding: 6px 8px 7px !important;
    }

    .food-name {
        padding-right: 26px !important;
        font-size: 8.5px !important;
        line-height: 1.25 !important;
        min-height: 22px !important;
    }

    .price {
        margin-top: 3px !important;
        font-size: 9px !important;
    }

    .add {
        width: 23px !important;
        height: 23px !important;
        right: 6px !important;
        bottom: 6px !important;
        border-radius: 7px !important;
        font-size: 9px !important;
    }

    .cart {
        top: 18px;
    }

    .cart-head {
        padding: 15px 17px;
    }

    .cart-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        font-size: 12px;
    }

    .cart-title strong {
        font-size: 14px;
    }

    .cart-title small {
        font-size: 8px;
    }

    .cart-items {
        max-height: 340px;
    }

    @media (max-width: 1350px) {
        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 1150px) {
        .layout {
            grid-template-columns: minmax(0, 1fr) 350px;
        }

        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 900px) {
        .layout {
            grid-template-columns: 1fr;
        }

        .cart {
            position: static;
        }

        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 620px) {
        .content {
            padding: 18px 12px 30px;
        }

        .page-header {
            align-items: flex-start;
        }

        .title-icon {
            width: 40px;
            height: 40px;
            flex-basis: 40px;
        }

        h1 {
            font-size: 20px;
        }

        .food-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 7px !important;
            padding: 8px !important;
        }

        .food-image {
            height: 76px !important;
            min-height: 76px !important;
        }

        .food-info {
            min-height: 49px !important;
            padding: 5px 6px 6px !important;
        }

        .food-name,
        .price {
            font-size: 8px !important;
        }
    }
    </style>
</head>

<body>

    <div class="app">

        <?php
        if (file_exists("../includes/sidebar.php")) {
            include "../includes/sidebar.php";
        }
        ?>

        <main class="main">



            <div class="content">

                <div class="page-header">
                    <div class="title">
                        <div class="title-icon"><i class="fa-solid fa-receipt"></i></div>
                        <div>
                            <h1>New Order</h1>
                            <p class="subtitle">Select food, choose payment method and place the order</p>
                        </div>
                    </div>
                    <div class="date"><i
                            class="fa-regular fa-calendar me-1"></i><?= htmlspecialchars(date('l, F j, Y')) ?>
                    </div>
                </div>

                <?php if ($pageError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($pageError) ?></div>
                <?php endif; ?>

                <div class="layout">

                    <section class="panel">
                        <div class="panel-head">
                            <div class="panel-head-row">
                                <h2>Food Menu</h2>
                                <span class="badge-count"><?= count($foods) ?> Items</span>
                            </div>
                            <div class="menu-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input id="foodSearch" type="text" placeholder="Search food...">
                            </div>
                        </div>

                        <div class="food-grid" id="foodGrid">
                            <?php if ($foods): ?>
                            <?php foreach ($foods as $food): ?>
                            <article class="food-card" data-id="<?= (int)$food['id'] ?>"
                                data-name="<?= htmlspecialchars(strtolower($food['name']), ENT_QUOTES) ?>"
                                data-food-name="<?= htmlspecialchars($food['name'], ENT_QUOTES) ?>"
                                data-price="<?= (float)$food['price'] ?>"
                                data-image="<?= htmlspecialchars($food['image'] ?? '', ENT_QUOTES) ?>">

                                <div class="food-image">
                                    <?php if (!empty($food['image'])): ?>
                                    <img src="../assets/uploads/<?= htmlspecialchars($food['image'], ENT_QUOTES) ?>"
                                        alt="<?= htmlspecialchars($food['name'], ENT_QUOTES) ?>"
                                        onerror="this.style.display='none';this.parentElement.innerHTML='<div class=&quot;placeholder&quot;><i class=&quot;fa-solid fa-utensils&quot;></i></div>';">
                                    <?php else: ?>
                                    <div class="placeholder"><i class="fa-solid fa-utensils"></i></div>
                                    <?php endif; ?>
                                </div>

                                <div class="food-info">
                                    <span class="food-name"><?= htmlspecialchars($food['name']) ?></span>
                                    <div class="price">GH₵ <?= number_format((float)$food['price'], 2) ?></div>
                                </div>

                                <button type="button" class="add"><i class="fa-solid fa-plus"></i></button>
                            </article>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty">
                                <div class="empty-icon"><i class="fa-solid fa-utensils"></i></div>
                                <strong>No food available</strong>
                                <p>Add available food items from the food menu before creating an order.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <aside class="panel cart">
                        <div class="cart-head">
                            <div class="cart-title">
                                <div class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                                <div><strong>Current Order</strong><small>Items selected</small></div>
                            </div>
                            <span class="cart-count" id="cartCount">0</span>
                        </div>

                        <div class="cart-items" id="cartItems">
                            <div class="empty" id="emptyCart">
                                <div class="empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                                <strong>Your order is empty</strong>
                                <p>Select food items from the menu to add them to this order.</p>
                            </div>
                        </div>

                        <div class="details">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="label">Order Type</label>
                                    <select id="orderType" class="select">
                                        <option value="Dine In">Dine In</option>
                                        <option value="Takeaway">Takeaway</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="label">Payment Method</label>
                                    <select id="paymentMethod" class="select">
                                        <option value="Cash">Cash</option>
                                        <option value="Mobile Money">Mobile Money</option>
                                        <option value="Card">Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="summary">
                            <div class="rowline"><span>Items</span><strong id="summaryItems">0</strong></div>
                            <div class="rowline"><span>Subtotal</span><strong id="summarySubtotal">GH₵ 0.00</strong>
                            </div>
                            <div class="total"><span>Total</span><strong id="summaryTotal">GH₵ 0.00</strong></div>

                            <button id="placeOrder" class="place" disabled>
                                <i class="fa-solid fa-check me-1"></i>Place Order
                            </button>
                            <button id="clearOrder" class="clear">
                                <i class="fa-solid fa-trash-can me-1"></i>Clear Order
                            </button>
                        </div>
                    </aside>

                </div>
            </div>
        </main>
    </div>

    <div class="confirm-overlay" id="confirmOverlay" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">

            <div class="confirm-top">
                <div class="confirm-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <h3 id="confirmTitle">Confirm Order</h3>

                <p>
                    Please review the order details before submitting.
                </p>
            </div>

            <div class="confirm-details">
                <div class="confirm-detail">
                    <span>Order Type</span>
                    <strong id="confirmOrderType">Dine In</strong>
                </div>

                <div class="confirm-detail">
                    <span>Payment Method</span>
                    <strong id="confirmPaymentMethod">Cash</strong>
                </div>

                <div class="confirm-detail">
                    <span>Items</span>
                    <strong id="confirmItemCount">0</strong>
                </div>

                <div class="confirm-detail total">
                    <span>Total Amount</span>
                    <strong id="confirmTotal">GH₵ 0.00</strong>
                </div>
            </div>

            <div class="confirm-actions">
                <button type="button" class="confirm-btn confirm-cancel" id="cancelConfirm">
                    Cancel
                </button>

                <button type="button" class="confirm-btn confirm-place" id="confirmPlaceOrder">
                    <i class="fa-solid fa-check me-1"></i>
                    Confirm &amp; Place
                </button>
            </div>

        </div>
    </div>

    <div id="alertBox" class="alert-box">
        <strong id="alertTitle"></strong>
        <span id="alertMessage"></span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", () => {

        let cart = [];

        const $ = id => document.getElementById(id);
        const foodGrid = $("foodGrid");
        const cartItems = $("cartItems");
        const emptyCart = $("emptyCart");
        const placeOrder = $("placeOrder");

        function money(value) {
            return "GH₵ " + Number(value).toLocaleString("en-GH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escapeHtml(value) {
            const div = document.createElement("div");
            div.textContent = value;
            return div.innerHTML;
        }

        function showAlert(title, message, error = false) {
            $("alertTitle").textContent = title;
            $("alertMessage").textContent = message;
            $("alertBox").classList.toggle("error", error);
            $("alertBox").classList.add("show");
            clearTimeout(window.orderAlertTimer);
            window.orderAlertTimer = setTimeout(() => $("alertBox").classList.remove("show"), 6000);
        }

        function mergeDuplicateCartLines() {
            const merged = [];

            cart.forEach(item => {
                const normalizedName = String(item.name || "").trim().toLowerCase();
                const price = Number(item.price) || 0;

                const existing = merged.find(existingItem =>
                    String(existingItem.name || "").trim().toLowerCase() === normalizedName &&
                    Math.abs(Number(existingItem.price) - price) < 0.005
                );

                if (existing) {
                    existing.quantity += Number(item.quantity) || 0;
                } else {
                    merged.push({
                        ...item,
                        quantity: Number(item.quantity) || 0
                    });
                }
            });

            cart = merged.filter(item => item.quantity > 0);
        }

        function renderCart() {
            mergeDuplicateCartLines();

            if (!cart.length) {
                cartItems.innerHTML = "";
                cartItems.appendChild(emptyCart);
                emptyCart.style.display = "flex";
                $("cartCount").textContent = "0";
                $("summaryItems").textContent = "0";
                $("summarySubtotal").textContent = money(0);
                $("summaryTotal").textContent = money(0);
                placeOrder.disabled = true;
                return;
            }

            emptyCart.style.display = "none";
            cartItems.innerHTML = "";

            let itemCount = 0;
            let subtotal = 0;

            cart.forEach((item, index) => {
                itemCount += item.quantity;
                subtotal += item.price * item.quantity;

                const row = document.createElement("div");
                row.className = "item";

                const image = item.image ?
                    `<img src="../assets/uploads/${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}"
                     onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-solid fa-utensils&quot;></i>';">` :
                    `<i class="fa-solid fa-utensils"></i>`;

                row.innerHTML = `
                <div class="item-img">${image}</div>
                <div class="item-info">
                    <strong>${escapeHtml(item.name)}</strong>
                    <small>${money(item.price)}</small>
                </div>
                <div class="qty">
                    <button data-action="minus" data-index="${index}"><i class="fa-solid fa-minus"></i></button>
                    <span>${item.quantity}</span>
                    <button data-action="plus" data-index="${index}"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="item-total">${money(item.price * item.quantity)}</div>
                <button class="remove" data-action="remove" data-index="${index}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

                cartItems.appendChild(row);
            });

            $("cartCount").textContent = itemCount;
            $("summaryItems").textContent = itemCount;
            $("summarySubtotal").textContent = money(subtotal);
            $("summaryTotal").textContent = money(subtotal);
            placeOrder.disabled = false;
        }
        /*
        |--------------------------------------------------------------------------
        | ADD FOOD TO ORDER
        |--------------------------------------------------------------------------
        */

        foodGrid.addEventListener("click", function(event) {

            const card = event.target.closest(".food-card");

            if (!card) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const id = parseInt(card.dataset.id, 10);
            const name = card.dataset.foodName || "";
            const price = parseFloat(card.dataset.price) || 0;
            const image = card.dataset.image || "";

            if (!id || !name) {
                showAlert(
                    "Unable to add food",
                    "The selected food has invalid menu data.",
                    true
                );
                return;
            }

            /*
             * Cart identity is FOOD NAME + PRICE.
             *
             * This is important when the menu contains the same food name
             * at different prices, e.g.:
             *   Banku — GH₵ 30
             *   Banku — GH₵ 50
             *
             * Those are two separate order lines and can both be selected.
             * But the exact same food name at the exact same price must
             * remain one cart line; clicking it again increases quantity.
             */
            const normalizedName = name.trim().toLowerCase();

            const existing = cart.find(item =>
                item.name.trim().toLowerCase() === normalizedName &&
                Math.abs(Number(item.price) - price) < 0.005
            );

            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    image: image,
                    quantity: 1
                });
            }

            renderCart();

            showAlert(
                "Added to order",
                name + " has been added to your current order."
            );
        });

        cartItems.addEventListener("click", event => {
            const button = event.target.closest("[data-action]");
            if (!button) return;

            const index = Number(button.dataset.index);
            const action = button.dataset.action;

            if (!cart[index]) return;

            if (action === "plus") cart[index].quantity++;

            if (action === "minus") {
                cart[index].quantity--;
                if (cart[index].quantity <= 0) cart.splice(index, 1);
            }

            if (action === "remove") cart.splice(index, 1);

            renderCart();
        });

        $("clearOrder").addEventListener("click", () => {
            if (!cart.length) return;
            if (confirm("Are you sure you want to clear this order?")) {
                cart = [];
                renderCart();
            }
        });

        $("foodSearch").addEventListener("input", function() {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll(".food-card").forEach(card => {
                card.style.display = card.dataset.name.includes(term) ? "" : "none";
            });
        });

        /*
    |--------------------------------------------------------------------------
    | PROFESSIONAL CONFIRMATION MODAL
    |--------------------------------------------------------------------------
    */

        const confirmOverlay = $("confirmOverlay");
        const confirmOrderType = $("confirmOrderType");
        const confirmPaymentMethod = $("confirmPaymentMethod");
        const confirmItemCount = $("confirmItemCount");
        const confirmTotal = $("confirmTotal");
        const cancelConfirm = $("cancelConfirm");
        const confirmPlaceOrder = $("confirmPlaceOrder");

        let pendingOrderData = null;

        function openConfirmation() {

            if (!cart.length) return;

            const orderType = $("orderType").value;
            const paymentMethod = $("paymentMethod").value;

            const itemCount = cart.reduce(
                (sum, item) => sum + item.quantity,
                0
            );

            const total = cart.reduce(
                (sum, item) => sum + item.price * item.quantity,
                0
            );

            pendingOrderData = {
                order_type: orderType,
                payment_method: paymentMethod,
                items: cart.map(item => ({
                    food_id: item.id,
                    quantity: item.quantity
                }))
            };

            confirmOrderType.textContent = orderType;
            confirmPaymentMethod.textContent = paymentMethod;
            confirmItemCount.textContent = itemCount;
            confirmTotal.textContent = money(total);

            confirmOverlay.classList.add("show");
            confirmOverlay.setAttribute("aria-hidden", "false");

            setTimeout(() => {
                confirmPlaceOrder.focus();
            }, 50);
        }

        function closeConfirmation() {
            confirmOverlay.classList.remove("show");
            confirmOverlay.setAttribute("aria-hidden", "true");
            pendingOrderData = null;
        }

        placeOrder.addEventListener("click", () => {
            openConfirmation();
        });

        cancelConfirm.addEventListener("click", () => {
            closeConfirmation();
        });

        confirmOverlay.addEventListener("click", event => {
            if (event.target === confirmOverlay) {
                closeConfirmation();
            }
        });

        document.addEventListener("keydown", event => {
            if (
                event.key === "Escape" &&
                confirmOverlay.classList.contains("show")
            ) {
                closeConfirmation();
            }
        });

        confirmPlaceOrder.addEventListener("click", async () => {

            if (!pendingOrderData || !cart.length) {
                closeConfirmation();
                return;
            }

            const orderData = pendingOrderData;

            confirmPlaceOrder.disabled = true;
            confirmPlaceOrder.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving Order...';

            try {

                const response = await fetch(
                    "../handlers/add_order.php", {
                        method: "POST",

                        headers: {
                            "Content-Type": "application/json",
                            "Accept": "application/json"
                        },

                        body: JSON.stringify(orderData)
                    }
                );

                const text = await response.text();

                let result;

                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error("Server response:", text);

                    throw new Error(
                        "The server returned an invalid response. Check handlers/add_order.php."
                    );
                }

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message ||
                        "Unable to place order."
                    );
                }

                closeConfirmation();

                showAlert(
                    "Order placed successfully",
                    result.order_number +
                    " • " +
                    result.payment_method +
                    " • " +
                    money(result.total)
                );

                /*
                 * Open the receipt for THIS exact order.
                 */
                if (result.order_id) {

                    const receiptUrl =
                        "print_receipt.php?order_id=" +
                        encodeURIComponent(result.order_id);

                    const receiptWindow = window.open(
                        receiptUrl,
                        "_blank"
                    );

                    if (!receiptWindow) {

                        showAlert(
                            "Order saved",
                            "The order was saved, but the receipt window was blocked. Please allow pop-ups for this site.",
                            true
                        );
                    }
                }

                cart = [];
                renderCart();

            } catch (error) {

                console.error(error);

                closeConfirmation();

                showAlert(
                    "Order failed",
                    error.message,
                    true
                );

            } finally {

                confirmPlaceOrder.disabled = false;

                confirmPlaceOrder.innerHTML =
                    '<i class="fa-solid fa-check me-1"></i>Confirm &amp; Place';
            }
        });
        renderCart();

    });



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
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>