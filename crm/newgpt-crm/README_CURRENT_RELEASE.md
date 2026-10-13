# SPS CRM — Current Integrated Replacement Release

Target local installation:

C:\xampp\htdocs\newgpt\

Database:

gpt

## Replace

Extract this ZIP directly into the existing `newgpt` folder and choose **Replace existing files**.

## Login

http://localhost/newgpt/login.php

## Password recovery

http://localhost/newgpt/setup_password.php

Setup key:
SPS-LOCAL-SETUP-2026

After a successful password reset/login, delete `setup_password.php` from the local CRM folder.

## Phase 6 database upgrade

After logging in, open:

http://localhost/newgpt/upgrade_phase6.php

Apply the additive Phase 6 migration if it has not already been applied.

## Latest UI additions

- Futuristic 2050 visual theme
- Shared application sidebar
- Invoice command center
- Multi-line invoice creation
- Printable invoice template
- Payment ledger
- Layout settings/customization
- Sidebar shell assets
- Responsive UI assets
