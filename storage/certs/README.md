# CA certificates (Windows PHP)

PHP needs a CA bundle to verify HTTPS (Apify). Without it you get **cURL error 60**.

## Setup

1. Download the Mozilla bundle:

   https://curl.se/ca/cacert.pem

2. Save as `storage/certs/cacert.pem` (this file is gitignored).

3. Set absolute paths in `php.ini`:

```ini
curl.cainfo = "C:/full/path/to/FindYourInfluencer/storage/certs/cacert.pem"
openssl.cafile = "C:/full/path/to/FindYourInfluencer/storage/certs/cacert.pem"
```

4. Restart `php artisan serve` and `php artisan queue:work`.

The Apify client also falls back to this path (and `%LOCALAPPDATA%\fyi-cacert\cacert.pem`) when `curl.cainfo` is unset.
