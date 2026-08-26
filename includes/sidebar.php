<?php
/*
|--------------------------------------------------------------------------
| REUSABLE ROLE-BASED SIDEBAR
|--------------------------------------------------------------------------
| Include this file after session_start().
|
| The Reset Password link opens a modal. The actual password change is
| performed by ../handlers/reset_password.php.
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';

$isAdmin = ($role === 'Administrator');

$isSalesperson = in_array(
    $role,
    ['Salesperson', 'Sales Person'],
    true
);

$dashboardPage = $isSalesperson
    ? 'sales_dashboard.php'
    : 'dashboard.php';

$isDashboard =
    $currentPage === 'dashboard.php' ||
    $currentPage === 'sales_dashboard.php';
?>

<aside class="sidebar" id="mainSidebar">

    <div class="sidebar-brand">
        <div class="brand-logo">
            <i class="fa-solid fa-utensils"></i>
        </div>

        <div class="brand-copy">
            <strong>BETTER END</strong>
            <strong>RESTAURANT</strong>
            <small>Restaurant Management</small>
        </div>
    </div>

    <?php if ($isAdmin): ?>

    <div class="sidebar-label">Administration</div>

    <nav class="sidebar-nav">

        <a href="dashboard.php" class="<?= $isDashboard ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>

        <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>Orders</span>
        </a>

        <a href="food_menu.php" class="<?= $currentPage === 'food_menu.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-bowl-food"></i>
            <span>Food Menu</span>
        </a>

        <a href="category.php" class="<?= $currentPage === 'category.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-layer-group"></i>
            <span>Categories</span>
        </a>



        <a href="sales.php" class="<?= $currentPage === 'sales.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>Sales</span>
        </a>

        <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            <span>Users</span>
        </a>

    </nav>

    <?php elseif ($isSalesperson): ?>

    <div class="sidebar-label">Sales Workspace</div>

    <nav class="sidebar-nav">

        <a href="sales_dashboard.php" class="<?= $isDashboard ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>

        <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-plus"></i>
            <span>Place Order</span>
        </a>

        <a href="my_orders.php" class="<?= $currentPage === 'my_orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt"></i>
            <span>My Orders</span>
        </a>

    </nav>

    <?php endif; ?>

    <div class="sidebar-label account-label">Account</div>

    <nav class="sidebar-nav">

        <!-- RESET PASSWORD -->
        <a href="#" id="openPasswordFromNav">
            <i class="fa-solid fa-key"></i>
            <span>Reset Password</span>
        </a>

        <!-- SIGN OUT -->
        <a href="../handlers/logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Sign Out</span>
        </a>

    </nav>

    <div class="sidebar-user">

        <div class="sidebar-user-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="sidebar-user-copy">

            <strong>
                <?= htmlspecialchars(
                    $isAdmin ? 'Administrator' : 'Sales Person',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <small>
                <?= $isAdmin
                    ? 'Full system access'
                    : 'Sales access only'
                ?>
            </small>

        </div>

    </div>

</aside>


<!-- =========================================================
     RESET PASSWORD MODAL
========================================================== -->

<div class="password-modal" id="passwordModal" aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="passwordModalTitle">

    <div class="password-box">

        <button type="button" class="password-close" id="closePassword" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="password-modal-icon">
            <i class="fa-solid fa-key"></i>
        </div>

        <h3 id="passwordModalTitle">
            Reset Your Password
        </h3>

        <p>
            Enter your current password, then choose your new password.
        </p>

        <div id="passwordMessage" class="password-message" role="alert" aria-live="polite"></div>

        <form id="passwordForm" autocomplete="off">

            <div class="password-field">

                <label for="currentPassword">
                    Current Password
                </label>

                <div class="password-input-wrap">

                    <i class="fa-solid fa-lock"></i>

                    <input type="password" id="currentPassword" name="current_password" autocomplete="current-password"
                        required>

                    <button type="button" class="toggle-password" data-target="currentPassword"
                        aria-label="Show current password">
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

            </div>


            <div class="password-field">

                <label for="newPassword">
                    New Password
                </label>

                <div class="password-input-wrap">

                    <i class="fa-solid fa-key"></i>

                    <input type="password" id="newPassword" name="new_password" minlength="8"
                        autocomplete="new-password" required>

                    <button type="button" class="toggle-password" data-target="newPassword"
                        aria-label="Show new password">
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

                <small class="password-help">
                    Use at least 8 characters.
                </small>

            </div>


            <div class="password-field">

                <label for="confirmPassword">
                    Confirm New Password
                </label>

                <div class="password-input-wrap">

                    <i class="fa-solid fa-shield-halved"></i>

                    <input type="password" id="confirmPassword" name="confirm_password" minlength="8"
                        autocomplete="new-password" required>

                    <button type="button" class="toggle-password" data-target="confirmPassword"
                        aria-label="Show password confirmation">
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

            </div>


            <div class="password-actions">

                <button type="button" class="password-cancel" id="cancelPassword">
                    Cancel
                </button>

                <button type="submit" class="password-save" id="savePassword">
                    <i class="fa-solid fa-lock"></i>
                    Update Password
                </button>

            </div>

        </form>

    </div>

</div>


<style>
/* =========================================================
   RESET PASSWORD MODAL
========================================================= */

.password-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 12, 10, .58);
    backdrop-filter: blur(5px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition:
        opacity .2s ease,
        visibility .2s ease;
}

.password-modal.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.password-box {
    position: relative;
    width: min(100%, 430px);
    padding: 28px;
    border: 1px solid #ebe5df;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 25px 70px rgba(0, 0, 0, .22);
    transform: translateY(12px) scale(.98);
    transition: transform .22s ease;
}

.password-modal.show .password-box {
    transform: translateY(0) scale(1);
}

.password-close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 9px;
    color: #8e857e;
    background: #f7f5f3;
    cursor: pointer;
}

.password-close:hover {
    color: #d94b4b;
    background: #fff0f0;
}

.password-modal-icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    margin-bottom: 14px;
    border-radius: 13px;
    color: #fff;
    background: linear-gradient(135deg, #f58220, #df6810);
    box-shadow: 0 8px 18px rgba(245, 130, 32, .20);
}

.password-box h3 {
    margin: 0;
    color: #292522;
    font-size: 19px;
    font-weight: 800;
    letter-spacing: -.3px;
}

.password-box>p {
    margin: 6px 0 18px;
    color: #958c84;
    font-size: 10px;
    line-height: 1.6;
}

.password-message {
    display: none;
    margin-bottom: 13px;
    padding: 10px 11px;
    border-radius: 9px;
    font-size: 9px;
    line-height: 1.5;
    font-weight: 700;
}

.password-message.show {
    display: block;
}

.password-message.error {
    color: #a53b3b;
    background: #fff2f2;
    border: 1px solid #f0c8c8;
}

.password-message.success {
    color: #177246;
    background: #effaf4;
    border: 1px solid #c9ead7;
}

.password-field {
    margin-bottom: 14px;
}

.password-field>label {
    display: block;
    margin-bottom: 6px;
    color: #49423c;
    font-size: 9px;
    font-weight: 800;
}

.password-input-wrap {
    height: 43px;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 10px;
    border: 1px solid #e4ded8;
    border-radius: 10px;
    background: #fff;
    transition: .2s ease;
}

.password-input-wrap:focus-within {
    border-color: #efa267;
    box-shadow: 0 0 0 3px rgba(245, 130, 32, .08);
}

.password-input-wrap>i {
    width: 14px;
    color: #aaa19a;
    font-size: 10px;
    text-align: center;
}

.password-input-wrap:focus-within>i {
    color: #f58220;
}

.password-input-wrap input {
    width: 100%;
    min-width: 0;
    height: 100%;
    border: 0;
    outline: 0;
    color: #302b27;
    background: transparent;
    font-size: 10px;
    font-weight: 600;
}

.toggle-password {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 7px;
    color: #9b928a;
    background: transparent;
    cursor: pointer;
}

.toggle-password:hover {
    color: #f58220;
    background: #fff5ec;
}

.password-help {
    display: block;
    margin-top: 5px;
    color: #aaa19a;
    font-size: 7px;
}

.password-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
}

.password-cancel,
.password-save {
    min-height: 40px;
    padding: 0 14px;
    border-radius: 9px;
    font-size: 9px;
    font-weight: 800;
    cursor: pointer;
}

.password-cancel {
    border: 1px solid #e0dad4;
    color: #70675f;
    background: #fff;
}

.password-cancel:hover {
    background: #faf8f6;
}

.password-save {
    border: 0;
    color: #fff;
    background: linear-gradient(135deg, #f58220, #df6810);
    box-shadow: 0 7px 16px rgba(245, 130, 32, .18);
}

.password-save:hover {
    background: #df6810;
}

.password-save:disabled {
    opacity: .65;
    cursor: wait;
}

@media (max-width: 520px) {
    .password-modal {
        padding: 12px;
    }

    .password-box {
        padding: 23px 18px;
        border-radius: 16px;
    }

    .password-actions {
        display: grid;
        grid-template-columns: 1fr 1.4fr;
    }

    .password-cancel,
    .password-save {
        width: 100%;
    }
}
</style>


<script>
(function() {

    const modal = document.getElementById('passwordModal');
    const form = document.getElementById('passwordForm');
    const message = document.getElementById('passwordMessage');

    const openButton =
        document.getElementById('openPasswordFromNav');

    const closeButton =
        document.getElementById('closePassword');

    const cancelButton =
        document.getElementById('cancelPassword');

    const saveButton =
        document.getElementById('savePassword');


    if (!modal || !form || !openButton) {
        return;
    }


    function clearMessage() {

        message.className = 'password-message';
        message.textContent = '';

    }


    function openPasswordModal(event) {

        if (event) {
            event.preventDefault();
        }

        clearMessage();

        form.reset();

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');

        setTimeout(function() {

            document
                .getElementById('currentPassword')
                ?.focus();

        }, 100);

    }


    function closePasswordModal() {

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');

        clearMessage();

        form.reset();

    }


    openButton.addEventListener(
        'click',
        openPasswordModal
    );


    closeButton?.addEventListener(
        'click',
        closePasswordModal
    );


    cancelButton?.addEventListener(
        'click',
        closePasswordModal
    );


    /*
     * Clicking the dark background closes the modal.
     */
    modal.addEventListener(
        'click',
        function(event) {

            if (event.target === modal) {
                closePasswordModal();
            }

        }
    );


    /*
     * Escape closes the modal.
     */
    document.addEventListener(
        'keydown',
        function(event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains('show')
            ) {
                closePasswordModal();
            }

        }
    );


    /*
     * Show / hide password buttons.
     */
    document
        .querySelectorAll('.toggle-password')
        .forEach(function(button) {

            button.addEventListener(
                'click',
                function() {

                    const target =
                        document.getElementById(
                            button.dataset.target
                        );

                    if (!target) {
                        return;
                    }

                    const icon =
                        button.querySelector('i');

                    if (target.type === 'password') {

                        target.type = 'text';

                        icon.classList.remove(
                            'fa-eye'
                        );

                        icon.classList.add(
                            'fa-eye-slash'
                        );

                    } else {

                        target.type = 'password';

                        icon.classList.remove(
                            'fa-eye-slash'
                        );

                        icon.classList.add(
                            'fa-eye'
                        );

                    }

                }
            );

        });


    /*
     * Submit password change.
     */
    form.addEventListener(
        'submit',
        async function(event) {

            event.preventDefault();

            const currentPassword =
                document.getElementById(
                    'currentPassword'
                ).value.trim();

            const newPassword =
                document.getElementById(
                    'newPassword'
                ).value;

            const confirmPassword =
                document.getElementById(
                    'confirmPassword'
                ).value;


            clearMessage();


            if (!currentPassword) {

                showError(
                    'Enter your current password.'
                );

                return;

            }


            if (newPassword.length < 8) {

                showError(
                    'The new password must be at least 8 characters.'
                );

                return;

            }


            if (newPassword !== confirmPassword) {

                showError(
                    'The new passwords do not match.'
                );

                return;

            }


            if (currentPassword === newPassword) {

                showError(
                    'Your new password must be different from your current password.'
                );

                return;

            }


            saveButton.disabled = true;

            saveButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';


            try {

                const response =
                    await fetch(
                        '../handlers/reset_password.php', {
                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },

                            body: JSON.stringify({
                                current_password: currentPassword,

                                new_password: newPassword,

                                confirm_password: confirmPassword
                            })
                        }
                    );


                const raw =
                    await response.text();

                let result;

                try {

                    result =
                        JSON.parse(raw);

                } catch (parseError) {

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
                        'Unable to update your password.'
                    );

                }


                showSuccess(
                    result.message ||
                    'Your password has been updated successfully.'
                );


                form.reset();


                /*
                 * Close after the user has had time to see success.
                 */
                setTimeout(
                    closePasswordModal,
                    1500
                );


            } catch (error) {

                showError(
                    error.message ||
                    'Unable to update your password.'
                );

            } finally {

                saveButton.disabled = false;

                saveButton.innerHTML =
                    '<i class="fa-solid fa-lock"></i> Update Password';

            }

        }
    );


    function showError(text) {

        message.className =
            'password-message error show';

        message.textContent = text;

    }


    function showSuccess(text) {

        message.className =
            'password-message success show';

        message.textContent = text;

    }

})();
</script>