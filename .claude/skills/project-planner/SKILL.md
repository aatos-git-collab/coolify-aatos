---
name: project-planner
description: >-
  Creates comprehensive project plans with architecture, implementation guides,
  task workflows, and reference materials. Activates when user asks to plan a
  project, create a roadmap, design a system, or document a feature.
---

# Project Planner

## When to Use

Use this skill when the user wants to:
- Plan a new feature or system
- Create a project roadmap
- Design system architecture
- Document a technical implementation
- Compare technologies or projects

**Activates on keywords:**
- "plan", "roadmap", "design", "architecture"
- "create a project", "implement", "build"
- "compare X and Y"

## Framework

### 1. Research Phase
- Explore the codebase for existing patterns
- Find similar implementations to reference
- Document key learnings

### 2. Design Phase
- Architecture overview with components
- Feature prioritization (High/Medium/Low)
- Implementation phases

### 3. Documentation Phase
Create standard structure:
```
project-name/
├── README.md
├── planning/
│   ├── INTEGRATION_PLAN.md
│   ├── FEATURE_SUGGESTIONS.md
│   └── LEARNINGS.md
├── implementation/
│   ├── PHASE1_Foundation.md
│   └── PHASE2_xxx.md
├── skills/
├── tasks/
└── reference/
```

## Output Requirements

Every plan must include:
- ✅ Architecture overview
- ✅ Feature prioritization
- ✅ Implementation phases with code examples
- ✅ Task breakdown with dependencies
- ✅ Technical decisions with rationale
- ✅ Risks and mitigations
- ✅ Skills requirements
- ✅ Reference materials

## Key Patterns

When analyzing codebases, look for:
1. **Services** - `app/Services/`, business logic
2. **Models** - `app/Models/`, database schema
3. **UI Components** - `app/Livewire/` or similar
4. **Infrastructure** - config, deployment, deps

## Quick Start

1. Create planning folder: `mkdir -p project-name/planning`
2. Create README.md with overview
3. Research and document in LEARNINGS.md
4. Create architecture in INTEGRATION_PLAN.md
5. Break into phases with implementation guides
6. Add task breakdown and skills requirements