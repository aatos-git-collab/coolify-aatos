<?php

namespace App\Livewire\Settings;

use App\Models\InstanceSettings;
use App\Models\AiHealingLog;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AiMonitor extends Component
{
    #[Validate('nullable')]
    public ?bool $ai_monitor_enabled = false;

    #[Validate('nullable')]
    public ?int $ai_monitor_interval = 5;

    #[Validate('nullable')]
    public ?bool $ai_auto_heal_enabled = false;

    #[Validate('nullable')]
    public ?int $ai_monitor_log_lines = 500;

    public $healing_logs = [];

    public function mount()
    {
        $settings = InstanceSettings::get();
        $this->ai_monitor_enabled = $settings->ai_monitor_enabled;
        $this->ai_monitor_interval = $settings->ai_monitor_interval ?? 5;
        $this->ai_auto_heal_enabled = $settings->ai_auto_heal_enabled;
        $this->ai_monitor_log_lines = $settings->ai_monitor_log_lines ?? 500;

        $this->loadHealingLogs();
    }

    public function loadHealingLogs()
    {
        $this->healing_logs = AiHealingLog::orderBy('created_at', 'desc')->limit(50)->get()->toArray();
    }

    public function submit()
    {
        $settings = InstanceSettings::get();
        $settings->ai_monitor_enabled = $this->ai_monitor_enabled ?? false;
        $settings->ai_monitor_interval = $this->ai_monitor_interval ?? 5;
        $settings->ai_auto_heal_enabled = $this->ai_auto_heal_enabled ?? false;
        $settings->ai_monitor_log_lines = $this->ai_monitor_log_lines ?? 500;
        $settings->save();

        $this->dispatch('success', 'AI Monitor settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.ai-monitor');
    }
}
