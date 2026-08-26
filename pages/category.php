<?php

session_start();

/*
|--------------------------------------------------------------------------
| CATEGORY PAGE ACCESS PROTECTION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Administrator'
) {

    $_SESSION['login_message'] =
        'Please log in as an Administrator to access the Categories page.';

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

$category_message = $_SESSION['category_message'] ?? null;

unset($_SESSION['category_message']);


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
*/

$categories = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            description,
            status,
            created_at,
            updated_at
        FROM categories
        ORDER BY id DESC
    ");

    $categories = $stmt->fetchAll();

} catch (PDOException $e) {

    $category_message = [
        'type' => 'error',
        'message' => 'Unable to load categories from the database.'
    ];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Categories | Better End
    </title>


    <!-- =====================================================
         POPPINS FONT
    ====================================================== -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- =====================================================
         DASHBOARD CSS
    ====================================================== -->

    <link rel="stylesheet" href="../assets/css/styles.css">


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

    .categories-main {

        margin-left: var(--sidebar-width);

        min-height: 100vh;

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

    .category-message {

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
            categoryMessageIn .3s ease both;

        transition:
            opacity .35s ease,
            transform .35s ease;

    }


    .category-message.success {

        color: #176b43;

        background: #f0fbf5;

        border-color: #bfe8d2;

    }


    .category-message.error {

        color: #a93434;

        background: #fff5f5;

        border-color: #f0c3c3;

    }


    .category-message-icon {

        width: 35px;

        height: 35px;

        flex: 0 0 35px;

        display: grid;

        place-items: center;

        border-radius: 50%;

        color: #ffffff;

    }


    .category-message.success .category-message-icon {

        background: var(--green);

    }


    .category-message.error .category-message-icon {

        background: var(--red);

    }


    .category-message-text {

        flex: 1;

        font-size: 12px;

        font-weight: 700;

        line-height: 1.5;

    }


    .category-message-close {

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


    .category-message-close:hover {

        opacity: 1;

        background: rgba(0, 0, 0, .05);

    }


    .category-message-progress {

        position: absolute;

        left: 0;

        bottom: 0;

        width: 100%;

        height: 3px;

        transform-origin: left;

        animation:
            categoryMessageProgress 5s linear forwards;

    }


    .category-message.success .category-message-progress {

        background: var(--green);

    }


    .category-message.error .category-message-progress {

        background: var(--red);

    }


    .category-message.hide {

        opacity: 0;

        transform: translateY(-8px);

    }


    @keyframes categoryMessageIn {

        from {

            opacity: 0;

            transform:
                translateY(-8px);

        }

        to {

            opacity: 1;

            transform:
                translateY(0);

        }

    }


    @keyframes categoryMessageProgress {

        from {

            transform:
                scaleX(1);

        }

        to {

            transform:
                scaleX(0);

        }

    }


    /* =========================================================
   PAGE HERO
========================================================= */

    .categories-intro {

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


    .categories-intro::after {

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


    .categories-intro>* {

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


    .categories-intro h2 {

        margin: 0;

        color: #ffffff;

        font-size: 29px;

        font-weight: 800;

        letter-spacing: -.8px;

    }


    .categories-intro p {

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
   FORM + TABLE LAYOUT
========================================================= */

    .categories-layout {

        display: grid;

        grid-template-columns:
            390px minmax(0, 1fr);

        gap: 22px;

        align-items: start;

    }


    /* =========================================================
   GENERAL PANEL
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

    .category-form {

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
   INPUT
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

        min-height: 105px;

        align-items: flex-start;

        padding-top: 14px;

    }


    .field textarea {

        height: 80px;

        resize: none;

        line-height: 1.5;

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
   FORM BUTTONS
========================================================= */

    .form-footer {

        display: grid;

        grid-template-columns: 105px 1fr;

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

        transform:
            translateY(-1px);

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

        transform:
            translateY(-2px);

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

    .table-panel .panel-header {

        min-height: 82px;

        display: flex;

        align-items: center;

        gap: 13px;

        padding: 18px 22px;

        border-bottom: 1px solid #eeeae6;

        background: #ffffff;

    }


    .table-panel .panel-header-icon {

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


    .table-panel .panel-header h3 {

        margin: 0;

        color: #24201d;

        font-size: 16px;

        font-weight: 800;

        letter-spacing: -.25px;

    }


    .table-panel .panel-header p {

        margin: 4px 0 0;

        color: #9a928b;

        font-size: 10px;

        font-weight: 500;

    }


    /* =========================================================
   CATEGORY COUNT
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


    .table-tools>div:first-child {

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
   TABLE WRAPPER
========================================================= */

    .table-wrap {

        width: 100%;

        overflow-x: auto;

        background: #ffffff;

    }


    .categories-table {

        width: 100%;

        min-width: 760px;

        border-collapse: separate;

        border-spacing: 0;

    }


    /* =========================================================
   TABLE HEAD
========================================================= */

    .categories-table thead th {

        padding: 15px 18px;

        color: #8b827a;

        background: #faf9f7;

        border-bottom: 1px solid #e9e4df;

        font-size: 9px;

        font-weight: 800;

        letter-spacing: 1px;

        text-align: left;

        white-space: nowrap;

    }


    .categories-table thead th:first-child {

        padding-left: 22px;

    }


    .categories-table thead th:last-child {

        padding-right: 22px;

        text-align: right;

    }


    /* =========================================================
   TABLE BODY
========================================================= */

    .categories-table tbody tr {

        background: #ffffff;

        transition:
            background .18s ease;

    }


    .categories-table tbody tr:hover {

        background: #fffaf6;

    }


    .categories-table tbody td {

        padding: 17px 18px;

        color: #655d56;

        border-bottom: 1px solid #f0ece8;

        font-size: 11px;

        vertical-align: middle;

    }


    .categories-table tbody tr:last-child td {

        border-bottom: 0;

    }


    .categories-table tbody td:first-child {

        padding-left: 22px;

    }


    .categories-table tbody td:last-child {

        padding-right: 22px;

        text-align: right;

    }


    /* =========================================================
   CATEGORY CELL
========================================================= */

    .category-cell {

        min-width: 190px;

        display: flex;

        align-items: center;

        gap: 12px;

    }


    .category-icon {

        width: 43px;

        height: 43px;

        flex: 0 0 43px;

        display: grid;

        place-items: center;

        border-radius: 13px;

        color: #e56d14;

        background:
            linear-gradient(145deg,
                #fff3e7,
                #ffead9);

        border: 1px solid #f6dfcc;

        font-size: 14px;

        font-weight: 800;

        box-shadow:
            0 4px 10px rgba(245, 130, 32, .06);

    }


    .category-cell strong {

        display: block;

        margin-bottom: 3px;

        color: #302b27;

        font-size: 12px;

        font-weight: 800;

    }


    .category-cell small {

        display: block;

        color: #aaa19a;

        font-size: 9px;

        font-weight: 500;

    }


    /* =========================================================
   DESCRIPTION
========================================================= */

    .description {

        max-width: 300px;

        color: #817971;

        font-size: 10px;

        line-height: 1.6;

    }


    /* =========================================================
   STATUS
========================================================= */

    .category-status {

        min-width: 78px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        gap: 7px;

        padding: 7px 11px;

        border-radius: 30px;

        font-size: 9px;

        font-weight: 800;

    }


    .category-status::before {

        content: "";

        width: 6px;

        height: 6px;

        flex: 0 0 6px;

        border-radius: 50%;

    }


    .category-status.active {

        color: #087944;

        background: #eaf8f0;

        border: 1px solid #d5f0e1;

    }


    .category-status.active::before {

        background: #1fa463;

        box-shadow:
            0 0 0 3px #dff4e9;

    }


    .category-status.inactive {

        color: #9b3f3f;

        background: #fff0f0;

        border: 1px solid #f5dada;

    }


    .category-status.inactive::before {

        background: #d94b4b;

        box-shadow:
            0 0 0 3px #ffe2e2;

    }


    /* =========================================================
   CREATED DATE
========================================================= */

    .categories-table td .date {

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

        transform:
            translateY(-2px);

    }


    .action.delete:hover {

        color: var(--red);

        background: #fff4f4;

        border-color: #efc5c5;

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

        .categories-layout {

            grid-template-columns:
                350px minmax(0, 1fr);

        }

    }


    @media (max-width: 992px) {

        .categories-main {

            margin-left: 0;

        }


        .categories-layout {

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


        .top-profile div:not(.avatar) {

            display: none;

        }


        .categories-intro {

            min-height: auto;

            padding: 23px;

            align-items: flex-start;

        }


        .categories-intro h2 {

            font-size: 23px;

        }


        .categories-intro p {

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


        .category-form {

            padding:
                20px 16px;

        }


        .table-panel .panel-header {

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


        .table-tools>div:first-child {

            align-items: flex-start;

            flex-direction: column;

            gap: 2px;

        }


        .table-search {

            width: 100%;

        }


        .categories-table thead th {

            padding:
                13px 14px;

        }


        .categories-table tbody td {

            padding:
                15px 14px;

        }


        .categories-table tbody td:first-child,
        .categories-table thead th:first-child {

            padding-left: 16px;

        }


        .categories-table tbody td:last-child,
        .categories-table thead th:last-child {

            padding-right: 16px;

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

    <main class="categories-main">


        <!-- =====================================================
         TOP BAR
    ====================================================== -->

        <header class="topbar">

            <div class="topbar-title">

                <div class="topbar-icon">

                    <i class="fa-solid fa-layer-group"></i>

                </div>


                <div>

                    <h1>
                        Categories
                    </h1>

                    <p>
                        Manage your restaurant food categories
                    </p>

                </div>

            </div>


            <div class="topbar-right">


                <a href="dashboard.php" class="topbar-link">

                    <i class="fa-solid fa-house"></i>

                    <span>
                        Dashboard
                    </span>

                </a>


                <div class="top-profile">

                    <div class="avatar">

                        <?= htmlspecialchars($avatar) ?>

                    </div>


                    <div>

                        <strong>

                            <?= htmlspecialchars($username) ?>

                        </strong>


                        <small>

                            <?= htmlspecialchars($role) ?>

                        </small>

                    </div>

                </div>

            </div>

        </header>



        <!-- =====================================================
         CONTENT
    ====================================================== -->

        <div class="content">


            <!-- =================================================
             NOTIFICATION
        ================================================== -->

            <?php if ($category_message): ?>

            <div id="categoryMessage" class="category-message
                <?= $category_message['type'] === 'success'
                    ? 'success'
                    : 'error' ?>" role="alert">


                <div class="category-message-icon">

                    <i class="fa-solid
                        <?= $category_message['type'] === 'success'
                            ? 'fa-check'
                            : 'fa-xmark' ?>"></i>

                </div>


                <div class="category-message-text">

                    <?= htmlspecialchars(
                        $category_message['message']
                    ) ?>

                </div>


                <button type="button" class="category-message-close" id="closeCategoryMessage"
                    aria-label="Close notification">

                    <i class="fa-solid fa-xmark"></i>

                </button>


                <div class="category-message-progress"></div>

            </div>

            <?php endif; ?>



            <!-- =================================================
             HERO
        ================================================== -->

            <section class="categories-intro">


                <div>

                    <span class="eyebrow">

                        FOOD MANAGEMENT

                    </span>


                    <h2>

                        Manage Categories

                    </h2>


                    <p>

                        Create and organize the categories used
                        by your restaurant food menu.

                    </p>

                </div>


                <div class="intro-icon">

                    <i class="fa-solid fa-layer-group"></i>

                </div>


            </section>



            <!-- =================================================
             FORM + TABLE
        ================================================== -->

            <section class="categories-layout">


                <!-- =================================================
                 ADD CATEGORY FORM
            ================================================= -->

                <article class="panel form-panel">


                    <div class="form-card">


                        <!-- FORM HEADER -->

                        <div class="form-top">


                            <div class="form-top-icon">

                                <i class="fa-solid fa-folder-plus"></i>

                            </div>


                            <h3>

                                Add New Category

                            </h3>


                            <p>

                                Create a new category for your food menu.

                            </p>


                        </div>



                        <!-- FORM -->

                        <form method="POST" action="../handlers/add_category.php" class="category-form"
                            autocomplete="off">


                            <!-- CATEGORY NAME -->

                            <div class="form-group">


                                <label class="form-label" for="name">

                                    Category Name

                                    <span>*</span>

                                </label>


                                <div class="field">


                                    <i class="fa-solid fa-layer-group"></i>


                                    <input type="text" id="name" name="name" placeholder="e.g. Rice Meals"
                                        maxlength="100" required>


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
                                        placeholder="Brief description of this category..." maxlength="255"></textarea>


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

                                        <option value="Active" selected>

                                            Active

                                        </option>


                                        <option value="Inactive">

                                            Inactive

                                        </option>

                                    </select>


                                </div>


                            </div>



                            <!-- INFORMATION -->

                            <div class="form-note">


                                <i class="fa-solid fa-circle-info"></i>


                                <span>

                                    Category names must be unique.
                                    You can deactivate a category
                                    without deleting it.

                                </span>


                            </div>



                            <!-- BUTTONS -->

                            <div class="form-footer">


                                <button type="reset" class="btn-clear">

                                    <i class="fa-solid fa-rotate-left"></i>

                                    Clear

                                </button>


                                <button type="submit" class="btn-save">

                                    <i class="fa-solid fa-folder-plus"></i>

                                    Add Category

                                </button>


                            </div>


                        </form>


                    </div>


                </article>



                <!-- =================================================
                 CATEGORY TABLE
            ================================================== -->

                <article class="panel table-panel">


                    <!-- TABLE HEADER -->

                    <div class="panel-header">


                        <div class="panel-header-icon">

                            <i class="fa-solid fa-list"></i>

                        </div>


                        <div>

                            <h3>

                                Current Categories

                            </h3>


                            <p>

                                Categories registered in the system.

                            </p>

                        </div>


                        <div class="table-count">

                            <strong>

                                <?= count($categories) ?>

                            </strong>


                            <span>

                                Categories

                            </span>

                        </div>


                    </div>



                    <!-- TABLE TOOLBAR -->

                    <div class="table-tools">


                        <div>

                            <strong>

                                Category List

                            </strong>


                            <small>

                                <?= count($categories) ?>

                                registered categor<?= count($categories) === 1
                                ? 'y'
                                : 'ies' ?>

                            </small>

                        </div>


                        <div class="table-search">


                            <i class="fa-solid fa-magnifying-glass"></i>


                            <input type="text" id="categorySearch" placeholder="Search categories..."
                                autocomplete="off">


                        </div>


                    </div>



                    <!-- TABLE -->

                    <div class="table-wrap">


                        <?php if (!empty($categories)): ?>


                        <table class="categories-table">


                            <thead>

                                <tr>

                                    <th>
                                        CATEGORY
                                    </th>


                                    <th>
                                        DESCRIPTION
                                    </th>


                                    <th>
                                        STATUS
                                    </th>


                                    <th>
                                        CREATED
                                    </th>


                                    <th>
                                        ACTION
                                    </th>

                                </tr>

                            </thead>


                            <tbody id="categoriesBody">


                                <?php foreach ($categories as $category): ?>


                                <?php

                                    $initial = strtoupper(
                                        substr(
                                            trim($category['name']),
                                            0,
                                            1
                                        )
                                    );


                                    $statusClass =
                                        $category['status'] === 'Active'
                                            ? 'active'
                                            : 'inactive';

                                    ?>


                                <tr>


                                    <!-- CATEGORY -->

                                    <td>


                                        <div class="category-cell">


                                            <div class="category-icon">

                                                <?= htmlspecialchars(
                                                        $initial
                                                    ) ?>

                                            </div>


                                            <div>


                                                <strong>

                                                    <?= htmlspecialchars(
                                                            $category['name']
                                                        ) ?>

                                                </strong>


                                                <small>

                                                    Category
                                                    #<?= (int) $category['id'] ?>

                                                </small>


                                            </div>


                                        </div>


                                    </td>



                                    <!-- DESCRIPTION -->

                                    <td>


                                        <div class="description">

                                            <?= htmlspecialchars(
                                                    $category['description']
                                                        ?: 'No description'
                                                ) ?>

                                        </div>


                                    </td>



                                    <!-- STATUS -->

                                    <td>


                                        <span class="category-status
                                                <?= $statusClass ?>">

                                            <?= htmlspecialchars(
                                                    $category['status']
                                                ) ?>

                                        </span>


                                    </td>



                                    <!-- CREATED -->

                                    <td>


                                        <span class="date">

                                            <?= htmlspecialchars(
                                                    date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $category['created_at']
                                                        )
                                                    )
                                                ) ?>

                                        </span>


                                    </td>



                                    <!-- ACTION -->

                                    <td>


                                        <div class="actions">


                                            <a href="#" class="action" title="Edit category">

                                                <i class="fa-solid fa-pen"></i>

                                            </a>


                                            <a href="#" class="action delete" title="Delete category">

                                                <i class="fa-solid fa-trash"></i>

                                            </a>


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

                                <i class="fa-solid fa-layer-group"></i>

                            </div>


                            <strong>

                                No categories yet

                            </strong>


                            <p>

                                Add your first food category using
                                the form. Categories will appear here
                                automatically after they are saved.

                            </p>


                        </div>


                        <?php endif; ?>


                    </div>


                </article>


            </section>


        </div>


    </main>



    <!-- =========================================================
     JAVASCRIPT
========================================================= -->

    <script>
    document.addEventListener(
        "DOMContentLoaded",
        function() {


            /*
            |--------------------------------------------------------------------------
            | CATEGORY NOTIFICATION
            |--------------------------------------------------------------------------
            */

            const message =
                document.getElementById(
                    "categoryMessage"
                );


            const closeMessage =
                document.getElementById(
                    "closeCategoryMessage"
                );


            function hideMessage() {

                if (!message) {
                    return;
                }


                message.classList.add(
                    "hide"
                );


                setTimeout(
                    function() {

                        if (message) {

                            message.remove();

                        }

                    },
                    350
                );

            }


            if (closeMessage) {

                closeMessage.addEventListener(
                    "click",
                    hideMessage
                );

            }


            if (message) {

                setTimeout(
                    hideMessage,
                    5000
                );

            }



            /*
            |--------------------------------------------------------------------------
            | CATEGORY SEARCH
            |--------------------------------------------------------------------------
            */

            const search =
                document.getElementById(
                    "categorySearch"
                );


            const body =
                document.getElementById(
                    "categoriesBody"
                );


            if (search && body) {

                search.addEventListener(
                    "input",
                    function() {


                        const value =
                            this.value
                            .toLowerCase()
                            .trim();


                        const rows =
                            body.querySelectorAll(
                                "tr"
                            );


                        rows.forEach(
                            function(row) {


                                const text =
                                    row.textContent
                                    .toLowerCase();


                                if (
                                    text.includes(
                                        value
                                    )
                                ) {

                                    row.style.display =
                                        "";

                                } else {

                                    row.style.display =
                                        "none";

                                }


                            }
                        );


                    }
                );

            }

        }
    );
    </script>


    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>