# Changelog

All notable changes to Gravity CRM will be documented in this file.

## [1.1.0] - 2026-09-21

### Added
- CSRF token validation on login form
- Rate limiting for login attempts
- Account lockout after failed attempts
- Security event logging
- Login attempt tracking in database

### Fixed
- Missing `login_attempts` table schema
- Missing `security_log` table schema  
- Missing `severity` and `session_id` columns in `activities` table
- Missing `failed_login_attempts` and `locked_until` columns in `users` table

### Security
- Added IP-based throttling for brute force protection
- Session regeneration on successful login
- Password hash upgrade on first login

## [1.0.0] - 2026-09-20

### Added
- Initial release
- Client management
- Invoice generation
- Service tracking
- AI assistant integration
- Multi-tenant support
