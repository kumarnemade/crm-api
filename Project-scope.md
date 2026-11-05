## Scenario
You're building a small slice of a CRM. Sales reps work Leads. Each lead can have many Activities logged against it (call, email, meeting, note). Leads get assigned to a rep, move through a pipeline of statuses, and managers need a report to see how reps are performing.

Build a JSON API for this. No frontend required.

## Domain rules
A User has a role: manager or rep.
A Lead belongs to one assigned rep (nullable until assigned). It has: name, email, phone, company (nullable), source (enum: web, referral, cold_call, event, other), status (enum: new, contacted, qualified, won, lost), and an expected_value (decimal).
An Activity belongs to a lead and to the user who logged it. It has: type (call, email, meeting, note), body (text), and occurred_at.
Visibility: a rep may only see and act on leads assigned to them. A manager may see and act on all leads.
A lead may only be marked won or lost if it has at least one logged activity. Reject the transition otherwise with a clear error.

## Required endpoints
Authenticated via Sanctum token. Return proper status codes and a consistent JSON shape (use API Resources).

POST /api/login — issue a token.
GET /api/leads — list leads. Must support:
filtering by status, source, and assigned_to
search by name/email/company
pagination
sorting by created_at and expected_value
POST /api/leads — create a lead.
GET /api/leads/{id} — show a lead with its activities and assigned rep.
PATCH /api/leads/{id} — update lead fields, including status transitions (enforce the won/lost rule above).
POST /api/leads/{id}/assign — assign/reassign a lead to a rep (manager only).
POST /api/leads/{id}/activities — log an activity against a lead.
GET /api/reports/rep-performance — per-rep summary: total leads, count by status, total + won expected_value, and activity count. Managers see all reps; a rep sees only their own row. Write this to avoid N+1 and to scale to ~100k leads.

## What we're evaluating
This is the part that matters more than feature completeness:

Data modelling — migrations, relationships, sensible indexes, correct column types (money as decimal, not float), enums handled cleanly.
Eloquent fluency — eager loading, no N+1, query-level filtering rather than collection filtering, an efficient report query.
Authorization — Policies/Gates for the rep-vs-manager visibility rules. We should not see role checks copy-pasted into every controller.
Validation — Form Request classes, not inline $request->validate() everywhere; clear error messages.
API design — resource transformers, consistent envelope, correct status codes (201, 422, 403, 404…).
Testing — feature tests for the core flows (auth, visibility, the won/lost rule, the report). Use factories. We care about meaningful assertions, not coverage percentage.
Code quality — thin controllers, logic in the right place, readable naming, no dead code. Clean git history is a plus.
Bonus (only if time allows — not required)
Pick at most one or two; we'd rather discuss your choices than see all of them half-done:

Dispatch a queued job on assignment (e.g. "notify rep") — show you understand queues even if it just logs.
An event + listener that records an activity automatically when a lead's status changes.
Cache the report and invalidate it correctly on writes.
Multi-tenancy sketch: how would you scope all of the above per-tenant? A short note in the README is fine if you don't implement it.
Docker / Sail setup so we can run it with one command.

## Deliverables
A git repo (or zip) with the code.
A README covering: setup steps, any assumptions you made, what you'd do with more time, and one trade-off you deliberately made.
Seed data or a seeder so the API is usable immediately (a manager + a couple of reps + some leads).