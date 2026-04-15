# API Root Access - Troubleshooting

If `/api` is showing 404 but `/api/index.php` works, try these solutions:

## Solution 1: Use DirectoryIndex (Recommended)

Create or update `.htaccess` in `public_html/api/` to set index.php as directory index:

```apache
DirectoryIndex index.php
RewriteEngine On
```

## Solution 2: Server Configuration

Some servers require the trailing slash. Try:
- `https://elmohaqiqpress.com/api/` (with trailing slash)

## Solution 3: Alternative Access

You can always use:
- `https://elmohaqiqpress.com/api/index.php` - Works directly
- `https://elmohaqiqpress.com/api/health.php` - Health check

The frontend will use specific endpoints anyway, so this is just for testing.

