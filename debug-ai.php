<?php
/**
 * AI Debug Script - Run from Coolify container
 * Usage: docker exec coolify php /var/www/html/debug-ai.php
 */

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AI Debug Test ===\n\n";

// Test 1: Check AI Service Configuration
echo "1. Checking AI Service Configuration...\n";
$aiService = new \App\Services\AiService();
$isConfigured = $aiService->isConfigured();
echo "   AI Configured: " . ($isConfigured ? "YES" : "NO") . "\n";

if ($isConfigured) {
    echo "   Provider: " . config('ai.provider') . "\n";
    echo "   Model: " . config('ai.model') . "\n";
} else {
    echo "   NOTE: Set AI_API_KEY in Settings > AI Debug\n";
}
echo "\n";

// Test 2: Container Health Check
echo "2. Testing Container Health Check...\n";
$server = \App\Models\Server::first();
if (!$server) {
    echo "   ERROR: No server found\n";
} else {
    echo "   Server: {$server->name} ({$server->ip})\n";

    $containers = $server->getContainers();
    $containerList = data_get($containers, 'containers');

    if ($containerList && $containerList->isNotEmpty()) {
        echo "   Total containers: " . $containerList->count() . "\n";

        $app = \App\Models\Application::first();
        if ($app) {
            $applicationId = $app->id;
            echo "   Application ID: {$applicationId}\n";

            foreach ($containerList as $container) {
                $labels = data_get($container, 'Config.Labels', []);
                $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
                $containerAppId = data_get($labels, 'coolify.applicationId');

                if ($containerAppId == $applicationId || ($containerAppId && str($containerAppId)->before('-') == $applicationId)) {
                    $containerName = data_get($labels, 'com.docker.compose.service')
                        ?? data_get($labels, 'coolify.serviceName')
                        ?? data_get($labels, 'coolify.name')
                        ?? 'unknown';

                    $status = data_get($container, 'State.Status');
                    $health = data_get($container, 'State.Health.Status');
                    $restarts = data_get($container, 'RestartCount', 0);

                    echo "   - {$containerName}: {$status}" . ($health ? "/{$health}" : "") . " (restarts: {$restarts})\n";
                }
            }
        }
    } else {
        echo "   No containers found\n";
    }
}
echo "\n";

// Test 3: Test AI Log Analysis
echo "3. Testing AI Log Analysis...\n";
if (!$isConfigured) {
    echo "   SKIPPED: AI not configured\n";
} else {
    $testLogs = "2026-03-27 10:00:00 Error: Connection refused to database
2026-03-27 10:00:01 Error: Failed to connect to MySQL
2026-03-27 10:00:02 Warning: Retrying connection attempt 1/3
2026-03-27 10:00:03 Error: Database connection timeout";

    try {
        $result = $aiService->analyzeLogs($testLogs, 'test-deployment');
        echo "   Result: " . substr($result, 0, 200) . "...\n";
    } catch (\Throwable $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 4: Check latest deployment
echo "4. Checking Latest Deployment...\n";
$deployment = \App\Models\ApplicationDeploymentQueue::orderBy('created_at', 'desc')->first();
if ($deployment) {
    echo "   UUID: {$deployment->deployment_uuid}\n";
    echo "   Status: {$deployment->status}\n";
    echo "   Created: {$deployment->created_at}\n";
    echo "   AI Analysis: " . ($deployment->ai_analysis ? "Yes" : "No") . "\n";
} else {
    echo "   No deployments found\n";
}
echo "\n";

echo "=== End of Debug Test ===\n";