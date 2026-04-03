<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private ?string $apiKey;

    private string $provider;

    private string $model;

    private string $baseUrl;

    private string $groupId;

    public function __construct(?string $apiKey = null, ?string $provider = null, ?string $model = null)
    {
        $settings = \App\Models\InstanceSettings::get();

        $this->apiKey = $apiKey ?? $settings->ai_api_key;
        $this->provider = $provider ?? $settings->ai_provider ?? 'minimax';
        $this->model = $model ?? $settings->ai_model ?? 'MiniMax-M2.7';

        $this->baseUrl = match ($this->provider) {
            'anthropic' => 'https://api.anthropic.com/v1',
            'minimax' => 'https://api.minimax.io/anthropic/v1',
            default => 'https://api.openai.com/v1',
        };
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function analyzeLogs(string $logs, ?string $deploymentUuid = null): string
    {
        if (! $this->isConfigured()) {
            throw new \Exception('AI service is not configured. Please set up your API key in settings.');
        }

        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->getUserPrompt($logs, $deploymentUuid);

        return match ($this->provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
            'minimax' => $this->callMinimax($systemPrompt, $userPrompt),
            default => $this->callOpenAI($systemPrompt, $userPrompt),
        };
    }

    private function callMinimax(string $systemPrompt, string $userPrompt): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(120)
                ->retry(3, function (int $attempt, \Exception $exception) {
                    return $attempt * 1000;
                })
                ->post('https://api.minimax.io/anthropic/v1/messages', [
                    'model' => $this->model,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 2048,
                ]);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown MiniMax error');
                $errorType = $response->json('error.type', '');
                Log::error("MiniMax API error: " . $error . " | Type: " . $errorType . " | Status: " . $response->status());

                throw new \Exception('MiniMax API error: '.$error);
            }

            $content = $response->json('content', []);
            $text = collect($content)->pluck('text')->implode("\n");

            Log::info("MiniMax API success, response length: " . strlen($text));

            return $text;
        } catch (\Exception $e) {
            Log::error("MiniMax API exception: " . $e->getMessage());
            throw $e;
        }
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert DevOps and debugging assistant. Your role is to analyze deployment logs and container outputs to identify the root cause of failures.

Provide:
1. A clear diagnosis of what went wrong
2. The most likely cause of the failure
3. Specific actionable steps to fix the issue
4. Any relevant commands or configuration changes needed

Be concise but thorough. Focus on the most critical issues first.
PROMPT;
    }

    private function getUserPrompt(string $logs, ?string $deploymentUuid): string
    {
        $context = $deploymentUuid ? "Deployment UUID: {$deploymentUuid}\n\n" : '';

        return <<<PROMPT
{$context}Analyze these deployment logs and container output to identify the failure cause:

```
{$logs}
```

Provide your analysis in this format:
- **Diagnosis:** [What went wrong]
- **Root Cause:** [Most likely cause]
- **Fix:** [How to resolve it]
- **Suggested Actions:** [Any commands or config changes]
PROMPT;
    }

    private function callOpenAI(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(60)
            ->retry(3, function (int $attempt, \Exception $exception) {
                return $attempt * 500; // Exponential backoff
            })
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Unknown OpenAI error');
            Log::error('OpenAI API error: '.$error);

            throw new \Exception('OpenAI API error: '.$error);
        }

        return $response->json('choices.0.message.content', '');
    }

    private function callAnthropic(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])
            ->timeout(60)
            ->retry(3, function (int $attempt, \Exception $exception) {
                return $attempt * 500;
            })
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1024,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Unknown Anthropic error');
            Log::error('Anthropic API error: '.$error);

            throw new \Exception('Anthropic API error: '.$error);
        }

        $content = $response->json('content', []);
        $text = collect($content)->pluck('text')->implode("\n");

        return $text;
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'API key not configured'];
        }

        try {
            $result = $this->analyzeLogs('Test connection. Just respond with "OK" if you can read this.');

            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Analyze logs specifically for generating fixes (not just analysis)
     */
    public function analyzeLogsForFix(string $logs, ?string $deploymentUuid = null): string
    {
        if (! $this->isConfigured()) {
            throw new \Exception('AI service is not configured.');
        }

        $systemPrompt = <<<'PROMPT'
You are an expert DevOps assistant. Your task is to analyze deployment/container logs AND generate specific fixes.

For docker-compose or Dockerfile issues:
1. Identify what's wrong
2. Generate the FIXED docker-compose.yml or Dockerfile content
3. Return the fixed content with [FIXED_FILE] markers

Format your response as:
- **Diagnosis:** What went wrong
- **Root Cause:** Why it failed
- **Fix:** How to resolve it
- **[FIXED_FILE:docker-compose.yml]** (if fixing docker-compose)
```
# Fixed docker-compose.yml content here
```
- **[FIXED_FILE:Dockerfile]** (if fixing Dockerfile)
```
# Fixed Dockerfile content here
```

IMPORTANT: Only fix Docker-level files (docker-compose.yml, Dockerfile), NOT application source code.
PROMPT;

        $userPrompt = $this->getUserPrompt($logs, $deploymentUuid);

        return match ($this->provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
            'minimax' => $this->callMinimax($systemPrompt, $userPrompt),
            default => $this->callOpenAI($systemPrompt, $userPrompt),
        };
    }

    /**
     * Generate fixed docker-compose.yml based on current content and analysis
     */
    public function generateFixedDockerCompose(string $currentContent, string $analysis): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are an expert at Docker and docker-compose. Given the current docker-compose.yml content and analysis of what's wrong, generate the FIXED version.

Rules:
1. Only output the fixed YAML content, nothing else
2. Keep all existing services that are working
3. Fix only the issues identified in the analysis
4. Preserve Coolify labels and configuration
5. Ensure valid YAML syntax
PROMPT;

        $userPrompt = <<<PROMPT
Current docker-compose.yml:
```
{$currentContent}
```

Analysis/Issues to fix:
```
{$analysis}
```

Generate the fixed docker-compose.yml:
PROMPT;

        $result = match ($this->provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
            'minimax' => $this->callMinimax($systemPrompt, $userPrompt),
            default => $this->callOpenAI($systemPrompt, $userPrompt),
        };

        // Extract YAML from result
        return $this->extractYamlFromResponse($result);
    }

    /**
     * Generate fixed Dockerfile based on current content and analysis
     */
    public function generateFixedDockerfile(string $currentContent, string $analysis): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are an expert at Docker. Given the current Dockerfile content and analysis of what's wrong, generate the FIXED version.

Rules:
1. Only output the fixed Dockerfile content, nothing else
2. Fix only the issues identified in the analysis
3. Preserve existing working parts
4. Ensure valid Dockerfile syntax
PROMPT;

        $userPrompt = <<<PROMPT
Current Dockerfile:
```
{$currentContent}
```

Analysis/Issues to fix:
```
{$analysis}
```

Generate the fixed Dockerfile:
PROMPT;

        $result = match ($this->provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
            'minimax' => $this->callMinimax($systemPrompt, $userPrompt),
            default => $this->callOpenAI($systemPrompt, $userPrompt),
        };

        return $this->extractDockerfileFromResponse($result);
    }

    /**
     * Fix generic file content
     */
    public function fixFileContent(string $currentContent, string $filename, string $analysis): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are an expert at fixing configuration files. Given the current file content and analysis of what's wrong, generate the FIXED version.

Rules:
1. Only output the fixed content, nothing else
2. Fix only the issues identified in the analysis
3. Preserve existing working parts
PROMPT;

        $userPrompt = <<<PROMPT
Current {$filename}:
```
{$currentContent}
```

Analysis/Issues to fix:
```
{$analysis}
```

Generate the fixed file:
PROMPT;

        return match ($this->provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
            'minimax' => $this->callMinimax($systemPrompt, $userPrompt),
            default => $this->callOpenAI($systemPrompt, $userPrompt),
        };
    }

    private function extractYamlFromResponse(string $response): ?string
    {
        // Look for markdown code blocks
        if (preg_match('/```(?:yaml|yml)?\s*(.*?)\s*```/s', $response, $matches)) {
            return trim($matches[1]);
        }

        // Look for [FIXED_FILE] marker
        if (preg_match('/\[FIXED_FILE:docker-compose\.?yml?\]\s*(.*?)(?=\n\[FIXED_FILE|$)/s', $response, $matches)) {
            return trim($matches[1]);
        }

        // If no code block, return the whole response if it looks like YAML
        $response = trim($response);
        if (strpos($response, 'version:') !== false || strpos($response, 'services:') !== false) {
            return $response;
        }

        return null;
    }

    private function extractDockerfileFromResponse(string $response): ?string
    {
        // Look for markdown code blocks
        if (preg_match('/```(?:dockerfile)?\s*(.*?)\s*```/s', $response, $matches)) {
            return trim($matches[1]);
        }

        // Look for [FIXED_FILE] marker
        if (preg_match('/\[FIXED_FILE:Dockerfile\]\s*(.*?)(?=\n\[FIXED_FILE|$)/s', $response, $matches)) {
            return trim($matches[1]);
        }

        // If no code block, return the whole response if it looks like Dockerfile
        $response = trim($response);
        if (strpos($response, 'FROM') !== false || strpos($response, 'RUN') !== false) {
            return $response;
        }

        return null;
    }

    /**
     * Analyze source code to detect framework/language
     * Receives file analysis data instead of running commands directly
     */
    public function analyzeSourceCode(array $filesAnalysis, string $deploymentUuid): array
    {
        if (! $this->isConfigured()) {
            throw new \Exception('AI service is not configured.');
        }

        $systemPrompt = <<<'PROMPT'
You are an expert DevOps engineer specializing in detecting programming languages and frameworks from source code.

Your task is to analyze the provided project files and determine:
1. The primary programming language (e.g., node, python, php, go, rust, java, ruby)
2. The framework if applicable (e.g., express, django, laravel, fastapi, rails, spring)
3. The package manager/build system (e.g., npm, pip, composer, cargo, maven)
4. The default port the application runs on
5. Key dependencies that indicate the framework

Look at:
- File extensions (.js, .py, .php, .go, .rs, .java, .rb)
- Config files (package.json, requirements.txt, composer.json, go.mod, Cargo.toml, pom.xml)
- Framework-specific files (Dockerfile, docker-compose.yml, next.config.js, nuxt.config.ts, etc.)

IMPORTANT: You MUST respond with ONLY valid JSON in this exact format:
{"framework": "framework-name", "language": "language-name", "build_system": "build-tool", "port": "port-number", "dependencies": ["dep1", "dep2"]}

Do NOT include any other text or explanation. Just the JSON.
PROMPT;

        // Check if project already has Dockerfile or docker-compose
        $fileList = $filesAnalysis['file_list'] ?? '';
        $contents = $filesAnalysis['contents'] ?? [];

        // DEBUG: Log what's in contents
        Log::info("AI analyzeSourceCode: contents keys = " . implode(', ', array_keys($contents)));

        // Check for both Dockerfile and docker-compose files
        $hasDockerfile = !empty($contents['Dockerfile']);
        $hasComposeYml = !empty($contents['docker-compose.yml'] ?? '');
        $hasComposeYaml = !empty($contents['docker-compose.yaml'] ?? '');

        Log::info("AI analyzeSourceCode: hasDockerfile=" . ($hasDockerfile ? 'true' : 'false') .
            ", hasComposeYml=" . ($hasComposeYml ? 'true' : 'false') .
            ", hasComposeYaml=" . ($hasComposeYaml ? 'true' : 'false'));

        if ($hasDockerfile) {
            Log::info("AI analyzeSourceCode: Dockerfile content length = " . strlen($contents['Dockerfile']));
        }

        // If project has existing Dockerfile or docker-compose, use them directly
        if ($hasDockerfile || $hasComposeYml || $hasComposeYaml) {
            Log::info("AI analyzeSourceCode: Found existing docker files, using them directly");

            // Return early with existing docker config
            return [
                'framework' => 'existing_docker',
                'language' => 'docker',
                'build_system' => 'docker',
                'port' => '3000',
                'dependencies' => [],
                'has_dockerfile' => $hasDockerfile,
                'has_compose' => $hasComposeYml || $hasComposeYaml,
                'existing_dockerfile' => $contents['Dockerfile'] ?? '',
                'existing_compose' => $contents['docker-compose.yml'] ?? $contents['docker-compose.yaml'] ?? '',
            ];
        }

        Log::info("AI analyzeSourceCode: No existing docker files found in contents");

        // Build a clear summary of the project
        $fileList = $filesAnalysis['file_list'] ?? '';
        $contents = $filesAnalysis['contents'] ?? [];

        $summary = "Project Files Found:\n";
        $summary .= $fileList . "\n\n";
        $summary .= "Key File Contents:\n";

        foreach ($contents as $filename => $content) {
            $summary .= "\n=== $filename ===\n";
            $summary .= substr($content, 0, 2000) . "\n";
        }

        $userPrompt = <<<PROMPT
Analyze this project and determine its framework. Look at the file names and their contents to identify what kind of application this is.

{$summary}

Respond with ONLY valid JSON like: {"framework": "express", "language": "node", "build_system": "npm", "port": "3000", "dependencies": ["express", "react"]}
PROMPT;

        try {
            $result = match ($this->provider) {
                'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
                'minimax' => $this->callMinimax($systemPrompt, $userPrompt),
                default => $this->callOpenAI($systemPrompt, $userPrompt),
            };

            // Log for debugging
            Log::info("AI Source Analysis Result: " . substr($result, 0, 1000));

            // Try to parse JSON from response
            if (preg_match('/\{.*\}/s', $result, $matches)) {
                $json = json_decode($matches[0], true);
                if ($json && !empty($json['framework'])) {
                    Log::info("AI Framework detected: " . json_encode($json));
                    return [
                        'framework' => $json['framework'] ?? 'unknown',
                        'language' => $json['language'] ?? 'unknown',
                        'build_system' => $json['build_system'] ?? 'unknown',
                        'port' => $json['port'] ?? '3000',
                        'dependencies' => $json['dependencies'] ?? [],
                        'has_dockerfile' => $json['has_dockerfile'] ?? false,
                        'has_compose' => $json['has_compose'] ?? false,
                    ];
                }
            } else {
                Log::warning("AI returned non-JSON response: " . substr($result, 0, 500));
            }
        } catch (\Exception $e) {
            Log::error("AI Source Analysis failed: " . $e->getMessage() . " | Provider: " . $this->provider);
        }

        // Improved fallback logic - try to detect from file list
        $fallback = $this->detectFromFileList($fileList);
        if ($fallback['framework'] !== 'unknown') {
            return $fallback;
        }

        // Final fallback
        return [
            'framework' => 'unknown',
            'language' => 'unknown',
            'build_system' => 'unknown',
            'port' => '3000',
            'dependencies' => [],
            'has_dockerfile' => false,
            'has_compose' => false,
        ];
    }

    /**
     * Fallback detection from file list when AI fails
     */
    private function detectFromFileList(string $fileList): array
    {
        $files = strtolower($fileList);

        // Node.js detection
        if (str_contains($files, 'package.json')) {
            if (str_contains($files, 'next.config') || str_contains($files, 'next.js')) {
                return ['framework' => 'next', 'language' => 'node', 'build_system' => 'npm', 'port' => '3000', 'dependencies' => ['next', 'react']];
            }
            if (str_contains($files, 'nuxt.config')) {
                return ['framework' => 'nuxt', 'language' => 'node', 'build_system' => 'npm', 'port' => '3000', 'dependencies' => ['nuxt', 'vue']];
            }
            if (str_contains($files, 'package.json')) {
                return ['framework' => 'node', 'language' => 'node', 'build_system' => 'npm', 'port' => '3000', 'dependencies' => []];
            }
        }

        // Python detection
        if (str_contains($files, 'requirements.txt') || str_contains($files, 'pyproject.toml') || str_contains($files, 'Pipfile')) {
            if (str_contains($files, 'django')) {
                return ['framework' => 'django', 'language' => 'python', 'build_system' => 'pip', 'port' => '8000', 'dependencies' => ['django']];
            }
            if (str_contains($files, 'flask')) {
                return ['framework' => 'flask', 'language' => 'python', 'build_system' => 'pip', 'port' => '5000', 'dependencies' => ['flask']];
            }
            if (str_contains($files, 'fastapi')) {
                return ['framework' => 'fastapi', 'language' => 'python', 'build_system' => 'pip', 'port' => '8000', 'dependencies' => ['fastapi']];
            }
            return ['framework' => 'python', 'language' => 'python', 'build_system' => 'pip', 'port' => '8000', 'dependencies' => []];
        }

        // PHP detection
        if (str_contains($files, 'composer.json')) {
            if (str_contains($files, 'laravel')) {
                return ['framework' => 'laravel', 'language' => 'php', 'build_system' => 'composer', 'port' => '80', 'dependencies' => ['laravel']];
            }
            if (str_contains($files, 'symfony')) {
                return ['framework' => 'symfony', 'language' => 'php', 'build_system' => 'composer', 'port' => '80', 'dependencies' => ['symfony']];
            }
            return ['framework' => 'php', 'language' => 'php', 'build_system' => 'composer', 'port' => '80', 'dependencies' => []];
        }

        // Go detection
        if (str_contains($files, 'go.mod')) {
            return ['framework' => 'go', 'language' => 'go', 'build_system' => 'go', 'port' => '8080', 'dependencies' => []];
        }

        // Rust detection
        if (str_contains($files, 'cargo.toml')) {
            return ['framework' => 'rust', 'language' => 'rust', 'build_system' => 'cargo', 'port' => '8080', 'dependencies' => []];
        }

        // Java detection
        if (str_contains($files, 'pom.xml') || str_contains($files, 'build.gradle')) {
            if (str_contains($files, 'spring')) {
                return ['framework' => 'spring', 'language' => 'java', 'build_system' => 'maven', 'port' => '8080', 'dependencies' => ['spring-boot']];
            }
            return ['framework' => 'java', 'language' => 'java', 'build_system' => 'maven', 'port' => '8080', 'dependencies' => []];
        }

        // Ruby detection
        if (str_contains($files, 'gemfile')) {
            if (str_contains($files, 'rails')) {
                return ['framework' => 'rails', 'language' => 'ruby', 'build_system' => 'bundle', 'port' => '3000', 'dependencies' => ['rails']];
            }
            return ['framework' => 'ruby', 'language' => 'ruby', 'build_system' => 'bundle', 'port' => '3000', 'dependencies' => []];
        }

        return ['framework' => 'unknown', 'language' => 'unknown', 'build_system' => 'unknown', 'port' => '3000', 'dependencies' => []];
    }

    /**
     * Generate Dockerfile based on source analysis
     */
    public function generateDockerfileFromAnalysis(array $analysis): string
    {
        if (! $this->isConfigured()) {
            throw new \Exception('AI service is not configured.');
        }

        $framework = strtolower($analysis['framework'] ?? 'unknown');
        $language = strtolower($analysis['language'] ?? 'unknown');
        // Ensure port is a valid number, default to 3000
        $port = $analysis['port'] ?? '3000';
        if (! is_numeric($port) || $port <= 0) {
            $port = '3000';
        }
        $dependencies = $analysis['dependencies'] ?? [];

        // Generate specific Dockerfile based on detected stack
        return $this->generateFrameworkDockerfile($framework, $language, $port, $dependencies);
    }

    private function generateFrameworkDockerfile(string $framework, string $language, string $port, array $dependencies): string
    {
        $port = (string) $port;

        // Node.js / JavaScript / TypeScript frameworks
        if (in_array($framework, ['node', 'nodejs', 'next', 'nuxt', 'express', 'nest', 'svelte', 'vue', 'react', 'angular']) ||
            str_contains($language, 'node') || str_contains($language, 'javascript') || str_contains($language, 'typescript')) {
            return $this->getNodeDockerfile($port, $dependencies);
        }

        // Python frameworks
        if (in_array($framework, ['python', 'django', 'flask', 'fastapi', 'pandas', 'uvicorn']) ||
            str_contains($language, 'python')) {
            return $this->getPythonDockerfile($port, $dependencies);
        }

        // PHP frameworks
        if (in_array($framework, ['php', 'laravel', 'symfony', 'wordpress', 'codeigniter']) ||
            str_contains($language, 'php')) {
            return $this->getPhpDockerfile($port);
        }

        // Go
        if (in_array($framework, ['go', 'golang']) || str_contains($language, 'go')) {
            return $this->getGoDockerfile($port);
        }

        // Rust
        if (in_array($framework, ['rust', 'cargo']) || str_contains($language, 'rust')) {
            return $this->getRustDockerfile($port);
        }

        // Ruby
        if (in_array($framework, ['ruby', 'rails', 'sinatra']) || str_contains($language, 'ruby')) {
            return $this->getRubyDockerfile($port);
        }

        // Java
        if (in_array($framework, ['java', 'spring', 'maven', 'gradle']) || str_contains($language, 'java')) {
            return $this->getJavaDockerfile($port);
        }

        // Static/HTML
        if (in_array($framework, ['static', 'html', 'html5'])) {
            return $this->getStaticDockerfile($port);
        }

        // Default - try to infer from dependencies
        if (! empty($dependencies)) {
            if (in_array('npm', $dependencies) || in_array('yarn', $dependencies)) {
                return $this->getNodeDockerfile($port, $dependencies);
            }
            if (in_array('pip', $dependencies) || in_array('poetry', $dependencies)) {
                return $this->getPythonDockerfile($port, $dependencies);
            }
            if (in_array('composer', $dependencies)) {
                return $this->getPhpDockerfile($port);
            }
        }

        // Fallback - use the detected language to generate a sensible default
        return $this->getDefaultDockerfile($language, $port);
    }

    private function getNodeDockerfile(string $port, array $dependencies): string
    {
        $nodeVersion = '18';
        $hasNpm = in_array('npm', $dependencies);
        $hasYarn = in_array('yarn', $dependencies);
        $hasPnpm = in_array('pnpm', $dependencies);
        $packageManager = $hasPnpm ? 'pnpm' : ($hasYarn ? 'yarn' : 'npm');

        return <<<DOCKERFILE
# Build stage
FROM node:{$nodeVersion}-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./
COPY yarn.lock* ./
COPY pnpm-lock.yaml* ./

# Install dependencies
RUN if [ -f pnpm-lock.yaml ]; then \\
    corepack enable pnpm && pnpm install --frozen-lockfile; \\
    elif [ -f yarn.lock ]; then yarn install --frozen-lockfile; \\
    else npm ci; fi

# Copy source
COPY . .

# Build if needed
RUN if [ -f "next.config.js" ]; then npm run build; fi
RUN if [ -f "vite.config.js" ]; then npm run build; fi
RUN if [ -f "nuxt.config.ts" ]; then npm run build; fi

# Production stage
FROM node:{$nodeVersion}-alpine

WORKDIR /app

# Create non-root user
RUN addgroup -g 1001 -S nodejs && \\
    adduser -S nodejs -u 1001

# Copy from builder
COPY --from=builder --chown=nodejs:nodejs /app/node_modules ./node_modules
COPY --from=builder --chown=nodejs:nodejs /app ./

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD wget --no-verbose --tries=1 --spider http://localhost:{$port}/ || exit 1

# Run as non-root
USER nodejs

CMD ["node", "index.js"]
DOCKERFILE;
    }

    private function getPythonDockerfile(string $port, array $dependencies): string
    {
        $hasPip = in_array('pip', $dependencies);
        $hasPoetry = in_array('poetry', $dependencies);
        $hasPipenv = in_array('pipenv', $dependencies);
        $hasUv = in_array('uv', $dependencies);

        $pythonVersion = '3.11';

        $installCmd = 'pip install --no-cache-dir -r requirements.txt';
        if ($hasPoetry) {
            $installCmd = 'pip install poetry && poetry install --no-interaction --no-root';
        } elseif ($hasPipenv) {
            $installCmd = 'pip install pipenv && pipenv install --deploy --prod';
        } elseif ($hasUv) {
            $installCmd = 'pip install uv && uv pip install -r requirements.txt --system';
        }

        return <<<DOCKERFILE
# Build stage
FROM python:{$pythonVersion}-slim AS builder

WORKDIR /app

# Install dependencies
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Copy source
COPY . .

# Production stage
FROM python:{$pythonVersion}-slim

WORKDIR /app

# Install runtime dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \\
    gcc libpq-dev && rm -rf /var/lib/apt/lists/*

COPY --from=builder /usr/local/lib/python3.11/site-packages /usr/local/lib/python3.11/site-packages
COPY --from=builder /usr/local/bin /usr/local/bin
COPY . .

# Create non-root user
RUN useradd -m -u 1000 appuser

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:{$port}')" || exit 1

# Run as non-root
USER appuser

CMD ["python", "app.py"]
DOCKERFILE;
    }

    private function getPhpDockerfile(string $port): string
    {
        return <<<DOCKERFILE
FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# Install extensions and dependencies
RUN apk add --no-cache \\
    nginx curl git \\
    && docker-php-ext-install pdo pdo_mysql opcache

# Copy source
COPY . .

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install dependencies
RUN if [ -f "composer.json" ]; then composer install --no-interaction --no-dev; fi

# Configure PHP
RUN echo "[www]" >> /usr/local/etc/php/php.ini && \\
    echo "pm = ondemand" >> /usr/local/etc/php/php.ini && \\
    echo "pm.max_children = 10" >> /usr/local/etc/php/php.ini

# Configure Nginx
RUN echo "server { listen {$port}; root /var/www/html; index index.php; location / { try_files \$uri \$uri/ /index.php?\$query_string; } location ~ \\.php$ { fastcgi_pass 127.0.0.1:9000; fastcgi_index index.php; fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; include fastcgi_params; } }" > /etc/nginx/http.d/default.conf

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD curl -f http://localhost:{$port}/ || exit 1

CMD ["sh", "-c", "php-fpm && nginx -g 'daemon off;'"]
DOCKERFILE;
    }

    private function getGoDockerfile(string $port): string
    {
        return <<<DOCKERFILE
# Build stage
FROM golang:1.21-alpine AS builder

WORKDIR /app

# Copy go mod files
COPY go.mod ./
RUN go mod download

# Copy source
COPY . .

# Build
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o main .

# Production stage
FROM alpine:3.19

WORKDIR /app

# Install CA certificates
RUN apk --no-cache add ca-certificates

# Copy binary from builder
COPY --from=builder /app/main .

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD wget --no-verbose --tries=1 --spider http://localhost:{$port}/ || exit 1

CMD ["./main"]
DOCKERFILE;
    }

    private function getRustDockerfile(string $port): string
    {
        return <<<DOCKERFILE
# Build stage
FROM rust:1.75-alpine AS builder

WORKDIR /app

# Copy Cargo files
COPY Cargo.toml ./
COPY src/ ./src/

# Build
RUN cargo build --release

# Production stage
FROM alpine:3.19

WORKDIR /app

# Install runtime dependencies
RUN apk --no-cache add ca-certificates

# Copy binary from builder
COPY --from=builder /app/target/release/app .

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD wget --no-verbose --tries=1 --spider http://localhost:{$port}/ || exit 1

CMD ["./app"]
DOCKERFILE;
    }

    private function getRubyDockerfile(string $port): string
    {
        return <<<DOCKERFILE
FROM ruby:3.2-alpine

WORKDIR /app

# Install dependencies
RUN apk add --no-cache build-base libpq-dev nodejs yarn

# Copy Gemfile
COPY Gemfile ./
COPY Gemfile.lock ./
RUN bundle install

# Copy source
COPY . .

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD wget --no-verbose --tries=1 --spider http://localhost:{$port}/ || exit 1

CMD ["bundle", "exec", "rackup", "-p", "{$port}"]
DOCKERFILE;
    }

    private function getJavaDockerfile(string $port): string
    {
        return <<<DOCKERFILE
# Build stage
FROM eclipse-temurin:17-jdk-alpine AS builder

WORKDIR /app

# Copy pom.xml
COPY pom.xml ./
RUN mvn dependency:go-offline -B

# Copy source
COPY src ./src

# Build
RUN mvn package -DskipTests

# Production stage
FROM eclipse-temurin:17-jre-alpine

WORKDIR /app

# Copy jar from builder
COPY --from=builder /app/target/*.jar app.jar

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD wget --no-verbose --tries=1 --spider http://localhost:{$port}/ || exit 1

CMD ["java", "-jar", "app.jar"]
DOCKERFILE;
    }

    private function getStaticDockerfile(string $port): string
    {
        return <<<DOCKERFILE
FROM nginx:alpine

# Copy custom config
COPY nginx.conf /etc/nginx/nginx.conf

# Copy static files
COPY . /usr/share/nginx/html/

# Expose port
EXPOSE {$port}

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
    CMD wget --no-verbose --tries=1 --spider http://localhost:{$port}/ || exit 1

CMD ["nginx", "-g", "daemon off;"]
DOCKERFILE;
    }

    private function getDefaultDockerfile(string $language, string $port): string
    {
        // Ensure we have a valid port
        $validPort = is_numeric($port) && $port > 0 ? $port : '3000';

        $baseImage = match (strtolower($language)) {
            'node', 'javascript', 'typescript' => 'node:18-alpine',
            'python' => 'python:3.11-slim',
            'php' => 'php:8.2-fpm',
            'go', 'golang' => 'golang:1.21-alpine',
            'rust' => 'rust:1.75-alpine',
            'ruby' => 'ruby:3.2-alpine',
            'java' => 'eclipse-temurin:17-jre-alpine',
            default => 'alpine:3.19',
        };

        return <<<DOCKERFILE
FROM {$baseImage}

WORKDIR /app

COPY . .

EXPOSE {$validPort}

CMD ["echo", "Container running on port {$validPort}"]
DOCKERFILE;
    }

    /**
     * Generate docker-compose.yml based on source analysis
     */
    public function generateDockerComposeFromAnalysis(array $analysis): string
    {
        $framework = strtolower($analysis['framework'] ?? 'unknown');
        $language = strtolower($analysis['language'] ?? 'unknown');
        // Ensure port is a valid number, default to 3000
        $port = $analysis['port'] ?? '3000';
        if (! is_numeric($port) || $port <= 0) {
            $port = '3000';
        }

        // Generate specific docker-compose based on framework
        return $this->generateFrameworkCompose($framework, $language, $port);
    }

    private function generateFrameworkCompose(string $framework, string $language, string $port): string
    {
        $portStr = (string) $port;

        // Check for database-like frameworks (might need additional services)
        $needsDb = false;
        if (in_array($framework, ['laravel', 'django', 'rails', 'spring'])) {
            $needsDb = true;
        }

        if ($needsDb) {
            return $this->getComposeWithDatabase($portStr);
        }

        // Node.js compose
        if (in_array($framework, ['node', 'nodejs', 'next', 'nuxt', 'express', 'nest', 'svelte', 'vue', 'react', 'angular']) ||
            str_contains($language, 'node')) {
            return $this->getNodeCompose($portStr);
        }

        // Python compose
        if (in_array($framework, ['python', 'django', 'flask', 'fastapi']) || str_contains($language, 'python')) {
            return $this->getPythonCompose($portStr);
        }

        // PHP compose
        if (in_array($framework, ['php', 'laravel', 'symfony']) || str_contains($language, 'php')) {
            return $this->getPhpCompose($portStr);
        }

        // Go compose
        if (in_array($framework, ['go', 'golang']) || str_contains($language, 'go')) {
            return $this->getGoCompose($portStr);
        }

        // Static compose
        if (in_array($framework, ['static', 'html'])) {
            return $this->getStaticCompose($portStr);
        }

        // Default compose
        return $this->getDefaultCompose($language, $portStr);
    }

    private function getNodeCompose(string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':' . $port . '"
    environment:
      - NODE_ENV=production
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:' . $port . '/"]
      interval: 30s
      timeout: 3s
      retries: 3
      start_period: 10s

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }

    private function getPythonCompose(string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':' . $port . '"
    environment:
      - PYTHONUNBUFFERED=1
      - PYTHONDONTWRITEBYTECODE=1
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always
    healthcheck:
      test: ["CMD", "python", "-c", "import urllib.request; urllib.request.urlopen(\'http://localhost:' . $port . '\')"]
      interval: 30s
      timeout: 3s
      retries: 3
      start_period: 10s

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }

    private function getPhpCompose(string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':' . $port . '"
    environment:
      - PHP_MEMORY_LIMIT=256M
      - PHP_MAX_EXECUTION_TIME=300
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:' . $port . '/"]
      interval: 30s
      timeout: 3s
      retries: 3
      start_period: 10s

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }

    private function getGoCompose(string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':' . $port . '"
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:' . $port . '/"]
      interval: 30s
      timeout: 3s
      retries: 3
      start_period: 10s

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }

    private function getStaticCompose(string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':80"
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:80/"]
      interval: 30s
      timeout: 3s
      retries: 3

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }

    private function getComposeWithDatabase(string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':' . $port . '"
    depends_on:
      - database
    environment:
      - DATABASE_URL=postgres://postgres:postgres@database:5432/app
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always

  database:
    image: postgres:15-alpine
    container_name: ${APP_NAME}-db
    environment:
      - POSTGRES_DB=app
      - POSTGRES_USER=postgres
      - POSTGRES_PASSWORD=postgres
    volumes:
      - ${APP_NAME}-db:/var/lib/postgresql/data
    restart: always

volumes:
  ${APP_NAME}-db:

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }

    private function getDefaultCompose(string $language, string $port): string
    {
        $content = 'version: \'3.8\'

services:
  app:
    build: .
    image: ${APP_IMAGE}
    container_name: ${APP_NAME}
    ports:
      - "' . $port . ':' . $port . '"
    labels:
      - "coolify.name=${APP_NAME}"
      - "coolify.teamId=${TEAM_ID}"
      - "coolify.applicationId=${APP_ID}"
      - "coolify.domain=${DOMAIN}"
    restart: always

networks:
  default:
    name: coolify
    external: true';

        return $content;
    }
}