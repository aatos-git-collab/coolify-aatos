# Session Handoff - Coolify AI Features

## Overview
This session focused on implementing and debugging AI-powered features in Coolify, specifically:
1. AI Build Pack - Auto-detect existing Dockerfile/docker-compose in repositories
2. AI Auto-Fix - Automatically fix deployment failures
3. Swarm Domain Mappings - Bug fix for SQL error

---

## What Was Done

### 1. AI Build Pack (Priority - Main Focus)

**Files Modified:**
- `app/Services/AiService.php` - Added detection for existing Dockerfile/docker-compose
- `app/Jobs/ApplicationDeploymentJob.php` - Modified `deploy_ai_buildpack()` and `getFilesForAiAnalysis()`

**How It Works:**
1. When deploying with AI Build Pack, the system:
   - Clones repository to `/artifacts/{deployment_uuid}`
   - Gets file list and reads key files (Dockerfile, docker-compose.yaml, etc.)
   - Passes to `AiService::analyzeSourceCode()` for detection

2. Detection Logic (AiService.php lines ~450-485):
   - If project has existing `Dockerfile` or `docker-compose.yml` or `docker-compose.yaml`
   - Returns `framework: 'existing_docker'` with the actual file contents
   - Deployment job then uses these files instead of generating new ones

3. Debug Logging Added:
   - In `ApplicationDeploymentJob.php`: Logs what files are read for Dockerfile/docker-compose
   - In `AiService.php`: Logs contents keys and whether files were found

**Current Problem:**
The AI is NOT detecting existing Dockerfile/docker-compose. In the last deployment:
- AI detected "unknown" framework
- Generated a simple alpine:3.19 Dockerfile (138 bytes)
- Instead of using the existing complex multi-stage Dockerfile from the repo
- This caused container to be "degraded" because it was running wrong image

**Why It's Failing:**
The debug logs show the files ARE being read from the container (Dockerfile shows 138B in the log for AI-generated, but we saw the real 6000+ byte Dockerfile earlier). The issue is likely in how the contents are being passed to or processed by `analyzeSourceCode()`.

---

### 2. AI Auto-Fix (Implemented)

**Files:**
- `app/Jobs/ApplicationDeploymentJob.php` - `attemptAutoFix()` method (line ~4531)
- `app/Jobs/AiAutoFixJob.php` - Queued job
- `app/Services/AiAutoFixService.php` - Main logic

**How It Works:**
1. Post-deployment monitoring runs for 120 seconds
2. Checks container health every 2 seconds
3. If container is degraded/exited, triggers `attemptAutoFix()`
4. Attempts container restart first
5. Gets container + deployment logs
6. Analyzes with AI
7. Stores analysis in `ai_analysis` database column
8. Queues `AiAutoFixJob` for deeper fix

**Status:** Working - successfully detected the degraded container and attempted fix in the last deployment.

---

### 3. Swarm Domain Mappings Bug Fix

**File:** `app/Livewire/Settings/SwarmDomains.php`

**Problem:** SQL error "column 'type' does not exist" when loading Swarm domains page

**Fix:** Changed line 177 from:
```php
return Application::whereHas('destination')->get();
```
to:
```php
return Application::where('destination_type', 'App\Models\SwarmDocker')->get();
```

**Status:** Fixed

---

## Current Code Changes (Not Committed)

### 1. AiService.php (lines ~450-485)
Added debug logging to trace why detection fails:
```php
Log::info("AI analyzeSourceCode: contents keys = " . implode(', ', array_keys($contents)));
Log::info("AI analyzeSourceCode: hasDockerfile=" . ($hasDockerfile ? 'true' : 'false'));
// etc.
```

### 2. ApplicationDeploymentJob.php (getFilesForAiAnalysis)
Added debug logging:
```php
$this->application_deployment_queue->addLogEntry("DEBUG: Read {$file}: found ({$bytes} bytes)");
$this->application_deployment_queue->addLogEntry("DEBUG: Total files read: " . count($fileContents));
```

---

## What Needs To Be Done

### Priority 1: Fix AI Build Pack Detection

**Test the last deployment:**
The debug logging is now added. Need to redeploy the "mission-claw" application and check:

1. In deployment logs, look for:
   - "DEBUG: Read Dockerfile: found (xxx bytes)" - should be ~6000+ bytes, not 138
   - "DEBUG: Read docker-compose.yaml: found (xxx bytes)" - should be large
   - "DEBUG: Total files read: X"

2. In Laravel logs (or system logs), look for:
   - "AI analyzeSourceCode: contents keys = ..."
   - "AI analyzeSourceCode: hasDockerfile=true"

**If detection still fails:**
- Check if the file reading is returning correct content
- Check if contents array is being passed correctly to analyzeSourceCode
- The file reading loop runs AFTER file list is obtained, check if there's a timing issue

**Expected behavior after fix:**
- "AI: Detected - existing_docker" instead of "unknown"
- "AI: Using existing Dockerfile from repository..." instead of "AI: Generating Dockerfile..."

---

### Priority 2: Verify Auto-Fix Works End-to-End

Once detection is fixed, test the full flow:
1. Deploy with AI Build Pack
2. Container should use the real Dockerfile (complex multi-stage)
3. If it fails, AI Auto-Fix should analyze and try to fix
4. If git-based, fix should be pushed back to git

---

## Application to Test

**mission-claw:main-rga9jf90hlqx829hqz80zarn** (or current deployment ID)

This is a Node.js application with:
- Existing complex Dockerfile (multi-stage, node:20-bookworm-slim, bun, python, chromium)
- Existing docker-compose.yaml (with openclaw, docker-proxy, searxng services)
- Port: 18789

---

## Commands to Run

```bash
# Check deployment logs (in Coolify UI)
# Look for DEBUG lines and "AI: Detected -"

# If need to check Laravel logs
docker logs coolify -f 2>&1 | grep "AI analyzeSourceCode"
```

---

## Key Files Reference

| File | Purpose | Lines |
|------|---------|-------|
| `app/Services/AiService.php:423` | `analyzeSourceCode()` - detects framework | ~423-550 |
| `app/Services/AiService.php:455` | Check for existing docker files | ~455-485 |
| `app/Jobs/ApplicationDeploymentJob.php:959` | `deploy_ai_buildpack()` - main AI deployment | ~959-1058 |
| `app/Jobs/ApplicationDeploymentJob.php:1063` | `getFilesForAiAnalysis()` - get files for AI | ~1063-1105 |
| `app/Jobs/ApplicationDeploymentJob.php:4531` | `attemptAutoFix()` - auto-fix on failure | ~4531-4668 |
| `app/Services/AiAutoFixService.php:23` | `runAutoFixLoop()` - fix logic | ~23-94 |

---

## Notes for Next Session

1. The debug logging was just added - need to test it
2. The detection logic in AiService.php should work - it checks `$contents['Dockerfile']` etc.
3. The issue might be in how `$fileContents` array is built in getFilesForAiAnalysis
4. Once detection works, the full AI Build Pack flow should be functional
5. Auto-Fix is already working - it's just fixing the wrong image because detection failed