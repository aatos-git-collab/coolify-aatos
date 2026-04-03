---
name: ai-integration
description: >-
  Develops AI-powered debugging and analysis features. Activates when working with AI services,
  deployment analysis, log parsing, or AI-assisted troubleshooting in Coolify.
---

# AI Integration

## When to Apply

Activate this skill when:
- Working with `AiService.php` for AI-powered features
- Adding AI debugging to deployments
- Integrating new AI providers (OpenAI, Anthropic, MiniMax)
- Processing deployment logs for AI analysis

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/AiService.php` | Main AI service with provider support |
| `app/Models/InstanceSettings.php` | AI configuration fields (ai_provider, ai_api_key, ai_model) |
| `app/Models/ApplicationDeploymentQueue.php` | Stores ai_analysis results |
| `app/Jobs/ApplicationDeploymentJob.php` | Auto-debug on deployment failure |

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
```

## Usage Pattern

```php
$aiService = new AiService();
$result = $aiService->analyzeLogs($logs, $deploymentUuid);
```

## Testing AI Features

- Mock AI service responses in tests
- Test with both successful and failed deployment scenarios
- Verify encrypted storage of API keys
