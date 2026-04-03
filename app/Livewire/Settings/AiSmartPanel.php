<?php

namespace App\Livewire\Settings;

use App\Models\InstanceSettings;
use App\Models\AiHealingLog;
use App\Services\AiService;
use App\Services\AiAutoFixService;
use App\Jobs\AiAutoFixJob;
use App\Jobs\AiLogMonitorJob;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class AiSmartPanel extends Component
{
    public InstanceSettings $settings;

    // AI Provider Settings
    #[Validate('nullable|string')]
    public ?string $ai_provider;

    #[Validate('nullable|string')]
    public ?string $ai_api_key;

    #[Validate('nullable|string')]
    public ?string $ai_model;

    // AI Build Pack Settings
    public bool $ai_buildpack_enabled = true;
    public bool $ai_buildpack_auto_detect_docker = true;
    public bool $ai_buildpack_fallback_nixpacks = true;

    // AI Auto-Fix Settings
    public bool $ai_autofix_enabled = true;
    public int $ai_autofix_max_retries = 5;
    public int $ai_autofix_retry_delay = 10;

    // AI Log Monitor Settings
    public bool $ai_monitor_enabled = false;
    public int $ai_monitor_interval = 60;
    public int $ai_monitor_log_lines = 500;
    public bool $ai_auto_heal_enabled = false;

    // Load Balancer / Domain Settings
    public bool $ip_whitelist_enabled = false;
    public ?string $ip_whitelist_sources = null;

    // UI State
    public bool $testing_connection = false;
    public ?string $test_result = null;
    public bool $running_health_check = false;
    public ?string $health_check_result = null;
    public bool $running_test_fix = false;
    public ?string $test_fix_result = null;

    // Recent healing logs
    public array $recentHealingLogs = [];

    public function mount()
    {
        if (! isInstanceAdmin()) {
            return redirect()->route('dashboard');
        }
        $this->loadSettings();
        $this->loadRecentHealingLogs();
    }

    public function loadSettings()
    {
        $this->settings = instanceSettings();

        // Provider settings
        $this->ai_provider = $this->settings->ai_provider ?? 'minimax';
        $this->ai_api_key = $this->settings->ai_api_key;
        $this->ai_model = $this->settings->ai_model ?? 'MiniMax-M2.7';

        // Build pack settings
        $this->ai_buildpack_enabled = $this->settings->ai_buildpack_enabled ?? true;
        $this->ai_buildpack_auto_detect_docker = $this->settings->ai_buildpack_auto_detect_docker ?? true;
        $this->ai_buildpack_fallback_nixpacks = $this->settings->ai_buildpack_fallback_nixpacks ?? true;

        // Auto-fix settings
        $this->ai_autofix_enabled = $this->settings->ai_autofix_enabled ?? true;
        $this->ai_autofix_max_retries = $this->settings->ai_autofix_max_retries ?? 5;
        $this->ai_autofix_retry_delay = $this->settings->ai_autofix_retry_delay ?? 10;

        // Monitor settings
        $this->ai_monitor_enabled = $this->settings->ai_monitor_enabled ?? false;
        $this->ai_monitor_interval = $this->settings->ai_monitor_interval ?? 60;
        $this->ai_monitor_log_lines = $this->settings->ai_monitor_log_lines ?? 500;
        $this->ai_auto_heal_enabled = $this->settings->ai_auto_heal_enabled ?? false;

        // Domain/IP settings
        $this->ip_whitelist_enabled = $this->settings->ip_whitelist_enabled ?? false;
        $this->ip_whitelist_sources = $this->settings->ip_whitelist_sources;
    }

    public function loadRecentHealingLogs()
    {
        $this->recentHealingLogs = AiHealingLog::orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    public function submit()
    {
        try {
            $this->validate();

            // Save provider settings
            $this->settings->ai_provider = $this->ai_provider;
            $this->settings->ai_api_key = $this->ai_api_key;
            $this->settings->ai_model = $this->ai_model;

            // Save build pack settings
            $this->settings->ai_buildpack_enabled = $this->ai_buildpack_enabled;
            $this->settings->ai_buildpack_auto_detect_docker = $this->ai_buildpack_auto_detect_docker;
            $this->settings->ai_buildpack_fallback_nixpacks = $this->ai_buildpack_fallback_nixpacks;

            // Save auto-fix settings
            $this->settings->ai_autofix_enabled = $this->ai_autofix_enabled;
            $this->settings->ai_autofix_max_retries = $this->ai_autofix_max_retries;
            $this->settings->ai_autofix_retry_delay = $this->ai_autofix_retry_delay;

            // Save monitor settings
            $this->settings->ai_monitor_enabled = $this->ai_monitor_enabled;
            $this->settings->ai_monitor_interval = $this->ai_monitor_interval;
            $this->settings->ai_monitor_log_lines = $this->ai_monitor_log_lines;
            $this->settings->ai_auto_heal_enabled = $this->ai_auto_heal_enabled;

            // Save domain/IP settings
            $this->settings->ip_whitelist_enabled = $this->ip_whitelist_enabled;
            $this->settings->ip_whitelist_sources = $this->ip_whitelist_sources;

            $this->settings->save();

            $this->dispatch('success', 'AI Smart Panel Saved', 'All AI settings have been updated.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function testConnection()
    {
        try {
            $this->testing_connection = true;
            $this->test_result = null;

            $aiService = new AiService(
                apiKey: $this->ai_api_key,
                provider: $this->ai_provider,
                model: $this->ai_model
            );

            if (! $aiService->isConfigured()) {
                $this->test_result = 'error:AI service is not configured. Please add your API key.';
                return;
            }

            $result = $aiService->testConnection();

            $this->test_result = $result['success']
                ? 'success:Connection successful! Model: ' . ($result['model'] ?? 'unknown')
                : 'error:' . $result['message'];
        } catch (\Throwable $e) {
            $this->test_result = 'error:' . $e->getMessage();
        } finally {
            $this->testing_connection = false;
        }
    }

    public function runHealthCheck()
    {
        try {
            $this->running_health_check = true;
            $this->health_check_result = null;

            // Dispatch health check job
            AiLogMonitorJob::dispatch();

            $this->health_check_result = 'success:Health check started. Check recent healing logs for results.';
        } catch (\Throwable $e) {
            $this->health_check_result = 'error:' . $e->getMessage();
        } finally {
            $this->running_health_check = false;
        }
    }

    public function testAiFix()
    {
        try {
            $this->running_test_fix = true;
            $this->test_fix_result = null;

            // This would typically run against a test deployment
            // For now, just verify the service is working
            $aiService = new AiService(
                apiKey: $this->ai_api_key,
                provider: $this->ai_provider,
                model: $this->ai_model
            );

            if (! $aiService->isConfigured()) {
                $this->test_fix_result = 'error:AI service is not configured.';
                return;
            }

            // Test with a sample log
            $testLogs = "[ERROR] Sample application error for testing AI fix capability";
            $analysis = $aiService->analyzeLogsForFix($testLogs, 'test-deployment');

            $this->test_fix_result = 'success:AI Fix test completed. Analysis: ' . substr($analysis, 0, 200) . '...';
        } catch (\Throwable $e) {
            $this->test_fix_result = 'error:' . $e->getMessage();
        } finally {
            $this->running_test_fix = false;
        }
    }

    public function toggleAiMonitor()
    {
        $this->ai_monitor_enabled = ! $this->ai_monitor_enabled;
        $this->submit();
    }

    public function toggleAiAutoHeal()
    {
        $this->ai_auto_heal_enabled = ! $this->ai_auto_heal_enabled;
        $this->submit();
    }

    public function toggleAiBuildpack()
    {
        $this->ai_buildpack_enabled = ! $this->ai_buildpack_enabled;
        $this->submit();
    }

    public function toggleAiAutoFix()
    {
        $this->ai_autofix_enabled = ! $this->ai_autofix_enabled;
        $this->submit();
    }

    public function clearHealingLogs()
    {
        AiHealingLog::truncate();
        $this->loadRecentHealingLogs();
        $this->dispatch('success', 'Logs Cleared', 'All healing logs have been cleared.');
    }

    public function render()
    {
        return view('livewire.settings.ai-smart-panel');
    }
}
