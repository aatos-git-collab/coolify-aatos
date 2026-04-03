<?php

namespace App\Livewire\Project\Application;

use App\Models\Application;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Swarm extends Component
{
    public Application $application;

    #[Validate('required')]
    public int $swarmReplicas;

    #[Validate(['nullable'])]
    public ?string $swarmPlacementConstraints = null;

    #[Validate('required')]
    public bool $isSwarmOnlyWorkerNodes;

    // Auto-scaling
    public bool $autoScalingEnabled = false;

    public int $autoScalingMinReplicas = 1;

    public int $autoScalingMaxReplicas = 5;

    public int $autoScalingTargetCpu = 70;

    public int $autoScalingTargetMemory = 80;

    // Service Identifier for LB
    public ?string $swarmServiceIdentifier = null;

    public function mount()
    {
        try {
            $this->syncData();
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function syncData(bool $toModel = false)
    {
        if ($toModel) {
            $this->validate();
            $this->application->swarm_replicas = $this->swarmReplicas;
            $this->application->swarm_placement_constraints = $this->swarmPlacementConstraints ? base64_encode($this->swarmPlacementConstraints) : null;
            $this->application->settings->is_swarm_only_worker_nodes = $this->isSwarmOnlyWorkerNodes;

            // Auto-scaling
            $this->application->auto_scaling_enabled = $this->autoScalingEnabled;
            $this->application->auto_scaling_min_replicas = $this->autoScalingMinReplicas;
            $this->application->auto_scaling_max_replicas = $this->autoScalingMaxReplicas;
            $this->application->auto_scaling_target_cpu = $this->autoScalingTargetCpu;
            $this->application->auto_scaling_target_memory = $this->autoScalingTargetMemory;

            // Service Identifier
            $this->application->swarm_service_identifier = $this->swarmServiceIdentifier;

            $this->application->save();
            $this->application->settings->save();
        } else {
            $this->swarmReplicas = $this->application->swarm_replicas;
            if ($this->application->swarm_placement_constraints) {
                $this->swarmPlacementConstraints = base64_decode($this->application->swarm_placement_constraints);
            } else {
                $this->swarmPlacementConstraints = null;
            }
            $this->isSwarmOnlyWorkerNodes = $this->application->settings->is_swarm_only_worker_nodes;

            // Auto-scaling
            $this->autoScalingEnabled = $this->application->auto_scaling_enabled ?? false;
            $this->autoScalingMinReplicas = $this->application->auto_scaling_min_replicas ?? 1;
            $this->autoScalingMaxReplicas = $this->application->auto_scaling_max_replicas ?? 5;
            $this->autoScalingTargetCpu = $this->application->auto_scaling_target_cpu ?? 70;
            $this->autoScalingTargetMemory = $this->application->auto_scaling_target_memory ?? 80;

            // Service Identifier
            $this->swarmServiceIdentifier = $this->application->swarm_service_identifier;
        }
    }

    public function instantSave()
    {
        try {
            $this->syncData(true);
            $this->dispatch('success', 'Swarm settings updated.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function submit()
    {
        try {
            $this->syncData(true);
            $this->dispatch('success', 'Swarm settings updated.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function render()
    {
        return view('livewire.project.application.swarm');
    }
}
