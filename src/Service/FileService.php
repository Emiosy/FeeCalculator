<?php

namespace App\Service;

use App\Exception\FileException;
use Symfony\Component\Filesystem\Filesystem;

class FileService
{
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Check if file exist and has a good extension.
     *
     * @param string $filePath  Path to file for parse
     * @param string $extension Extension to validate
     *
     * @throws FileException
     */
    public function checkFileAndExtension(string $filePath, string $extension): bool
    {
        try {
            $this->checkFileExistAtPath($filePath);
            $this->checkFileExtension($filePath, $extension);
        } catch (FileException $e) {
            throw new FileException($e->getMessage());
        }

        return true;
    }

    /**
     * Check if file exist at passed $filePath.
     *
     * @param string $filePath File path for check
     *
     * @throws FileException
     */
    private function checkFileExistAtPath(string $filePath): void
    {
        if (!$this->filesystem->exists($filePath)) {
            throw new FileException("File not exist at path: $filePath");
        }
    }

    /**
     * Check file extension of file passed at $filePath.
     *
     * @param string $filePath  Filepath with file to check
     * @param string $extension Extension to compare with passed file
     *
     * @throws FileException
     */
    private function checkFileExtension(string $filePath, string $extension): void
    {
        if (strtolower(pathinfo($filePath)['extension']) !== strtolower($extension)) {
            throw new FileException("Wrong extension (not .$extension) of file: $filePath");
        }
    }
}
