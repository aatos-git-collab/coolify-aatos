<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Server;
use App\Traits\ExecuteRemoteCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoScaleSwarmJob implements ShouldQueue
{
    use Dispatchable, ExecuteRemoteCommand, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        $applications = Application::where('auto_scaling_enabled', true)
            ->whereNotNull('destination_id')
            ->get();

        foreach ($applications as $application) {
            $this->processAutoScaling($application);
        }
    }

    private function processAutoScaling(Application $application): void
    {
        $destination = $application->destination;
        if (! $destination || ! $destination instanceof \App\Models\SwarmDocker) {
            return;
        }

        $server = $destination->server;
        if (! $server || ! $server->isFunctional()) {
            return;
        }

        if (! $server->isSwarm()) {
            return;
        }

        try {
            $currentReplicas = $this->getCurrentReplicas($server, $application->uuid);
            $metrics = $this->getServiceMetrics($server, $application->uuid);

            $targetReplicas = $this->calculateTargetReplicas(
                $currentReplicas,
                $metrics['cpu_percent'],
                $metrics['memory_percent'],
                $application->auto_scaling_min_replicas ?? 1,
                $application->auto_scaling_max_replicas ?? 5,
                $application->auto_scaling_target_cpu ?? 70,
                $application->auto_scaling_target_memory ?? 80
            );

            if ($targetReplicas !== $currentReplicas) {
                $this->scaleService($server, $application->uuid, $targetReplicas);
                Log::info("Scaled {$application->name} from {$currentReplicas} to {$targetReplicas} replicas");
            }
        } catch (\Throwable $e) {
            Log::error("Auto-scaling failed for {$application->name}: ".$e->getMessage());
        }
    }

    private function getCurrentReplicas(Server $server, string $applicationUuid): int
    {
        $command = "docker service ls --filter name={$applicationUuid} --format '{{.Replicas}}'";
        $output = instant_remote_process([$command], $server);

        if (preg_match('/(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function getServiceMetrics(Server $server, string $applicationUuid): array
    {
        // Get CPU and memory metrics from docker stats
        $command = "docker stats --no-stream --format '{{.CPUPerc}}|{{.MemPerc}}' {$applicationUuid}";
        $output = instant_remote_process([$command], $server);

        $cpuPercent = 0;
        $memoryPercent = 0;

        if (preg_match('/([\d.]+)%?\|([\d.]+)%?/', $output, $matches)) {
            $cpuPercent = (float) $matches[1];
            $memoryPercent = (float) $matches[2];
        }

        return [
            'cpu_percent' => $cpuPercent,
            'memory_percent' => $memoryPercent,
        ];
    }

    private function calculateTargetReplicas(
        int $currentReplicas,
        float $cpuPercent,
        float $memoryPercent,
        int $minReplicas,
        int $maxReplicas,
        int $targetCpu,
        int $targetMemory
    ): int {
        $targetReplicas = $currentReplicas;

        // Scale up if either CPU or memory exceeds target
        if ($cpuPercent > $targetCpu || $memoryPercent > $targetMemory) {
            $maxUsage = max($cpuPercent, $memoryPercent);
            $scaleFactor = ceil($maxUsage / $targetCpu);
            $targetReplicas = min($currentReplicas * $scaleFactor, $maxReplicas);
        }
        // Scale down if both CPU and memory are below 50% of target
        elseif ($cpuPercent < ($targetCpu * 0.5) && $memoryPercent < ($targetMemory * 0.5)) {
            $targetReplicas = max((int) ($currentReplicas * 0.7), $minReplicas);
        }

        return max($minReplicas, min($targetReplicas, $maxReplicas));
    }

    private function scaleService(Server $server, string $applicationUuid, int $replicas): void
    {
        $command = "docker service scale {$applicationUuid}={$replicas}";
        instant_remote_process([$command], $server);
    }
}