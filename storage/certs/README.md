# CA certificates for Windows PHP (curl / openssl)

`cacert.pem` is the Mozilla CA bundle used by PHP to verify HTTPS.

If missing, download:

```
https://curl.se/ca/cacert.pem
```

Place it here, then set in `php.ini`:

```
curl.cainfo = "C:\full\path\to\storage\certs\cacert.pem"
openssl.cafile = "C:\full\path\to\storage\certs\cacert.pem"
```

Restart `php artisan serve` and `php artisan queue:work` after changing php.ini.
