<?php

namespace App\Jobs;

use App\Models\KubernetesApp;
use App\Models\KubernetesCluster;
use App\Models\KubernetesPipeline;
use App\Services\KubernetesService;
use App\Services\KubernetesManifestGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Exception;

class KubernetesDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 600;

    public function __construct(
        public KubernetesApp $app,
        public KubernetesCluster $cluster,
        public ?KubernetesPipeline $pipeline = null
    ) {}

    public function handle(): void
    {
        Log::info("K8s Deployment: Starting deployment for {$this->app->name}");

        try {
            // Initialize K8s service
            $k8s = new KubernetesService();
            $k8s->setCluster($this->cluster);

            // Test connection first
            if (!$k8s->testConnection()) {
                throw new Exception("Cannot connect to Kubernetes cluster: {$this->cluster->name}");
            }

            Event::dispatch(new KubernetesAppStatusChanged(
                (string) $this->app->id,
                $this->app->name,
                'deploying',
                'Starting deployment to cluster: ' . $this->cluster->name
            ));

            // Ensure namespace exists
            $this->ensureNamespace($k8s);

            // Prepare env vars and secrets
            $envVars = $this->app->env_vars ?? [];
            $secrets = $this->app->secrets ?? [];

            // Generate manifests
            $generator = new KubernetesManifestGenerator($this->app, $this->cluster);
            $generator->setEnvVars($envVars)->setSecrets($secrets);
            $manifests = $generator->generateAll();

            // Deploy in order: ConfigMap, Secret, Deployment, Service, Ingress, HPA
            $this->deployManifests($k8s, $manifests);

            // Wait for deployment to be ready
            $this->waitForDeployment($k8s);

            // Update app status
            $this->app->update(['status' => 'deployed']);

            Event::dispatch(new KubernetesAppStatusChanged(
                (string) $this->app->id,
                $this->app->name,
                'deployed',
                'Deployment completed successfully'
            ));

            Log::info("K8s Deployment: Successfully deployed {$this->app->name}");

        } catch (Exception $e) {
            Log::error("K8s Deployment failed: {$e->getMessage()}");
            $this->app->update(['status' => 'failed']);

            Event::dispatch(new KubernetesAppStatusChanged(
                (string) $this->app->id,
                $this->app->name,
                'failed',
                $e->getMessage()
            ));

            throw $e;
        }
    }

    private function ensureNamespace(KubernetesService $k8s): void
    {
        $namespace = $this->app->namespace;

        if (!$k8s->namespaceExists($namespace)) {
            Log::info("K8s Deployment: Creating namespace {$namespace}");
            $k8s->createNamespace($namespace, [
                'coolify-managed' => 'true',
            ]);
        }
    }

    private function deployManifests(KubernetesService $k8s, array $manifests): void
    {
        // Deploy ConfigMap
        if (!empty($manifests['configmap'])) {
            Log::info("K8s Deployment: Creating ConfigMap");
            try {
                $k8s->createConfigMap(
                    $manifests['configmap']['metadata']['name'],
                    $manifests['configmap']['data'] ?? [],
                    $this->app->namespace
                );
            } catch (Exception $e) {
                // ConfigMap might already exist, log and continue
                Log::warning("ConfigMap creation warning: {$e->getMessage()}");
            }
        }

        // Deploy Secret
        if (!empty($manifests['secret'])) {
            Log::info("K8s Deployment: Creating Secret");
            try {
                $k8s->createSecret(
                    $manifests['secret']['metadata']['name'],
                    $manifests['secret']['data'] ?? [],
                    $this->app->namespace
                );
            } catch (Exception $e) {
                Log::warning("Secret creation warning: {$e->getMessage()}");
            }
        }

        // Deploy Deployment
        Log::info("K8s Deployment: Creating Deployment");
        $deployment = $k8s->createDeployment($manifests['deployment']);
        $this->app->update([
            'kubernetes_resource_version' => data_get($deployment, 'metadata.resourceVersion'),
        ]);

        // Deploy Service
        Log::info("K8s Deployment: Creating Service");
        try {
            $k8s->createService($manifests['service']);
        } catch (Exception $e) {
            Log::warning("Service creation warning: {$e->getMessage()}");
        }

        // Deploy Ingress
        if (!empty($manifests['ingress'])) {
            Log::info("K8s Deployment: Creating Ingress");
            try {
                $k8s->createIngress($manifests['ingress']);
            } catch (Exception $e) {
                Log::warning("Ingress creation warning: {$e->getMessage()}");
            }
        }

        // Deploy HPA
        if (!empty($manifests['hpa'])) {
            Log::info("K8s Deployment: Creating HPA");
            try {
                $k8s->createOrUpdateHPA(
                    $manifests['hpa']['metadata']['name'],
                    $this->app->namespace,
                    $manifests['hpa']['spec']
                );
            } catch (Exception $e) {
                Log::warning("HPA creation warning: {$e->getMessage()}");
            }
        }
    }

    private function waitForDeployment(KubernetesService $k8s, int $timeout = 300): void
    {
        $start = time();
        $maxWait = $timeout;

        while (time() - $start < $maxWait) {
            $status = $k8s->getDeploymentStatus($this->app->name, $this->app->namespace);

            if ($status['available']) {
                Log::info("K8s Deployment: Deployment {$this->app->name} is ready");
                return;
            }

            Log::info("K8s Deployment: Waiting for {$this->app->name}... ({$status['ready']}/{$status['replicas']} ready)");
            sleep(5);
        }

        throw new Exception("Timeout waiting for deployment {$this->app->name} to be ready");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("K8s DeploymentJob failed for {$this->app->name}: {$exception->getMessage()}");
        $this->app->update(['status' => 'failed']);

        Event::dispatch(new KubernetesAppStatusChanged(
            (string) $this->app->id,
            $this->app->name,
            'failed',
            'Job failed: ' . $exception->getMessage()
        ));
    }
}
