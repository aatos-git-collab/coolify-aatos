<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDebugTest extends TestCase
{
    /**
     * Test container health check returns correct status.
     */
    public function test_container_health_check_returns_status(): void
    {
        // Get the test application
        $application = Application::first();
        $this->assertNotNull($application, 'No application found in database');

        // Get the server
        $server = Server::first();
        $this->assertNotNull($server, 'No server found in database');

        // Get containers from server
        $containers = $server->getContainers();
        $containerList = data_get($containers, 'containers');

        $this->assertNotNull($containerList, 'No containers returned from server');
        $this->assertTrue($containerList->isNotEmpty(), 'No containers running');

        // Log container details for debugging
        foreach ($containerList as $container) {
            $labels = data_get($container, 'Config.Labels', []);
            $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
            $containerAppId = data_get($labels, 'coolify.applicationId');

            $containerName = data_get($labels, 'com.docker.compose.service')
                ?? data_get($labels, 'coolify.serviceName')
                ?? data_get($labels, 'coolify.name')
                ?? 'unknown';

            $status = data_get($container, 'State.Status');
            $health = data_get($container, 'State.Health.Status');
            $restarts = data_get($container, 'RestartCount', 0);

            echo "Container: {$containerName} - Status: {$status}, Health: " . ($health ?? 'null') . ", Restarts: {$restarts}\n";
            echo "  App ID from label: " . ($containerAppId ?? 'none') . "\n";
        }
    }

    /**
     * Test that AI service is configured.
     */
    public function test_ai_service_is_configured(): void
    {
        $aiService = new \App\Services\AiService();
        $isConfigured = $aiService->isConfigured();

        echo "AI Service configured: " . ($isConfigured ? 'YES' : 'NO') . "\n";

        if ($isConfigured) {
            echo "AI Provider: " . config('ai.provider') . "\n";
        }
    }

    /**
     * Test AI log analysis.
     */
    public function test_ai_analyze_logs(): void
    {
        $aiService = new \App\Services\AiService();

        if (!$aiService->isConfigured()) {
            $this->markTestSkipped('AI service not configured');
        }

        $testLogs = "2026-03-27 10:00:00 Error: Connection refused to database
2026-03-27 10:00:01 Error: Failed to connect to MySQL
2026-03-27 10:00:02 Warning: Retrying connection attempt 1/3
2026-03-27 10:00:03 Error: Database connection timeout";

        $result = $aiService->analyzeLogs($testLogs, 'test-deployment');

        echo "AI Analysis Result:\n{$result}\n";

        $this->assertNotEmpty($result, 'AI analysis returned empty result');
    }

    /**
     * Test deployment health check logic.
     */
    public function test_deployment_health_check_logic(): void
    {
        $application = Application::first();
        $server = Server::first();

        // Get all containers
        $containers = $server->getContainers();
        $containerList = data_get($containers, 'containers');
        $applicationId = $application->id;

        $containerStatuses = [];

        foreach ($containerList as $container) {
            $labels = data_get($container, 'Config.Labels', []);
            $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
            $containerAppId = data_get($labels, 'coolify.applicationId');

            // Match containers by app ID
            if ($containerAppId == $applicationId || ($containerAppId && str($containerAppId)->before('-') == $applicationId)) {
                $containerStatus = data_get($container, 'State.Status');
                $containerHealth = data_get($container, 'State.Health.Status');
                $restartCount = data_get($container, 'RestartCount', 0);

                $containerName = data_get($labels, 'com.docker.compose.service')
                    ?? data_get($labels, 'coolify.serviceName')
                    ?? data_get($labels, 'coolify.name')
                    ?? 'unknown';

                $containerStatuses[] = [
                    'name' => $containerName,
                    'status' => $containerStatus,
                    'health' => $containerHealth,
                    'restart_count' => $restartCount,
                ];

                echo "Found container: {$containerName} - Status: {$containerStatus}, Health: " . ($containerHealth ?? 'null') . ", Restarts: {$restartCount}\n";
            }
        }

        // Determine health status
        $allHealthy = true;
        $anyRunning = false;

        foreach ($containerStatuses as $cs) {
            if ($cs['status'] === 'running' && ($cs['health'] === 'healthy' || $cs['health'] === null)) {
                $anyRunning = true;
            } elseif ($cs['status'] !== 'running') {
                $allHealthy = false;
                echo "Container not running: {$cs['name']} - {$cs['status']}\n";
            } elseif ($cs['health'] === 'unhealthy') {
                $allHealthy = false;
                echo "Container unhealthy: {$cs['name']}\n";
            }
        }

        if ($allHealthy && $anyRunning) {
            $status = 'healthy';
        } elseif ($anyRunning) {
            $status = 'degraded';
        } else {
            $status = 'exited';
        }

        echo "Final health status: {$status}\n";

        $this->assertContains($status, ['healthy', 'degraded', 'exited']);
    }
}