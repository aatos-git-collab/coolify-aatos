<?php

namespace App\Livewire\Project\Application\Deployment;

use App\Jobs\AiAutoFixJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Services\AiService;
use Livewire\Component;
use Visus\Cuid2\Cuid2;

class Show extends Component
{
    public Application $application;

    public ApplicationDeploymentQueue $application_deployment_queue;

    public string $deployment_uuid;

    public string $horizon_job_status;

    public $isKeepAliveOn = true;

    public bool $is_debug_enabled = false;

    public bool $fullscreen = false;

    // AI Debug properties
    public bool $ai_analyzing = false;

    public ?string $ai_analysis_result = null;

    private ?string $deploymentUuid = null;

    private bool $deploymentFinishedDispatched = false;

    public function getListeners()
    {
        return [
            'refreshQueue',
        ];
    }

    public function mount()
    {
        $deploymentUuid = request()->route('deployment_uuid');

        $project = currentTeam()->load(['projects'])->projects->where('uuid', request()->route('project_uuid'))->first();
        if (! $project) {
            return redirect()->route('dashboard');
        }
        $environment = $project->load(['environments'])->environments->where('uuid', request()->route('environment_uuid'))->first()->load(['applications']);
        if (! $environment) {
            return redirect()->route('dashboard');
        }
        $application = $environment->applications->where('uuid', request()->route('application_uuid'))->first();
        if (! $application) {
            return redirect()->route('dashboard');
        }
        $application_deployment_queue = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        if (! $application_deployment_queue) {
            return redirect()->route('project.application.deployment.index', [
                'project_uuid' => $project->uuid,
                'environment_uuid' => $environment->uuid,
                'application_uuid' => $application->uuid,
            ]);
        }
        $this->application = $application;
        $this->application_deployment_queue = $application_deployment_queue;
        $this->horizon_job_status = $this->application_deployment_queue->getHorizonJobStatus();
        $this->deployment_uuid = $deploymentUuid;
        $this->is_debug_enabled = $this->application->settings->is_debug_enabled;
        $this->isKeepAliveOn();
        // Load existing AI analysis if available
        $this->ai_analysis_result = $this->application_deployment_queue->ai_analysis;
    }

    public function toggleDebug()
    {
        try {
            $this->authorize('update', $this->application);
            $this->application->settings->is_debug_enabled = ! $this->application->settings->is_debug_enabled;
            $this->application->settings->save();
            $this->is_debug_enabled = $this->application->settings->is_debug_enabled;
            $this->application_deployment_queue->refresh();
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function refreshQueue()
    {
        $this->application_deployment_queue->refresh();
    }

    private function isKeepAliveOn()
    {
        if (data_get($this->application_deployment_queue, 'status') === 'finished' || data_get($this->application_deployment_queue, 'status') === 'failed') {
            $this->isKeepAliveOn = false;
        } else {
            $this->isKeepAliveOn = true;
        }
    }

    public function polling()
    {
        $this->application_deployment_queue->refresh();
        $this->horizon_job_status = $this->application_deployment_queue->getHorizonJobStatus();
        $this->isKeepAliveOn();

        // Dispatch event when deployment finishes to stop auto-scroll (only once)
        if (! $this->isKeepAliveOn && ! $this->deploymentFinishedDispatched) {
            $this->deploymentFinishedDispatched = true;
            $this->dispatch('deploymentFinished');
        }
    }

    public function getLogLinesProperty()
    {
        return decode_remote_command_output($this->application_deployment_queue);
    }

    public function copyLogs(): string
    {
        $logs = decode_remote_command_output($this->application_deployment_queue)
            ->map(function ($line) {
                return $line['timestamp'].' '.
                       (isset($line['command']) && $line['command'] ? '[CMD]: ' : '').
                       trim($line['line']);
            })
            ->join("\n");

        return sanitizeLogsForExport($logs);
    }

    public function downloadAllLogs(): string
    {
        $logs = decode_remote_command_output($this->application_deployment_queue, includeAll: true)
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

        return sanitizeLogsForExport($logs);
    }

    public function analyzeWithAi()
    {
        try {
            $this->authorize('view', $this->application);

            $this->ai_analyzing = true;
            $this->ai_analysis_result = null;

            $logs = decode_remote_command_output($this->application_deployment_queue, includeAll: true)
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

            $aiService = new AiService();
            $this->ai_analysis_result = $aiService->analyzeLogs($logs, $this->deployment_uuid);

            $this->dispatch('success', 'AI Analysis Complete', 'Check the results below.');
        } catch (\Throwable $e) {
            $this->ai_analysis_result = 'Error: '.$e->getMessage();
            return handleError($e, $this);
        } finally {
            $this->ai_analyzing = false;
        }
    }

    public function redeploy()
    {
        try {
            $this->authorize('deploy', $this->application);

            $deploymentUuid = new Cuid2;
            $result = queue_application_deployment(
                application: $this->application,
                deployment_uuid: $deploymentUuid,
                force_rebuild: true,
            );

            if ($result['status'] === 'queue_full') {
                $this->dispatch('error', 'Deployment queue full', $result['message']);

                return;
            }

            return $this->redirectRoute('project.application.deployment.show', [
                'project_uuid' => request()->route('project_uuid'),
                'application_uuid' => request()->route('application_uuid'),
                'deployment_uuid' => $deploymentUuid,
                'environment_uuid' => request()->route('environment_uuid'),
            ], navigate: false);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function restart()
    {
        try {
            $this->authorize('deploy', $this->application);

            $deploymentUuid = new Cuid2;
            $result = queue_application_deployment(
                application: $this->application,
                deployment_uuid: $deploymentUuid,
                restart_only: true,
            );

            if ($result['status'] === 'queue_full') {
                $this->dispatch('error', 'Deployment queue full', $result['message']);

                return;
            }

            return $this->redirectRoute('project.application.deployment.show', [
                'project_uuid' => request()->route('project_uuid'),
                'application_uuid' => request()->route('application_uuid'),
                'deployment_uuid' => $deploymentUuid,
                'environment_uuid' => request()->route('environment_uuid'),
            ], navigate: false);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function render()
    {
        return view('livewire.project.application.deployment.show');
    }

    public function applyFix()
    {
        try {
            $this->authorize('deploy', $this->application);

            $aiService = new AiService();
            if (! $aiService->isConfigured()) {
                $this->dispatch('error', 'AI Not Configured', 'Please configure your AI API key in Settings > AI Debug.');

                return;
            }

            // Queue the auto-fix job
            AiAutoFixJob::dispatch($this->application, $this->application_deployment_queue);

            $this->dispatch('info', 'AI Auto-Fix Started', 'The AI is analyzing the logs and attempting to fix the issue automatically. You will be notified when complete.');
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }
}
