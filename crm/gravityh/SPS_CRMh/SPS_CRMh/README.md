# Syed Professional Services (SPS) AI CRM (Windows XAMPP Edition)

A powerful, practical, AI-enabled Professional Services CRM designed for **Syed Professional Services (SPS)** running on Windows XAMPP. Built with a clean, single-folder architecture and self-contained PHP pages for maximum clarity, maintainability, and operational automation.

---

## 🚀 Quick Setup Instructions for Windows XAMPP

### Step 1: Copy Folder to XAMPP htdocs
Copy the entire `SPS_CRM` folder to your XAMPP web root directory:
```text
C:\xampp\htdocs\SPS_CRM\
```

### Step 2: Start XAMPP Server
1. Launch **XAMPP Control Panel**.
2. Start the **Apache** service.
3. Start the **MySQL** service.

### Step 3: Import Master Database (`crm.sql`)
1. Open phpMyAdmin in your web browser: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **Databases** tab and create a new database named `sps_crm` (Collation: `utf8mb4_unicode_ci`).
3. Select the `sps_crm` database.
4. Click the **Import** tab.
5. Choose the `crm.sql` file located at `C:\xampp\htdocs\SPS_CRM\crm.sql` and click **Import**.

### Step 4: Open the CRM & Login
Open your web browser and navigate to:
```text
http://localhost/SPS_CRM/
```

#### Default Administrator Credentials
- **Username:** `admin`
- **Password:** `ChangeMeImmediately!123`

---

## 🛠️ Complete Syed Professional Services Catalog & Workflows

The CRM comes pre-configured with the full service offerings of Syed Professional Services:
1. **Bank Statement Collection & Monthly Bookkeeping Preparation** (Form SRV-BOOKKEEPING)
2. **Corporate Tax Return Filing Form 1120 / 1120S** (Form SRV-TAX-CORP-1120)
3. **Individual Income Tax Filing Form 1040 / Schedule C** (Form SRV-TAX-INDIV-1040)
4. **Annual Corporate Report & Compliance Filing** (Form SRV-CORP-ANNUAL)
5. **Automated Monthly Payroll Processing & Form 941/940** (Form SRV-PAYROLL)
6. **Quarterly State Sales Tax Filing** (Form SRV-SALES-TAX-QT)
7. **LLC / Inc Business Formation & EIN Setup** (Form SRV-LLC-FORMATION)

---

## ⚡ Realtime Service Automation inside Projects & Pipeline

Every **Project** (`project_view.php`) features an embedded **Realtime Service Automation Pipeline**:
- Visual workflow step progression (Step 1 → Step 5).
- Required document checklist.
- **"⚡ Execute Realtime Service Automation"**: Automatically scans missing items, dispatches client email notices, and sends automated Twilio SMS / Voice calls.
- **"📞 Trigger Twilio Call"**: Initiates Twilio AI voice call to client asking for missing requirements, logging duration and script to the Client Profile timeline.

---

## 📞 Twilio, Email SMTP & Multi-Channel Communications

Navigate to **Admin → System Settings** (`settings.php`) to configure:
1. **Twilio API Credentials:** Add your Twilio Account SID, Auth Token, and Outbound Phone Number.
2. **Email SMTP Setup:** Add your Host, Port, Username, Password, and From Email.
3. **WhatsApp Business API:** Add Phone Number ID and Access Token.
4. **Unified Client Profile Communication Timeline (`client_profile.php`):** All Twilio Calls (with duration), Emails, SMS, and WhatsApp messages are logged and visible in the client profile.

---

## 🎨 CRM Theme Customizer

Change the entire visual appearance of the CRM instantly in **Admin → System Settings**:
- **Executive Navy** (Default Clean Light)
- **Modern Dark Mode** (Sleek Slate Dark)
- **Emerald Green** (Fresh Accounting Green)
- **Glassmorphism Indigo** (Modern Translucent Glass)
- **Luxury Gold / Dark** (Executive Slate & Gold)

---

## 🎙️ AI Voice Assistant & Natural Language Speech Engine (`ai_assistant.php`)

- **Built-in Speech Recognition (Speech-to-Text):** Click the microphone button and speak human language prompts (e.g. *"Who has missing bank statements?"* or *"What is our total outstanding revenue?"*).
- **Live Database Query:** Fetches real-time structured data from clients, missing statements, invoices, and urgent tasks.
- **Speech Synthesis (Text-to-Speech):** Automatically speaks the AI response back aloud using browser speech synthesis!

---

## ⚡ Automated Lead Intelligence & 1-Click Conversion (`leads.php`)

- Automatic AI Lead Qualification Scoring (0-100).
- Automated Welcome email & Twilio SMS outreach.
- **"⚡ Auto-Convert to Client & Project"**: 1-click execution that creates a Client + Business Entity + Project + Service Workflow + 2026 Bank Statement Checklist in a single click!
