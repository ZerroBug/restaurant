<?php
session_start();

$login_message = $_SESSION['login_message'] ?? null;
unset($_SESSION['login_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Better End</title>

    <!-- Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    :root {
        --orange: #f58220;
        --orange-dark: #df6810;
        --orange-light: #fff1e6;

        --black: #090909;
        --black-soft: #151515;

        --text: #28231f;
        --muted: #817870;

        --border: #e3dbd3;

        --white: #ffffff;
        --background: #f5f3f0;

        --green: #1fa463;
        --red: #d94b4b;
    }


    /* =========================================================
   RESET
========================================================= */

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }


    html,
    body {
        min-height: 100%;
    }


    body {
        min-height: 100vh;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 24px;

        color: var(--text);

        background:
            radial-gradient(circle at 8% 8%,
                rgba(245, 130, 32, .13),
                transparent 25%),
            radial-gradient(circle at 92% 92%,
                rgba(245, 130, 32, .08),
                transparent 28%),
            #f5f3f0;

        font-family: "Poppins", sans-serif;
    }


    button,
    input {
        font-family: inherit;
    }


    button {
        cursor: pointer;
    }


    a {
        text-decoration: none;
    }


    /* =========================================================
   LOGIN SHELL
========================================================= */

    .login-shell {

        width: min(100%, 820px);

        min-height: 510px;

        display: grid;

        grid-template-columns: .92fr 1.08fr;

        overflow: hidden;

        border: 1px solid rgba(20, 20, 20, .07);

        border-radius: 22px;

        background: #ffffff;

        box-shadow:
            0 28px 65px rgba(38, 28, 21, .12),
            0 8px 22px rgba(38, 28, 21, .05);

    }


    /* =========================================================
   BRAND PANEL
========================================================= */

    .brand-panel {

        position: relative;

        overflow: hidden;

        display: flex;

        flex-direction: column;

        justify-content: space-between;

        padding: 32px;

        color: #ffffff;

        background:
            radial-gradient(circle at 82% 12%,
                rgba(245, 130, 32, .23),
                transparent 29%),
            radial-gradient(circle at 5% 90%,
                rgba(245, 130, 32, .10),
                transparent 32%),
            linear-gradient(145deg,
                #070707,
                #171717 58%,
                #0b0b0b);
    }


    .brand-panel::before {

        content: "";

        position: absolute;

        width: 230px;
        height: 230px;

        right: -105px;
        bottom: -105px;

        border: 1px solid rgba(245, 130, 32, .16);

        border-radius: 50%;

        box-shadow:
            0 0 0 30px rgba(245, 130, 32, .035),
            0 0 0 60px rgba(245, 130, 32, .025);
    }


    .brand-panel::after {

        content: "";

        position: absolute;

        width: 150px;
        height: 150px;

        left: -90px;
        top: 42%;

        border-radius: 50%;

        background: rgba(245, 130, 32, .07);

        filter: blur(30px);
    }


    .brand-panel>* {
        position: relative;
        z-index: 2;
    }


    /* =========================================================
   BRAND
========================================================= */

    .brand {

        display: flex;

        align-items: center;

        gap: 11px;
    }


    .brand-logo {

        width: 43px;
        height: 43px;

        display: grid;

        place-items: center;

        border-radius: 12px;

        color: #ffffff;

        background: var(--orange);

        box-shadow:
            0 9px 20px rgba(245, 130, 32, .25);

        font-size: 17px;
    }


    .brand-name {

        color: #ffffff;

        font-size: 19px;

        line-height: 1.1;

        font-weight: 800;

        letter-spacing: -.5px;
    }


    .brand-name span {
        color: var(--orange);
    }


    .brand-subtitle {

        margin-top: 3px;

        color: #929292;

        font-size: 9px;

        font-weight: 500;

        letter-spacing: .7px;

        text-transform: uppercase;
    }


    /* =========================================================
   BRAND CONTENT
========================================================= */

    .brand-content {

        max-width: 280px;
    }


    .brand-content .eyebrow {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        margin-bottom: 13px;

        color: var(--orange);

        font-size: 9px;

        font-weight: 800;

        letter-spacing: 1.5px;
    }


    .brand-content .eyebrow::before {

        content: "";

        width: 19px;
        height: 2px;

        border-radius: 5px;

        background: var(--orange);
    }


    .brand-content h1 {

        color: #ffffff;

        font-size: 28px;

        line-height: 1.18;

        font-weight: 800;

        letter-spacing: -.8px;
    }


    .brand-content h1 span {
        color: var(--orange);
    }


    .brand-content p {

        margin-top: 12px;

        color: #aaa;

        font-size: 11px;

        line-height: 1.7;
    }


    /* =========================================================
   FEATURES
========================================================= */

    .brand-features {

        display: grid;

        gap: 8px;

        margin-top: 20px;
    }


    .brand-feature {

        display: flex;

        align-items: center;

        gap: 9px;

        color: #c8c4c0;

        font-size: 10px;

        font-weight: 600;
    }


    .feature-icon {

        width: 27px;
        height: 27px;

        flex: 0 0 27px;

        display: grid;

        place-items: center;

        border: 1px solid rgba(245, 130, 32, .20);

        border-radius: 8px;

        color: var(--orange);

        background: rgba(245, 130, 32, .08);

        font-size: 9px;
    }


    .brand-footer {

        color: #666;

        font-size: 8px;

        font-weight: 500;
    }


    /* =========================================================
   LOGIN PANEL
========================================================= */

    .login-panel {

        display: flex;

        align-items: center;

        justify-content: center;

        padding: 34px 48px;

        background: #ffffff;
    }


    .login-box {

        width: min(100%, 350px);
    }


    .mobile-brand {
        display: none;
    }


    /* =========================================================
   LOGIN HEADING
========================================================= */

    .login-heading {

        margin-bottom: 23px;
    }


    .login-heading .small-title {

        margin-bottom: 6px;

        color: var(--orange-dark);

        font-size: 10px;

        font-weight: 800;

        letter-spacing: 1.4px;

        text-transform: uppercase;
    }


    .login-heading h2 {

        color: #25211e;

        font-size: 25px;

        line-height: 1.2;

        font-weight: 800;

        letter-spacing: -.6px;
    }


    .login-heading p {

        margin-top: 7px;

        color: var(--muted);

        font-size: 11px;

        line-height: 1.6;
    }


    /* =========================================================
   FORM
========================================================= */

    .form-group {

        margin-bottom: 15px;
    }


    .form-label {

        display: block;

        margin-bottom: 7px;

        color: #3f3832;

        font-size: 12px;

        font-weight: 800;
    }


    /* =========================================================
   INPUT FIELD
========================================================= */

    .input-field {

        position: relative;

        height: 54px;

        display: flex;

        align-items: center;

        gap: 11px;

        padding: 0 14px;

        border: 1.5px solid #ddd3ca;

        border-radius: 12px;

        background:
            linear-gradient(180deg,
                #fffdfb,
                #fff9f5);

        box-shadow:
            0 4px 12px rgba(38, 28, 21, .035);

        transition:
            border-color .2s ease,
            box-shadow .2s ease,
            transform .2s ease;
    }


    .input-field:hover {

        border-color: #d0c3b9;

    }


    .input-field:focus-within {

        border-color: var(--orange);

        background: #ffffff;

        box-shadow:
            0 0 0 4px rgba(245, 130, 32, .10),
            0 7px 18px rgba(245, 130, 32, .07);

        transform: translateY(-1px);
    }


    /* =========================================================
   INPUT ICON
========================================================= */

    .input-icon {

        width: 19px;

        flex: 0 0 19px;

        color: #a49a92;

        text-align: center;

        font-size: 14px;

        transition: .2s ease;
    }


    .input-field:focus-within .input-icon {

        color: var(--orange);
    }


    /* =========================================================
   INPUT
========================================================= */

    .input-field input {

        width: 100%;

        height: 100%;

        min-width: 0;

        border: 0;

        outline: 0;

        color: #302a25;

        background: transparent;

        font-size: 13px;

        font-weight: 600;
    }


    .input-field input::placeholder {

        color: #aaa19a;

        font-size: 12px;

        font-weight: 500;
    }


    /* =========================================================
   PASSWORD TOGGLE
========================================================= */

    .password-toggle {

        width: 30px;
        height: 30px;

        display: grid;

        place-items: center;

        flex: 0 0 30px;

        border: 0;

        border-radius: 8px;

        color: #9d938b;

        background: transparent;

        font-size: 13px;

        transition: .2s ease;
    }


    .password-toggle:hover {

        color: var(--orange);

        background: var(--orange-light);
    }


    /* =========================================================
   OPTIONS
========================================================= */

    .login-options {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 10px;

        margin: 2px 0 18px;
    }


    .remember {

        display: flex;

        align-items: center;

        gap: 7px;

        color: #6f665f;

        font-size: 10px;

        font-weight: 600;

        cursor: pointer;
    }


    .remember input {

        width: 15px;
        height: 15px;

        accent-color: var(--orange);

        cursor: pointer;
    }


    .forgot {
        color: var(--orange-dark);
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }


    .forgot:hover {

        color: var(--orange);

        text-decoration: underline;
    }


    /* =========================================================
   LOGIN BUTTON
========================================================= */

    .login-button {

        width: 100%;

        height: 53px;

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 9px;

        border: 0;

        border-radius: 12px;

        color: #ffffff;

        background:
            linear-gradient(135deg,
                #f58220,
                #e76b12);

        box-shadow:
            0 10px 22px rgba(245, 130, 32, .23);

        font-size: 13px;

        font-weight: 800;

        transition: .2s ease;
    }


    .login-button:hover {

        transform: translateY(-2px);

        background:
            linear-gradient(135deg,
                #ff9138,
                #df610d);

        box-shadow:
            0 14px 28px rgba(245, 130, 32, .27);
    }


    .login-button:active {

        transform: translateY(0);
    }


    /* =========================================================
   SECURITY NOTE
========================================================= */

    .secure-note {

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 6px;

        margin-top: 14px;

        color: #968d85;

        font-size: 9px;

        font-weight: 500;
    }


    .secure-note i {

        color: var(--green);

        font-size: 10px;
    }


    /* =========================================================
   DIVIDER
========================================================= */

    .login-divider {

        display: flex;

        align-items: center;

        gap: 10px;

        margin: 21px 0 14px;

        color: #aaa19a;

        font-size: 8px;

        font-weight: 700;
    }


    .login-divider::before,
    .login-divider::after {

        content: "";

        height: 1px;

        flex: 1;

        background: #eee9e5;
    }


    /* =========================================================
   HELP
========================================================= */

    .help-text {

        text-align: center;

        color: #938a82;

        font-size: 9px;

        line-height: 1.6;
    }


    .help-text strong {

        color: #5b534c;
    }


    /* =========================================================
   LOGIN ALERT
========================================================= */

    .login-alert {

        position: relative;

        overflow: hidden;

        display: flex;

        align-items: center;

        gap: 10px;

        width: 100%;

        margin: 0 0 16px;

        padding: 11px 12px;

        border: 1px solid #efc4c4;

        border-radius: 11px;

        color: #a83232;

        background: #fff6f6;

        box-shadow:
            0 7px 18px rgba(80, 30, 30, .07);

        font-size: 11px;

        font-weight: 600;

        animation:
            loginAlertIn .35s ease forwards;
    }


    .login-alert-icon {

        width: 32px;
        height: 32px;

        flex: 0 0 32px;

        display: grid;

        place-items: center;

        border-radius: 50%;

        color: #ffffff;

        background: #d94b4b;

        font-size: 12px;
    }


    .login-alert-message {

        flex: 1;

        line-height: 1.5;
    }


    .login-alert-close {

        width: 27px;
        height: 27px;

        display: grid;

        place-items: center;

        border: 0;

        border-radius: 7px;

        color: #a83232;

        background: transparent;

        cursor: pointer;

        opacity: .65;

        transition: .2s ease;
    }


    .login-alert-close:hover {

        opacity: 1;

        background: rgba(217, 75, 75, .08);
    }


    .login-alert-progress {

        position: absolute;

        left: 0;
        bottom: 0;

        width: 100%;
        height: 3px;

        background: #d94b4b;

        transform-origin: left;

        animation:
            loginAlertProgress 5s linear forwards;
    }


    .login-alert.hide {

        animation:
            loginAlertOut .35s ease forwards;
    }


    /* =========================================================
   ANIMATIONS
========================================================= */

    @keyframes loginAlertIn {

        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }

    }


    @keyframes loginAlertOut {

        from {
            opacity: 1;
            transform: translateY(0);
        }

        to {
            opacity: 0;
            transform: translateY(-7px);
        }

    }


    @keyframes loginAlertProgress {

        from {
            transform: scaleX(1);
        }

        to {
            transform: scaleX(0);
        }

    }


    /* =========================================================
       RESPONSIVE LAYOUT
    ========================================================= */

    html,
    body {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }

    img,
    svg {
        max-width: 100%;
    }

    @media (max-width: 820px) {

        body {
            min-height: 100dvh;
            display: block;
            padding: 16px;
        }

        .login-shell {
            width: 100%;
            max-width: 470px;
            min-height: 0;
            margin: 0 auto;
            display: block;
            border-radius: 19px;
        }

        .brand-panel {
            display: none;
        }

        .login-panel {
            width: 100%;
            min-height: 0;
            padding: 34px 30px;
        }

        .login-box {
            width: 100%;
            max-width: 390px;
            margin: 0 auto;
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-bottom: 25px;
        }

        .mobile-brand .brand-logo {
            width: 39px;
            height: 39px;
            flex: 0 0 39px;
            border-radius: 11px;
            font-size: 15px;
        }

        .mobile-brand .brand-name {
            color: #24201d;
            font-size: 18px;
        }

        .login-heading h2 {
            font-size: clamp(22px, 6vw, 25px);
        }

        .login-heading p {
            font-size: 10px;
        }

        .input-field {
            width: 100%;
        }

        .login-options {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 480px) {

        body {
            padding: 10px;
        }

        .login-shell {
            width: 100%;
            border-radius: 16px;
        }

        .login-panel {
            padding: 25px 18px 28px;
        }

        .login-box {
            max-width: none;
        }

        .mobile-brand {
            margin-bottom: 21px;
        }

        .mobile-brand .brand-logo {
            width: 36px;
            height: 36px;
            flex-basis: 36px;
        }

        .mobile-brand .brand-name {
            font-size: 17px;
        }

        .login-heading {
            margin-bottom: 20px;
        }

        .login-heading h2 {
            font-size: 22px;
            letter-spacing: -.4px;
        }

        .login-heading p {
            font-size: 10px;
        }

        .form-group {
            margin-bottom: 13px;
        }

        .form-label {
            font-size: 11px;
        }

        .input-field {
            height: 50px;
            padding: 0 12px;
        }

        .input-field input {
            font-size: 12px;
        }

        .input-field input::placeholder {
            font-size: 11px;
        }

        .login-options {
            align-items: flex-start;
            flex-direction: column;
            gap: 9px;
            margin-bottom: 16px;
        }

        .login-button {
            height: 50px;
            font-size: 12px;
        }

        .secure-note {
            font-size: 8px;
            text-align: center;
        }

        .help-text {
            font-size: 8px;
        }

        .login-alert {
            align-items: flex-start;
            font-size: 10px;
        }

        .login-alert-icon {
            width: 29px;
            height: 29px;
            flex-basis: 29px;
        }
    }

    @media (max-width: 360px) {

        body {
            padding: 7px;
        }

        .login-panel {
            padding: 22px 14px 24px;
        }

        .mobile-brand .brand-name {
            font-size: 16px;
        }

        .login-heading h2 {
            font-size: 20px;
        }

        .login-heading p {
            font-size: 9px;
        }

        .input-field {
            height: 48px;
            gap: 8px;
        }

        .input-field input {
            font-size: 11px;
        }

        .remember,
        .forgot {
            font-size: 9px;
        }

        .login-button {
            height: 48px;
        }

        .secure-note {
            align-items: flex-start;
            font-size: 7px;
        }

        .login-divider {
            margin-top: 18px;
        }
    }

    @media (max-width: 820px) and (orientation: landscape) {

        body {
            display: block;
            padding: 12px;
        }

        .login-shell {
            max-width: 620px;
        }

        .login-panel {
            padding: 22px 30px;
        }

        .mobile-brand {
            margin-bottom: 14px;
        }

        .login-heading {
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 10px;
        }

        .secure-note {
            margin-top: 10px;
        }

        .login-divider {
            margin: 14px 0 10px;
        }
    }
    </style>
</head>

<body>

    <main class="login-shell">

        <!-- BRAND / INFORMATION PANEL -->
        <section class="brand-panel">

            <div class="brand">
                <div class="brand-logo">
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <div>
                    <div class="brand-name">
                        Better<span> End</span>
                    </div>

                    <div class="brand-subtitle">
                        RESTAURANT
                    </div>
                </div>
            </div>


            <div class="brand-content">

                <div class="eyebrow">
                    RESTAURANT MANAGEMENT
                </div>

                <h1>
                    Everything your
                    <span>food point</span>
                    needs.
                </h1>

                <p>
                    Manage orders, sales, customers, food menus and
                    your restaurant team from one simple workspace.
                </p>


                <div class="brand-features">

                    <div class="brand-feature">
                        <span class="feature-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </span>
                        Daily sales and business insights
                    </div>

                    <div class="brand-feature">
                        <span class="feature-icon">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        Simple and fast order management
                    </div>

                    <div class="brand-feature">
                        <span class="feature-icon">
                            <i class="fa-solid fa-users"></i>
                        </span>
                        Manage your restaurant team
                    </div>

                </div>

            </div>


            <div class="brand-footer">
                © <?php echo date('Y'); ?> Better End. All rights reserved.
            </div>

        </section>


        <!-- LOGIN PANEL -->
        <section class="login-panel">

            <div class="login-box">

                <!-- Mobile-only brand -->
                <div class="mobile-brand">

                    <div class="brand-logo">
                        <i class="fa-solid fa-utensils"></i>
                    </div>

                    <div class="brand-name">
                        Better<span> End</span>
                    </div>

                </div>


                <div class="login-heading">

                    <div class="small-title">
                        Welcome Back
                    </div>

                    <h2>
                        Sign in to your account
                    </h2>

                    <p>
                        Enter your details to access your restaurant dashboard.
                    </p>

                </div>


                <!-- LOGIN FORM -->
                <form method="POST" action="./handlers/login.php" autocomplete="off">
                    <?php if ($login_message): ?>

                    <div class="login-alert" id="loginAlert" role="alert">

                        <div class="login-alert-icon">
                            <i class="fa-solid fa-xmark"></i>
                        </div>

                        <div class="login-alert-message">
                            <?= htmlspecialchars($login_message) ?>
                        </div>

                        <button type="button" class="login-alert-close" onclick="closeLoginAlert()"
                            aria-label="Close notification">
                            <i class="fa-solid fa-xmark"></i>
                        </button>

                        <div class="login-alert-progress"></div>

                    </div>

                    <?php endif; ?>
                    <div class="form-group">

                        <label class="form-label" for="username">
                            Username
                        </label>

                        <div class="input-field">

                            <i class="fa-solid fa-user input-icon"></i>

                            <input type="text" id="username" name="username" placeholder="Enter your username"
                                autocomplete="username" required>

                        </div>

                    </div>


                    <div class="form-group">

                        <label class="form-label" for="password">
                            Password
                        </label>

                        <div class="input-field">

                            <i class="fa-solid fa-lock input-icon"></i>

                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                autocomplete="current-password" required>

                            <button type="button" class="password-toggle" id="passwordToggle"
                                aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>

                        </div>

                    </div>


                    <div class="login-options">

                        <label class="remember">
                            <input type="checkbox" name="remember" value="1">
                            Remember me
                        </label>

                        <a href="#" class="forgot">
                            Forgot password?
                        </a>

                    </div>


                    <button type="submit" class="login-button">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Sign In
                    </button>

                </form>


                <div class="secure-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Your account information is securely protected.
                </div>


                <div class="login-divider">
                    <span>BETTER END</span>
                </div>


                <p class="help-text">
                    Need help accessing your account?
                    <strong>Contact your administrator.</strong>
                </p>

            </div>

        </section>

    </main>


    <script>
    const passwordInput = document.getElementById("password");
    const passwordToggle = document.getElementById("passwordToggle");

    passwordToggle.addEventListener("click", function() {

        const isPassword = passwordInput.type === "password";

        passwordInput.type = isPassword ?
            "text" :
            "password";

        this.innerHTML = isPassword ?
            '<i class="fa-regular fa-eye-slash"></i>' :
            '<i class="fa-regular fa-eye"></i>';

        this.setAttribute(
            "aria-label",
            isPassword ? "Hide password" : "Show password"
        );
    });
    </script>

    <script>
    function closeLoginAlert() {

        const alert = document.getElementById('loginAlert');

        if (!alert) {
            return;
        }

        alert.classList.add('hide');

        setTimeout(() => {
            alert.remove();
        }, 350);
    }


    /*
    |--------------------------------------------------------------------------
    | Automatically fade the alert after 5 seconds
    |--------------------------------------------------------------------------
    */

    document.addEventListener('DOMContentLoaded', function() {

        const alert = document.getElementById('loginAlert');

        if (!alert) {
            return;
        }

        setTimeout(() => {

            if (!alert.classList.contains('hide')) {

                alert.classList.add('hide');

                setTimeout(() => {
                    alert.remove();
                }, 350);

            }

        }, 5000);

    });
    </script>

</body>

</html>