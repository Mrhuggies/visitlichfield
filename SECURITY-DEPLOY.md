# Deploying the security fixes (Oct 2026)

1. **Rotate the database password** (the old one is in git history and in the sibling `api` repo).
   Create `visitlichfield.config.php` OUTSIDE the web root (see `api/config.local.example.php`;
   default location is three directories above `api/`, or set `VISITLICHFIELD_CONFIG`).
   The site will not connect to the DB until this file (or DB_USER/DB_PASS env vars) exists.
2. Delete from the server if present: `api/generate-demo-data.php`, `api/clear-rate-limits.php`.
3. Upload the new `.htaccess` files (root, `api/`, `uploads/drivers/`). If images stop loading,
   the vhost's AllowOverride is too strict for the uploads rule: tell me and we'll move it to vhost.conf.
4. Behaviour changes to expect:
   - Email verification no longer activates a driver; an admin must Approve them in Admin > Drivers.
   - Everyone is signed out once (rate limiter, login and token reading changed).
   - Tour updates (`tours.php` POST) need the driver's token and ownership.
   - Analysts can read everything but can no longer approve/delete drivers, change tours,
     delete feedback or reset passwords; that needs the `admin` role.
   - Tokens are sent as `X-Admin-Token` / `X-Driver-Token` headers (js/auth-shim.js does this for
     the existing pages). The `?token=` fallback is still accepted for old clients; remove it from
     `requestToken()` once nothing uses it.
5. Run `sql/schema-additions.sql`, then restrict the DB user to DML only.
6. Set `TRUSTED_PROXIES` only if a proxy/CDN sits in front of Apache.
