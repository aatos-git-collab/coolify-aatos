<?php

namespace App\Services;

use App\Models\KubernetesCluster;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class KubernetesService
{
    private ?KubernetesCluster $cluster = null;
    private ?string $token = null;
    private ?string $caBundle = null;
    private ?string $apiServer = null;

    public function setCluster(KubernetesCluster $cluster): self
    {
        $this->cluster = $cluster;
        $this->apiServer = $cluster->api_server_url;

        $kubeconfig = $this->decodeKubeconfig($cluster->kubeconfig);

        if (isset($kubeconfig['token'])) {
            $this->token = $kubeconfig['token'];
        }
        if (isset($kubeconfig['certificate-authority-data'])) {
            $this->caBundle = $kubeconfig['certificate-authority-data'];
        }

        return $this;
    }

    private function decodeKubeconfig(?string $encrypted): array
    {
        if (empty($encrypted)) {
            return [];
        }

        try {
            $decrypted = decrypt($encrypted);
            return json_decode($decrypted, true) ?? [];
        } catch (Exception $e) {
            Log::error('Failed to decode kubeconfig: ' . $e->getMessage());
            return [];
        }
    }

    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    private function baseUrl(): string
    {
        return rtrim($this->apiServer, '/');
    }

    // ==================== Connection ====================

    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get($this->baseUrl() . '/api/v1');

            return $response->successful();
        } catch (Exception $e) {
            Log::error('K8s connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getClusterInfo(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . '/api/v1');

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get cluster info: ' . $e->getMessage());
            return [];
        }
    }

    public function getClusterVersion(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . '/version');

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get cluster version: ' . $e->getMessage());
            return [];
        }
    }

    // ==================== Namespaces ====================

    public function listNamespaces(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . '/api/v1/namespaces');

            return $response->json()['items'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to list namespaces: ' . $e->getMessage());
            return [];
        }
    }

    public function createNamespace(string $name, array $labels = []): array
    {
        $manifest = [
            'apiVersion' => 'v1',
            'kind' => 'Namespace',
            'metadata' => [
                'name' => $name,
                'labels' => array_merge(['managed-by' => 'coolify'], $labels),
            ],
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . '/api/v1/namespaces');

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create namespace: ' . $e->getMessage());
            throw $e;
        }
    }

    public function namespaceExists(string $name): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/api/v1/namespaces/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteNamespace(string $name): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/api/v1/namespaces/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete namespace: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== Deployments ====================

    public function createDeployment(array $manifest): array
    {
        $namespace = $manifest['metadata']['namespace'] ?? 'default';

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/apis/apps/v1/namespaces/{$namespace}/deployments");

            if (!$response->successful()) {
                Log::error('Failed to create deployment: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create deployment: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getDeployment(string $name, string $namespace = 'default'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/apis/apps/v1/namespaces/{$namespace}/deployments/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get deployment: ' . $e->getMessage());
            return [];
        }
    }

    public function updateDeployment(string $name, string $namespace, array $manifest): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->put($this->baseUrl() . "/apis/apps/v1/namespaces/{$namespace}/deployments/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to update deployment: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteDeployment(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/apis/apps/v1/namespaces/{$namespace}/deployments/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete deployment: ' . $e->getMessage());
            return false;
        }
    }

    public function rollbackDeployment(string $name, string $namespace, string $revision): bool
    {
        try {
            $rollbackPayload = [
                'kind' => 'RollbackConfig',
                'apiVersion' => 'apps/v1',
                'rollbackTo' => ['revision' => $revision],
            ];

            $response = Http::withHeaders($this->headers())
                ->post(
                    $this->baseUrl() . "/apis/apps/v1/namespaces/{$namespace}/deployments/{$name}/rollback",
                    $rollbackPayload
                );

            if ($response->successful()) {
                Log::info("K8s Rollback: Deployment {$name} rolled back to revision {$revision}");
                return true;
            }

            Log::error('K8s rollback failed: ' . $response->body());
            return false;
        } catch (Exception $e) {
            Log::error('K8s rollback error: ' . $e->getMessage());
            return false;
        }
    }

    public function scaleDeployment(string $name, string $namespace, int $replicas): array
    {
        $deployment = $this->getDeployment($name, $namespace);

        if (empty($deployment)) {
            throw new Exception("Deployment not found: {$name}");
        }

        $deployment['spec']['replicas'] = $replicas;

        return $this->updateDeployment($name, $namespace, $deployment);
    }

    public function listDeployments(string $namespace = 'default'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/apis/apps/v1/namespaces/{$namespace}/deployments");

            return $response->json()['items'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to list deployments: ' . $e->getMessage());
            return [];
        }
    }

    public function getDeploymentStatus(string $name, string $namespace = 'default'): array
    {
        $deployment = $this->getDeployment($name, $namespace);

        if (empty($deployment)) {
            return ['available' => false, 'message' => 'Deployment not found'];
        }

        $status = data_get($deployment, 'status', []);
        $readyReplicas = data_get($status, 'readyReplicas', 0);
        $replicas = data_get($status, 'replicas', 0);

        return [
            'available' => $readyReplicas > 0 && $readyReplicas === $replicas,
            'ready' => $readyReplicas,
            'replicas' => $replicas,
            'unavailable' => data_get($status, 'unavailableReplicas'),
            'conditions' => data_get($status, 'conditions', []),
        ];
    }

    // ==================== Services ====================

    public function createService(array $manifest): array
    {
        $namespace = $manifest['metadata']['namespace'] ?? 'default';

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/api/v1/namespaces/{$namespace}/services");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create service: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getService(string $name, string $namespace = 'default'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/api/v1/namespaces/{$namespace}/services/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get service: ' . $e->getMessage());
            return [];
        }
    }

    public function deleteService(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/api/v1/namespaces/{$namespace}/services/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete service: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== Ingress ====================

    public function createIngress(array $manifest): array
    {
        $namespace = $manifest['metadata']['namespace'] ?? 'default';

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/apis/networking.k8s.io/v1/namespaces/{$namespace}/ingresses");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create ingress: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getIngress(string $name, string $namespace = 'default'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/apis/networking.k8s.io/v1/namespaces/{$namespace}/ingresses/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get ingress: ' . $e->getMessage());
            return [];
        }
    }

    public function deleteIngress(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/apis/networking.k8s.io/v1/namespaces/{$namespace}/ingresses/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete ingress: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== Pods ====================

    public function listPods(string $namespace = 'default', string $labelSelector = ''): array
    {
        try {
            $url = $this->baseUrl() . "/api/v1/namespaces/{$namespace}/pods";
            if ($labelSelector) {
                $url .= '?labelSelector=' . urlencode($labelSelector);
            }

            $response = Http::withHeaders($this->headers())->get($url);

            return $response->json()['items'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to list pods: ' . $e->getMessage());
            return [];
        }
    }

    public function getPodLogs(string $name, string $namespace = 'default', int $lines = 100): string
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/api/v1/namespaces/{$namespace}/pods/{$name}/log", [
                    'tailLines' => $lines,
                ]);

            return $response->body();
        } catch (Exception $e) {
            Log::error('Failed to get pod logs: ' . $e->getMessage());
            return '';
        }
    }

    public function deletePod(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/api/v1/namespaces/{$namespace}/pods/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete pod: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== Jobs ====================

    public function createJob(array $manifest): array
    {
        $namespace = $manifest['metadata']['namespace'] ?? 'default';

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/apis/batch/v1/namespaces/{$namespace}/jobs");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create job: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getJobStatus(string $name, string $namespace = 'default'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/apis/batch/v1/namespaces/{$namespace}/jobs/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get job status: ' . $e->getMessage());
            return [];
        }
    }

    public function deleteJob(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/apis/batch/v1/namespaces/{$namespace}/jobs/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete job: ' . $e->getMessage());
            return false;
        }
    }

    public function waitForJobCompletion(string $name, string $namespace = 'default', int $timeout = 300): bool
    {
        $start = time();

        while (time() - $start < $timeout) {
            $status = $this->getJobStatus($name, $namespace);
            $conditions = data_get($status, 'status.conditions', []);

            foreach ($conditions as $condition) {
                if (data_get($condition, 'type') === 'Complete' && data_get($condition, 'status') === 'True') {
                    return true;
                }
                if (data_get($condition, 'type') === 'Failed' && data_get($condition, 'status') === 'True') {
                    return false;
                }
            }

            sleep(5);
        }

        return false;
    }

    // ==================== HPA (Horizontal Pod Autoscaler) ====================

    public function createOrUpdateHPA(string $name, string $namespace, array $spec): array
    {
        $manifest = [
            'apiVersion' => 'autoscaling/v2',
            'kind' => 'HorizontalPodAutoscaler',
            'metadata' => [
                'name' => $name,
                'namespace' => $namespace,
            ],
            'spec' => $spec,
        ];

        // Try to get existing HPA first
        $existing = $this->getHPA($name, $namespace);

        if (!empty($existing)) {
            return $this->updateHPA($name, $namespace, $manifest);
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/apis/autoscaling/v2/namespaces/{$namespace}/horizontalpodautoscalers");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create HPA: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getHPA(string $name, string $namespace = 'default'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/apis/autoscaling/v2/namespaces/{$namespace}/horizontalpodautoscalers/{$name}");

            return $response->json();
        } catch (Exception $e) {
            return [];
        }
    }

    private function updateHPA(string $name, string $namespace, array $manifest): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->put($this->baseUrl() . "/apis/autoscaling/v2/namespaces/{$namespace}/horizontalpodautoscalers/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to update HPA: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteHPA(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/apis/autoscaling/v2/namespaces/{$namespace}/horizontalpodautoscalers/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete HPA: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== ConfigMaps & Secrets ====================

    public function createConfigMap(string $name, array $data, string $namespace = 'default'): array
    {
        $manifest = [
            'apiVersion' => 'v1',
            'kind' => 'ConfigMap',
            'metadata' => [
                'name' => $name,
                'namespace' => $namespace,
            ],
            'data' => $data,
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/api/v1/namespaces/{$namespace}/configmaps");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create configmap: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteConfigMap(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/api/v1/namespaces/{$namespace}/configmaps/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete configmap: ' . $e->getMessage());
            return false;
        }
    }

    public function createSecret(string $name, array $data, string $namespace = 'default', string $type = 'Opaque'): array
    {
        $manifest = [
            'apiVersion' => 'v1',
            'kind' => 'Secret',
            'metadata' => [
                'name' => $name,
                'namespace' => $namespace,
            ],
            'type' => $type,
            'data' => $this->encodeSecretData($data),
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/api/v1/namespaces/{$namespace}/secrets");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create secret: ' . $e->getMessage());
            throw $e;
        }
    }

    private function encodeSecretData(array $data): array
    {
        $encoded = [];
        foreach ($data as $key => $value) {
            $encoded[$key] = base64_encode($value);
        }
        return $encoded;
    }

    public function deleteSecret(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/api/v1/namespaces/{$namespace}/secrets/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete secret: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== PV/PVC (Persistent Volumes) ====================

    public function createPersistentVolumeClaim(string $name, array $spec, string $namespace = 'default'): array
    {
        $manifest = [
            'apiVersion' => 'v1',
            'kind' => 'PersistentVolumeClaim',
            'metadata' => [
                'name' => $name,
                'namespace' => $namespace,
            ],
            'spec' => $spec,
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->withBody(json_encode($manifest), 'application/json')
                ->post($this->baseUrl() . "/api/v1/namespaces/{$namespace}/persistentvolumeclaims");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to create PVC: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deletePersistentVolumeClaim(string $name, string $namespace = 'default'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete($this->baseUrl() . "/api/v1/namespaces/{$namespace}/persistentvolumeclaims/{$name}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to delete PVC: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== Events ====================

    public function getEvents(string $namespace = 'default', int $limit = 50): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/api/v1/namespaces/{$namespace}/events", [
                    'limit' => $limit,
                ]);

            return $response->json()['items'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to get events: ' . $e->getMessage());
            return [];
        }
    }

    // ==================== Node Operations ====================

    public function listNodes(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . '/api/v1/nodes');

            return $response->json()['items'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to list nodes: ' . $e->getMessage());
            return [];
        }
    }

    public function getNodeInfo(string $name): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get($this->baseUrl() . "/api/v1/nodes/{$name}");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get node info: ' . $e->getMessage());
            return [];
        }
    }
}
