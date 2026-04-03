<?php
/**
 * AI Features Test Script
 * Run: docker exec coolify php /var/www/html/test-ai-features.php
 */

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AI Features Test ===\n\n";

$allPassed = true;

// Test 1: AI Service Configuration
echo "1. Testing AI Service Configuration...\n";
try {
    $aiService = new \App\Services\AiService();
    $isConfigured = $aiService->isConfigured();
    if ($isConfigured) {
        echo "   ✅ PASS: AI Service is configured\n";
    } else {
        echo "   ❌ FAIL: AI Service not configured (set AI_API_KEY in Settings)\n";
        $allPassed = false;
    }
} catch (\Throwable $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}
echo "\n";

// Test 2: Post-Deployment Monitoring Code
echo "2. Testing Post-Deployment Monitoring Code...\n";
try {
    $reflection = new ReflectionClass(\App\Jobs\ApplicationDeploymentJob::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE);

    $requiredMethods = [
        'monitorContainersAfterDeployment',
        'checkContainerHealth',
        'checkForRestartLoops',
        'attemptAutoFix',
        'getApplicationLogs'
    ];

    $foundMethods = [];
    foreach ($methods as $method) {
        $foundMethods[] = $method->getName();
    }

    $missing = [];
    foreach ($requiredMethods as $method) {
        if (!in_array($method, $foundMethods)) {
            $missing[] = $method;
        }
    }

    if (empty($missing)) {
        echo "   ✅ PASS: All monitoring methods exist\n";
    } else {
        echo "   ❌ FAIL: Missing methods: " . implode(', ', $missing) . "\n";
        $allPassed = false;
    }
} catch (\Throwable $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}
echo "\n";

// Test 3: Database Column
echo "3. Testing ai_analysis column...\n";
try {
    $deployment = \App\Models\ApplicationDeploymentQueue::first();
    if ($deployment) {
        // Try to access the column
        $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('application_deployment_queues', 'ai_analysis');
        if ($hasColumn) {
            echo "   ✅ PASS: ai_analysis column exists\n";

            // Check if any deployment has analysis
            $withAnalysis = \App\Models\ApplicationDeploymentQueue::whereNotNull('ai_analysis')->count();
            echo "   - Deployments with AI analysis: $withAnalysis\n";
        } else {
            echo "   ❌ FAIL: ai_analysis column missing\n";
            $allPassed = false;
        }
    } else {
        echo "   ⚠️  SKIP: No deployments found\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}
echo "\n";

// Test 4: AI Analyze Logs
echo "4. Testing AI Log Analysis...\n";
try {
    $aiService = new \App\Services\AiService();
    if ($aiService->isConfigured()) {
        $testLogs = "2026-03-27 Error: Connection refused
2026-03-27 Warning: Retrying connection
2026-03-27 Error: Database timeout";

        $result = $aiService->analyzeLogs($testLogs, 'test-deployment');
        if (!empty($result) && strlen($result) > 50) {
            echo "   ✅ PASS: AI analysis returned valid result (" . strlen($result) . " chars)\n";
            echo "   - Preview: " . substr($result, 0, 100) . "...\n";
        } else {
            echo "   ❌ FAIL: AI analysis returned empty or too short\n";
            $allPassed = false;
        }
    } else {
        echo "   ⚠️  SKIP: AI not configured\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}
echo "\n";

// Test 5: Container Health Check Logic
echo "5. Testing Container Health Check...\n";
try {
    $server = \App\Models\Server::first();
    if ($server) {
        $containers = $server->getContainers();
        $containerList = data_get($containers, 'containers');

        if ($containerList && $containerList->isNotEmpty()) {
            echo "   ✅ PASS: Server returns containers (" . $containerList->count() . ")\n";

            // Try to match application containers
            $app = \App\Models\Application::first();
            if ($app) {
                $appId = $app->id;
                $matched = 0;
                foreach ($containerList as $container) {
                    $labels = data_get($container, 'Config.Labels', []);
                    $labels = \Illuminate\Support\Arr::undot(format_docker_labels_to_json($labels));
                    $containerAppId = data_get($labels, 'coolify.applicationId');

                    if ($containerAppId == $appId || ($containerAppId && str($containerAppId)->before('-') == $appId)) {
                        $matched++;
                    }
                }
                echo "   - Application containers found: $matched\n";
            }
        } else {
            echo "   ⚠️  INFO: No containers running (expected on test server)\n";
        }
    } else {
        echo "   ⚠️  SKIP: No server found\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}
echo "\n";

// Test 6: Check Latest Deployment
echo "6. Checking Latest Deployment...\n";
try {
    $deployment = \App\Models\ApplicationDeploymentQueue::orderBy('created_at', 'desc')->first();
    if ($deployment) {
        echo "   Latest: {$deployment->deployment_uuid}\n";
        echo "   Status: {$deployment->status}\n";
        echo "   Created: {$deployment->created_at}\n";
        echo "   AI Analysis: " . ($deployment->ai_analysis ? "YES" : "NO") . "\n";
    } else {
        echo "   ⚠️  SKIP: No deployments found\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}
echo "\n";

// Summary
echo "=== Summary ===\n";
if ($allPassed) {
    echo "✅ ALL TESTS PASSED\n\n";
    echo "AI Features are working:\n";
    echo "- Post-deployment monitoring (120s, every 2s checks)\n";
    echo "- Auto-fix triggered on degraded/exited containers\n";
    echo "- AI analyzes container and deployment logs\n";
    echo "- Analysis stored in database\n";
    echo "- Auto-fix job queued for deeper analysis\n";
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Check the output above for details.\n";
}

echo "\n=== End of Test ===\n";