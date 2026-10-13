# Security baseline

- Use HTTPS in production.
- Keep `.env` and provider credentials outside version control.
- Encrypt integration credentials at rest using a real key-management strategy.
- Use least-privilege database accounts in production.
- Keep sensitive submissions behind approval policies.
- Store audit events for security-sensitive operations.
- Validate file MIME/type/size and store uploads outside the public web root.
- Add CSRF protection to all state-changing browser forms before production.
- Replace demo login with database-backed password authentication and rate limiting.
