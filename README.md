# CRM API

A JSON API for a slice of a CRM: reps work leads, log activities against them and move them through a pipeline, and managers report on rep performance. Built with Laravel 13, Sanctum and MySQL 8.4.

## Setup

You need Docker. These commands work in any shell, including PowerShell and Git Bash on Windows.

```sh
cp .env.example .env
docker run --rm -v "${PWD}:/app" -w /app composer:2 install --ignore-platform-reqs
docker compose up -d --wait
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed
```

The API is now at `http://localhost/api`. If ports 80 or 3306 are taken, set `APP_PORT` or `FORWARD_DB_PORT` in `.env` before running `docker compose up`.

To process queued jobs, run a worker:

```sh
docker compose exec laravel.test php artisan queue:work
```

### Seeded users

Every password is `password`.

| Email | Role |
|---|---|
| `manager@crm.test` | manager |
| `alice@crm.test` | rep |
| `bob@crm.test` | rep |
| `carol@crm.test` | rep |

The seeder also creates 35 leads: 10 per rep across all statuses, plus 5 unassigned.

### Tests

```sh
docker compose exec laravel.test php artisan test
```

Tests use in-memory SQLite, so they also run without Docker if PHP has `pdo_sqlite`. The suite has also been run against MySQL 8.4 by setting the `DB_*` environment variables.

## API

Send `Authorization: Bearer <token>` and `Accept: application/json` on every request except login.

| Method | Path | Notes |
|---|---|---|
| POST | `/api/login` | Returns a token. Limited to 6 attempts per minute. |
| GET | `/api/leads` | See the query parameters below. |
| POST | `/api/leads` | Returns 201. |
| GET | `/api/leads/{id}` | Includes the assigned rep and activities, newest first. |
| PATCH | `/api/leads/{id}` | Partial update, including status. |
| POST | `/api/leads/{id}/assign` | Manager only. Body: `{"assigned_to": 2}`. |
| POST | `/api/leads/{id}/activities` | Returns 201. `occurred_at` defaults to now. |
| GET | `/api/reports/rep-performance` | All reps for a manager, own row for a rep. |

`GET /api/leads` accepts `status`, `source`, `assigned_to`, `search`, `sort`, `per_page` (1–100, default 15) and `page`. `sort` takes `created_at` or `expected_value`, with a leading `-` for descending. The default is `-created_at`.

```sh
TOKEN=$(curl -s localhost/api/login -H 'Accept: application/json' \
  -d email=manager@crm.test -d password=password | jq -r .data.token)

curl -s 'localhost/api/leads?status=qualified&sort=-expected_value' \
  -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN"
```

### Response shape

Successful responses wrap the payload in `data`. Lists add Laravel's `links` and `meta`. Errors always have a `message`, and validation errors add `errors`:

```json
{
  "message": "A lead needs at least one logged activity before it can be marked won.",
  "errors": { "status": ["A lead needs at least one logged activity before it can be marked won."] }
}
```

Money is returned as a string such as `"1500.50"`, so clients don't lose precision by parsing it as a float.

## How it's organised

- **Authorization:** `Lead::visibleTo()` limits lists and `LeadPolicy` guards single leads through `->can()` in `routes/api.php`. Controllers contain no role checks.
- **Won/lost rule:** `App\Rules\HasActivityBeforeClosing` runs in `UpdateLeadRequest`, so it returns a normal 422. Leads can't be created with a status, which closes the obvious way around it.
- **Report:** `App\Reports\RepPerformanceReport` is a single query. Leads and activities are aggregated in separate subqueries, because joining them directly would repeat each lead once per activity and inflate the sums.
- **Enums:** statuses and types are string columns backed by PHP enums, so adding a value doesn't need an `ALTER TABLE`.

### Performance

Measured on MySQL 8.4 in Docker on a laptop, with 100k leads, 300k activities and 20 reps:

| Query | Median |
|---|---|
| Report, all reps | 356 ms |
| Report, one rep | 61 ms |
| Lead list, default sort | 147 ms |
| Lead list, with search | 338 ms |

`EXPLAIN` shows the lead half of the report reads only the `(assigned_to, status, expected_value)` index, never the table rows.

## Assumptions

- **Reps creating leads:** the lead is assigned to the rep who created it, otherwise they couldn't see it.
- **Managers creating leads:** `assigned_to` is optional, so a lead can start unassigned.
- **New leads:** they always start as `new`. Sending `status` on create returns 422.
- **Reassignment:** it only happens through the assign endpoint. Sending `assigned_to` to PATCH returns 422.
- **Other reps' leads:** a rep gets 403, not 404. This does reveal that the ID exists, but IDs are sequential and carry no lead data. Unassigned leads are also 403 for reps.
- **Status transitions:** only the won/lost rule is enforced. Any other move is allowed, including reopening a closed lead.
- **The won/lost rule:** it applies to managers as well as reps.
- **Report activity count:** it counts all activity on a rep's leads, including entries logged by a manager, rather than activity the rep logged personally.
- **Report scope:** unassigned leads aren't in the report. Reps with no leads appear with zeroes.
- **Activity dates:** `occurred_at` can't be in the future, because activities record what has happened. Offsets are converted to UTC.
- **Lead emails:** they aren't unique, since the same contact can appear as more than one lead.
- **Bad login:** returns 422 on `email` with the same message whether or not the account exists.

## Trade-off

Search uses `LIKE '%term%'` across name, email and company. A leading wildcard can't use a B-tree index, so every search scans the rows it's allowed to see.

I kept it because it matches any part of a value, needs no extra infrastructure, and took 338 ms at the 100k leads the brief targets. Cost grows linearly with table size, so I'd move to a MySQL `FULLTEXT` index or Meilisearch through Laravel Scout at around a million leads. Both would change matching behaviour, which is why I didn't do it early.

## Bonus items

- **Queued job on assignment:** `NotifyRepOfAssignment` runs when a lead's assignee changes and writes to the log. It doesn't run when a rep creates their own lead.
- **Docker:** Laravel Sail's Compose file, with MySQL.

I skipped the event listener that logs status changes as activities. As specified, an automatic activity from `new → contacted` would count as "at least one activity" and quietly defeat the won/lost rule. It would need its own activity type that the rule ignores.

## With more time

- Cache the report per viewer, invalidated on lead and activity writes. It's fast enough live today, so the extra invalidation paths aren't worth it yet.
- Paginate the report if rep numbers grow into the hundreds.
- Add token expiry and a logout endpoint.
- Send a real notification from the assignment job, with retries.
- Keep a status history table, so the report can show conversion over time.
- Publish an OpenAPI spec.
