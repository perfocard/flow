# Perfocard Flow

[![Packagist Version](https://img.shields.io/packagist/v/perfocard/flow.svg)](https://packagist.org/packages/perfocard/flow) [![Packagist Downloads](https://img.shields.io/packagist/dt/perfocard/flow.svg)](https://packagist.org/packages/perfocard/flow) [![License](https://img.shields.io/packagist/l/perfocard/flow.svg)](https://packagist.org/packages/perfocard/flow) [![PHP Version](https://img.shields.io/packagist/php-v/perfocard/flow.svg)](https://packagist.org/packages/perfocard/flow)

Perfocard Flow is a lightweight, extensible package for managing the statuses of business objects and archiving large payloads in Laravel projects.

Overview:

- Centralized enum-based status model and lifecycle management for business objects.
- Automatic archiving of large payloads to remote storage (ZIP) to reduce database size, with restore and purge capabilities.
- Integration with Laravel Nova: built-in resources, a status field, actions (Compress / Restore / Purge / Defibrillate) and universal metrics.
- Support for asynchronous processing via Tasks, Endpoints and queued listeners/jobs; includes stubs for quick setup.
- Installer that publishes configuration and stubs, plus scheduler commands (`flow:compress`, `flow:purge`).

Benefits:

- Clean architecture for handling long-running and failed processes (defibrillation + events).
- Reduced database size through payload archiving.
- Ready-made Nova integration to speed up administrative workflows.
- Task/Endpoint structure that simplifies testing and scaling.

Use cases:

- When object statuses require complex business flows.
- When payloads grow large and bloat the database.
- When interactive administration via Nova is needed.

## Contents

- [Perfocard Flow](#perfocard-flow)
  - [Contents](#contents)
  - [1. Installation](#1-installation)
    - [Package installation](#package-installation)
    - [Run the package installer (publishes config and stubs)](#run-the-package-installer-publishes-config-and-stubs)
    - [Migrations](#migrations)
  - [2. Configuration](#2-configuration)
    - [Base Nova Resource](#base-nova-resource)
    - [Registering the Nova resource](#registering-the-nova-resource)
    - [Disks for compression](#disks-for-compression)
    - [Scheduler](#scheduler)
  - [3. Usage](#3-usage)
    - [3.1 Generating a model and migration](#31-generating-a-model-and-migration)
      - [Example migration](#example-migration)
    - [3.2 The Document model](#32-the-document-model)
    - [3.3 Generating a status](#33-generating-a-status)
    - [3.4 Example implementation of DocumentStatus](#34-example-implementation-of-documentstatus)
    - [3.5 Adding casts to the model](#35-adding-casts-to-the-model)
    - [3.6 Generating an event](#36-generating-an-event)
    - [3.7 Creating a listener](#37-creating-a-listener)
    - [3.8 Using a Task](#38-using-a-task)
    - [3.9 Using an Endpoint](#39-using-an-endpoint)
      - [Example of using an Endpoint in a listener](#example-of-using-an-endpoint-in-a-listener)
  - [4. Laravel Nova integration](#4-laravel-nova-integration)
    - [4.1 Status resource](#41-status-resource)
    - [4.2 Status field](#42-status-field)
    - [4.3 Actions](#43-actions)
      - [Defibrillation](#defibrillation)
      - [Programmatic defibrillation](#programmatic-defibrillation)
      - [Archiving payloads](#archiving-payloads)
      - [Restoring payloads](#restoring-payloads)
      - [Purging payloads](#purging-payloads)
    - [4.4 Metrics](#44-metrics)
      - [NewItems](#newitems)
      - [ItemsPerDay](#itemsperday)
      - [ItemsByEnum](#itemsbyenum)

## 1. Installation

### Package installation

```bash
composer require perfocard/flow
```

### Run the package installer (publishes config and stubs)

Note: the installer publishes configuration and stub files into your application. If you've previously published any of these stubs, the installer will not overwrite them. Rename or remove the existing stub files in your project before running the installer to allow the package to publish its versions.

The package publishes the following stubs:

- `endpoint.stub`
- `enum.stub`
- `listener.queued.stub`
- `listener.typed.queued.stub`
- `migration.create.stub`
- `model.stub`
- `status.stub`
- `task.stub`

```bash
php artisan flow:install
```

### Migrations

```bash
php artisan migrate
```

## 2. Configuration

### Base Nova Resource

In your application make `App\Nova\Resource` extend the package resource instead of the default Nova resource. In `app/Nova/Resource.php` add or change the import and the base class:

```php
use Perfocard\Flow\Nova\Resource as FlowNovaResource;

abstract class Resource extends FlowNovaResource
{
    // ...
}
```

### Registering the Nova resource

Add the `Status` resource in `NovaServiceProvider::resources()` so it appears in Nova (this method is absent by default, so you will most likely need to add it manually):

```php
class NovaServiceProvider extends NovaApplicationServiceProvider
{
    // ... other methods

    public function resources()
    {
        parent::resources();

        Nova::resources([
            \Perfocard\Flow\Nova\Resources\Status::class,
        ]);
    }
}
```

### Disks for compression

In `config/flow.php` specify the disks used for compression:

- `compression.disk.remote` — the disk for storing finished ZIP archives (e.g. `s3`).
- `compression.disk.temp` — a temporary disk for creating archives (e.g. `local`). You can define a separate disk for temporary files if needed.

⚠️ Make sure these disks are declared in `config/filesystems.php` and have read/write permissions. Do not use the same disk for both `remote` and `temp`.

**Example .env variables**

```env
FLOW_COMPRESSION_DISK_REMOTE=s3
FLOW_COMPRESSION_DISK_TEMP=local
FLOW_COMPRESSION_TIMEOUT=2880
FLOW_PURGE_TIMEOUT=2880
```

### Scheduler

Add scheduled tasks to automatically run the package commands. This is needed to save database space: payloads from the statuses table will be moved to the remote filesystem and archived there automatically.

```php

// routes/console.php

use Illuminate\Support\Facades\Schedule;

Schedule::command('flow:compress')->everyMinute();
Schedule::command('flow:purge')->everyMinute();
```

Remember: in production you must have cron that runs `php artisan schedule:run` every minute.

## 3. Usage

All examples below use a fictional `Document` model to demonstrate practical scenarios and how the package works.

### 3.1 Generating a model and migration

In Laravel you can generate a model and migration with the standard command:

```bash
php artisan make:model Document -m
```

This command will create the model class `app/Models/Document.php` and a migration file in `database/migrations`.

#### Example migration

```php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // Core field for the Perfocard Flow package
            $table->unsignedTinyInteger('status');

            // Other fields
            $table->string('title');
            $table->text('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
```

### 3.2 The Document model

When you generate the model using the published package stub, the generated model already extends the package base model `Perfocard\\Flow\\Models\\FlowModel`. No manual change is required.

```php
// app/Models/Document.php

use Perfocard\Flow\Models\FlowModel;

class Document extends FlowModel
{
    protected $fillable = [
        'status',
        'title',
        'content',
    ];
}
```

> Using `FlowModel` enables the package to manage statuses and automatically integrate the business process into your model.

### 3.3 Generating a status

For the `Document` model you need to create a corresponding status enum class. Generate it using the command:

```bash
php artisan make:status DocumentStatus
```

This will generate `app/Models/DocumentStatus.php`, where you will describe all possible statuses and their transitions. The file will be located next to the `Document` model.

### 3.4 Example implementation of DocumentStatus

```php
// app/Models/DocumentStatus.php

use Perfocard\Flow\Contracts\ShouldBeDefibrillated;
use Perfocard\Flow\Contracts\ShouldDispatchEvents;
use Perfocard\Flow\Contracts\BackedEnum;
use Perfocard\Flow\Traits\IsBackedEnum;

enum DocumentStatus: int implements BackedEnum, ShouldBeDefibrillated, ShouldDispatchEvents
{
    use IsBackedEnum;

    case QUEUED = 0;
    case PROCESSING = 1;
    case ERROR = 2;
    case COMPLETE = 3;

    // ... other methods, not necessary for this example
}
```

### 3.5 Adding casts to the model

After generating the status enum you should add the attributes and the `casts()` method to the `Document` model. This sets a default status and casts the attribute to the enum class:

```php
// app/Models/Document.php

class Document extends FlowModel
{
    // ... other properties and methods

    protected $attributes = [
        'status' => DocumentStatus::QUEUED,
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
        ];
    }
}
```

### 3.6 Generating an event

You need an event to be dispatched when a document is queued. Use:

```bash
php artisan make:event DocumentQueued
```

This will create `app/Events/DocumentQueued.php`. Add the `Document` model to the generated event constructor:

```php
// app/Events/DocumentQueued.php

use App\Models\Document;

class DocumentQueued
{
    public function __construct(
        public Document $document,
    ) {}
}
```

Also register this event in the `DocumentStatus` class in the `events()` method. Example:

```php
// app/Models/DocumentStatus.php

use App\Events\DocumentQueued;

enum DocumentStatus: int
{
    // ... other methods and properties

    public function events(): array
    {
        return match ($this) {
            self::QUEUED => [
                DocumentQueued::class,
            ],
            default => [],
        };
    }
}
```

### 3.7 Creating a listener

When a document is created it will dispatch the `DocumentQueued` event. You need a listener to handle this event.

Run:

```bash
php artisan make:listener Document/ProcessQueuedDocument --event=DocumentQueued --queued
```

This will generate `app/Listeners/Document/ProcessQueuedDocument.php`. The listener will listen for the event and run in the queue. Example implementation:

```php
// app/Listeners/Document/ProcessQueuedDocument.php

use App\Models\DocumentStatus;

class ProcessQueuedDocument
{
    public function handle(DocumentQueued $event): void
    {
        $event->document->setStatusAndSave(
            status: DocumentStatus::PROCESSING,
        );

        $event->document->content = 'Lorem ipsum dolor sit amet.';

        $event->document->setStatusAndSave(
            status: DocumentStatus::COMPLETE,
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(DocumentQueued $event, Throwable $exception): void
    {
        parent::saveException(
            resource: $event->document,
            status: DocumentStatus::ERROR,
            exception: $exception,
        );

        throw $exception;
    }
}
```

### 3.8 Using a Task

An alternative approach is to move processing logic to a separate Task class.

Generate the class:

```bash
php artisan make:task DocumentGenerator -m Document
```

This will create `app/Tasks/DocumentGenerator.php`.

Example implementation:

```php
// app/Tasks/DocumentGenerator.php

use App\Models\DocumentStatus;

class DocumentGenerator implements HandledTask
{
    public function processing(FlowModel $model): BackedEnum
    {
        return DocumentStatus::PROCESSING;
    }

    public function complete(FlowModel $model): BackedEnum
    {
        return DocumentStatus::COMPLETE;
    }

    public function handle(FlowModel $model): FlowModel
    {
        $model->content = 'Lorem ipsum dolor sit amet.';
        return $model;
    }
}
```

After creating `DocumentGenerator` you can use it in the listener instead of inline logic. Example:

```php
// app/Listeners/Document/ProcessQueuedDocument.php

use Perfocard\Flow\Task;
use App\Tasks\DocumentGenerator;

class ProcessQueuedDocument
{
    public function handle(DocumentQueued $event): void
    {
        Task::for(DocumentGenerator::class)
            ->on($event->document)
            ->dispatch();
    }

    public function failed(DocumentQueued $event, Throwable $exception): void
    {
        parent::saveException(
            resource: $event->document,
            status: DocumentStatus::ERROR,
            exception: $exception,
        );

        throw $exception;
    }
}
```

### 3.9 Using an Endpoint

Another implementation option is to use an Endpoint when document content must be fetched from an external HTTP service. Generate an endpoint class:

```bash
php artisan make:endpoint ExternalDocumentContent -m Document
```

This will create `app/Endpoints/ExternalDocumentContent.php`.

Example implementation:

```php
// app/Endpoints/ExternalDocumentContent.php

use App\Models\DocumentStatus;

class ExternalDocumentContent
{
    public function processing(): BackedEnum
    {
        return DocumentStatus::PROCESSING;
    }

    public function complete(): BackedEnum
    {
        return DocumentStatus::COMPLETE;
    }

    public function method(FlowModel $model): string
    {
        return 'POST';
    }

    public function url(FlowModel $model): string
    {
        return 'https://example.com/api/generate';
    }

    public function buildPayload(FlowModel $model): array
    {
        return [
            'title' => $model->title,
        ];
    }

    public function processResponse(Response $response, FlowModel $model): FlowModel
    {
        $model->content = $response->json('content');
        return $model;
    }
}
```

#### Example of using an Endpoint in a listener

After creating `ExternalDocumentContent` you can use it in a listener instead of inline logic. Example:

```php
// app/Listeners/Document/ProcessQueuedDocument.php

use Perfocard\Flow\Endpoint;
use App\Endpoints\ExternalDocumentContent;

class ProcessQueuedDocument extends ThrowableListener
{
    public function handle(DocumentQueued $event): void
    {
        Endpoint::for(ExternalDocumentContent::class)
            ->on($event->document)
            ->dispatch();
    }

    public function failed(DocumentQueued $event, Throwable $exception): void
    {
        parent::saveException(
            resource: $event->document,
            status: DocumentStatus::ERROR,
            exception: $exception,
        );

        throw $exception;
    }
}
```

## 4. Laravel Nova integration

This package ships with built-in support for Laravel Nova and provides resources, fields, filters and some universal metrics out of the box.

### 4.1 Status resource

All statuses for different models are stored in a separate `statuses` table. The package includes a model and a Nova resource `Status` and supports polymorphic relations with your models.

If you need to view all statuses in Laravel Nova, enable it in the configuration:

```php
// config/flow.php

return [
    'status' => [
        // ... other settings

        'nova_navigation' => true,
    ],
];
```

After that the Status resource will appear in your Nova panel.

### 4.2 Status field

When installing the package a stub for the Nova resource is published or replaced. As a result, newly generated Nova resources include modified methods.

When generating a resource run:

```bash
php artisan nova:resource Document
```

Note the fields method — instead of directly returning an array it calls the parent `mergeFields` method:

```php
// app/Nova/Document.php

class Document extends Resource
{
    public function fields(NovaRequest $request)
    {
        return parent::mergeFields([
            //
        ]);
    }
}
```

This allows the status field to be automatically displayed in Nova without manual definition. This is the default behavior and should work for most cases. In special cases you can return a regular array to revert to Nova's default behavior.

### 4.3 Actions

The package provides several Nova actions out of the box.

#### Defibrillation

This feature restarts the business process. To use it, implement the `defibrillation` method in your status enum and ensure it implements the `ShouldBeDefibrillated` contract:

```php
// app/Models/DocumentStatus.php

use Perfocard\Flow\Contracts\ShouldBeDefibrillated;

enum DocumentStatus: int implements ShouldBeDefibrillated
{
    public function defibrillate(): ?self
    {
        return match ($this) {
            self::ERROR => self::QUEUED,
            default => null,
        };
    }
}
```

Now, if a resource has the `ERROR` status, Nova will show the `Defibrillate` action on that resource and executing it will change the status from `ERROR` to `QUEUED` and the business process will be restarted.

#### Programmatic defibrillation

You can also defibrillate programmatically:

```php
use App\Models\Document;
use App\Models\DocumentStatus;

$document = Document::where('status', DocumentStatus::ERROR)->first();

// defibrillate
$document->defibrillate();
```

#### Archiving payloads

Statuses contain a `payload` field which can grow large over time (for example when using an Endpoint, both the request and response can be stored). To avoid large database growth use the archiving feature.

If you configure the scheduler to run `php artisan flow:compress` regularly this process will run automatically.

If you need to archive payloads manually (for example before the scheduled compress run) use the `Compress` action in Nova. This will archive the payload immediately. The action is available on a specific status resource and only when the payload has not yet been archived.

#### Restoring payloads

Once payloads are archived they are no longer present in the database field. To restore them for analysis use the `Restore` action. This temporarily restores the payload into the database field.

#### Purging payloads

After restoring a payload it again occupies database space. Use the `Purge` action to remove the payload permanently when it is no longer needed.

If you schedule `php artisan flow:purge`, purging will run automatically.

### 4.4 Metrics

The package ships with three universal metrics. Below we describe each of them.

#### NewItems

A universal metric implementing the `Laravel\Nova\Metrics\Value` metric.

Example usage:

```php
// app/Nova/Document.php

use Perfocard\Flow\Nova\Metrics\NewItems;

class Document extends Resource
{
    public function cards(NovaRequest $request)
    {
        return [
            NewItems::make(__('New documents'), self::$model),
        ];
    }
}
```

#### ItemsPerDay

A universal metric implementing the `Laravel\Nova\Metrics\Trend` metric.

Example usage:

```php
// app/Nova/Document.php

use Perfocard\Flow\Nova\Metrics\ItemsPerDay;

class Document extends Resource
{
    public function cards(NovaRequest $request)
    {
        return [
            ItemsPerDay::make(__('Documents per day'), self::$model),
        ];
    }
}
```

#### ItemsByEnum

A universal metric implementing the `Laravel\Nova\Metrics\Partition` metric.

Example usage:

```php
// app/Nova/Document.php

use Perfocard\Flow\Nova\Metrics\ItemsByEnum;
use App\Models\DocumentStatus;

class Document extends Resource
{
    public function cards(NovaRequest $request)
    {
        return [
            ItemsByEnum::make(__('Documents by status'), self::$model, DocumentStatus::class, 'status'),
        ];
    }
}
```
