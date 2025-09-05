<?php

namespace Perfocard\Flow;

use Exception;
use Illuminate\Support\Str;
use Perfocard\Flow\Traits\Instantiatable;
use ZipArchive;

/**
 * ZipArchive toolbox for handling single zip archive.
 *
 * Provides methods for creating, extracting, and managing zip files.
 */
class Zip
{
    use Instantiatable;

    /**
     * Files to skip during extraction.
     */
    private string $skipMode = 'NONE';

    /**
     * Supported skip modes.
     */
    private array $supportedSkipModes = ['HIDDEN', 'ZANYSOFT', 'ALL', 'NONE'];

    /**
     * Mask for the extraction folder.
     */
    private int $mask = 0777;

    /**
     * Internal ZipArchive pointer.
     */
    private ?ZipArchive $zipArchive = null;

    /**
     * Zip file password (for extraction).
     */
    private ?string $password = null;

    /**
     * Current base path.
     */
    private ?string $path = null;

    /**
     * Well known zip status codes.
     */
    private static array $zipStatusCodes = [
        ZipArchive::ER_OK => 'No error',
        ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
        ZipArchive::ER_RENAME => 'Renaming temporary file failed',
        ZipArchive::ER_CLOSE => 'Closing zip archive failed',
        ZipArchive::ER_SEEK => 'Seek error',
        ZipArchive::ER_READ => 'Read error',
        ZipArchive::ER_WRITE => 'Write error',
        ZipArchive::ER_CRC => 'CRC error',
        ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
        ZipArchive::ER_NOENT => 'No such file',
        ZipArchive::ER_EXISTS => 'File already exists',
        ZipArchive::ER_OPEN => 'Can\'t open file',
        ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
        ZipArchive::ER_ZLIB => 'Zlib error',
        ZipArchive::ER_MEMORY => 'Malloc failure',
        ZipArchive::ER_CHANGED => 'Entry has been changed',
        ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
        ZipArchive::ER_EOF => 'Premature EOF',
        ZipArchive::ER_INVAL => 'Invalid argument',
        ZipArchive::ER_NOZIP => 'Not a zip archive',
        ZipArchive::ER_INTERNAL => 'Internal error',
        ZipArchive::ER_INCONS => 'Zip archive inconsistent',
        ZipArchive::ER_REMOVE => 'Can\'t remove file',
        ZipArchive::ER_DELETED => 'Entry has been deleted',
    ];

    /**
     * Create a new Zip instance.
     */
    public function __construct(?string $zipFile = null)
    {
        if ($zipFile) {
            $this->open($zipFile);
        }
    }

    /**
     * Open a zip archive.
     *
     * @return $this
     */
    public function open(string $zipFile): self
    {
        try {
            $this->setArchive(self::openZipFile($zipFile));
        } catch (Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * Check if a zip archive is valid.
     */
    public function check(string $zipFile): bool
    {
        try {
            $zip = self::openZipFile($zipFile, ZipArchive::CHECKCONS);
            $zip->close();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Create a new zip archive.
     *
     * @return $this
     */
    public function create(string $zipFile, bool $overwrite = false): self
    {
        if ($overwrite && ! $this->check($zipFile)) {
            $overwrite = false;
        }
        $overwrite = (bool) $overwrite;
        try {
            if ($overwrite) {
                $this->setArchive(self::openZipFile($zipFile, ZipArchive::OVERWRITE));
            } else {
                $this->setArchive(self::openZipFile($zipFile, ZipArchive::CREATE));
            }
        } catch (Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * Set files to skip during extraction.
     *
     * @return $this
     */
    public function setSkipped(string $mode): self
    {
        $mode = strtoupper($mode);
        if (! in_array($mode, $this->supportedSkipModes)) {
            throw new Exception('Unsupported skip mode');
        }
        $this->skipMode = $mode;

        return $this;
    }

    /**
     * Get current skip mode.
     */
    public function getSkipped(): string
    {
        return $this->skipMode;
    }

    /**
     * Set extraction password.
     *
     * @return $this
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get current extraction password.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set current base path for relative files.
     *
     * @return $this
     */
    public function setPath(string $path): self
    {
        $path = rtrim(str_replace('\\', '/', $path), '/').'/';
        if (! file_exists($path)) {
            throw new Exception('Path does not exist');
        }
        $this->path = $path;

        return $this;
    }

    /**
     * Get current base path.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Set extraction folder mask.
     *
     * @return $this
     */
    public function setMask(int $mask): self
    {
        $mask = ($mask > 0 && $mask <= 0777) ? $mask : 0777;
        $this->mask = $mask;

        return $this;
    }

    /**
     * Get current extraction folder mask.
     */
    public function getMask(): int
    {
        return $this->mask;
    }

    /**
     * Set the current ZipArchive object.
     *
     * @return $this
     */
    public function setArchive(ZipArchive $zip): self
    {
        $this->zipArchive = $zip;

        return $this;
    }

    /**
     * Get current ZipArchive object.
     */
    public function getArchive(): ?ZipArchive
    {
        return $this->zipArchive;
    }

    /**
     * Get a list of files in archive.
     */
    public function listFiles(): array
    {
        $list = [];
        for ($i = 0; $i < $this->zipArchive->numFiles; $i++) {
            $name = $this->zipArchive->getNameIndex($i);
            if ($name === false) {
                throw new Exception(self::getStatus($this->zipArchive->status));
            }
            $list[] = $name;
        }

        return $list;
    }

    /**
     * Check if zip archive has a file.
     */
    public function has(string $file, int $flags = 0): bool
    {
        if (empty($file)) {
            throw new Exception('Invalid file');
        }

        return $this->zipArchive->locateName($file, $flags) !== false;
    }

    /**
     * Extract files from zip archive.
     *
     * @param  array|string|null  $files
     */
    public function extract(string $destination, $files = null): bool
    {
        if (empty($destination)) {
            throw new Exception('Invalid destination path');
        }
        if (! file_exists($destination)) {
            $omask = umask(0);
            $action = mkdir($destination, $this->mask, true);
            umask($omask);
            if ($action === false) {
                throw new Exception('Error creating folder '.$destination);
            }
        }
        if (! is_writable($destination)) {
            throw new Exception('Destination path not writable');
        }
        $fileMatrix = (is_array($files) && count($files) > 0)
            ? $files
            : $this->getArchiveFiles();
        if (! empty($this->password)) {
            $this->zipArchive->setPassword($this->password);
        }
        $extract = $this->zipArchive->extractTo($destination, $fileMatrix);
        if ($extract === false) {
            throw new Exception(self::getStatus($this->zipArchive->status));
        }

        return true;
    }

    /**
     * Add file from string content to zip archive.
     */
    public function addFromString(string $name, string $content): void
    {
        $this->zipArchive->addFromString($name, $content);
    }

    /**
     * Add files to zip archive.
     *
     * @param  string|array  $filePath
     * @return $this
     */
    public function add($filePath, bool $flatroot = false): self
    {
        if (empty($filePath)) {
            throw new Exception(self::getStatus(ZipArchive::ER_NOENT));
        }
        $flatroot = (bool) $flatroot;
        try {
            if (is_array($filePath)) {
                foreach ($filePath as $file) {
                    $this->addItem($file, $flatroot);
                }
            } else {
                $this->addItem($filePath, $flatroot);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * Delete files from zip archive.
     *
     * @param  string|array  $filename
     * @return $this
     */
    public function delete($filename): self
    {
        if (empty($filename)) {
            throw new Exception(self::getStatus(ZipArchive::ER_NOENT));
        }
        try {
            if (is_array($filename)) {
                foreach ($filename as $fileName) {
                    $this->deleteItem($fileName);
                }
            } else {
                $this->deleteItem($filename);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * Close the zip archive.
     */
    public function close(): bool
    {
        if ($this->zipArchive->close() === false) {
            throw new Exception(self::getStatus($this->zipArchive->status));
        }

        return true;
    }

    /**
     * Get a list of files in zip archive before extraction.
     */
    private function getArchiveFiles(): array
    {
        $list = [];
        for ($i = 0; $i < $this->zipArchive->numFiles; $i++) {
            $file = $this->zipArchive->statIndex($i);
            if ($file === false) {
                continue;
            }
            $name = str_replace('\\', '/', $file['name']);
            if ($name[0] == '.' && in_array($this->skipMode, ['HIDDEN', 'ALL'])) {
                continue;
            }
            if ($name[0] == '.' && @$name[1] == '_' && in_array($this->skipMode, ['ZANYSOFT', 'ALL'])) {
                continue;
            }
            $list[] = $name;
        }

        return $list;
    }

    /**
     * Add item to zip archive.
     *
     * @throws Exception
     */
    private function addItem(string $file, bool $flatroot = false, ?string $base = null): void
    {
        if ($this->path && ! Str::startsWith($file, $this->path)) {
            $file = $this->path.$file;
        }

        $real_file = str_replace('\\', '/', realpath($file));

        $real_name = basename($real_file);

        if (! is_null($base)) {
            if ($real_name[0] == '.' && in_array($this->skipMode, ['HIDDEN', 'ALL'])) {
                return;
            }
            if ($real_name[0] == '.' && @$real_name[1] == '_' && in_array($this->skipMode, ['ZANYSOFT', 'ALL'])) {
                return;
            }
        }

        if (is_dir($real_file)) {
            if (! $flatroot) {
                $folder_target = is_null($base) ? $real_name : $base.$real_name;
                $new_folder = $this->zipArchive->addEmptyDir($folder_target);
                if ($new_folder === false) {
                    throw new Exception(self::getStatus($this->zipArchive->status));
                }
            } else {
                $folder_target = null;
            }
            foreach (new \DirectoryIterator($real_file) as $path) {
                if ($path->isDot()) {
                    continue;
                }
                $file_real = $path->getPathname();
                $base = is_null($folder_target) ? null : ($folder_target.'/');
                try {
                    $this->addItem($file_real, false, $base);
                } catch (Exception $e) {
                    throw $e;
                }
            }
        } elseif (is_file($real_file)) {
            $file_target = is_null($base) ? $real_name : $base.$real_name;
            $add_file = $this->zipArchive->addFile($real_file, $file_target);
            if ($add_file === false) {
                throw new Exception(self::getStatus($this->zipArchive->status));
            }
        } else {
            return;
        }
    }

    /**
     * Delete item from zip archive.
     *
     * @throws Exception
     */
    private function deleteItem(string $file): void
    {
        $deleted = $this->zipArchive->deleteName($file);
        if ($deleted === false) {
            throw new Exception(self::getStatus($this->zipArchive->status));
        }
    }

    /**
     * Open a zip file.
     *
     * @throws Exception
     */
    private static function openZipFile(string $zipFile, ?int $flags = null): ZipArchive
    {
        $zip = new ZipArchive;
        $open = is_null($flags)
            ? $zip->open($zipFile)
            : $zip->open($zipFile, $flags);
        if ($open !== true) {
            throw new Exception(self::getStatus($open));
        }

        return $zip;
    }

    /**
     * Get status message from zip status code.
     */
    private static function getStatus(int $code): string
    {
        return self::$zipStatusCodes[$code] ?? sprintf('Unknown status %s', $code);
    }
}
