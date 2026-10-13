/* ===================================================================
   Guided product tour — walks a new user through every page of the
   attendance system, one highlighted element at a time. Started from
   the compass icon in the topbar; state survives page navigation via
   sessionStorage so the tour can carry the user across routes.
   =================================================================== */
(() => {
    'use strict';

    const STEPS = [
        { route: 'dashboard',    selector: '[data-tour="sidebar-nav"]', title: 'Welcome aboard',       text: "This is your main menu — every module of the system lives here: Employees, Attendance, Reports, Payroll, Leaves, Holidays and more. Let's walk through each one." },
        { route: 'dashboard',    selector: '[data-tour="dash-kpis"]',   title: 'Dashboard at a glance', text: "Total employees, who's present, late or absent today — updated live." },
        { route: 'dashboard',    selector: '[data-tour="dash-feed"]',   title: 'Live punch feed',       text: 'Every clock-in / clock-out from the biometric device streams in here in real time.' },
        { route: 'dashboard',    selector: '#btn-sync-now',             title: 'Sync anytime',          text: 'Click Sync now to pull the latest punches straight from the device on demand.' },
        { route: 'employees',    selector: '[data-tour="emp-add"]',     title: 'Add employees',         text: 'Add an employee manually, or click Sync from device to import everyone already enrolled on the biometric machine.' },
        { route: 'employees',    selector: '[data-tour="emp-table"]',   title: 'Employee directory',    text: 'ID, department, designation and card number for everyone. Use the pencil / trash icons to edit or remove someone.' },
        { route: 'attendance',   selector: '[data-tour="att-filter"]',  title: 'Filter the log',        text: 'Narrow the attendance log by date range, or search for an employee / user ID.' },
        { route: 'attendance',   selector: '[data-tour="att-actions"]', title: 'Keep it accurate',      text: 'Sync pulls new punches from the device, Add break / Manual punch record anything it missed, and CSV exports what you see.' },
        { route: 'attendance',   selector: '[data-tour="att-table"]',   title: 'Every punch, logged',   text: 'Type, source and status for every punch — edit or delete any row with the icons on the right.' },
        { route: 'reports',      selector: '[data-tour="rep-filter"]',  title: 'Monthly report',        text: "Pick a month to see everyone's daily attendance summarized in one table." },
        { route: 'reports',      selector: '[data-tour="rep-table"]',   title: 'Full daily breakdown',  text: 'First-in, last-out, breaks, worked hours, overtime and arrival status — one row per employee per day.' },
        { route: 'workinghours', selector: '[data-tour="wh-filter"]',   title: 'Working hours',         text: 'Choose a date range and, optionally, a single employee to see their worked hours.' },
        { route: 'workinghours', selector: '[data-tour="wh-kpis"]',     title: 'Quick totals',          text: 'Employees with hours, total worked hours and days in range, at a glance.' },
        { route: 'workinghours', selector: '[data-tour="wh-table"]',    title: 'Grouped by employee',   text: 'Expand any employee to see their day-by-day breakdown, including breaks and overtime.' },
        { route: 'payroll',      selector: '[data-tour="pay-filter"]',  title: 'Payroll',               text: 'Choose a date range, an employee and Daily or Summary view.' },
        { route: 'payroll',      selector: '[data-tour="pay-kpis"]',    title: 'Payable at a glance',   text: 'Total net hours and total payable amount for the selected range.' },
        { route: 'payroll',      selector: '[data-tour="pay-table"]',   title: 'Automatic wages',       text: "Wages are calculated automatically from worked hours and each employee's hourly rate — set on the Salary Setup page." },
        { route: 'leaves',       selector: '[data-tour="lv-actions"]',  title: 'Manage leave requests', text: 'Add leave requests, assign yearly leave balances, and approve or reject pending requests.' },
        { route: 'leaves',       selector: '[data-tour="lv-kpis"]',     title: 'Leave status',          text: 'Pending, approved and total leave requests, at a glance.' },
        { route: 'leaves',       selector: '[data-tour="lv-table"]',    title: 'Review requests',       text: 'Approve, reject, edit or delete any leave request with the icons on the right.' },
        { route: 'leaves',       selector: '[data-tour="lv-balances"]', title: 'Track balances',        text: 'See how many leave days each employee has used versus what they were assigned.' },
        { route: 'holidays',     selector: '[data-tour="hol-actions"]', title: 'Set up holidays',       text: 'Load a ready-made USA holiday calendar, or add your own custom holidays.' },
        { route: 'holidays',     selector: '[data-tour="hol-table"]',   title: 'Paid or unpaid',        text: 'Mark each holiday as paid or unpaid by default — this feeds straight into payroll.' },
        { route: 'holidays',     selector: '[data-tour="hol-avails"]',  title: 'Who availed what',      text: 'Record which employee availed which holiday, in case it needs to be tracked individually.' },
        { route: 'salary',       selector: '[data-tour="sal-table"]',   title: 'Salary setup',          text: "Set each employee's hourly rate here — Payroll calculates wages straight from these rates." },
        { route: 'settings',     selector: '[data-tour="set-device"]',  title: 'Device connection',     text: "Configure your biometric device's IP, port and connection key, and test, sync or restart it." },
        { route: 'settings',     selector: '[data-tour="set-timing"]',  title: 'Office timing',         text: 'Set office hours, the late-arrival threshold and how often attendance auto-syncs.' },
        { route: 'settings',     selector: '[data-tour="set-payroll"]', title: 'Payroll rules',         text: 'Currency, compulsory break minutes and the meal-break threshold — used across Payroll and Working Hours.' },
        { route: 'settings',     selector: '[data-tour="set-leave"]',   title: 'Paid leave rules',      text: 'Set how many hours count as one full paid-leave day.' },
        { route: 'settings',     selector: '#tour-start-btn',           title: "You're all set!",       text: 'That was the whole system. Click this guide icon anytime to run the tour again.' },
    ];

    const KEY_ACTIVE = 'lv-tour-active';
    const KEY_STEP    = 'lv-tour-step';

    const currentRoute = () => new URLSearchParams(location.search).get('route') || 'dashboard';

    let spotlight = null, popover = null, reposition = null, renderTimer = null;

    function teardown() {
        clearTimeout(renderTimer);
        if (reposition) {
            window.removeEventListener('scroll', reposition, true);
            window.removeEventListener('resize', reposition);
        }
        spotlight?.remove();
        popover?.remove();
        spotlight = popover = reposition = null;
    }

    function clearState() {
        sessionStorage.removeItem(KEY_ACTIVE);
        sessionStorage.removeItem(KEY_STEP);
    }

    function finishTour() {
        teardown();
        clearState();
        if (window.toast) window.toast("Tour complete — you're all set!", 'success');
    }

    function skipTour() {
        teardown();
        clearState();
    }

    function advanceTo(i) {
        if (i >= STEPS.length) { finishTour(); return; }
        if (i < 0) return;
        sessionStorage.setItem(KEY_ACTIVE, '1');
        sessionStorage.setItem(KEY_STEP, String(i));
        const step = STEPS[i];
        if (step.route !== currentRoute()) {
            teardown();
            const url = new URL(location.href);
            url.searchParams.set('route', step.route);
            location.href = url.toString();
            return;
        }
        renderStep(i);
    }

    function renderStep(i, attempt) {
        attempt = attempt || 0;
        const step = STEPS[i];
        const target = document.querySelector(step.selector);
        if (!target) {
            if (attempt < 20) { renderTimer = setTimeout(() => renderStep(i, attempt + 1), 150); return; }
            advanceTo(i + 1); // element never showed up on this page — don't get the user stuck
            return;
        }
        teardown();
        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        renderTimer = setTimeout(() => paint(target, step, i), 350);
    }

    function paint(target, step, i) {
        spotlight = document.createElement('div');
        spotlight.className = 'tour-spotlight';
        document.body.appendChild(spotlight);

        popover = document.createElement('div');
        popover.className = 'tour-popover';
        popover.innerHTML =
            '<button type="button" class="tour-close" aria-label="Close tour"><i data-lucide="x"></i></button>' +
            '<p class="tour-step-count">Step ' + (i + 1) + ' of ' + STEPS.length + '</p>' +
            '<h4></h4>' +
            '<p class="tour-desc"></p>' +
            '<div class="tour-actions">' +
                '<button type="button" class="btn sm ghost" data-back>Back</button>' +
                '<div class="flex" style="gap:14px">' +
                    '<button type="button" class="tour-skip" data-skip>Skip tour</button>' +
                    '<button type="button" class="btn sm primary" data-next></button>' +
                '</div>' +
            '</div>';
        popover.querySelector('h4').textContent = step.title;
        popover.querySelector('.tour-desc').textContent = step.text;
        document.body.appendChild(popover);
        if (window.lucide) lucide.createIcons({ nodes: [popover] });

        const backBtn = popover.querySelector('[data-back]');
        const nextBtn = popover.querySelector('[data-next]');
        backBtn.disabled = i === 0;
        nextBtn.textContent = i === STEPS.length - 1 ? 'Finish' : 'Next';

        backBtn.addEventListener('click', () => advanceTo(i - 1));
        nextBtn.addEventListener('click', () => advanceTo(i + 1));
        popover.querySelector('[data-skip]').addEventListener('click', skipTour);
        popover.querySelector('.tour-close').addEventListener('click', skipTour);

        const place = () => {
            const r = target.getBoundingClientRect();
            const pad = 6;
            spotlight.style.top    = (r.top - pad) + 'px';
            spotlight.style.left   = (r.left - pad) + 'px';
            spotlight.style.width  = (r.width + pad * 2) + 'px';
            spotlight.style.height = (r.height + pad * 2) + 'px';

            const pw = popover.offsetWidth, ph = popover.offsetHeight, margin = 14;
            let top;
            if (window.innerHeight - r.bottom > ph + margin + 20) top = r.bottom + margin;
            else if (r.top > ph + margin + 20) top = r.top - ph - margin;
            else top = Math.max(margin, Math.min(window.innerHeight - ph - margin, r.bottom + margin));
            const left = Math.max(margin, Math.min(window.innerWidth - pw - margin, r.left));
            popover.style.top  = top + 'px';
            popover.style.left = left + 'px';
        };
        place();
        reposition = () => requestAnimationFrame(place);
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);
    }

    document.getElementById('tour-start-btn')?.addEventListener('click', () => advanceTo(0));

    if (sessionStorage.getItem(KEY_ACTIVE) === '1') {
        const i = parseInt(sessionStorage.getItem(KEY_STEP) || '0', 10);
        const step = STEPS[i];
        if (step && step.route === currentRoute()) renderStep(i);
        else clearState(); // user navigated away manually — quietly drop the stale tour
    }
})();
