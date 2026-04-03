<div>
    <x-slot:title>
        AI Debug Settings | Coolify
    </x-slot>
    <x-settings.navbar />
    <div class="flex flex-col h-full gap-8 sm:flex-row">
        <x-settings.sidebar activeMenu="ai" />
        <form wire:submit='submit' class="flex flex-col w-full">
            <div class="flex items-center gap-2">
                <h2>AI Debug</h2>
                <x-forms.button type="submit">
                    Save
                </x-forms.button>
            </div>
            <div class="pb-4">Configure AI service for deployment debugging and analysis.</div>

            <div class="flex flex-col gap-1">
                <div class="md:w-96">
                    <x-forms.select id="ai_provider" label="AI Provider"
                        helper="Select which AI provider to use for log analysis.">
                        <option value="openai" {{ $ai_provider === 'openai' ? 'selected' : '' }}>OpenAI</option>
                        <option value="anthropic" {{ $ai_provider === 'anthropic' ? 'selected' : '' }}>Anthropic (Claude)</option>
                        <option value="minimax" {{ $ai_provider === 'minimax' ? 'selected' : '' }}>MiniMax</option>
                    </x-forms.select>
                </div>

                <div class="md:w-96">
                    <x-forms.input type="password" id="ai_api_key" label="API Key"
                        helper="Your API key for the selected AI provider. This will be stored encrypted."
                        placeholder="sk-... or your provider key" />
                </div>

                <div class="md:w-96">
                    <x-forms.select id="ai_model" label="Model"
                        helper="Select the AI model to use for analysis.">
                        @if ($ai_provider === 'openai')
                            <option value="gpt-4o" {{ $ai_model === 'gpt-4o' ? 'selected' : '' }}>GPT-4o</option>
                            <option value="gpt-4-turbo" {{ $ai_model === 'gpt-4-turbo' ? 'selected' : '' }}>GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo" {{ $ai_model === 'gpt-3.5-turbo' ? 'selected' : '' }}>GPT-3.5 Turbo</option>
                        @elseif ($ai_provider === 'anthropic')
                            <option value="claude-sonnet-4-20250514" {{ $ai_model === 'claude-sonnet-4-20250514' ? 'selected' : '' }}>Claude Sonnet 4</option>
                            <option value="claude-3-5-sonnet-20240620" {{ $ai_model === 'claude-3-5-sonnet-20240620' ? 'selected' : '' }}>Claude 3.5 Sonnet</option>
                            <option value="claude-3-opus-20240229" {{ $ai_model === 'claude-3-opus-20240229' ? 'selected' : '' }}>Claude 3 Opus</option>
                        @elseif ($ai_provider === 'minimax')
                            <option value="MiniMax-M2.7" {{ $ai_model === 'MiniMax-M2.7' ? 'selected' : '' }}>MiniMax-M2.7</option>
                            <option value="MiniMax-M2.7-highspeed" {{ $ai_model === 'MiniMax-M2.7-highspeed' ? 'selected' : '' }}>MiniMax-M2.7-highspeed</option>
                        @endif
                    </x-forms.select>
                </div>

                <div class="flex items-center gap-2 pt-4">
                    <x-forms.button type="button" wire:click="testConnection"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50">
                        <span wire:loading.remove>Test Connection</span>
                        <span wire:loading>Testing...</span>
                    </x-forms.button>

                    @if ($test_result)
                        @if (str_starts_with($test_result, 'success:'))
                            <span class="text-success">{{ str_replace('success:', '', $test_result) }}</span>
                        @else
                            <span class="text-error">{{ str_replace('error:', '', $test_result) }}</span>
                        @endif
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>