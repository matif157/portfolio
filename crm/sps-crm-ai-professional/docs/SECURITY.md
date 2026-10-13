# Security Notes
- Never store plaintext passwords.
- Never place secrets, SSNs, payment credentials or secure-area values in activities.
- Use prepared statements only.
- All state-changing browser requests require CSRF validation.
- Private documents are stored under storage/private and streamed through authorized controllers.
- Login, logout, failed login, billing access and secure-area access are security events.
- Use HTTPS in production, secure cookies, SameSite=Lax/Strict as appropriate and rotate sessions after privilege changes.
