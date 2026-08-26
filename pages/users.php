<?php

session_start();

/*
|--------------------------------------------------------------------------
| USERS PAGE ACCESS PROTECTION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Administrator'
) {

    $_SESSION['login_message'] =
        'Please log in as an Administrator to access this page.';

    header('Location: ../index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once "../includes/db_connection.php";


/*
|--------------------------------------------------------------------------
| SESSION MESSAGE
|--------------------------------------------------------------------------
*/

$user_message = $_SESSION['user_message'] ?? null;

unset($_SESSION['user_message']);


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
| LOAD USERS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            full_name,
            username,
            email,
            phone,
            role,
            created_at
        FROM users
        ORDER BY id DESC
    ");

    $users = $stmt->fetchAll();

} catch (PDOException $e) {

    $users = [];

    if (!$user_message) {

        $user_message = [
            'type' => 'error',
            'message' => 'Unable to load users from the database.'
        ];

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Users | Better End</title>


    <!-- =====================================================
         POPPINS
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
    select {

        font-family: inherit;

    }


    a {
        text-decoration: none;
    }


    /* =========================================================
   MAIN
========================================================= */

    .users-main {

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

        gap: 15px;

    }


    .topbar-link {

        display: flex;

        align-items: center;

        gap: 8px;

        padding: 10px 13px;

        border-radius: 9px;

        color: #4b4540;

        font-size: 12px;

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

    .user-message {

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
            userMessageIn .3s ease both;

        transition:
            opacity .35s ease,
            transform .35s ease;

    }


    .user-message.success {

        color: #176b43;

        background: #f0fbf5;

        border-color: #bfe8d2;

    }


    .user-message.error {

        color: #a93434;

        background: #fff5f5;

        border-color: #f0c3c3;

    }


    .user-message-icon {

        width: 35px;

        height: 35px;

        flex: 0 0 35px;

        display: grid;

        place-items: center;

        border-radius: 50%;

        color: #ffffff;

    }


    .user-message.success .user-message-icon {

        background: var(--green);

    }


    .user-message.error .user-message-icon {

        background: var(--red);

    }


    .user-message-text {

        flex: 1;

        font-size: 12px;

        font-weight: 700;

        line-height: 1.5;

    }


    .user-message-close {

        width: 32px;

        height: 32px;

        display: grid;

        place-items: center;

        border: 0;

        border-radius: 8px;

        color: currentColor;

        background: transparent;

        cursor: pointer;

    }


    .user-message-close:hover {

        background: rgba(0, 0, 0, .05);

    }


    .user-message-progress {

        position: absolute;

        left: 0;

        bottom: 0;

        width: 100%;

        height: 3px;

        transform-origin: left;

        animation:
            userMessageProgress 5s linear forwards;

    }


    .user-message.success .user-message-progress {

        background: var(--green);

    }


    .user-message.error .user-message-progress {

        background: var(--red);

    }


    .user-message.hide {

        opacity: 0;

        transform: translateY(-8px);

    }


    @keyframes userMessageIn {

        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }

    }


    @keyframes userMessageProgress {

        from {
            transform: scaleX(1);
        }

        to {
            transform: scaleX(0);
        }

    }


    /* =========================================================
   PAGE INTRO
========================================================= */

    .users-intro {

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


    .users-intro::after {

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


    .users-intro>* {

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


    .users-intro h2 {

        margin: 0;

        color: #ffffff;

        font-size: 29px;

        font-weight: 800;

        letter-spacing: -.8px;

    }


    .users-intro p {

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

    .users-layout {

        display: grid;

        grid-template-columns:
            390px minmax(0, 1fr);

        gap: 22px;

        align-items: start;

    }


    .panel {

        overflow: hidden;

        border: 1px solid var(--border);

        border-radius: 20px;

        background: #ffffff;

        box-shadow:
            0 12px 35px rgba(38, 28, 21, .055);

    }


    /* =========================================================
   FORM
========================================================= */

    .form-panel {

        position: sticky;

        top: 104px;

    }


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

    }


    .form-top p {

        margin: 5px 0 0;

        color: #99918a;

        font-size: 10px;

    }


    /* =========================================================
   FORM BODY
========================================================= */

    .form-body {

        margin: 0 10px 10px;

        padding: 23px 20px 20px;

        border-radius: 17px;

        background: #f1f1f3;

    }


    .form-section-label {

        margin-bottom: 15px;

        padding-bottom: 9px;

        border-bottom: 1px solid #e4e1de;

        color: #8f8780;

        font-size: 11px;

        font-weight: 800;

        letter-spacing: 1.2px;

    }


    .form-group {

        margin-bottom: 15px;

    }


    .form-label {

        display: block;

        margin-bottom: 7px;

        color: #403a35;

        font-size: 12px;

        font-weight: 800;

    }


    .form-label span {

        color: var(--orange);

    }


    .field {

        height: 48px;

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
    .field select {

        width: 100%;

        min-width: 0;

        height: 100%;

        border: 0;

        outline: 0;

        color: #302b27;

        background: transparent;

        font-size: 12px;

        font-weight: 600;

    }


    .field input::placeholder {

        color: #aaa39d;

        font-size: 11px;

        font-weight: 500;

    }


    .select-field select {

        cursor: pointer;

    }


    /* =========================================================
   FORM NOTE
========================================================= */

    .form-note {

        display: flex;

        align-items: flex-start;

        gap: 8px;

        margin: 4px 0 18px;

        padding: 10px 11px;

        border: 1px solid #f2e2d5;

        border-radius: 11px;

        color: #8c827a;

        background: #fff8f2;

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

    }


    /* =========================================================
   TABLE
========================================================= */

    .table-panel {

        min-width: 0;

    }


    .table-panel-header {

        min-height: 82px;

        display: flex;

        align-items: center;

        gap: 13px;

        padding: 18px 22px;

        border-bottom: 1px solid #eeeae6;

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


    .table-panel-header h3 {

        margin: 0;

        color: #24201d;

        font-size: 16px;

        font-weight: 800;

    }


    .table-panel-header p {

        margin: 4px 0 0;

        color: #9a928b;

        font-size: 10px;

    }


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

        align-items: baseline;

        gap: 8px;

    }


    .table-tools-info strong {

        color: #3c3631;

        font-size: 12px;

        font-weight: 800;

    }


    .table-tools-info span {

        color: #a29a93;

        font-size: 9px;

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

    }


    /* =========================================================
   TABLE
========================================================= */

    .table-wrap {

        width: 100%;

        overflow-x: auto;

    }


    .users-table {

        width: 100%;

        min-width: 850px;

        border-collapse: separate;

        border-spacing: 0;

    }


    .users-table thead th {

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


    .users-table thead th:first-child {

        padding-left: 22px;

    }


    .users-table thead th:last-child {

        padding-right: 22px;

        text-align: right;

    }


    .users-table tbody tr {

        background: #ffffff;

        transition: .18s ease;

    }


    .users-table tbody tr:hover {

        background: #fffaf6;

    }


    .users-table tbody td {

        padding: 17px 18px;

        color: #655d56;

        border-bottom: 1px solid #f0ece8;

        font-size: 11px;

        vertical-align: middle;

    }


    .users-table tbody tr:last-child td {

        border-bottom: 0;

    }


    .users-table tbody td:first-child {

        padding-left: 22px;

    }


    .users-table tbody td:last-child {

        padding-right: 22px;

    }


    /* =========================================================
   USER CELL
========================================================= */

    .user-cell {

        min-width: 210px;

        display: flex;

        align-items: center;

        gap: 12px;

    }


    .user-avatar {

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

    }


    .user-cell strong {

        display: block;

        margin-bottom: 3px;

        color: #302b27;

        font-size: 12px;

        font-weight: 800;

    }


    .user-cell small {

        display: block;

        color: #aaa19a;

        font-size: 9px;

        font-weight: 500;

    }


    /* =========================================================
   USERNAME / EMAIL / PHONE
========================================================= */

    .username {

        color: #403a35;

        font-size: 11px;

        font-weight: 700;

    }


    .email {

        color: #817971;

        font-size: 10px;

    }


    .phone {

        color: #817971;

        font-size: 10px;

        white-space: nowrap;

    }


    /* =========================================================
   ROLE
========================================================= */

    .role {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        padding: 7px 11px;

        border-radius: 30px;

        font-size: 9px;

        font-weight: 800;

        white-space: nowrap;

    }


    .role.admin {

        color: #8b5a00;

        background: #fff3d3;

        border: 1px solid #f5e5b5;

    }


    .role.sales {

        color: #087944;

        background: #eaf8f0;

        border: 1px solid #d5f0e1;

    }


    /* =========================================================
   STATUS
========================================================= */

    .status {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        padding: 7px 11px;

        border-radius: 30px;

        color: #087944;

        background: #eaf8f0;

        border: 1px solid #d5f0e1;

        font-size: 9px;

        font-weight: 800;

    }


    .status::before {

        content: "";

        width: 6px;

        height: 6px;

        border-radius: 50%;

        background: var(--green);

        box-shadow:
            0 0 0 3px #dff4e9;

    }


    /* =========================================================
   DATE
========================================================= */

    .date {

        color: #918981;

        font-size: 10px;

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

        transition: .18s ease;

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

        .users-layout {

            grid-template-columns:
                350px minmax(0, 1fr);

        }

    }


    @media (max-width: 992px) {

        .users-main {

            margin-left: 0;

        }


        .users-layout {

            grid-template-columns: 1fr;

        }


        .form-panel {

            position: static;

            width: 100%;

            max-width: 620px;

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


        .topbar-link {

            display: none;

        }


        .top-profile div:not(.avatar) {

            display: none;

        }


        .users-intro {

            min-height: auto;

            padding: 23px;

            align-items: flex-start;

        }


        .users-intro h2 {

            font-size: 23px;

        }


        .users-intro p {

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


        .form-body {

            padding:
                20px 16px;

        }


        .table-panel-header {

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

    <main class="users-main">


        <!-- =====================================================
         TOP BAR
    ====================================================== -->

        <header class="topbar">


            <div class="topbar-title">

                <div class="topbar-icon">

                    <i class="fa-solid fa-users"></i>

                </div>


                <div>

                    <h1>
                        Users
                    </h1>

                    <p>
                        Manage restaurant staff accounts and access
                    </p>

                </div>

            </div>


            <div class="topbar-right">


                <a href="dashboard.php" class="topbar-link">

                    <i class="fa-solid fa-house"></i>

                    Dashboard

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
             ALERT
        ================================================== -->

            <?php if ($user_message): ?>


            <div id="userMessage" class="user-message
                <?= $user_message['type'] === 'success'
                    ? 'success'
                    : 'error' ?>" role="alert">


                <div class="user-message-icon">

                    <i class="fa-solid
                        <?= $user_message['type'] === 'success'
                            ? 'fa-check'
                            : 'fa-xmark' ?>"></i>

                </div>


                <div class="user-message-text">

                    <?= htmlspecialchars(
                        $user_message['message']
                    ) ?>

                </div>


                <button type="button" class="user-message-close" id="closeUserMessage" aria-label="Close notification">

                    <i class="fa-solid fa-xmark"></i>

                </button>


                <div class="user-message-progress"></div>


            </div>


            <?php endif; ?>



            <!-- =================================================
             PAGE INTRO
        ================================================== -->

            <section class="users-intro">


                <div>

                    <span class="eyebrow">

                        USER MANAGEMENT

                    </span>


                    <h2>

                        Manage Users

                    </h2>


                    <p>

                        Create staff accounts and manage access
                        to the Better End restaurant system.

                    </p>

                </div>


                <div class="intro-icon">

                    <i class="fa-solid fa-users-gear"></i>

                </div>


            </section>



            <!-- =================================================
             FORM + TABLE
        ================================================== -->

            <section class="users-layout">


                <!-- =================================================
                 ADD USER
            ================================================== -->

                <article class="panel form-panel">


                    <div class="form-top">


                        <div class="form-top-icon">

                            <i class="fa-solid fa-user-plus"></i>

                        </div>


                        <h3>

                            Add New User

                        </h3>


                        <p>

                            Create a new staff account.

                        </p>


                    </div>



                    <div class="form-body">


                        <form id="userForm" method="POST" action="../handlers/add_user.php" autocomplete="off">


                            <div class="form-section-label">

                                USER INFORMATION

                            </div>



                            <!-- FULL NAME -->

                            <div class="form-group">


                                <label class="form-label" for="full_name">

                                    Full Name

                                    <span>*</span>

                                </label>


                                <div class="field">

                                    <i class="fa-regular fa-user"></i>


                                    <input type="text" id="full_name" name="full_name" placeholder="Enter full name"
                                        required>

                                </div>


                            </div>



                            <!-- USERNAME -->

                            <div class="form-group">


                                <label class="form-label" for="username">

                                    Username

                                    <span>*</span>

                                </label>


                                <div class="field">

                                    <i class="fa-solid fa-at"></i>


                                    <input type="text" id="username" name="username" placeholder="Enter username"
                                        required>

                                </div>


                            </div>



                            <!-- EMAIL -->

                            <div class="form-group">


                                <label class="form-label" for="email">

                                    Email Address

                                    <span>*</span>

                                </label>


                                <div class="field">

                                    <i class="fa-regular fa-envelope"></i>


                                    <input type="email" id="email" name="email" placeholder="name@example.com" required>

                                </div>


                            </div>



                            <!-- PHONE -->

                            <div class="form-group">


                                <label class="form-label" for="phone">

                                    Phone Number

                                    <span>*</span>

                                </label>


                                <div class="field">

                                    <i class="fa-solid fa-phone"></i>


                                    <input type="tel" id="phone" name="phone" placeholder="e.g. 024 000 0000" required>

                                </div>


                            </div>



                            <!-- ROLE -->

                            <div class="form-group">


                                <label class="form-label" for="role">

                                    User Role

                                    <span>*</span>

                                </label>


                                <div class="field">

                                    <i class="fa-solid fa-user-tag"></i>


                                    <select id="role" name="role" required>

                                        <option value="" selected disabled>

                                            Select user role

                                        </option>


                                        <option value="Administrator">

                                            Administrator

                                        </option>


                                        <option value="Salesperson">

                                            Salesperson

                                        </option>

                                    </select>


                                </div>


                            </div>



                            <!-- INFORMATION -->

                            <div class="form-note">


                                <i class="fa-solid fa-circle-info"></i>


                                <span>

                                    The username will be used as the
                                    initial password and securely hashed
                                    before it is stored.

                                </span>


                            </div>



                            <!-- BUTTONS -->

                            <div class="form-footer">


                                <button type="reset" class="btn-clear">

                                    <i class="fa-solid fa-rotate-left"></i>

                                    Clear

                                </button>


                                <button type="submit" class="btn-save">

                                    <i class="fa-solid fa-user-plus"></i>

                                    Add User

                                </button>


                            </div>


                        </form>


                    </div>


                </article>



                <!-- =================================================
                 USERS TABLE
            ================================================== -->

                <article class="panel table-panel">


                    <!-- TABLE HEADER -->

                    <div class="table-panel-header">


                        <div class="panel-header-icon">

                            <i class="fa-solid fa-users"></i>

                        </div>


                        <div>

                            <h3>

                                Current Users

                            </h3>


                            <p>

                                Users registered in the system.

                            </p>

                        </div>


                        <div class="table-count">

                            <strong>

                                <?= count($users) ?>

                            </strong>


                            <span>

                                Users

                            </span>

                        </div>


                    </div>



                    <!-- TABLE TOOLS -->

                    <div class="table-tools">


                        <div class="table-tools-info">

                            <strong>

                                User List

                            </strong>


                            <span>

                                <?= count($users) ?>

                                registered user<?= count($users) === 1
                                ? ''
                                : 's' ?>

                            </span>

                        </div>


                        <div class="table-search">

                            <i class="fa-solid fa-magnifying-glass"></i>


                            <input type="text" id="userSearch" placeholder="Search users..." autocomplete="off">

                        </div>


                    </div>



                    <!-- TABLE -->

                    <div class="table-wrap">


                        <?php if (!empty($users)): ?>


                        <table class="users-table">


                            <thead>

                                <tr>

                                    <th>
                                        FULL NAME
                                    </th>

                                    <th>
                                        USERNAME
                                    </th>

                                    <th>
                                        EMAIL
                                    </th>

                                    <th>
                                        PHONE
                                    </th>

                                    <th>
                                        ROLE
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


                            <tbody id="usersBody">


                                <?php foreach ($users as $user): ?>


                                <?php

                                    $initial = strtoupper(
                                        substr(
                                            trim(
                                                $user['full_name']
                                            ),
                                            0,
                                            1
                                        )
                                    );


                                    $roleClass =
                                        $user['role'] === 'Administrator'
                                            ? 'admin'
                                            : 'sales';

                                    ?>


                                <tr>


                                    <!-- NAME -->

                                    <td>

                                        <div class="user-cell">


                                            <div class="user-avatar">

                                                <?= htmlspecialchars(
                                                        $initial
                                                    ) ?>

                                            </div>


                                            <div>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                            $user['full_name']
                                                        ) ?>

                                                </strong>


                                                <small>

                                                    User #<?= (int) $user['id'] ?>

                                                </small>

                                            </div>


                                        </div>

                                    </td>



                                    <!-- USERNAME -->

                                    <td>

                                        <span class="username">

                                            <?= htmlspecialchars(
                                                    $user['username']
                                                ) ?>

                                        </span>

                                    </td>



                                    <!-- EMAIL -->

                                    <td>

                                        <span class="email">

                                            <?= htmlspecialchars(
                                                    $user['email']
                                                ) ?>

                                        </span>

                                    </td>



                                    <!-- PHONE -->

                                    <td>

                                        <span class="phone">

                                            <?= htmlspecialchars(
                                                    $user['phone']
                                                ) ?>

                                        </span>

                                    </td>



                                    <!-- ROLE -->

                                    <td>

                                        <span class="role <?= $roleClass ?>">

                                            <?= htmlspecialchars(
                                                    $user['role']
                                                ) ?>

                                        </span>

                                    </td>



                                    <!-- STATUS -->

                                    <td>

                                        <span class="status">

                                            Active

                                        </span>

                                    </td>



                                    <!-- CREATED -->

                                    <td>

                                        <span class="date">

                                            <?= htmlspecialchars(
                                                    date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $user['created_at']
                                                        )
                                                    )
                                                ) ?>

                                        </span>

                                    </td>



                                    <!-- ACTIONS -->

                                    <td>


                                        <div class="actions">


                                            <button type="button" class="action" title="Edit user">

                                                <i class="fa-solid fa-pen"></i>

                                            </button>


                                            <button type="button" class="action delete" title="Delete user">

                                                <i class="fa-solid fa-trash"></i>

                                            </button>


                                        </div>


                                    </td>


                                </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                        <?php else: ?>


                        <div class="empty-state">


                            <div class="empty-icon">

                                <i class="fa-solid fa-users-slash"></i>

                            </div>


                            <strong>

                                No users yet

                            </strong>


                            <p>

                                Add your first user using the form.
                                Registered users will appear here
                                automatically.

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
            | AUTO-HIDE ALERT
            |--------------------------------------------------------------------------
            */

            const message =
                document.getElementById(
                    "userMessage"
                );


            const closeButton =
                document.getElementById(
                    "closeUserMessage"
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


            if (closeButton) {

                closeButton.addEventListener(
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
            | USER SEARCH
            |--------------------------------------------------------------------------
            */

            const search =
                document.getElementById(
                    "userSearch"
                );


            const usersBody =
                document.getElementById(
                    "usersBody"
                );


            if (search && usersBody) {

                search.addEventListener(
                    "input",
                    function() {


                        const searchValue =
                            this.value
                            .toLowerCase()
                            .trim();


                        const rows =
                            usersBody.querySelectorAll(
                                "tr"
                            );


                        rows.forEach(
                            function(row) {


                                const rowText =
                                    row.textContent
                                    .toLowerCase();


                                if (
                                    rowText.includes(
                                        searchValue
                                    )
                                ) {

                                    row.style.display = "";

                                } else {

                                    row.style.display = "none";

                                }


                            }
                        );

                    }
                );

            }

        }
    );
    </script>


</body>

</html>