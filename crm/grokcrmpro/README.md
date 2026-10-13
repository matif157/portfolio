# SPS CRM Phase 2 — Leads + Projects

Extends Phase 1. Same architecture, same database.

## Modules

### Leads
- Capture, source, priority, assignment
- Status: new → contacted → qualified → estimate → accepted → converted / lost
- **Convert to Client** (one click, history preserved)

### Projects / Cases
- Linked to client
- Services attached from catalog
- Staff assignment + manager
- Status workflow
- Optional invoice linkage

## Install

See **INSTALL.txt**

## Definition of Done

```
Create Lead → Qualify → Convert to Client
→ Create Project for client → Attach services → Assign staff
→ View project from client context
```
