# Perfocard Flow

A short guide for installing and configuring the `Perfocard\Flow` package.

1. Install the package

```bash
composer require perfocard/flow
```

2. Run the package installer (publishes config and stubs)

```bash
php artisan flow:install
```

3. Run migrations

```bash
php artisan migrate
```

4. Replace the base Nova Resource

In your application, make `App\Nova\Resource` extend the package's Flow Nova Resource instead of the default Nova resource. In `app/Nova/Resource.php` add or change the import:

```php
use Perfocard\Flow\Nova\Resource as FlowNovaResource;

abstract class Resource extends FlowNovaResource
{
    // ...
}
```

5. Register the Nova resource

Add the `Status` resource to `NovaServiceProvider::resources()` so it appears in Nova:

```php
public function resources()
{
    parent::resources();

    Nova::resources([
        \Perfocard\Flow\Nova\Resources\Status::class,
    ]);
}
```

6. Configure disks for compression

In `config/flow.php` set the disks used for compression:

-   `compression.disk.remote` — disk for storing completed ZIP archives (for example `s3`).
-   `compression.disk.temp` — temporary disk used to create archives (for example `local`).

Make sure these disks are defined in `config/filesystems.php` and are writable/readable. Do not use the same disk for both `remote` and `temp`.

Example `.env` variables:

```
FLOW_COMPRESSION_DISK_REMOTE=s3
FLOW_COMPRESSION_DISK_TMP=local
FLOW_COMPRESSION_TIMEOUT=2880
FLOW_PURGE_TIMEOUT=2880
```

7. Scheduler (recommended)

Add scheduled tasks to automatically run compression and purge commands. Example (in `app/Console/Kernel.php`, inside the `schedule` method):

```php
protected function schedule(Schedule $schedule)
{
    // Compress daily at 01:00
    $schedule->command('flow:compress')->dailyAt('01:00');

    // Purge daily at 02:00
    $schedule->command('flow:purge')->dailyAt('02:00');
}
```

Remember: to make the scheduler work in production you must have a cron job calling `php artisan schedule:run` every minute.

8. Notes

-   For local testing, use small payloads and a local temporary disk.
-   If you encounter errors creating temporary files, check directory existence and write permissions on the temporary disk.

If you want, I can add a sample `config/flow.php` snippet in the docs or provide a ready patch for `app/Console/Kernel.php`.
