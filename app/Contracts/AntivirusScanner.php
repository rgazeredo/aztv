<?php

namespace App\Contracts;

interface AntivirusScanner
{
    /**
     * Scan a file for viruses and malware
     *
     * @param string $filePath Absolute path to the file to scan
     * @return array Result array with 'clean', 'threats', and 'scan_time' keys
     */
    public function scanFile(string $filePath): array;

    /**
     * Check if the scanner is available and configured
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get scanner version and status information
     *
     * @return array
     */
    public function getInfo(): array;

    /**
     * Update virus definitions if supported
     *
     * @return bool
     */
    public function updateDefinitions(): bool;
}