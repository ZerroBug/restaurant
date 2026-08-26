<header class="header">

    <!-- MOBILE MENU -->
    <button class="mobile-toggle" id="mobileToggle" aria-label="Open menu">
        <i class="fa-solid fa-bars"></i>
    </button>


    <!-- SEARCH -->
    <div class="search">

        <i class="fa-solid fa-magnifying-glass"></i>

        <input type="text" placeholder="Search orders, food, customers and salespeople">

        <button type="button">
            Search
        </button>

    </div>


    <!-- HEADER ACTIONS -->
    <div class="header-actions">


        <!-- NOTIFICATIONS -->
        <a href="#" class="header-action">

            <i class="fa-regular fa-bell"></i>

            <span>
                Notifications
            </span>

        </a>



        <!-- USER PROFILE -->
        <div class="profile-dropdown-wrap">

            <button class="profile-trigger" id="profileTrigger" type="button" aria-expanded="false">

                <span class="profile-avatar">
                    <?= htmlspecialchars($avatar) ?>
                </span>

                <span class="profile-copy">

                    <strong>
                        <?= htmlspecialchars($username) ?>
                    </strong>

                    <small>
                        <?= htmlspecialchars($role) ?>
                    </small>

                </span>

                <i class="fa-solid fa-chevron-down profile-chevron"></i>

            </button>


            <!-- PROFILE DROPDOWN -->
            <div class="profile-dropdown" id="profileDropdown">

                <div class="profile-dropdown-head">

                    <span class="profile-avatar large">
                        <?= htmlspecialchars($avatar) ?>
                    </span>

                    <div>

                        <strong>
                            <?= htmlspecialchars($full_name) ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($role) ?>
                        </small>

                    </div>

                </div>


                <div class="profile-divider"></div>


                <a href="#" class="profile-menu-item">

                    <i class="fa-regular fa-user"></i>

                    <span>
                        My Profile
                    </span>

                </a>


                <a href="#" class="profile-menu-item">

                    <i class="fa-solid fa-gear"></i>

                    <span>
                        Account Settings
                    </span>

                </a>


                <div class="profile-divider"></div>


                <a href="../handlers/logout.php" class="profile-menu-item logout-item">

                    <i class="fa-solid fa-arrow-right-from-bracket"></i>

                    <span>
                        Log Out
                    </span>

                </a>

            </div>

        </div>

    </div>

</header>