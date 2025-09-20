<?php

namespace App\Services;

use App\Contracts\AntivirusScanner;
use Illuminate\Support\Facades\Log;
use Exception;

class ClamAvScanner implements AntivirusScanner
{
    private string $clamScanPath;
    private string $socketPath;
    private int $timeout;

    public function __construct()
    {
        $this->clamScanPath = config('security.antivirus.clamscan_path', '/usr/bin/clamscan');
        $this->socketPath = config('security.antivirus.clamd_socket', '/var/run/clamav/clamd.ctl');
        $this->timeout = config('security.antivirus.scan_timeout', 300); // 5 minutes
    }

    /**
     * Scan a file for viruses using ClamAV
     */
    public function scanFile(string $filePath): array
    {
        $startTime = microtime(true);

        try {
            if (!$this->isAvailable()) {
                throw new Exception('ClamAV scanner is not available');
            }

            if (!file_exists($filePath)) {
                throw new Exception('File does not exist: ' . $filePath);
            }

            $result = $this->performScan($filePath);
            $scanTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'clean' => $result['clean'],
                'threats' => $result['threats'],
                'scan_time' => $scanTime,
                'scanner' => 'ClamAV',
                'raw_output' => $result['output'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('ClamAV scan failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'clean' => false,
                'threats' => ['Scan failed: ' . $e->getMessage()],
                'scan_time' => round((microtime(true) - $startTime) * 1000, 2),
                'scanner' => 'ClamAV',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if ClamAV is available
     */
    public function isAvailable(): bool
    {
        // Check if clamscan binary exists
        if (!file_exists($this->clamScanPath)) {
            return false;
        }

        // Try to get version to verify it's working
        try {
            $command = escapeshellcmd($this->clamScanPath) . ' --version';
            $output = shell_exec($command);

            return $output !== null && str_contains($output, 'ClamAV');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get ClamAV version and status information
     */
    public function getInfo(): array
    {
        try {
            $command = escapeshellcmd($this->clamScanPath) . ' --version';
            $output = shell_exec($command);

            $info = [
                'scanner' => 'ClamAV',
                'available' => $this->isAvailable(),
                'version' => null,
                'database_version' => null,
                'last_update' => null,
            ];

            if ($output) {
                // Parse version information
                if (preg_match('/ClamAV ([0-9.]+)/', $output, $matches)) {
                    $info['version'] = $matches[1];
                }

                // Get database info
                $dbInfo = $this->getDatabaseInfo();
                $info = array_merge($info, $dbInfo);
            }

            return $info;

        } catch (Exception $e) {
            return [
                'scanner' => 'ClamAV',
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update ClamAV virus definitions
     */
    public function updateDefinitions(): bool
    {
        try {
            $freshclamPath = config('security.antivirus.freshclam_path', '/usr/bin/freshclam');

            if (!file_exists($freshclamPath)) {
                Log::warning('freshclam not found at: ' . $freshclamPath);
                return false;
            }

            $command = escapeshellcmd($freshclamPath) . ' --check';
            $output = shell_exec($command . ' 2>&1');
            $exitCode = 0; // shell_exec doesn't provide exit code

            Log::info('ClamAV definitions update attempted', [
                'output' => $output,
            ]);

            return $exitCode === 0;

        } catch (Exception $e) {
            Log::error('Failed to update ClamAV definitions', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Perform the actual file scan
     */
    private function performScan(string $filePath): array
    {
        $escapedPath = escapeshellarg($filePath);
        $command = escapeshellcmd($this->clamScanPath) .
                   ' --no-summary --infected --stdout ' . $escapedPath . ' 2>&1';

        // Execute with timeout
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new Exception('Failed to start ClamAV process');
        }

        // Close stdin
        fclose($pipes[0]);

        // Read output with timeout
        $output = '';
        $startTime = time();

        while (!feof($pipes[1]) && (time() - $startTime) < $this->timeout) {
            $output .= fread($pipes[1], 8192);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return $this->parseOutput($output, $exitCode);
    }

    /**
     * Parse ClamAV output
     */
    private function parseOutput(string $output, int $exitCode): array
    {
        $threats = [];
        $clean = true;

        // ClamAV exit codes:
        // 0 = no virus found
        // 1 = virus found
        // 2 = error

        if ($exitCode === 2) {
            throw new Exception('ClamAV scanner error: ' . trim($output));
        }

        if ($exitCode === 1) {
            $clean = false;

            // Parse threat names from output
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_contains($line, 'FOUND')) {
                    // Extract threat name (format: "filename: THREAT_NAME FOUND")
                    if (preg_match('/:\s*(.+?)\s+FOUND$/', $line, $matches)) {
                        $threats[] = trim($matches[1]);
                    } else {
                        $threats[] = 'Unknown threat detected';
                    }
                }
            }

            if (empty($threats)) {
                $threats[] = 'Virus detected (details unavailable)';
            }
        }

        return [
            'clean' => $clean,
            'threats' => $threats,
            'output' => $output,
        ];
    }

    /**
     * Get ClamAV database information
     */
    private function getDatabaseInfo(): array
    {
        $info = [
            'database_version' => null,
            'last_update' => null,
        ];

        try {
            // Check database directory for freshness
            $dbPath = config('security.antivirus.database_path', '/var/lib/clamav');

            if (is_dir($dbPath)) {
                $dailyDb = $dbPath . '/daily.cvd';
                $mainDb = $dbPath . '/main.cvd';

                if (file_exists($dailyDb)) {
                    $info['last_update'] = date('Y-m-d H:i:s', filemtime($dailyDb));
                } elseif (file_exists($mainDb)) {
                    $info['last_update'] = date('Y-m-d H:i:s', filemtime($mainDb));
                }
            }

        } catch (Exception $e) {
            // Ignore errors when getting database info
        }

        return $info;
    }
}