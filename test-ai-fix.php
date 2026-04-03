<?php
/**
 * AI Fix Test - Manually trigger AI fix on latest deployment
 * Usage: docker exec coolify php /var/www/html/test-ai-fix.php
 */

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AI Fix Test ===\n\n";

// Get latest deployment
$deployment = \App\Models\ApplicationDeploymentQueue::orderBy('created_at', 'desc')->first();
if (!$deployment) {
    echo "ERROR: No deployment found\n";
    exit(1);
}

echo "Deployment: {$deployment->deployment_uuid}\n";
echo "Status: {$deployment->status}\n\n";

// Get application
$app = \App\Models\Application::find($deployment->application_id);
if (!$app) {
    echo "ERROR: Application not found\n";
    exit(1);
}

echo "Application: {$app->name}\n";
echo "Build Pack: {$app->build_pack}\n\n";

// Get server
$server = \App\Models\Server::find($deployment->server_id);
echo "Server: {$server->name} ({$server->ip})\n\n";

// Get container logs
echo "Getting container logs...\n";
$containers = $server->getContainers();
$containerList = data_get($containers, 'containers');
$applicationId = $app->id;
$containerLogs = [];

if ($containerList) {
    foreach ($containerList as $container) {
        $labels = data_get($container, 'Config.Labels', []);
        $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
        $containerAppId = data_get($labels, 'coolify.applicationId');

        if ($containerAppId == $applicationId || ($containerAppId && str($containerAppId)->before('-') == $applicationId)) {
            $containerName = data_get($labels, 'com.docker.compose.service')
                ?? data_get($labels, 'coolify.serviceName')
                ?? data_get($labels, 'coolify.name')
                ?? 'unknown';

            echo "Getting logs from: {$containerName}\n";
            $logCmd = "docker logs --tail 50 {$containerName} 2>&1";
            $result = instant_remote_process([$logCmd], $server, false);
            $containerLogs[] = "=== {$containerName} ===\n" . ($result ?: "No logs");
        }
    }
}

$allLogs = implode("\n\n", $containerLogs);
echo "\nContainer logs retrieved: " . strlen($allLogs) . " bytes\n\n";

// Analyze with AI
echo "Analyzing with AI...\n";
$aiService = new \App\Services\AiService();

if (!$aiService->isConfigured()) {
    echo "ERROR: AI not configured\n";
    exit(1);
}

try {
    $analysis = $aiService->analyzeLogs($allLogs, "fix-test: {$deployment->deployment_uuid}");
    echo "\n=== AI Analysis ===\n";
    echo $analysis . "\n";

    // Store analysis
    $deployment->update(['ai_analysis' => $analysis]);
    echo "\nAnalysis stored in deployment.\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== End of AI Fix Test ===\n";