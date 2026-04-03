# Coolify AI Integration Plan
**Status: Planning | Created: 2026-04-03**

---

## Executive Summary

Transform Coolify from a self-hosting platform into an **AI-agent-controlled deployment platform**. The goal is to enable Hermes agent to:
1. Deploy any application via Coolify API
2. Auto-fix broken deployments
3. Manage scaling (Swarm/Kubernetes)
4. Handle security (IP whitelisting, private access)
5. Continuously learn and update skills from failures

---

## Current State Analysis

### What Coolify Already Has (Built-in)
| Feature | Status | Location |
|---------|--------|----------|
| REST API | Full OpenAPI spec | `/openapi.json` (600+ endpoints) |
| AI Build Pack | Implemented | `app/Services/AiService.php` |
| AI Auto-Fix | Implemented | `app/Services/AiAutoFixService.php`, `app/Jobs/AiAutoFixJob.php` |
| AI Log Monitor | Implemented | `app/Jobs/AiLogMonitorJob.php` |
| MCP Server | Built-in Laravel Boost | `php artisan boost:mcp` (`.mcp.json`) |

### What We Need to Add/Improve

| Feature | Priority | Gap |
|---------|----------|-----|
| Hermes ↔ Coolify Connector | **P0** | No MCP bridge to external agents |
| IP Whitelist/Access Lists | **P0** | Not exposed in API |
| Kubernetes Support | P1 | Exists but not fully documented |
| Swarm Management | P1 | Partial implementation |
| Auto-Skill Update from Failures | P2 | AI fixes but doesn't update skills |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Hermes Agent (You)                        │
│  - Orchestrates all work                                      │
│  - Uses skills for domain knowledge                          │
│  - Delegates to Coolify for deployments                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ MCP / API
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   Coolify (This Fork)                       │
│  - Deployment execution                                      │
│  - Resource management                                       │
│  - Health monitoring                                         │
│  - Built-in AI features                                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Docker/SSH
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              Target Servers (Self-Hosted)                    │
│  - Standalone Docker                                         │
│  - Docker Swarm                                              │
│  - Kubernetes (future)                                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Plan

### Phase 1: Hermes-Coolify Connector (P0)

#### 1.1 Create Coolify MCP Client Skill
```
hermes/skills/coolify-mcp/
├── SKILL.md              # Main skill
├── references/
│   ├── api-endpoints.md  # Key API endpoints indexed
│   ├── auth.md           # API token management
│   └── deployments.md    # Deployment workflow
```

**Key API Endpoints to Expose:**
```yaml
applications:
  - POST /applications/public      # Deploy from git
  - POST /applications/dockerfile  # Deploy from Dockerfile
  - POST /applications/dockercompose # Deploy compose
  - GET  /applications/{uuid}      # Get status
  - GET  /applications/{uuid}/start
  - GET  /applications/{uuid}/stop
  - GET  /applications/{uuid}/restart
  - GET  /applications/{uuid}/logs

databases:
  - POST /databases/{type}         # Create database
  - GET  /databases/{uuid}
  - DELETE /databases/{uuid}

servers:
  - GET  /servers                  # List servers
  - POST /servers                  # Add server

projects:
  - GET  /projects                 # List projects
  - POST /projects                 # Create project
```

#### 1.2 Create Coolify Management Skill
```
hermes/skills/coolify-manager/
├── SKILL.md
├── scripts/
│   ├── deploy-app.sh
│   ├── health-check.sh
│   ├── auto-fix.sh
│   └── scale.sh
```

**Capabilities:**
- Deploy application from git/Dockerfile/docker-compose
- Check deployment health
- Restart/stop applications
- View logs
- Manage databases
- Handle IP whitelisting (via API or direct)

#### 1.3 API Token Management
```bash
# Coolify API tokens are per-user
# Token stored in: Coolify UI → Settings → API Tokens
# Or via: POST /api/tokens
```

---

### Phase 2: IP Security & Private Access (P0)

#### 2.1 IP Whitelist Implementation

**Current Gap:** Coolify doesn't have built-in IP whitelisting for deployments.

**Solution Options:**

| Option | Pros | Cons |
|--------|------|------|
| Traefik middleware | Native to Coolify's proxy | Requires Traefik config |
| Coolify Firewall Feature | Built-in when ready | Not yet in API |
| Nginx ingress rules | Universal | Manual config per app |
| Hermes iptables wrapper | Full control | Server-level, not app-level |

**Recommended:** Create a `coolify-firewall` skill that:
1. Reads Coolify's proxy config
2. Adds nginx `allow/deny` rules per application
3. Manages `allowed_ips` field in Coolify DB

#### 2.2 Private Access Lists

```yaml
# Coolify already supports:
# - Private keys for git repos
# - Private registries
# - Secret env vars

# What we need to add:
# - IP-based access control per deployment
# - VPN/bastion integration
# - WireGuard support for private networking
```

---

### Phase 3: Auto-Fix & Self-Healing (P1)

#### 3.1 Current Auto-Fix Flow (Already Built)
```
Deployment Failed
      ↓
AiLogMonitorJob (120s monitoring)
      ↓
Container Degraded? → attemptAutoFix()
      ↓
Get logs → Analyze with AI
      ↓
Store in ai_analysis column
      ↓
Queue AiAutoFixJob
      ↓
AI suggests/pushes fix
```

#### 3.2 Enhancement: Skill Learning Loop

```
Auto-Fix Applied
      ↓
Success? → Update skill with fix pattern
      ↓
Failure? → Log and escalate to Hermes
      ↓
Hermes analyzes → Updates skill
      ↓
Next similar failure → Auto-fix from skill
```

**Skill Update Mechanism:**
```python
# When auto-fix succeeds:
skill_manage(action='patch', 
  name='coolify-auto-fixes',
  old_string='# Pattern: timeout',
  new_string='# Pattern: timeout → increased to 300s')
```

---

### Phase 4: Swarm & Kubernetes Management (P1)

#### 4.1 Docker Swarm

**Current Status:**
- Coolify supports Swarm deployments
- `SwarmDocker` destination type
- Domain mappings via `SwarmDomainMapping.php`

**What We Need:**
```yaml
# Add to coolify-manager skill:
swarm:
  - Initialize swarm cluster
  - Add nodes to swarm
  - Deploy stack
  - Scale services
  - Monitor swarm health
```

#### 4.2 Kubernetes

**Coolify K8s Support:**
- Exists in codebase (Kubernetes namespace, Helm charts)
- Not fully documented
- API has Kubernetes endpoints

**Roadmap:**
1. Document existing K8s integration
2. Create K8s deployment patterns
3. Add to skill system

---

### Phase 5: Learning & Skill Updates (P2)

#### 5.1 Failure Pattern Database

Store patterns from auto-fixes:

```sql
CREATE TABLE deployment_fix_patterns (
    id SERIAL PRIMARY KEY,
    error_pattern TEXT,
    framework VARCHAR(50),
    fix_applied TEXT,
    success_count INT DEFAULT 1,
    last_used TIMESTAMP,
    skill_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### 5.2 Skill Auto-Update Flow

```
1. Hermes detects deployment failure
2. Checks deployment_fix_patterns for known pattern
3. If found → Apply known fix
4. If not found → 
   a. Query Coolify AI (if available)
   b. Or analyze logs with Hermes AI
   c. Apply fix
   d. If successful → Add to patterns
   e. Update relevant skill
```

---

## Key Files Reference

| File | Purpose | Notes |
|------|---------|-------|
| `/openapi.json` | Full API spec | 600+ endpoints |
| `app/Services/AiService.php` | AI framework detection | Detect Dockerfile/compose |
| `app/Services/AiAutoFixService.php` | Auto-fix logic | Already implemented |
| `app/Jobs/AiAutoFixJob.php` | Queued fix job | Background processing |
| `app/Jobs/AiLogMonitorJob.php` | Health monitoring | Post-deploy checks |
| `.mcp.json` | Laravel Boost MCP | Built-in tool access |
| `app/Models/Application.php` | Application model | Deployment config |
| `app/Models/Server.php` | Server model | Target servers |

---

## Immediate Actions (This Session)

### TODO: Create Core Skills

- [ ] `coolify-mcp` - MCP client to connect to Coolify's built-in MCP
- [ ] `coolify-manager` - Full deployment management skill
- [ ] `coolify-deploy-workflow` - Step-by-step deployment workflow
- [ ] `coolify-auto-fix-patterns` - Known fix patterns

### TODO: Document Current AI Features

- [ ] Document existing `php artisan boost:mcp` usage
- [ ] Document AI Build Pack flow
- [ ] Document Auto-Fix flow
- [ ] Create troubleshooting guide

### TODO: Bridge to Hermes

- [ ] Create MCP config to connect Hermes to Coolify
- [ ] Create API token management in skill
- [ ] Test full deploy → monitor → auto-fix flow

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Deploy app via Hermes | < 5 minutes |
| Auto-fix success rate | > 80% |
| Skill update frequency | On every new fix pattern |
| IP whitelist creation | < 1 minute |
| Swarm deployment | < 10 minutes |

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| API rate limits | Add caching, batch operations |
| Auto-fix makes things worse | Always require confirmation for first-time patterns |
| Skill bloat | Keep skills focused, use references |
| Security of API tokens | Use environment variables, not hardcoded |

---

## Session Handoff Points

**After this session, the next agent should:**

1. Start with `coolify-mcp` skill creation
2. Test API connectivity: `curl http://localhost:8010/api/v1/health`
3. Verify existing AI features work before adding new ones
4. Use `SESSION_HANDOFF.md` for notes on what was tried

---

## Appendix: API Quick Reference

### Authentication
```bash
# Bearer token in Authorization header
curl -H "Authorization: Bearer $COOLIFY_TOKEN" \
     http://localhost:8010/api/v1/applications
```

### Deploy Application
```bash
POST /api/v1/applications/public
{
  "name": "my-app",
  "project_uuid": "...",
  "environment_uuid": "...",
  "git_repository": "owner/repo",
  "git_branch": "main"
}
```

### Check Deployment Status
```bash
GET /api/v1/applications/{uuid}
GET /api/v1/applications/{uuid}/logs
```

### Restart Application
```bash
GET /api/v1/applications/{uuid}/restart
```

---

*Document Version: 1.0*
*Next Update: After Phase 1 completion*

---

## Update: 2026-04-03 Evening

### What Was Built (A+B+C Enhanced)

**NEW: Aatos AI Smart Panel** - Unifies ALL AI features into ONE panel:

1. **Unified AI Provider Settings**
   - Provider selection (MiniMax, Anthropic, OpenAI)
   - API Key management (encrypted)
   - Model selection per provider

2. **AI Build Pack (Enhanced)**
   - Toggle to enable/disable
   - Auto-detect existing Dockerfile/docker-compose
   - Fallback to Nixpacks when no Docker found
   - AI analyzes source → writes Docker files → builds directly

3. **AI Auto-Fix (Enhanced)**
   - Configurable max retries (default: 5)
   - Configurable retry delay (default: 10s)
   - Test auto-fix button
   - Full integration with AiAutoFixService

4. **AI Log Monitor (Enhanced)**
   - Toggle to enable/disable
   - Configurable check interval
   - Configurable log lines to analyze
   - Auto-heal toggle for automatic container restart

5. **Load Balancer & Security**
   - IP Whitelist toggle
   - IP range sources configuration
   - Link to Domain Mappings management

6. **Healing Logs Dashboard**
   - Recent AI healing actions
   - Success/failure status
   - Issue detection details
   - Clear logs button

### Key Implementation Details

**Migration: `2026_04_03_000001_add_ai_smart_panel_settings.php`**
- Adds all new InstanceSettings fields
- Creates `ai_healing_logs` table

**AiSmartPanel.php**
- Single component managing ALL AI settings
- Methods: submit(), testConnection(), runHealthCheck(), testAiFix(), toggleAiMonitor(), toggleAiAutoHeal(), clearHealingLogs()

**deploy_ai_buildpack() Fix**
- Fixed the double-clone issue
- Now uses build_ai_docker_compose() and build_ai_dockerfile()
- AI writes files to workdir first, THEN builds (no re-clone)

### Files Changed
```
app/Livewire/Settings/AiSmartPanel.php (NEW)
app/Livewire/Settings/ai-smart-panel.blade.php (NEW)
app/Models/InstanceSettings.php (ADDED FIELDS)
app/Jobs/ApplicationDeploymentJob.php (FIXED + ENHANCED)
database/migrations/2026_04_03_000001_add_ai_smart_panel_settings.php (NEW)
routes/web.php (UPDATED ROUTE)
```

### Commit: 7660bc894
