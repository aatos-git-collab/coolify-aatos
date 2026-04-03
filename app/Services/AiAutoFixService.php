<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Visus\Cuid2\Cuid2;

class AiAutoFixService
{
    protected int $maxRetries = 5;

    protected int $currentRetry = 0;

    protected ?string $deploymentUuid = null;

    protected ?string $applicationDir = null;

    protected array $appliedFixes = [];

    public function runAutoFixLoop(Application $application, ApplicationDeploymentQueue $deployment): void
    {
        $this->deploymentUuid = $deployment->deployment_uuid;
        $this->currentRetry = 0;
        $this->appliedFixes = [];

        // Find application directory on the server
        $this->applicationDir = $this->findApplicationDir($application, $deployment->server);

        if (! $this->applicationDir) {
            Log::error("AI Auto-Fix: Could not find application directory on server");

            return;
        }

        Log::info("AI Auto-Fix: Found application dir: {$this->applicationDir}");

        while ($this->currentRetry < $this->maxRetries) {
            $this->currentRetry++;

            Log::info("AI Auto-Fix: Starting attempt {$this->currentRetry}/{$this->maxRetries}");

            // Get current deployment logs
            $logs = $this->getDeploymentLogs($deployment);

            // Get container logs for more context
            $containerLogs = $this->getContainerLogs($application, $deployment->server);

            // Combine logs for analysis
            $combinedLogs = "=== Deployment Logs ===\n{$logs}\n\n=== Container Logs ===\n{$containerLogs}";

            // Analyze with AI
            $aiService = new AiService();
            $analysis = $aiService->analyzeLogsForFix($combinedLogs, $this->deploymentUuid);

            Log::info("AI Auto-Fix: Analysis result: " . substr($analysis, 0, 500));

            // Check if fix is possible and extract fix instructions
            $fixInstructions = $this->extractFixInstructions($analysis);

            if (empty($fixInstructions)) {
                Log::info("AI Auto-Fix: No fix instructions found, stopping");
                break;
            }

            // Apply fix directly in container
            $fixResult = $this->applyFixInContainer($application, $deployment->server, $fixInstructions);

            if ($fixResult['success']) {
                // Verify the fix worked by checking container health
                $verified = $this->verifyFix($application, $deployment->server);

                if ($verified) {
                    Log::info("AI Auto-Fix: Fix verified successfully!");

                    // If git-based and we have write access, push to git
                    if ($application->git_based() && $this->hasGitWriteAccess($application)) {
                        $this->pushFixToGit($application, $deployment->server);
                    }

                    break;
                }
            }

            // Wait before next attempt
            sleep(10);
        }

        if ($this->currentRetry >= $this->maxRetries) {
            Log::warning("AI Auto-Fix: Max retries ({$this->maxRetries}) reached");
        }
    }

    protected function findApplicationDir(Application $application, Server $server): ?string
    {
        // Common application directories
        $possibleDirs = [
            "/var/www/coolify/applications/{$application->uuid}",
            "/opt/coolify/applications/{$application->uuid}",
            "/home/coolify/applications/{$application->uuid}",
            "/data/coolify/applications/{$application->uuid}",
        ];

        foreach ($possibleDirs as $dir) {
            $result = instant_remote_process(["ls -la {$dir} 2>/dev/null || echo 'NOT_FOUND'"], $server, false);

            if ($result && strpos($result, 'NOT_FOUND') === false && strpos($result, 'cannot access') === false) {
                // Check for docker-compose.yml or Dockerfile
                $hasFiles = instant_remote_process(["ls {$dir}/docker-compose.yml {$dir}/Dockerfile 2>/dev/null || echo 'NO_FILES'"], $server, false);

                if ($hasFiles && strpos($hasFiles, 'NO_FILES') === false) {
                    return $dir;
                }
            }
        }

        // Try to find by looking for the application's UUID in docker volume or container labels
        $result = instant_remote_process([
            "find /var/www -type d -name '*' 2>/dev/null | head -20",
        ], $server, false);

        Log::info("AI Auto-Fix: Search result: " . substr($result ?? '', 0, 500));

        return $possibleDirs[0] ?? null;
    }

    protected function getDeploymentLogs(ApplicationDeploymentQueue $deployment): string
    {
        $logs = decode_remote_command_output($deployment, includeAll: true)
            ->map(function ($line) {
                $prefix = '';
                if ($line['hidden']) {
                    $prefix = '[DEBUG] ';
                }
                if (isset($line['command']) && $line['command']) {
                    $prefix .= '[CMD]: ';
                }

                return $line['timestamp'].' '.$prefix.trim($line['line']);
            })
            ->join("\n");

        if (strlen($logs) > 50000) {
            $logs = substr($logs, -50000);
        }

        return $logs;
    }

    protected function getContainerLogs(Application $application, Server $server): string
    {
        try {
            $containers = $server->getContainers();
            $containerList = data_get($containers, 'containers');

            if (! $containerList) {
                return "No containers found";
            }

            $applicationId = $application->id;
            $logs = [];

            foreach ($containerList as $container) {
                if ($server->isSwarm()) {
                    $labels = data_get($container, 'Spec.Labels');
                } else {
                    $labels = data_get($container, 'Config.Labels');
                }

                $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
                $containerAppId = data_get($labels, 'coolify.applicationId');

                if ($containerAppId == $applicationId || ($containerAppId && str($containerAppId)->before('-') == $applicationId)) {
                    $containerName = data_get($labels, 'com.docker.compose.service')
                        ?? data_get($labels, 'coolify.serviceName')
                        ?? data_get($labels, 'coolify.name')
                        ?? 'unknown';

                    // Get last 50 lines of logs
                    $logCmd = "docker logs --tail 50 {$containerName} 2>&1";
                    $containerLog = instant_remote_process([$logCmd], $server, false);

                    $logs[] = "=== {$containerName} ===\n" . ($containerLog ?: "No logs");
                }
            }

            return implode("\n\n", $logs);
        } catch (\Throwable $e) {
            Log::error("AI Auto-Fix: Error getting container logs: " . $e->getMessage());

            return "Error getting logs: " . $e->getMessage();
        }
    }

    protected function analyzeLogs(ApplicationDeploymentQueue $deployment): string
    {
        $logs = $this->getDeploymentLogs($deployment);
        $aiService = new AiService();

        return $aiService->analyzeLogsForFix($logs, $this->deploymentUuid);
    }

    protected function extractFixInstructions(string $analysis): array
    {
        $instructions = [];

        // Look for file paths and suggested changes in the analysis
        // The AI should return JSON with fix instructions
        if (preg_match('/\[(.*?)\]/', $analysis, $matches)) {
            // Try to parse as JSON
            $json = json_decode($matches[1], true);

            if ($json && isset($json['file'])) {
                $instructions[] = $json;
            }
        }

        // Alternative: look for file edit patterns
        if (preg_match_all('/(docker-compose\.yml|Dockerfile|dockerfile|.*\.dockerfile|.*\.yaml):/i', $analysis, $matches)) {
            foreach ($matches[1] as $file) {
                if (! isset($instructions['file'])) {
                    $instructions[] = ['file' => trim($file), 'analysis' => $analysis];
                }
            }
        }

        return $instructions;
    }

    protected function applyFixInContainer(Application $application, Server $server, array $fixInstructions): array
    {
        try {
            if (! $this->applicationDir) {
                return ['success' => false, 'message' => 'No application directory found'];
            }

            $fixesApplied = [];

            foreach ($fixInstructions as $instruction) {
                $file = $instruction['file'] ?? null;
                $analysis = $instruction['analysis'] ?? '';

                if (! $file) {
                    continue;
                }

                // Determine if it's docker-compose.yml or Dockerfile
                $isCompose = stripos($file, 'docker-compose') !== false || stripos($file, '.yml') !== false || stripos($file, '.yaml') !== false;
                $isDockerfile = stripos($file, 'dockerfile') !== false;

                if ($isCompose) {
                    $result = $this->fixDockerCompose($server, $this->applicationDir, $analysis);
                    $fixesApplied[] = "docker-compose.yml: " . ($result['message'] ?? 'fixed');
                } elseif ($isDockerfile) {
                    $result = $this->fixDockerfile($server, $this->applicationDir, $analysis);
                    $fixesApplied[] = "Dockerfile: " . ($result['message'] ?? 'fixed');
                } else {
                    // Try to find the file and fix it
                    $result = $this->fixGenericFile($server, $this->applicationDir, $file, $analysis);
                    $fixesApplied[] = "{$file}: " . ($result['message'] ?? 'fixed');
                }
            }

            // After applying fixes, restart the containers
            $this->restartContainers($server, $this->applicationDir);

            $this->appliedFixes = $fixesApplied;

            return [
                'success' => true,
                'message' => implode(', ', $fixesApplied),
            ];
        } catch (\Throwable $e) {
            Log::error("AI Auto-Fix: Error applying fix: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    protected function fixDockerCompose(Server $server, string $appDir, string $analysis): array
    {
        $composeFile = "{$appDir}/docker-compose.yml";

        // Check if file exists
        $check = instant_remote_process(["ls -la {$composeFile} 2>/dev/null"], $server, false);

        if (strpos($check, 'No such file') !== false) {
            // Try docker-compose.yaml
            $composeFile = "{$appDir}/docker-compose.yaml";
            $check = instant_remote_process(["ls -la {$composeFile} 2>/dev/null"], $server, false);

            if (strpos($check, 'No such file') !== false) {
                return ['success' => false, 'message' => 'No docker-compose file found'];
            }
        }

        // Read current content
        $currentContent = instant_remote_process(["cat {$composeFile}"], $server, false);

        // Ask AI to generate fixed version based on analysis
        $aiService = new AiService();
        $fixedContent = $aiService->generateFixedDockerCompose($currentContent, $analysis);

        if ($fixedContent && $fixedContent !== $currentContent) {
            // Backup original
            instant_remote_process(["cp {$composeFile} {$composeFile}.backup"], $server, false);

            // Write new content using a temp file approach
            $tempFile = "/tmp/docker-compose-fixed-" . time() . ".yml";
            instant_remote_process(["echo '" . addslashes($fixedContent) . "' > {$tempFile}"], $server, false);
            instant_remote_process(["cp {$tempFile} {$composeFile} && rm {$tempFile}"], $server, false);

            return ['success' => true, 'message' => 'docker-compose.yml updated'];
        }

        return ['success' => false, 'message' => 'No changes needed or AI could not generate fix'];
    }

    protected function fixDockerfile(Server $server, string $appDir, string $analysis): array
    {
        $dockerfile = "{$appDir}/Dockerfile";

        // Check if file exists
        $check = instant_remote_process(["ls -la {$dockerfile} 2>/dev/null"], $server, false);

        if (strpos($check, 'No such file') !== false) {
            return ['success' => false, 'message' => 'No Dockerfile found'];
        }

        // Read current content
        $currentContent = instant_remote_process(["cat {$dockerfile}"], $server, false);

        // Ask AI to generate fixed version
        $aiService = new AiService();
        $fixedContent = $aiService->generateFixedDockerfile($currentContent, $analysis);

        if ($fixedContent && $fixedContent !== $currentContent) {
            // Backup original
            instant_remote_process(["cp {$dockerfile} {$dockerfile}.backup"], $server, false);

            // Write new content
            $tempFile = "/tmp/dockerfile-fixed-" . time();
            instant_remote_process(["echo '" . addslashes($fixedContent) . "' > {$tempFile}"], $server, false);
            instant_remote_process(["cp {$tempFile} {$dockerfile} && rm {$tempFile}"], $server, false);

            return ['success' => true, 'message' => 'Dockerfile updated'];
        }

        return ['success' => false, 'message' => 'No changes needed'];
    }

    protected function fixGenericFile(Server $server, string $appDir, string $filename, string $analysis): array
    {
        $filePath = "{$appDir}/{$filename}";

        $check = instant_remote_process(["ls -la {$filePath} 2>/dev/null"], $server, false);

        if (strpos($check, 'No such file') !== false) {
            return ['success' => false, 'message' => "File {$filename} not found"];
        }

        $currentContent = instant_remote_process(["cat {$filePath}"], $server, false);

        $aiService = new AiService();
        $fixedContent = $aiService->fixFileContent($currentContent, $filename, $analysis);

        if ($fixedContent && $fixedContent !== $currentContent) {
            instant_remote_process(["cp {$filePath} {$filePath}.backup"], $server, false);

            $tempFile = "/tmp/fix-" . time();
            instant_remote_process(["echo '" . addslashes($fixedContent) . "' > {$tempFile}"], $server, false);
            instant_remote_process(["cp {$tempFile} {$filePath} && rm {$tempFile}"], $server, false);

            return ['success' => true, 'message' => "{$filename} updated"];
        }

        return ['success' => false, 'message' => 'No changes needed'];
    }

    protected function restartContainers(Server $server, string $appDir): void
    {
        // Try docker-compose restart first
        $composeResult = instant_remote_process([
            "cd {$appDir} && docker-compose down 2>&1",
            "cd {$appDir} && docker-compose up -d 2>&1",
        ], $server, false);

        Log::info("AI Auto-Fix: Docker-compose restart result: " . substr($composeResult, 0, 500));

        // Wait for containers to start
        sleep(10);
    }

    protected function verifyFix(Application $application, Server $server): bool
    {
        try {
            $containers = $server->getContainers();
            $containerList = data_get($containers, 'containers');

            if (! $containerList) {
                return false;
            }

            $applicationId = $application->id;
            $allHealthy = true;

            foreach ($containerList as $container) {
                if ($server->isSwarm()) {
                    $labels = data_get($container, 'Spec.Labels');
                } else {
                    $labels = data_get($container, 'Config.Labels');
                }

                $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
                $containerAppId = data_get($labels, 'coolify.applicationId');

                if ($containerAppId == $applicationId || ($containerAppId && str($containerAppId)->before('-') == $applicationId)) {
                    $status = data_get($container, 'State.Status');
                    $health = data_get($container, 'State.Health.Status');

                    if ($status !== 'running') {
                        $allHealthy = false;
                    }

                    if ($health && $health !== 'healthy') {
                        $allHealthy = false;
                    }
                }
            }

            return $allHealthy;
        } catch (\Throwable $e) {
            Log::error("AI Auto-Fix: Error verifying fix: " . $e->getMessage());

            return false;
        }
    }

    protected function hasGitWriteAccess(Application $application): bool
    {
        // Check if it's a GitHub-based app with source (implies write access via GitHub App)
        return $application->is_github_based() && data_get($application, 'source');
    }

    protected function pushFixToGit(Application $application, Server $server): void
    {
        if (empty($this->appliedFixes)) {
            Log::info("AI Auto-Fix: No fixes to push to git");

            return;
        }

        try {
            $appDir = $this->applicationDir;

            // Configure git
            $gitCommands = [
                "cd {$appDir} && git config user.email 'ai@coolify.io'",
                "cd {$appDir} && git config user.name 'Coolify AI'",
            ];

            instant_remote_process($gitCommands, $server, false);

            // Add changed files
            $addCommands = [
                "cd {$appDir} && git add -A",
                "cd {$appDir} && git status",
            ];

            $status = instant_remote_process($addCommands, $server, false);

            if (strpos($status, 'nothing to commit') !== false) {
                Log::info("AI Auto-Fix: No changes to commit");

                return;
            }

            // Commit with descriptive message
            $commitMessage = "AI Auto-Fix: " . implode('; ', $this->appliedFixes) . " (Deployment: {$this->deploymentUuid})";

            $commitCommands = [
                "cd {$appDir} && git commit -m '{$commitMessage}'",
                "cd {$appDir} && git push origin " . escapeshellarg($application->git_branch ?? 'main'),
            ];

            $pushResult = instant_remote_process($commitCommands, $server, false);

            Log::info("AI Auto-Fix: Pushed to git: " . substr($pushResult, 0, 500));
        } catch (\Throwable $e) {
            Log::error("AI Auto-Fix: Error pushing to git: " . $e->getMessage());
        }
    }

    protected function canAutoFix(string $analysis): bool
    {
        $analysis = strtolower($analysis);

        // Check if AI suggests actionable fixes
        $actionableKeywords = [
            'install', 'add', 'create', 'update', 'fix', 'remove',
            'change', 'set', 'configure', 'run', 'execute',
            'permission', 'path', 'missing', 'error', 'docker'
        ];

        foreach ($actionableKeywords as $keyword) {
            if (stripos($analysis, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function waitForDeployment(Application $application): bool
    {
        // Poll deployment status
        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            sleep(2);

            $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $this->deploymentUuid)->first();

            if (! $deployment) {
                continue;
            }

            if ($deployment->status === 'finished') {
                return true;
            }

            if ($deployment->status === 'failed') {
                return false;
            }
        }

        return false;
    }

    public function queueAutoFix(Application $application, ApplicationDeploymentQueue $deployment): void
    {
        // Queue the auto-fix job to run in background
        \App\Jobs\AiAutoFixJob::dispatch($application, $deployment);
    }
}