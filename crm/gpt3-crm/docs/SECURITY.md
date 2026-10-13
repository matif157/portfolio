# Security model
- Passwords are hashed with `password_hash`.
- Sessions regenerate on login and use HttpOnly/SameSite cookies.
- State-changing POST actions require CSRF.
- PDO prepared statements are used.
- Sensitive bank/card fields are represented as cipher text in normalized tables.
- Credit-card CVV should not be retained in a production payment environment; the legacy importer stores legacy data in staging only and must be secured/restricted before production use.
- Secure-area/billing/activity actions use separate permission stages.
- Legal/professional submissions require approval gates.
