<script src="scripts/jquery.min.js"></script>
<script src="scripts/jquery.form.js"></script>
<script src="scripts/popper.min.js"></script>
<script src="scripts/perfect-scrollbar.min.js"></script>
<script src="scripts/simplebar.min.js"></script>
<script src="scripts/bootstrap.min.js"></script>
<script src="scripts/feather.min.js"></script>
<script src="scripts/dash.js"></script>
<script src="scripts/moment.min.js"></script>
<script src="scripts/bootstrap-switch-button.min.js"></script>
<script src="scripts/sweetalert2.all.min.js"></script>
<script src="scripts/simple-datatables.js"></script>

<!-- Apex Chart -->
<script src="scripts/apexcharts.min.js"></script>
<script src="scripts/choices.min.js"></script>

<script src="scripts/flatpickr.min.js"></script>
<script src="scripts/main.min.js"></script>
<script src="scripts/jscolor.js"></script>
<script src="scripts/custom.js"></script>
<link rel='stylesheet' href='scripts/cookieconsent.css' media="screen" />
<script src="scripts/cookieconsent.js"></script>


<script src="scripts/code.js"></script>


<script>
    new DataTable('#example', {
        "paging": false,
        columnDefs: [
            {
                className: 'dtr-control',
                orderable: false,
                targets: 0
            }
        ],
        order: [1, 'asc'],
        responsive: {
            details: {
                type: 'column'
            }
        }
    });

    new DataTable('#example1', {
        "paging": false,
        columnDefs: [
            {
                className: 'dtr-control',
                orderable: false,
                targets: 0
            }
        ],
        order: [1, 'asc'],
        responsive: {
            details: {
                type: 'column'
            }
        }
    });

    new DataTable('#example2', {
        "paging": false,
        columnDefs: [
            {
                className: 'dtr-control',
                orderable: false,
                targets: 0
            }
        ],
        order: [1, 'asc'],
        responsive: {
            details: {
                type: 'column'
            }
        }
    });

    new DataTable('#example3', {
        "paging": false,
        columnDefs: [
            {
                className: 'dtr-control',
                orderable: false,
                targets: 0
            }
        ],
        order: [1, 'asc'],
        responsive: {
            details: {
                type: 'column'
            }
        }
    });
</script>
<script>
    feather.replace();
    var pctoggle = document.querySelector("#pct-toggler");
    if (pctoggle) {
        pctoggle.addEventListener("click", function () {
            if (
                !document.querySelector(".pct-customizer").classList.contains("active")
            ) {
                document.querySelector(".pct-customizer").classList.add("active");
            } else {
                document.querySelector(".pct-customizer").classList.remove("active");
            }
        });
    }

    var themescolors = document.querySelectorAll(".themes-color > a");
    for (var h = 0; h < themescolors.length; h++) {
        var c = themescolors[h];

        c.addEventListener("click", function (event) {
            var targetElement = event.target;
            if (targetElement.tagName == "SPAN") {
                targetElement = targetElement.parentNode;
            }
            var temp = targetElement.getAttribute("data-value");
            removeClassByPrefix(document.querySelector("body"), "theme-");
            document.querySelector("body").classList.add(temp);
        });
    }
    var custthemebg = document.querySelector("#cust-theme-bg");
    custthemebg.addEventListener("click", function () {
        if (custthemebg.checked) {
            document.querySelector(".dash-sidebar").classList.add("transprent-bg");
            document
                .querySelector(".dash-header:not(.dash-mob-header)")
                .classList.add("transprent-bg");
        } else {
            document.querySelector(".dash-sidebar").classList.remove("transprent-bg");
            document
                .querySelector(".dash-header:not(.dash-mob-header)")
                .classList.remove("transprent-bg");
        }
    });

    var custdarklayout = document.querySelector("#cust-darklayout");
    custdarklayout.addEventListener("click", function () {
        if (custdarklayout.checked) {

            document
                .querySelector("#main-style")
                .setAttribute("href", "https://demo.rajodiya.com/erpgo-saas/assets/css/style-dark.css");

            document
                .querySelector(".m-header > .b-brand > .logo-lg")
                .setAttribute("src", "https://demo.rajodiya.com/erpgo-saas/storage/uploads/logo/logo-light.png");
        } else {
            document
                .querySelector("#main-style")
                .setAttribute("href", "https://demo.rajodiya.com/erpgo-saas/assets/css/style.css");
            document
                .querySelector(".m-header > .b-brand > .logo-lg")
                .setAttribute("src", "https://demo.rajodiya.com/erpgo-saas/storage/uploads/logo/logo-dark.png");
        }
    });

    function removeClassByPrefix(node, prefix) {
        for (let i = 0; i < node.classList.length; i++) {
            let value = node.classList[i];
            if (value.startsWith(prefix)) {
                node.classList.remove(value);
            }
        }
    }
</script>
<script src="https://js.pusher.com/7.0.3/pusher.min.js"></script>

<script>
    // Pusher.logToConsole = true;

    var pusher = new Pusher("7c05973e1ae0fe5b7f28", {
        cluster: "us3",
        forceTLS: true
    });
</script>

<?php
$global_runtime_reminders = [];
$global_runtime_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$current_script = basename($_SERVER['PHP_SELF'] ?? '');

if ($global_runtime_user_id > 0 && isset($con) && $current_script !== 'index.php') {
    $sql = "SELECT r.reminder_id,
                   r.reminder_title,
                   r.reminder_description,
                   r.reminder_date,
                   r.reminder_type,
                   CONCAT(m.name, ' ', m.lname) AS client_name
            FROM client_calender_reminder r
            JOIN members m ON m.member_id = r.client_id
            WHERE DATE(r.reminder_date) = CURDATE()
              AND r.status = 1
              AND r.created_by = ?
            ORDER BY r.reminder_date ASC";

    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $global_runtime_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($row['reminder_description'])) {
                $row['reminder_description'] = trim((string) preg_replace('/\s*<!--REM_META:[A-Za-z0-9+\/=]+-->\s*$/', '', (string) $row['reminder_description']));
            }
            $global_runtime_reminders[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<script>
    (function () {
        const allReminders = <?php echo json_encode($global_runtime_reminders, JSON_UNESCAPED_UNICODE); ?>;
        const currentUserId = <?php echo (int) $global_runtime_user_id; ?>;

        if (!Array.isArray(allReminders) || allReminders.length === 0 || !currentUserId) return;

        const SNOOZE_DELAY_MS = 2 * 60 * 1000;
        const reminderStorageKeyPrefix = `calendarReminderState_${currentUserId}_`;
        let reminderWakeTimer = null;
        let runtimeReminderOpen = false;
        let runtimeReminderPopup = null;

        function injectIndexReminderStyles() {
            if (document.getElementById('runtimeReminderSharedStyles')) return;
            const style = document.createElement('style');
            style.id = 'runtimeReminderSharedStyles';
            style.textContent = `
                .runtime-reminder-popup {
                    --popup-theme: #6FD943;
                    --popup-danger: var(--bs-danger, #dc3545);
                    position: fixed;
                    top: 24px;
                    right: 20px;
                    width: 380px;
                    max-width: calc(100vw - 40px);
                    background: #ffffff;
                    border: 1px solid #e8edf3;
                    border-left: 6px solid var(--popup-theme);
                    border-radius: 14px;
                    box-shadow: 0 18px 38px rgba(14, 30, 62, 0.16);
                    z-index: 9999;
                    overflow: hidden;
                    opacity: 0;
                    transform: translateY(-14px) scale(0.98);
                    visibility: hidden;
                    pointer-events: none;
                    transition: opacity 0.22s ease, transform 0.22s ease, visibility 0s linear 0.22s;
                }

                .runtime-reminder-popup.is-visible {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                    visibility: visible;
                    pointer-events: auto;
                    transition: opacity 0.22s ease, transform 0.22s ease;
                }

                .runtime-reminder-popup-header {
                    padding: 12px 14px;
                    background: var(--popup-theme);
                    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
                    color: #ffffff;
                    font-weight: 700;
                    font-size: 0.93rem;
                    letter-spacing: 0.02em;
                }

                .runtime-reminder-popup-body {
                    padding: 12px 14px 10px;
                    font-size: 0.83rem;
                    background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
                }

                .runtime-reminder-popup-row {
                    margin-bottom: 7px;
                    display: grid;
                    grid-template-columns: 74px 1fr;
                    gap: 8px;
                    align-items: start;
                }

                .runtime-reminder-label {
                    color: #556070;
                    font-weight: 600;
                }

                .runtime-reminder-value {
                    color: #1f2a37;
                    font-weight: 500;
                    word-break: break-word;
                }

                .runtime-reminder-description {
                    margin-top: 9px;
                    padding: 8px 10px;
                    border-radius: 9px;
                    background: #f7fbff;
                    border: 1px solid rgba(0, 0, 0, 0.08);
                }

                .runtime-reminder-description-label {
                    color: #4f5d6f;
                    font-weight: 600;
                    margin-bottom: 3px;
                }

                .runtime-reminder-description-text {
                    color: #5b6573;
                    line-height: 1.45;
                    max-height: 92px;
                    overflow-y: auto;
                }

                .runtime-reminder-popup-actions {
                    display: flex;
                    gap: 8px;
                    justify-content: flex-end;
                    padding: 10px 14px 14px;
                    border-top: 1px solid #edf1f5;
                    background: #fbfcfe;
                }

                #runtimeReminderClose,
                #runtimeReminderSnooze,
                #runtimeReminderStop {
                    border-radius: 8px;
                    border: none;
                    padding: 6px 12px;
                    font-weight: 600;
                    transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
                }

                #runtimeReminderClose:hover,
                #runtimeReminderSnooze:hover,
                #runtimeReminderStop:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
                }

                #runtimeReminderClose {
                    background: #ffffff;
                    color: #2f7f17;
                    border: 1px solid #6FD943;
                }

                #runtimeReminderSnooze {
                    background: #6FD943;
                    color: #ffffff;
                }

                #runtimeReminderStop {
                    background: var(--popup-danger);
                    color: #fff;
                }
            `;
            document.head.appendChild(style);
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        }

        function formatDateForDisplay(date) {
            const options = { month: 'long', day: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        function formatTime(datetime) {
            const date = new Date(parseReminderTimestamp(datetime));
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function parseReminderTimestamp(datetime) {
            return new Date(String(datetime).replace(' ', 'T')).getTime();
        }

        function isReminderForToday(reminder) {
            const reminderDate = new Date(parseReminderTimestamp(reminder.reminder_date));
            const now = new Date();
            return (
                reminderDate.getFullYear() === now.getFullYear() &&
                reminderDate.getMonth() === now.getMonth() &&
                reminderDate.getDate() === now.getDate()
            );
        }

        function getReminderState(reminder) {
            const key = reminderStorageKeyPrefix + reminder.reminder_id;
            let state = null;

            try {
                state = JSON.parse(localStorage.getItem(key) || '{}');
            } catch (e) {
                state = {};
            }

            const dueTs = parseReminderTimestamp(reminder.reminder_date);
            if (!state.nextTriggerAt || Number.isNaN(Number(state.nextTriggerAt))) {
                state.nextTriggerAt = dueTs;
            }
            if (!state.snoozedUntil || Number.isNaN(Number(state.snoozedUntil))) {
                state.snoozedUntil = 0;
            }
            if (Number(state.snoozedUntil) > Number(state.nextTriggerAt)) {
                state.nextTriggerAt = Number(state.snoozedUntil);
            }
            if (typeof state.stopped !== 'boolean') {
                state.stopped = false;
            }
            return state;
        }

        function setReminderState(reminderId, state) {
            const key = reminderStorageKeyPrefix + reminderId;
            localStorage.setItem(key, JSON.stringify(state));
        }

        function openRuntimeReminderPopup() {
            runtimeReminderPopup.classList.add('is-visible');
        }

        function closeRuntimeReminderPopup() {
            runtimeReminderPopup.classList.remove('is-visible');
        }

        function buildRuntimeReminderPopup() {
            const existing = document.getElementById('runtimeReminderPopup');
            if (existing) return existing;

            const wrapper = document.createElement('div');
            wrapper.className = 'runtime-reminder-popup';
            wrapper.id = 'runtimeReminderPopup';
            wrapper.innerHTML = `
               <div class="runtime-reminder-popup-header" id="runtimeReminderHeader">Reminder Due</div>
               <div class="runtime-reminder-popup-body" id="runtimeReminderBody"></div>
               <div class="runtime-reminder-popup-actions">
                  <button type="button" class="btn btn-sm btn-secondary" id="runtimeReminderClose">Close</button>
                  <button type="button" class="btn btn-sm btn-warning" id="runtimeReminderSnooze">Snooze 2 min</button>
                  <button type="button" class="btn btn-sm btn-danger" id="runtimeReminderStop">Stop</button>
               </div>
            `;
            document.body.appendChild(wrapper);
            return wrapper;
        }

        function showRuntimeReminder(reminder, triggerAt, manualOpen = false) {
            runtimeReminderOpen = true;
            const header = runtimeReminderPopup.querySelector('#runtimeReminderHeader');
            const body = runtimeReminderPopup.querySelector('#runtimeReminderBody');
            header.textContent = manualOpen ? 'Reminder Details' : 'Reminder Due';
            body.innerHTML = `
               <div class="runtime-reminder-popup-row">
                  <div class="runtime-reminder-label">Client</div>
                  <div class="runtime-reminder-value">${escapeHtml(reminder.client_name || '-')}</div>
               </div>
               <div class="runtime-reminder-popup-row">
                  <div class="runtime-reminder-label">Title</div>
                  <div class="runtime-reminder-value">${escapeHtml(reminder.reminder_title || '-')}</div>
               </div>
               <div class="runtime-reminder-popup-row">
                  <div class="runtime-reminder-label">Type</div>
                  <div class="runtime-reminder-value">${escapeHtml(reminder.reminder_type || '-')}</div>
               </div>
               <div class="runtime-reminder-popup-row">
                  <div class="runtime-reminder-label">Time</div>
                  <div class="runtime-reminder-value">${formatDateForDisplay(new Date(parseReminderTimestamp(reminder.reminder_date)))} ${formatTime(reminder.reminder_date)}</div>
               </div>
               <div class="runtime-reminder-description">
                  <div class="runtime-reminder-description-label">Description</div>
                  <div class="runtime-reminder-description-text">${escapeHtml(reminder.reminder_description || 'No description')}</div>
               </div>
            `;
            openRuntimeReminderPopup();

            const closeBtn = runtimeReminderPopup.querySelector('#runtimeReminderClose');
            const snoozeBtn = runtimeReminderPopup.querySelector('#runtimeReminderSnooze');
            const stopBtn = runtimeReminderPopup.querySelector('#runtimeReminderStop');

            closeBtn.onclick = function () {
                closeRuntimeReminderPopup();
                runtimeReminderOpen = false;
            };

            snoozeBtn.onclick = function () {
                const state = getReminderState(reminder);
                const snoozeUntil = Date.now() + SNOOZE_DELAY_MS;
                state.lastTriggeredAt = Number(triggerAt) || Date.now();
                state.nextTriggerAt = snoozeUntil;
                state.snoozedUntil = snoozeUntil;
                state.stopped = false;
                setReminderState(reminder.reminder_id, state);
                closeRuntimeReminderPopup();
                runtimeReminderOpen = false;
                scheduleNextDueReminderCheck();
            };

            stopBtn.onclick = function () {
                const state = getReminderState(reminder);
                state.lastTriggeredAt = Number(triggerAt) || Date.now();
                state.snoozedUntil = 0;
                state.stopped = true;
                setReminderState(reminder.reminder_id, state);
                closeRuntimeReminderPopup();
                runtimeReminderOpen = false;
                scheduleNextDueReminderCheck();
            };
        }

        function checkDueReminders() {
            if (runtimeReminderOpen) {
                return;
            }

            const now = Date.now();
            for (const reminder of allReminders) {
                if (!isReminderForToday(reminder)) {
                    continue;
                }

                const state = getReminderState(reminder);
                const snoozedUntil = Number(state.snoozedUntil) || 0;
                if (now < snoozedUntil) {
                    continue;
                }

                const triggerAt = Math.max(Number(state.nextTriggerAt), snoozedUntil);
                if (!Number.isFinite(triggerAt)) {
                    continue;
                }

                if (state.stopped) {
                    continue;
                }

                if (now >= triggerAt && Number(state.lastTriggeredAt) !== triggerAt) {
                    showRuntimeReminder(reminder, triggerAt);
                    return;
                }
            }
        }

        function scheduleNextDueReminderCheck() {
            if (reminderWakeTimer) {
                clearTimeout(reminderWakeTimer);
                reminderWakeTimer = null;
            }

            const now = Date.now();
            let nextDueAt = null;

            for (const reminder of allReminders) {
                if (!isReminderForToday(reminder)) {
                    continue;
                }

                const state = getReminderState(reminder);
                if (state.stopped) {
                    continue;
                }

                const triggerAt = Number(state.nextTriggerAt);
                if (!Number.isFinite(triggerAt)) {
                    continue;
                }

                if (nextDueAt === null || triggerAt < nextDueAt) {
                    nextDueAt = triggerAt;
                }
            }

            if (nextDueAt === null) {
                return;
            }

            const delay = Math.max(0, nextDueAt - now) + 250;
            reminderWakeTimer = setTimeout(() => {
                checkDueReminders();
                scheduleNextDueReminderCheck();
            }, delay);
        }

        injectIndexReminderStyles();
        runtimeReminderPopup = buildRuntimeReminderPopup();
        checkDueReminders();
        scheduleNextDueReminderCheck();
        setInterval(checkDueReminders, 5000);
    })();
</script>
