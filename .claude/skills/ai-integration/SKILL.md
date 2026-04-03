---
name: ai-integration
description: >-
  Develops AI-powered debugging and analysis features. Activates when working with AI services,
  deployment analysis, log parsing, or AI-assisted troubleshooting in Coolify. Also covers
  AI self-healing monitor and container log analysis.
---

# AI Integration

## When to Apply

Activate this skill when:
- Working with `AiService.php` for AI-powered features
- Adding AI debugging to deployments
- Integrating new AI providers (OpenAI, Anthropic, MiniMax)
- Processing deployment logs for AI analysis
- Building self-healing AI monitor
- Adding sparkle button for live log analysis

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/AiService.php` | Main AI service with provider support |
| `app/Models/InstanceSettings.php` | AI configuration fields (ai_provider, ai_api_key, ai_model) |
| `app/Models/ApplicationDeploymentQueue.php` | Stores ai_analysis results |
| `app/Jobs/ApplicationDeploymentJob.php` | Auto-debug on deployment failure |
| `app/Jobs/AiLogMonitorJob.php` | Self-healing monitor job |
| `app/Models/AiHealingLog.php` | Healing action audit log |
| `app/Livewire/Settings/AiMonitor.php` | AI monitor settings UI |
| `app/Livewire/Project/Shared/GetLogs.php` | Container logs with AI analyze |

## AI Providers

- **OpenAI**: GPT-4o, GPT-4 Turbo, GPT-3.5 Turbo
- **Anthropic**: Claude Sonnet 4, Claude 3.5 Sonnet, Claude 3 Opus
- **MiniMax**: MiniMax-M2.7 (default), MiniMax-M2.7-highspeed

## Configuration

Settings stored in `InstanceSettings`:
```php
'ai_provider' => 'string',     // 'openai', 'anthropic', 'minimax'
'ai_api_key' => 'encrypted',  // Stored encrypted
'ai_model' => 'string',       // Provider-specific model
// AI Monitor settings
'ai_monitor_enabled' => 'boolean',
'ai_monitor_interval' => 'integer',
'ai_auto_heal_enabled' => 'boolean',
'ai_monitor_log_lines' => 'integer',
```

## Usage Pattern

```php
$aiService = new AiService();
$result = $aiService->analyzeLogs($logs, $deploymentUuid);
```

## Self-Healing Monitor

The `AiLogMonitorJob` runs every 5 minutes and:
1. Gets all servers with applications
2. Fetches container logs
3. Filters for errors (error, failed, exception, warning, oom, killed)
4. Sends to AI for analysis
5. If auto-heal enabled, attempts to restart failed containers

## Testing AI Features

- Mock AI service responses in tests
- Test with both successful and failed deployment scenarios
- Verify encrypted storage of API keys
- Test self-healing job with mock container errors
