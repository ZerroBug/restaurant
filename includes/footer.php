<?php
/*
|--------------------------------------------------------------------------
| REUSABLE PROFESSIONAL FOOTER
|--------------------------------------------------------------------------
| Include this file near the end of every page, before </body>.
|
| Example:
| <?php include __DIR__ . '/../includes/footer.php'; ?>
|--------------------------------------------------------------------------
*/

$currentYear = date('Y');
?>

<footer class="site-footer" id="siteFooter">

    <div class="site-footer-inner">

        <div class="footer-brand">
            <div class="footer-brand-mark">
                <i class="fa-solid fa-utensils"></i>
            </div>

            <div class="footer-brand-copy">
                <strong>BETTER END</strong>
                <strong>RESTAURANT</strong>
                <span>Restaurant Management System</span>
            </div>
        </div>

        <div class="footer-center">
            <p class="footer-copyright">
                &copy; <?= htmlspecialchars($currentYear, ENT_QUOTES, 'UTF-8') ?>
                Better End Restaurant. All rights reserved.
            </p>

            <p class="footer-credit">
                Design by <strong>ANATECH CONSULT</strong>
            </p>
        </div>

        <div class="footer-status">
            <span class="footer-status-dot"></span>
            <span>System Online</span>
        </div>

    </div>

</footer>

<style>
.site-footer {
    width: 100%;
    margin-top: auto;
    padding: 0 28px 22px;
    background: transparent;
}

.site-footer-inner {
    width: 100%;
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 16px 20px;
    border: 1px solid #ebe5df;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 6px 22px rgba(35, 29, 24, .045);
    transition: border-color .2s ease, box-shadow .2s ease;
}

.site-footer-inner:hover {
    border-color: #e4d8ce;
    box-shadow: 0 8px 26px rgba(35, 29, 24, .06);
}

.footer-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 205px;
}

.footer-brand-mark {
    width: 39px;
    height: 39px;
    flex: 0 0 39px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    color: #fff;
    background: linear-gradient(135deg, #f58220, #df6810);
    box-shadow: 0 6px 15px rgba(245, 130, 32, .18);
    font-size: 14px;
}

.footer-brand-copy {
    display: flex;
    flex-direction: column;
    line-height: 1.05;
}

.footer-brand-copy strong {
    color: #292522;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .55px;
}

.footer-brand-copy span {
    margin-top: 4px;
    color: #9a918a;
    font-size: 7px;
    font-weight: 500;
}

.footer-center {
    flex: 1;
    text-align: center;
}

.footer-copyright {
    margin: 0;
    color: #756c65;
    font-size: 8px;
    font-weight: 500;
    line-height: 1.5;
}

.footer-credit {
    margin: 3px 0 0;
    color: #aaa19a;
    font-size: 7px;
    font-weight: 500;
}

.footer-credit strong {
    color: #f58220;
    font-weight: 800;
    letter-spacing: .35px;
}

.footer-status {
    min-width: 105px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    color: #7d756e;
    font-size: 7px;
    font-weight: 700;
    white-space: nowrap;
}

.footer-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #1fa463;
    box-shadow: 0 0 0 3px rgba(31, 164, 99, .10);
}

@media (max-width: 760px) {
    .site-footer {
        padding: 0 15px 15px;
    }

    .site-footer-inner {
        min-height: auto;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 17px 15px;
        text-align: center;
    }

    .footer-brand {
        justify-content: center;
    }

    .footer-center {
        width: 100%;
    }

    .footer-status {
        justify-content: center;
    }
}

@media (max-width: 420px) {
    .footer-brand-mark {
        width: 36px;
        height: 36px;
        flex-basis: 36px;
    }

    .footer-brand-copy strong {
        font-size: 8px;
    }

    .footer-copyright {
        font-size: 7px;
    }
}
</style>
