<?php

namespace App\Livewire\Settings;

use App\Models\KubernetesCluster;
use App\Services\KubernetesService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KubernetesClusters extends Component
{
    public array $clusters = [];
    public bool $showForm = false;
    public bool $isEditing = false;

    // Form fields
    #[Validate('required|string|min:2|max:255')]
    public string $name = '';

    #[Validate('required|string')]
    public string $kubeconfig = '';

    #[Validate('required|url')]
    public string $api_server_url = '';

    #[Validate('nullable|string')]
    public string $default_namespace = 'default';

    public bool $is_default = false;

    public ?string $editingId = null;
    public ?string $testResult = null;
    public bool $testing = false;

    public function mount()
    {
        if (! isInstanceAdmin()) {
            return redirect()->route('dashboard');
        }
        $this->loadClusters();
    }

    public function loadClusters(): void
    {
        $this->clusters = KubernetesCluster::all()->toArray();
    }

    public function createCluster(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->isEditing = false;
    }

    public function editCluster(string $id): void
    {
        $cluster = KubernetesCluster::findOrFail($id);
        $this->editingId = $id;
        $this->name = $cluster->name;
        $this->kubeconfig = ''; // Don't expose existing kubeconfig
        $this->api_server_url = $cluster->api_server_url;
        $this->default_namespace = $cluster->default_namespace;
        $this->is_default = $cluster->is_default;
        $this->isEditing = true;
        $this->showForm = true;
    }

    public function saveCluster(): void
    {
        $this->validate();

        try {
            // Parse and validate kubeconfig
            $kubeconfigData = $this->parseKubeconfig($this->kubeconfig);

            $data = [
                'name' => $this->name,
                'api_server_url' => $kubeconfigData['server'] ?? $this->api_server_url,
                'default_namespace' => $this->default_namespace,
                'is_default' => $this->is_default,
                'version' => $kubeconfigData['version'] ?? null,
                'distribution' => $kubeconfigData['distribution'] ?? null,
            ];

            if (!empty($this->kubeconfig)) {
                $data['kubeconfig'] = encrypt(json_encode($kubeconfigData));
            }

            if ($this->isEditing && $this->editingId) {
                $cluster = KubernetesCluster::findOrFail($this->editingId);
                $cluster->update($data);
                $this->dispatch('banner', [
                    'type' => 'success',
                    'message' => 'Cluster updated successfully',
                ]);
            } else {
                KubernetesCluster::create($data);
                $this->dispatch('banner', [
                    'type' => 'success',
                    'message' => 'Cluster created successfully',
                ]);
            }

            $this->loadClusters();
            $this->resetForm();

        } catch (\Exception $e) {
            $this->dispatch('banner', [
                'type' => 'error',
                'message' => 'Failed to save cluster: ' . $e->getMessage(),
            ]);
        }
    }

    private function parseKubeconfig(string $kubeconfigRaw): array
    {
        // Handle base64 encoded kubeconfig
        $decoded = base64_decode($kubeconfigRaw, true);
        if ($decoded) {
            $kubeconfigRaw = $decoded;
        }

        $kubeconfig = yaml_parse($kubeconfigRaw);

        if (!$kubeconfig) {
            throw new \Exception('Invalid kubeconfig format');
        }

        // Extract relevant info from kubeconfig
        $context = data_get($kubeconfig, 'current-context');
        $clusters = data_get($kubeconfig, 'clusters', []);
        $users = data_get($kubeconfig, 'users', []);

        $server = null;
        $caData = null;

        // Find current cluster
        foreach ($clusters as $cluster) {
            $clusterData = data_get($cluster, 'cluster', []);
            if (data_get($cluster, 'name') === $context || $server === null) {
                $server = data_get($clusterData, 'server');
                $caData = data_get($clusterData, 'certificate-authority-data');
            }
        }

        // Find current user token or cert
        $token = null;
        $userData = null;
        foreach ($users as $user) {
            if (data_get($user, 'name') === $context || $token === null) {
                $userData = data_get($user, 'user', []);
                $token = data_get($userData, 'token');
            }
        }

        return [
            'server' => $server,
            'token' => $token,
            'certificate-authority-data' => $caData,
            'version' => null, // Will be fetched from API
            'distribution' => $this->detectDistribution($server),
        ];
    }

    private function detectDistribution(?string $server): ?string
    {
        if (!$server) return null;

        $serverLower = strtolower($server);

        if (str_contains($serverLower, 'eks.amazonaws.com')) return 'EKS';
        if (str_contains($serverLower, 'gke.googleapis.com')) return 'GKE';
        if (str_contains($serverLower, 'aks.azure.com')) return 'AKS';
        if (str_contains($serverLower, 'digitalocean.com')) return 'DOKS';
        if (str_contains($serverLower, 'linode.com')) return 'LKE';

        return 'self-managed';
    }

    public function testConnection(string $id): void
    {
        $this->testing = true;
        $this->testResult = null;

        try {
            $cluster = KubernetesCluster::findOrFail($id);

            $k8s = new KubernetesService();
            $k8s->setCluster($cluster);

            if ($k8s->testConnection()) {
                $this->testResult = 'success:Connection successful!';
            } else {
                $this->testResult = 'error:Connection failed. Check credentials.';
            }
        } catch (\Exception $e) {
            $this->testResult = 'error:' . $e->getMessage();
        } finally {
            $this->testing = false;
        }
    }

    public function deleteCluster(string $id): void
    {
        try {
            $cluster = KubernetesCluster::findOrFail($id);
            $cluster->delete();

            $this->dispatch('banner', [
                'type' => 'success',
                'message' => 'Cluster deleted successfully',
            ]);

            $this->loadClusters();
        } catch (\Exception $e) {
            $this->dispatch('banner', [
                'type' => 'error',
                'message' => 'Failed to delete cluster: ' . $e->getMessage(),
            ]);
        }
    }

    public function setDefault(string $id): void
    {
        try {
            // Unset all defaults first
            KubernetesCluster::query()->update(['is_default' => false]);

            // Set new default
            $cluster = KubernetesCluster::findOrFail($id);
            $cluster->update(['is_default' => true]);

            $this->dispatch('banner', [
                'type' => 'success',
                'message' => 'Default cluster updated',
            ]);

            $this->loadClusters();
        } catch (\Exception $e) {
            $this->dispatch('banner', [
                'type' => 'error',
                'message' => 'Failed to set default: ' . $e->getMessage(),
            ]);
        }
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->kubeconfig = '';
        $this->api_server_url = '';
        $this->default_namespace = 'default';
        $this->is_default = false;
        $this->showForm = false;
        $this->isEditing = false;
        $this->editingId = null;
        $this->testResult = null;
    }

    public function render()
    {
        return view('livewire.settings.kubernetes-clusters');
    }
}
