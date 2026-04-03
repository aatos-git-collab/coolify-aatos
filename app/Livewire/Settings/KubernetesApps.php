<?php

namespace App\Livewire\Settings;

use App\Models\KubernetesApp;
use App\Models\KubernetesCluster;
use App\Models\KubernetesPipeline;
use App\Models\Project;
use App\Models\Environment;
use App\Jobs\KubernetesDeploymentJob;
use App\Services\KubernetesService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class KubernetesApps extends Component
{
    public array $apps = [];
    public array $clusters = [];
    public array $pipelines = [];
    public bool $showForm = false;
    public bool $isEditing = false;

    // Form fields
    #[Validate('required|string|min:2|max:255')]
    public string $name = '';

    #[Validate('required')]
    public ?string $cluster_id = null;

    #[Validate('nullable')]
    public ?string $pipeline_id = null;

    public string $namespace = 'default';
    public string $image_repository = '';
    public string $image_tag = 'latest';
    public int $container_port = 80;
    public int $replicas = 1;
    public string $pod_size = 'small';

    // Build
    public string $buildstrategy = 'dockerfile';
    public string $dockerfile_path = 'Dockerfile';
    public ?string $build_commands = null;

    // Scaling
    public bool $autoscale_enabled = false;
    public int $autoscale_min = 1;
    public int $autoscale_max = 5;
    public int $autoscale_cpu_threshold = 70;

    // Pod Logs
    public bool $showLogs = false;
    public string $logContent = '';
    public string $logAppId = '';
    public string $logAppName = '';
    public bool $loadingLogs = false;
    public int $autoscale_memory_threshold = 80;

    // Healthcheck
    public bool $healthcheck_enabled = true;
    public string $healthcheck_path = '/';
    public int $healthcheck_port = 80;

    // Ingress
    public ?string $ingress_host = null;
    public string $ingress_path = '/';
    public bool $ingress_tls = false;

    // Env vars (JSON string for textarea)
    public string $env_vars_json = '{}';
    public string $secrets_json = '{}';

    // State
    public ?string $editingId = null;
    public ?string $deployResult = null;
    public bool $deploying = false;
    public ?string $statusResult = null;

    public function mount()
    {
        if (! isInstanceAdmin()) {
            return redirect()->route('dashboard');
        }
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->apps = KubernetesApp::all()->toArray();
        $this->clusters = KubernetesCluster::all()->toArray();
        $this->pipelines = KubernetesPipeline::with(['cluster', 'project'])
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'cluster' => $p->cluster?->name,
                'project' => $p->project?->name,
            ])
            ->toArray();
    }

    public function createApp(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->isEditing = false;
    }

    public function editApp(string $id): void
    {
        $app = KubernetesApp::findOrFail($id);
        $this->editingId = $id;
        $this->name = $app->name;
        $this->cluster_id = $app->pipeline?->kubernetes_cluster_id;
        $this->pipeline_id = $app->kubernetes_pipeline_id;
        $this->namespace = $app->namespace;
        $this->image_repository = $app->image_repository ?? '';
        $this->image_tag = $app->image_tag;
        $this->container_port = $app->container_port;
        $this->replicas = $app->replicas;
        $this->pod_size = $app->pod_size;
        $this->buildstrategy = $app->buildstrategy;
        $this->dockerfile_path = $app->dockerfile_path;
        $this->build_commands = $app->build_commands;
        $this->autoscale_enabled = $app->autoscale_enabled;
        $this->autoscale_min = $app->autoscale_min;
        $this->autoscale_max = $app->autoscale_max;
        $this->autoscale_cpu_threshold = $app->autoscale_cpu_threshold;
        $this->autoscale_memory_threshold = $app->autoscale_memory_threshold;
        $this->healthcheck_enabled = $app->healthcheck_enabled;
        $this->healthcheck_path = $app->healthcheck_path;
        $this->healthcheck_port = $app->healthcheck_port;
        $this->ingress_host = $app->ingress_host;
        $this->ingress_path = $app->ingress_path;
        $this->ingress_tls = $app->ingress_tls;
        $this->env_vars_json = json_encode($app->env_vars ?? []);
        $this->secrets_json = json_encode($app->secrets ?? []);
        $this->isEditing = true;
        $this->showForm = true;
    }

    public function saveApp(): void
    {
        $this->validate([
            'name' => 'required|string|min:2|max:255',
            'cluster_id' => 'required',
        ]);

        try {
            $envVars = json_decode($this->env_vars_json, true) ?: [];
            $secrets = json_decode($this->secrets_json, true) ?: [];

            $data = [
                'name' => $this->name,
                'namespace' => $this->namespace,
                'image_repository' => $this->image_repository,
                'image_tag' => $this->image_tag,
                'container_port' => $this->container_port,
                'replicas' => $this->replicas,
                'pod_size' => $this->pod_size,
                'buildstrategy' => $this->buildstrategy,
                'dockerfile_path' => $this->dockerfile_path,
                'build_commands' => $this->build_commands,
                'autoscale_enabled' => $this->autoscale_enabled,
                'autoscale_min' => $this->autoscale_min,
                'autoscale_max' => $this->autoscale_max,
                'autoscale_cpu_threshold' => $this->autoscale_cpu_threshold,
                'autoscale_memory_threshold' => $this->autoscale_memory_threshold,
                'healthcheck_enabled' => $this->healthcheck_enabled,
                'healthcheck_path' => $this->healthcheck_path,
                'healthcheck_port' => $this->healthcheck_port,
                'ingress_host' => $this->ingress_host,
                'ingress_path' => $this->ingress_path,
                'ingress_tls' => $this->ingress_tls,
                'env_vars' => $envVars,
                'secrets' => $secrets,
                'kubernetes_pipeline_id' => $this->pipeline_id,
                'status' => 'pending',
            ];

            if ($this->isEditing && $this->editingId) {
                $app = KubernetesApp::findOrFail($this->editingId);
                $app->update($data);
                $message = 'App updated successfully';
            } else {
                $app = KubernetesApp::create($data);
                $message = 'App created successfully';
            }

            $this->dispatch('banner', ['type' => 'success', 'message' => $message]);
            $this->loadData();
            $this->resetForm();

        } catch (\Exception $e) {
            $this->dispatch('banner', ['type' => 'error', 'message' => 'Failed to save: ' . $e->getMessage()]);
        }
    }

    public function deployApp(string $id): void
    {
        $this->deploying = true;
        $this->deployResult = null;

        try {
            $app = KubernetesApp::with('pipeline.cluster')->findOrFail($id);
            $cluster = $app->pipeline?->cluster ?? KubernetesCluster::where('is_default', true)->first();

            if (!$cluster) {
                throw new \Exception('No Kubernetes cluster available');
            }

            // Dispatch deployment job
            KubernetesDeploymentJob::dispatch($app, $cluster, $app->pipeline);

            $this->deployResult = 'success:Deployment started! Check logs for progress.';
            $app->update(['status' => 'deploying']);

            $this->loadData();

        } catch (\Exception $e) {
            $this->deployResult = 'error:' . $e->getMessage();
        } finally {
            $this->deploying = false;
        }
    }

    public function checkStatus(string $id): void
    {
        try {
            $app = KubernetesApp::with('pipeline.cluster')->findOrFail($id);
            $cluster = $app->pipeline?->cluster;

            if (!$cluster) {
                $this->statusResult = 'error:No cluster configured';
                return;
            }

            $k8s = new KubernetesService();
            $k8s->setCluster($cluster);

            $status = $k8s->getDeploymentStatus($app->name, $app->namespace);

            if ($status['available']) {
                $this->statusResult = 'success:Running (' . $status['ready'] . '/' . $status['replicas'] . ' replicas)';
                $app->update(['status' => 'deployed']);
            } else {
                $this->statusResult = 'info:Deploying... (' . ($status['ready'] ?? 0) . '/' . ($status['replicas'] ?? 0) . ' ready)';
            }

            $this->loadData();

        } catch (\Exception $e) {
            $this->statusResult = 'error:' . $e->getMessage();
        }
    }

    public function rollbackApp(string $id): void
    {
        try {
            $app = KubernetesApp::with('pipeline.cluster')->findOrFail($id);

            if (!$app->kubernetes_resource_version) {
                $this->deployResult = 'error:No previous version to rollback to';
                return;
            }

            $cluster = $app->pipeline?->cluster ?? KubernetesCluster::where('is_default', true)->first();
            if (!$cluster) {
                $this->deployResult = 'error:No cluster configured';
                return;
            }

            $k8s = new KubernetesService();
            $k8s->setCluster($cluster);

            // Get current deployment to find previous version
            $current = $k8s->getDeployment($app->name, $app->namespace);
            $annotations = data_get($current, 'metadata.annotations.deployment.kubernetes.io/revision');
            $prevRevision = $annotations ? ((int) $annotations - 1) : '1';

            if ($prevRevision < 1) {
                $this->deployResult = 'error:No previous revision found';
                return;
            }

            // Rollback to previous version
            $result = $k8s->rollbackDeployment($app->name, $app->namespace, (string) $prevRevision);

            if ($result) {
                $this->deployResult = 'success:Rolling back to revision ' . $prevRevision;
            } else {
                $this->deployResult = 'error:Rollback failed';
            }

        } catch (\Exception $e) {
            $this->deployResult = 'error:' . $e->getMessage();
        }
    }

    public function deleteApp(string $id): void
    {
        try {
            $app = KubernetesApp::findOrFail($id);

            // Try to delete from K8s first
            $cluster = $app->pipeline?->cluster;
            if ($cluster) {
                try {
                    $k8s = new KubernetesService();
                    $k8s->setCluster($cluster);
                    $k8s->deleteDeployment($app->name, $app->namespace);
                    $k8s->deleteService($app->name, $app->namespace);
                    if ($app->ingress_host) {
                        $k8s->deleteIngress($app->name, $app->namespace);
                    }
                } catch (\Exception $e) {
                    Log::warning('K8s cleanup warning: ' . $e->getMessage());
                }
            }

            $app->delete();
            $this->dispatch('banner', ['type' => 'success', 'message' => 'App deleted']);

            $this->loadData();

        } catch (\Exception $e) {
            $this->dispatch('banner', ['type' => 'error', 'message' => 'Failed to delete: ' . $e->getMessage()]);
        }
    }

    public function viewLogs(string $id): void
    {
        try {
            $app = KubernetesApp::with('pipeline.cluster')->findOrFail($id);
            $cluster = $app->pipeline?->cluster;

            if (!$cluster) {
                $this->logContent = "error:No cluster configured for this app";
                $this->showLogs = true;
                return;
            }

            $this->logAppId = $id;
            $this->logAppName = $app->name;
            $this->loadingLogs = true;
            $this->showLogs = true;

            $k8s = new KubernetesService();
            $k8s->setCluster($cluster);

            // Get pods for this app
            $pods = $k8s->listPods($app->namespace, 'app=' . $app->name);

            if (empty($pods)) {
                $this->logContent = "No pods found for {$app->name}";
                $this->loadingLogs = false;
                return;
            }

            // Get logs from first pod
            $firstPod = $pods[0];
            $podName = data_get($firstPod, 'metadata.name', '');

            if ($podName) {
                $this->logContent = $k8s->getPodLogs($podName, $app->namespace, 200);
            } else {
                $this->logContent = "Could not find pod name";
            }

        } catch (\Exception $e) {
            $this->logContent = "error:" . $e->getMessage();
        } finally {
            $this->loadingLogs = false;
        }
    }

    public function closeLogs(): void
    {
        $this->showLogs = false;
        $this->logContent = '';
        $this->logAppId = '';
        $this->logAppName = '';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->cluster_id = null;
        $this->pipeline_id = null;
        $this->namespace = 'default';
        $this->image_repository = '';
        $this->image_tag = 'latest';
        $this->container_port = 80;
        $this->replicas = 1;
        $this->pod_size = 'small';
        $this->buildstrategy = 'dockerfile';
        $this->dockerfile_path = 'Dockerfile';
        $this->build_commands = null;
        $this->autoscale_enabled = false;
        $this->autoscale_min = 1;
        $this->autoscale_max = 5;
        $this->autoscale_cpu_threshold = 70;
        $this->autoscale_memory_threshold = 80;
        $this->healthcheck_enabled = true;
        $this->healthcheck_path = '/';
        $this->healthcheck_port = 80;
        $this->ingress_host = null;
        $this->ingress_path = '/';
        $this->ingress_tls = false;
        $this->env_vars_json = '{}';
        $this->secrets_json = '{}';
        $this->showForm = false;
        $this->isEditing = false;
        $this->editingId = null;
        $this->deployResult = null;
        $this->deploying = false;
        $this->statusResult = null;
    }

    public function render()
    {
        return view('livewire.settings.kubernetes-apps');
    }
}
