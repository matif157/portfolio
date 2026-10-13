/*
 * =====================================================
 * FILE: app.js
 * PURPOSE: CRM frontend ki basic interactive functionality.
 *
 * CURRENT FEATURES:
 * - Mobile sidebar
 * - Global search visual interaction
 * - Quick action feedback
 *
 * NOTE:
 * Backend/API calls abhi intentionally nahi hain.
 * Real functionality PHP backend connect hone ke baad add hogi.
 * =====================================================
 */


document.addEventListener("DOMContentLoaded", function () {

    /*
     * =================================================
     * MOBILE SIDEBAR
     * =================================================
     */

    const mobileMenu = document.querySelector(".mobile-menu");
    const sidebar = document.querySelector(".sidebar");

    if (mobileMenu && sidebar) {

        mobileMenu.addEventListener("click", function () {

            // Class toggle karne se mobile sidebar open/close hota hai.
            sidebar.classList.toggle("open");

        });

    }


    /*
     * =================================================
     * GLOBAL SEARCH
     * =================================================
     */

    const searchInput = document.getElementById("globalSearch");

    if (searchInput) {

        searchInput.addEventListener("keyup", function () {

            /*
             * Abhi search sirf UI level par hai.
             *
             * Next phase:
             * AJAX/API
             * → Client search
             * → Case search
             * → Document search
             */
            const searchValue = searchInput.value.trim();

            if (searchValue.length > 0) {

                console.log(
                    "CRM search:",
                    searchValue
                );

            }

        });

    }


    /*
     * =================================================
     * QUICK ACTION BUTTONS
     * =================================================
     */

    const quickActions = document.querySelectorAll(".quick-action");

    quickActions.forEach(function (button) {

        button.addEventListener("click", function () {

            /*
             * Temporary notification.
             *
             * Later each button real page par navigate karega.
             */
            alert(
                "This CRM module will be connected in the next phase."
            );

        });

    });

});