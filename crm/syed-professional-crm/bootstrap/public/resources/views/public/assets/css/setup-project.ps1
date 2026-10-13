# =====================================================
# FILE: setup-project.ps1
# PURPOSE: SPS CRM ka complete folder + file structure
#          ek hi baar mein create karna.
#
# WORKFLOW:
# setup-project.ps1
#       ↓
# Project folders create
#       ↓
# Required PHP/CSS/JS/SQL files create
#       ↓
# Existing files ko overwrite NAHI karna
#
# NOTE:
# Ye script sirf structure banati hai.
# Existing code ko jaan-boojh kar overwrite nahi karti.
# =====================================================

# -----------------------------------------------------
# 1. PROJECT ROOT
# -----------------------------------------------------

# $PSScriptRoot = PowerShell ka built-in automatic variable.
# Ismein us folder ka path hota hai jahan ye script rakhi hai.
# Isliye manually C:\xampp\htdocs... likhne ki zaroorat nahi.

$projectRoot = $PSScriptRoot


# -----------------------------------------------------
# 2. FOLDERS
# -----------------------------------------------------

# Ye array CRM ke tamam required folders contain karta hai.
# foreach = PowerShell ka built-in loop hai.
# Har folder ko one-by-one create kiya jayega.

$folders = @(

    "app"
    "app\Core"
    "app\Database"
    "app\Auth"
    "app\Models"
    "app\Repositories"
    "app\Services"
    "app\Workflows"
    "app\Security"

    "bootstrap"

    "config"

    "database"
    "database\migrations"
    "database\seeders"

    "public"
    "public\assets"
    "public\assets\css"
    "public\assets\js"
    "public\assets\images"
    "public\admin"

    "resources"
    "resources\views"
    "resources\views\layouts"
    "resources\views\dashboard"
    "resources\views\clients"
    "resources\views\services"
    "resources\views\workflows"
    "resources\views\documents"
    "resources\views\billing"
    "resources\views\admin"

    "resources\templates"
    "resources\templates\emails"
    "resources\templates\invoices"
    "resources\templates\documents"

    "storage"
    "storage\logs"
    "storage\uploads"
    "storage\uploads\clients"
    "storage\uploads\cases"
    "storage\uploads\documents"
    "storage\generated"
    "storage\temp"

    "routes"

    "tools"
    "tools\import"
    "tools\backup"
    "tools\maintenance"
)


# -----------------------------------------------------
# 3. CREATE FOLDERS
# -----------------------------------------------------

foreach ($folder in $folders) {

    # Join-Path = PowerShell built-in command.
    # Ye project root aur folder name ko safely combine karta hai.

    $fullPath = Join-Path $projectRoot $folder


    # Test-Path = PowerShell built-in command.
    # Check karta hai ke folder already exist karta hai ya nahi.

    if (-not (Test-Path $fullPath)) {

        # New-Item = PowerShell built-in command.
        # Directory create karta hai.

        New-Item `
            -Path $fullPath `
            -ItemType Directory `
            -Force | Out-Null

        Write-Host "Created folder: $folder"

    }
    else {

        Write-Host "Already exists: $folder"

    }
}


# -----------------------------------------------------
# 4. FILE LIST
# -----------------------------------------------------

# Ab hum required files ki list banate hain.
# Important:
# Ye script existing files ko overwrite nahi karegi.

$files = @(

    # Core
    "app\Core\App.php"
    "app\Core\Container.php"
    "app\Core\ErrorHandler.php"
    "app\Core\Logger.php"
    "app\Core\Request.php"
    "app\Core\Response.php"
    "app\Core\Session.php"
    "app\Core\Url.php"
    "app\Core\Validator.php"

    # Database
    "app\Database\Database.php"
    "app\Database\Query.php"
    "app\Database\Transaction.php"

    # Authentication
    "app\Auth\Auth.php"
    "app\Auth\Login.php"
    "app\Auth\Logout.php"
    "app\Auth\Permission.php"

    # Models
    "app\Models\User.php"
    "app\Models\Client.php"
    "app\Models\Service.php"
    "app\Models\CaseModel.php"
    "app\Models\Task.php"
    "app\Models\Document.php"
    "app\Models\Invoice.php"
    "app\Models\Payment.php"
    "app\Models\Activity.php"

    # Repositories
    "app\Repositories\UserRepository.php"
    "app\Repositories\ClientRepository.php"
    "app\Repositories\ServiceRepository.php"
    "app\Repositories\CaseRepository.php"
    "app\Repositories\TaskRepository.php"
    "app\Repositories\DocumentRepository.php"
    "app\Repositories\InvoiceRepository.php"
    "app\Repositories\ActivityRepository.php"

    # Services
    "app\Services\ClientService.php"
    "app\Services\ServiceManager.php"
    "app\Services\CaseService.php"
    "app\Services\DocumentService.php"
    "app\Services\InvoiceService.php"
    "app\Services\NotificationService.php"
    "app\Services\AuditService.php"

    # Workflow / Automation
    "app\Workflows\WorkflowEngine.php"
    "app\Workflows\WorkflowStep.php"
    "app\Workflows\WorkflowRule.php"
    "app\Workflows\AutomationEngine.php"
    "app\Workflows\AutomationRule.php"

    # Security
    "app\Security\Csrf.php"
    "app\Security\AccessControl.php"
    "app\Security\FileSecurity.php"
    "app\Security\AuditLogger.php"

    # Bootstrap
    "bootstrap\app.php"

    # Configuration
    "config\app.php"
    "config\database.php"
    "config\services.php"
    "config\permissions.php"
    "config\security.php"

    # Database
    "database\database.sql"

    # Migrations
    "database\migrations\001_users.sql"
    "database\migrations\002_clients.sql"
    "database\migrations\003_services.sql"
    "database\migrations\004_cases.sql"
    "database\migrations\005_workflows.sql"
    "database\migrations\006_tasks.sql"
    "database\migrations\007_documents.sql"
    "database\migrations\008_invoices.sql"
    "database\migrations\009_payments.sql"
    "database\migrations\010_notifications.sql"
    "database\migrations\011_audit_logs.sql"

    # Seeders
    "database\seeders\users.sql"
    "database\seeders\services.sql"
    "database\seeders\workflow_templates.sql"

    # Public pages
    "public\index.php"
    "public\login.php"
    "public\logout.php"
    "public\dashboard.php"

    "public\clients.php"
    "public\client-view.php"
    "public\client-create.php"
    "public\client-edit.php"

    "public\services.php"
    "public\service-intake.php"
    "public\service-workflow.php"
    "public\service-case.php"

    "public\tasks.php"
    "public\documents.php"
    "public\invoices.php"
    "public\payments.php"

    "public\notifications.php"
    "public\activity.php"

    "public\reports.php"
    "public\automation.php"

    # Admin
    "public\admin\index.php"
    "public\admin\users.php"
    "public\admin\roles.php"
    "public\admin\services.php"
    "public\admin\workflows.php"
    "public\admin\settings.php"

    # CSS
    "public\assets\css\app.css"
    "public\assets\css\login.css"
    "public\assets\css\responsive.css"

    # JavaScript
    "public\assets\js\app.js"
    "public\assets\js\clients.js"
    "public\assets\js\services.js"
    "public\assets\js\workflow.js"
    "public\assets\js\notifications.js"

    # Resources
    "resources\views\layouts\header.php"
    "resources\views\layouts\sidebar.php"
    "resources\views\layouts\topbar.php"
    "resources\views\layouts\footer.php"

    # Routes
    "routes\web.php"
    "routes\api.php"
    "routes\admin.php"

    # Tools
    "tools\import\README.md"
    "tools\backup\README.md"
    "tools\maintenance\README.md"

    # Root files
    ".htaccess"
    ".gitignore"
    "README.md"
)


# -----------------------------------------------------
# 5. CREATE EMPTY FILES
# -----------------------------------------------------

foreach ($file in $files) {

    # Complete file path create karte hain.

    $filePath = Join-Path $projectRoot $file


    # Agar file already exist nahi karti tab hi create karein.

    if (-not (Test-Path $filePath)) {

        # New-Item = PowerShell built-in.
        # Empty file create karta hai.

        New-Item `
            -Path $filePath `
            -ItemType File `
            -Force | Out-Null

        Write-Host "Created file: $file"

    }
    else {

        # Existing code ko overwrite nahi karna.
        Write-Host "Kept existing file: $file"

    }
}


# -----------------------------------------------------
# 6. STORAGE SECURITY FILES
# -----------------------------------------------------

# Sensitive uploads ko direct browser access se protect
# karne ke liye future mein server-level protection bhi
# use karenge.

$storageFolders = @(
    "storage"
    "storage\logs"
    "storage\uploads"
    "storage\generated"
    "storage\temp"
)

foreach ($folder in $storageFolders) {

    $gitKeep = Join-Path `
        (Join-Path $projectRoot $folder) `
        ".gitkeep"

    if (-not (Test-Path $gitKeep)) {

        New-Item `
            -Path $gitKeep `
            -ItemType File `
            -Force | Out-Null

    }
}


# -----------------------------------------------------
# 7. COMPLETION MESSAGE
# -----------------------------------------------------

Write-Host ""
Write-Host "====================================================="
Write-Host " SPS CRM PROJECT STRUCTURE READY"
Write-Host "====================================================="
Write-Host ""
Write-Host "Project:"
Write-Host $projectRoot
Write-Host ""
Write-Host "Existing files were NOT overwritten."
Write-Host ""
Write-Host "Next:"
Write-Host "1. Open the project in VS Code."
Write-Host "2. Review the created folders."
Write-Host "3. Start Apache + MySQL in XAMPP."
Write-Host "4. We will then fill the files with final code."
Write-Host ""
Write-Host "====================================================="