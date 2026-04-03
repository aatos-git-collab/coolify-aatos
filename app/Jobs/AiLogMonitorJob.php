<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\AiHealingLog;
use App\Services\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AiLogMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(): void
    {
        $settings = \App\Models\InstanceSettings::get();

        // Check if monitoring is enabled
        if (! $settings->ai_monitor_enabled) {
            return;
        }

        $logLines = $settings->ai_monitor_log_lines ?? 500;
        $autoHeal = $settings->ai_auto_heal_enabled ?? false;

        // Get all servers with applications
        $servers = Server::whereHas('applications')->get();

        foreach ($servers as $server) {
            $this->monitorServer($server, $logLines, $autoHeal);
        }
    }

    private function monitorServer(Server $server, int $logLines, bool $autoHeal): void
    {
        try {
            // Get running containers
            $command = $server->isSwarm()
                ? "docker service ls --format '{{.Name}}' 2>/dev/null"
                : "docker ps --format '{{.Names}}' 2>/dev/null";

            $output = instant_remote_process([$command], $server, false);
            if (! $output) {
                return;
            }

            $containers = array_filter(explode("\n", trim($output)));

            foreach ($containers as $container) {
                $container = trim($container);
                if (empty($container)) {
                    continue;
                }

                $this->analyzeContainer($server, $container, $logLines, $autoHeal);
            }
        } catch (\Throwable $e) {
            Log::error("AI Log Monitor error for server {$server->name}: ".$e->getMessage());
        }
    }

    private function analyzeContainer(Server $server, string $container, int $logLines, bool $autoHeal): void
    {
        try {
            // Get container logs
            $command = $server->isSwarm()
                ? "docker service logs -n {$logLines} {$container} 2>&1"
                : "docker logs -n {$logLines} {$container} 2>&1";

            $logs = instant_remote_process([$command], $server, false);
            if (! $logs || strlen($logs) < 10) {
                return;
            }

            $logs = removeAnsiColors($logs);

            // Filter for errors
            $errorPatterns = ['error', 'failed', 'exception', 'warning', 'oom', 'killed', 'crash', 'fatal'];
            $hasError = false;

            foreach ($errorPatterns as $pattern) {
                if (stripos($logs, $pattern) !== false) {
                    $hasError = true;
                    break;
                }
            }

            if (! $hasError) {
                return;
            }

            // Send to AI for analysis
            $aiService = new AiService();
            if (! $aiService->isConfigured()) {
                return;
            }

            // Truncate logs if too long
            if (strlen($logs) > 50000) {
                $logs = substr($logs, -50000);
            }

            $analysis = $aiService->analyzeLogs($logs, "container: {$container}");

            // Log the analysis
            AiHealingLog::create([
                'server_id' => $server->id,
                'container_name' => $container,
                'issue_detected' => $this->extractErrorSummary($logs),
                'ai_analysis' => $analysis,
                'result' => 'analyzed',
            ]);

            // Auto-heal if enabled
            if ($autoHeal && $this->shouldAutoHeal($analysis)) {
                $this->performHealing($server, $container, $analysis);
            }
        } catch (\Throwable $e) {
            Log::error("AI Log Monitor container error {$container}: ".$e->getMessage());
        }
    }

    private function extractErrorSummary(string $logs): string
    {
        $lines = explode("\n", $logs);
        $errors = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) {
                $errors[] = substr($line, 0, 200);
                if (count($errors) >= 3) {
                    break;
                }
            }
        }

        return implode("\n", $errors);
    }

    private function shouldAutoHeal(string $analysis): bool
    {
        $analysis = strtolower($analysis);

        // Check if AI suggests restart or clear
        $healKeywords = ['restart', 'clear', 'delete', 'remove', 'restart'];
        foreach ($healKeywords as $keyword) {
            if (stripos($analysis, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function performHealing(Server $server, string $container, string $analysis): void
    {
        $remediation = 'docker restart';

        try {
            // Try to restart the container
            $command = $server->isSwarm()
                ? "docker service update --force {$container} 2>/dev/null"
                : "docker restart {$container} 2>/dev/null";

            $output = instant_remote_process([$command], $server, false);

            $result = $output ? 'success' : 'failed';

            AiHealingLog::create([
                'server_id' => $server->id,
                'container_name' => $container,
                'remediation_tried' => $remediation,
                'ai_analysis' => $analysis,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            AiHealingLog::create([
                'server_id' => $server->id,
                'container_name' => $container,
                'remediation_tried' => $remediation,
                'ai_analysis' => $analysis,
                'result' => 'failed',
            ]);
        }
    }
}
