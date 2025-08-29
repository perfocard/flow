<?php

namespace Perfocard\Flow;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Perfocard\Flow\Models\Status;
use Throwable;

class Compressor
{
    protected static function getRemoteDisk(): FilesystemAdapter
    {
        return Storage::disk(config('flow.compression.disk.remote'));
    }

    protected static function getTempDisk(): FilesystemAdapter
    {
        return Storage::disk(config('flow.compression.disk.temp'));
    }

    protected static function getCompressedFilePath(Status $status): string
    {
        return 'Status/'.$status->created_at->format('Ym/d/H').'.zip';
    }

    protected static function getPayloadFilePath(Status $status): string
    {
        return $status->created_at->format('Y/m/d_').$status->statusable->id.$status->created_at->format('_Y_m_d').'.txt';
    }

    public static function compress(Status $status): Status
    {
        if ($status->extracted_at) {
            $status->update([
                'extracted_at' => null,
                'payload' => null,
                'compressed_at' => Carbon::now(),
            ]);

            return $status;
        }

        $compressedFilePath = self::getCompressedFilePath($status);
        $payloadFilePath = self::getPayloadFilePath($status);

        if (self::getRemoteDisk()->exists($compressedFilePath)) {
            self::getTempDisk()->writeStream(
                $compressedFilePath,
                self::getRemoteDisk()->readStream($compressedFilePath),
            );
        }

        try {
            // Create directory if it doesn't exist
            $directory = dirname(self::getTempDisk()->path($compressedFilePath));
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $zip = Zip::instance()->create(
                self::getTempDisk()->path($compressedFilePath),
            );

            $zip->addFromString(
                name: $payloadFilePath,
                content: $status->payload ?: '',
            );

            $zip->close();

            self::getRemoteDisk()->writeStream(
                $compressedFilePath,
                self::getTempDisk()->readStream($compressedFilePath),
            );

            self::getTempDisk()->delete($compressedFilePath);

            $status->update([
                'payload' => null,
                'compressed_at' => Carbon::now(),
            ]);
        } catch (Throwable $exception) {
            self::getTempDisk()->delete($compressedFilePath);

            throw $exception;
        }

        return $status;
    }

    public static function extract(Status $status): Status
    {
        $compressedFilePath = self::getCompressedFilePath($status);
        $payloadFilePath = self::getPayloadFilePath($status);

        self::getTempDisk()->writeStream(
            $compressedFilePath,
            self::getRemoteDisk()->readStream($compressedFilePath),
        );

        try {
            // Create directory if it doesn't exist
            $directory = dirname(self::getTempDisk()->path($compressedFilePath));
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $zip = Zip::instance()->open(
                self::getTempDisk()->path($compressedFilePath),
            );

            $zip->extract(
                destination: self::getTempDisk()->path(''),
                files: [$payloadFilePath],
            );

            $zip->close();

            $status->update([
                'payload' => self::getTempDisk()->get($payloadFilePath),
                'extracted_at' => Carbon::now(),
            ]);

            self::getTempDisk()->delete($payloadFilePath);
            self::getTempDisk()->delete($compressedFilePath);
        } catch (Throwable $exception) {
            self::getTempDisk()->delete($payloadFilePath);
            self::getTempDisk()->delete($compressedFilePath);

            throw $exception;
        }

        return $status;
    }
}
