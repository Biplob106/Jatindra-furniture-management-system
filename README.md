# Jatindra-furniture-management-system

## PHP upload limits

Order item photos and design images are saved with the order in one request.
The browser shrinks each image to 1600px first, but PHP still has to accept the
batch. Set at least:

    upload_max_filesize = 10M
    post_max_size = 64M
    max_file_uploads = 100

in the `php.ini` used by the web server (check with `php --ini`), then restart
PHP-FPM or `php artisan serve`.
