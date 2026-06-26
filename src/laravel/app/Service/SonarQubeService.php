<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SonarQubeService
{
    protected string $host;

    protected ?string $token;

    protected string $scannerBin;

    public function __construct()
    {
        $this->host       = config('services.sonar.host', 'http://sonarqube:9000');
        $this->token      = config('services.sonar.token', env('SONAR_TOKEN'));
        $this->scannerBin = config('services.sonar.scanner_bin', '/opt/sonar-scanner/bin/sonar-scanner');
    }

    public function saveCodeSnippet(string $code, ?string $projectKey = null, string $extension = 'php'): string
    {
        $projectKey = $projectKey ?? ('review_' . Str::random(10));
        $tempDir    = storage_path("app/sonar/{$projectKey}");

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $filename = match ($extension) {
            'php'  => 'index.php',
            'js'   => 'index.js',
            'ts'   => 'index.ts',
            'py'   => 'main.py',
            'java' => 'Main.java',
            'cs'   => 'Program.cs',
            'go'   => 'main.go',
            'rb'   => 'main.rb',
            'rs'   => 'main.rs',
            'kt'   => 'Main.kt',
            'swift'=> 'main.swift',
            'cpp'  => 'main.cpp',
            'c'    => 'main.c',
            default => "code.{$extension}",
        };

        file_put_contents("{$tempDir}/{$filename}", $this->normalizeSnippet($code, $extension));

        return $projectKey;
    }

    private function normalizeSnippet(string $code, string $extension): string
    {
        if ($extension !== 'php') {
            return $code;
        }

        $trimmed = ltrim($code);

        if ($trimmed === '') {
            return $code;
        }

        if (preg_match('/^\s*<\?php/i', $trimmed)) {
            return $code;
        }

        return "<?php\n\n" . $trimmed;
    }

    public function analyzeProject(string $projectKey, string $extension = 'php'): array
    {
        if (!$this->token) {
            return [
                'success'     => false,
                'metrics'     => [],
                'issues'      => [],
                'project_key' => $projectKey,
                'error'       => 'SONAR_TOKEN не настроен в src/laravel/.env',
            ];
        }

        $projectDir = storage_path("app/sonar/{$projectKey}");
        if (!is_dir($projectDir)) {
            return [
                'success'     => false,
                'metrics'     => [],
                'issues'      => [],
                'project_key' => $projectKey,
                'error'       => 'Проект для анализа не найден',
            ];
        }

        if (!is_executable($this->scannerBin)) {
            return [
                'success'     => false,
                'metrics'     => [],
                'issues'      => [],
                'project_key' => $projectKey,
                'error'       => 'sonar-scanner не найден — пересоберите образ: docker compose build php kafka-code-review-consumer',
            ];
        }

        $workingDir = storage_path("app/sonar/.scannerwork/{$projectKey}");
        $sonarHome  = storage_path('app/sonar/.sonar-user-home');
        $javaTmp    = storage_path('app/sonar/.java-tmp');

        foreach ([$workingDir, $sonarHome, $javaTmp] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                return [
                    'success'     => false,
                    'metrics'     => [],
                    'issues'      => [],
                    'project_key' => $projectKey,
                    'error'       => 'Не удалось создать каталог для SonarScanner: ' . $dir,
                ];
            }
            @chmod($dir, 0777);
        }

        $suffix = $extension === 'php' ? 'php' : $extension;

        $command = sprintf(
            'SONAR_USER_HOME=%s JAVA_TOOL_OPTIONS=%s %s -Dsonar.projectKey=%s -Dsonar.sources=%s -Dsonar.host.url=%s -Dsonar.php.file.suffixes=%s -Dsonar.projectBaseDir=%s -Dsonar.working.directory=%s -Dsonar.token=%s 2>&1',
            escapeshellarg($sonarHome),
            escapeshellarg('-Djava.io.tmpdir=' . $javaTmp),
            escapeshellarg($this->scannerBin),
            escapeshellarg($projectKey),
            escapeshellarg($projectDir),
            escapeshellarg($this->host),
            escapeshellarg($suffix),
            escapeshellarg($projectDir),
            escapeshellarg($workingDir),
            escapeshellarg($this->token),
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('SonarScanner failed', [
                'project_key' => $projectKey,
                'return_code' => $returnCode,
                'output'      => implode("\n", array_slice($output, -20)),
            ]);
        } else {
            Log::info('SonarScanner finished', [
                'project_key' => $projectKey,
                'return_code' => $returnCode,
            ]);
        }

        $issues  = [];
        $metrics = [];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            sleep($attempt === 0 ? 3 : 2);
            $issues  = $this->fetchIssues($projectKey);
            $metrics = $this->fetchMetrics($projectKey);

            if (!empty($issues) || !empty($metrics)) {
                break;
            }
        }

        $scannerOutput = implode("\n", array_slice($output, -10));
        $error = null;

        if ($returnCode !== 0 && empty($issues) && empty($metrics)) {
            $error = $this->guessScannerError($scannerOutput, $returnCode);
        }

        return [
            'success'     => $returnCode === 0 || !empty($issues) || !empty($metrics),
            'metrics'     => $metrics,
            'issues'      => $issues,
            'project_key' => $projectKey,
            'error'       => $error,
            'scanner_log' => $returnCode !== 0 ? $scannerOutput : null,
        ];
    }

    private function guessScannerError(string $output, int $returnCode): string
    {
        if (str_contains($output, 'PluginFiles.mkdir') || str_contains($output, 'createDirectories')) {
            return 'SonarScanner не может создать рабочие каталоги — перезапустите контейнеры после обновления кода';
        }

        if (str_contains($output, 'Not authorized') || str_contains($output, '401')) {
            return 'Неверный SONAR_TOKEN — создайте новый токен в SonarQube и укажите его в src/laravel/.env';
        }

        if (str_contains($output, 'Connection refused') || str_contains($output, 'Unknown host')) {
            return 'SonarQube недоступен по адресу ' . $this->host . ' — проверьте: docker compose up -d sonarqube';
        }

        $tail = trim($output);

        return $tail !== ''
            ? 'SonarScanner: ' . mb_substr($tail, -300)
            : 'SonarScanner завершился с кодом ' . $returnCode;
    }

    public function fetchMetrics(string $projectKey): array
    {
        try {
            $response = Http::withBasicAuth($this->token, '')
                ->timeout(30)
                ->get("{$this->host}/api/measures/component", [
                    'component'  => $projectKey,
                    'metricKeys' => 'bugs,vulnerabilities,code_smells,coverage,duplicated_lines_density',
                ]);

            if (!$response->successful()) {
                return [];
            }

            $metrics = [];
            foreach ($response->json('component.measures', []) as $measure) {
                $metrics[$measure['metric']] = $measure['value'];
            }

            return $metrics;
        } catch (\Exception $e) {
            Log::warning('Sonar metrics fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchIssues(string $projectKey): array
    {
        $issuesList = [];
        $page       = 1;

        try {
            while (true) {
                $response = Http::withBasicAuth($this->token, '')
                    ->timeout(30)
                    ->get("{$this->host}/api/issues/search", [
                        'componentKeys'    => $projectKey,
                        'ps'               => 500,
                        'p'                => $page,
                        'additionalFields' => '_all',
                    ]);

                if (!$response->successful()) {
                    break;
                }

                $issues = $response->json('issues', []);
                if (empty($issues)) {
                    break;
                }

                foreach ($issues as $issue) {
                    $issuesList[] = [
                        'line'     => isset($issue['line']) ? (int) $issue['line'] : null,
                        'type'     => $issue['type'] ?? 'CODE_SMELL',
                        'severity' => $issue['severity'] ?? 'MAJOR',
                        'message'  => $issue['message'] ?? '',
                    ];
                }

                if (count($issues) < 500) {
                    break;
                }
                $page++;
            }
        } catch (\Exception $e) {
            Log::warning('Sonar issues fetch failed: ' . $e->getMessage());
        }

        return $issuesList;
    }

    public function hasBlockingIssues(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (in_array($issue['severity'] ?? '', ['BLOCKER', 'CRITICAL'], true)) {
                return true;
            }
            if (($issue['type'] ?? '') === 'BUG' && ($issue['severity'] ?? '') === 'MAJOR') {
                return true;
            }
        }

        return false;
    }
}
