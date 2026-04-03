<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Services\AiAutoFixService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AiAutoFixJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public Application $application,
        public ApplicationDeploymentQueue $deployment
    ) {}

    public function handle(): void
    {
        Log::info("AI Auto-Fix Job: Starting for deployment {$this->deployment->deployment_uuid}");

        try {
            $service = new AiAutoFixService();
            $service->runAutoFixLoop($this->application, $this->deployment);
        } catch (\Throwable $e) {
            Log::error("AI Auto-Fix Job failed: ".$e->getMessage());

            throw $e;
        }
    }
}