<?php

namespace App\Livewire\Settings;

use App\Models\InstanceSettings;
use App\Services\AiService;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Ai extends Component
{
    public InstanceSettings $settings;

    #[Validate('nullable|string')]
    public ?string $ai_provider;

    #[Validate('nullable|string')]
    public ?string $ai_api_key;

    #[Validate('nullable|string')]
    public ?string $ai_model;

    public bool $testing_connection = false;

    public ?string $test_result = null;

    public function mount()
    {
        if (! isInstanceAdmin()) {
            return redirect()->route('dashboard');
        }
        $this->settings = instanceSettings();
        $this->ai_provider = $this->settings->ai_provider ?? 'minimax';
        $this->ai_api_key = $this->settings->ai_api_key;
        $this->ai_model = $this->settings->ai_model ?? 'MiniMax-M2.7';
    }

    public function submit()
    {
        try {
            $this->validate();

            $this->settings->ai_provider = $this->ai_provider;
            $this->settings->ai_api_key = $this->ai_api_key;
            $this->settings->ai_model = $this->ai_model;
            $this->settings->save();

            $this->dispatch('success', 'AI Settings Saved', 'Your AI configuration has been updated.');
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

            $result = $aiService->testConnection();

            $this->test_result = $result['success']
                ? 'success:Connection successful!'
                : 'error:'.$result['message'];
        } catch (\Throwable $e) {
            $this->test_result = 'error:'.$e->getMessage();
        } finally {
            $this->testing_connection = false;
        }
    }

    public function render()
    {
        return view('livewire.settings.ai');
    }
}