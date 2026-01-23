# Rapid Dashboard 2

A Trello-powered sprint wallboard + reporting app. It syncs a Sprint Registry board, pulls each sprint board, snapshots progress, and renders a wallboard suitable for a large TV.

## Quick start

1) Install deps

- `composer install`
- `npm install`

2) Configure env

- Copy `.env.example` to `.env` if needed.
- Set at minimum:
  - `TRELLO_REGISTRY_BOARD_ID`
  - `TRELLO_KEY`
  - `TRELLO_TOKEN`

3) Migrate

- `php artisan migrate`

4) Run the master command

- `php artisan trello:run-dashboard`

5) Open the wallboard

- `http://localhost:8000/wallboard`

6) Build assets for production (optional for local)

- `npm run build`

## Trello data model

### Sprint Registry board
Each card in the registry represents one sprint. Custom fields (IDs are configured in `config/trello_sync.php`):

- `status` (dropdown, e.g. Planned / Active / Closed)
- `starts_at` (date)
- `ends_at` (date)
- `sprint_board` (text or URL containing the sprint board id or shortlink)

The registry card ID is stored as `trello_control_card_id` in the local DB, so we can label it on divergence.

### Sprint board
Each sprint board is expected to have:

- A list named `Sprint Admin` (configurable) containing a control card named `Sprint Control` (configurable).
- Control card description = sprint goal (shown on the wallboard).
- Custom fields on the board (names configurable):
  - `Starts At`
  - `Ends At`
  - `Sprint Status`
- One or more Done lists (names configurable).
- A Remakes list (name configurable).

## Master command flow

`php artisan trello:run-dashboard` performs the following:

1) Sync Sprint Registry -> local sprints table.
2) For each open sprint:
   - Resolve Done list ids.
   - Resolve Remakes list id.
   - Resolve control card id + sprint goal.
   - Resolve status field id + closed option id.
3) Divergence check:
   - If control card dates/status diverge from registry, apply label `Diverged Dates` to:
     - the sprint board control card
     - the registry card
   - If alignment is restored, remove the label from both cards.
4) Determine the active sprint:
   - If exactly one sprint has `status=active` and is not closed, use it.
   - Otherwise, fallback to date window (`starts_at <= now <= ends_at`).
5) Snapshot management:
   - Ensure a start snapshot exists.
   - Reconcile if enough time has passed or near sprint end.
   - Take an ad hoc snapshot at configured cadence.

## Wallboard

Route: `/wallboard`

Behavior:
- If active sprint exists, redirects to `/wallboard/sprints/{sprint}`.
- If no active sprint, shows a simple “No active sprint” page with upcoming sprints.

Wallboard shows:
- Sprint name, dates, goal
- Scope / Done / Remaining points
- Remakes count
- Burndown chart with manual re-sync

Authentication is currently disabled for the wallboard routes.

## Release/revision badge

The sidebar and wallboard show a revision and published timestamp.

Priority order:
- `APP_REVISION` (optional)
- Git HEAD (if `.git` is available)

Published timestamp is resolved from:
- `APP_RELEASED_AT` (optional)
- `public/build/manifest.json` mtime
- `public/index.php` mtime
- `.git/logs/HEAD` timestamp

Set these in your deploy pipeline if you want explicit values.

## Config reference

`config/trello_sync.php` keys you may care about:

- Registry board fields:
  - `trello_sync.registry_board_id`
  - `trello_sync.sprint_control.control_field_names.*`
  - `trello_sync.sprint_control.control_field_ids.*` (optional override)
  - `trello_sync.sprint_control.status_option_map`
- Sprint board fields:
  - `trello_sync.sprint_board.done_list_names`
  - `trello_sync.sprint_board.remakes_list_name`
  - `trello_sync.sprint_board.sprint_admin_list_name`
  - `trello_sync.sprint_board.control_card_name`
  - `trello_sync.sprint_board.starts_at_field_name`
  - `trello_sync.sprint_board.ends_at_field_name`
  - `trello_sync.sprint_board.status_field_name`
  - `trello_sync.sprint_board.closed_status_label`
- `trello_sync.sprint_board.diverged_label_name`
- `trello_sync.sprint_board.diverged_label_color`
- Remakes + labels:
  - `trello_sync.remake_label_actions.remove` (labels that zero out remake points)
  - `trello_sync.remake_label_actions.restore` (labels that restore original points)
  - `trello_sync.remake_reason_labels` (labels used for reason pie chart)
  - `trello_sync.remake_label_points` (label => points mapping used at snapshot time)

Associated env vars:

- `TRELLO_REGISTRY_BOARD_ID`
- `TRELLO_KEY`
- `TRELLO_TOKEN`
- `TRELLO_DONE_LIST_NAMES` (comma-separated)
- `TRELLO_REMAKES_LIST_NAME`
- `TRELLO_SPRINT_ADMIN_LIST_NAME`
- `TRELLO_SPRINT_CONTROL_CARD_NAME`
- `TRELLO_REGISTRY_STATUS_FIELD_NAME`
- `TRELLO_REGISTRY_STARTS_AT_FIELD_NAME`
- `TRELLO_REGISTRY_ENDS_AT_FIELD_NAME`
- `TRELLO_REGISTRY_SPRINT_BOARD_FIELD_NAME`
- `TRELLO_REGISTRY_DONE_LIST_IDS_FIELD_NAME`
- `TRELLO_SPRINT_STARTS_AT_FIELD_NAME`
- `TRELLO_SPRINT_ENDS_AT_FIELD_NAME`
- `TRELLO_SPRINT_STATUS_FIELD_NAME`
- `TRELLO_SPRINT_CLOSED_STATUS_LABEL`
- `TRELLO_SPRINT_DIVERGED_LABEL_NAME`
- `TRELLO_SPRINT_DIVERGED_LABEL_COLOR`
- `APP_REVISION` (optional)
- `APP_RELEASED_AT` (optional ISO8601)
- `APP_REVISION` (optional)
- `APP_RELEASED_AT` (optional ISO8601)
- `FOURJAW_BASE_URL`
- `FOURJAW_USER_EMAIL`
- `FOURJAW_USER_PASSWORD`
- `FOURJAW_REMEMBER_ME`
- `FOURJAW_LOGIN_PATH` (optional)
- `FOURJAW_CHECK_AUTH_PATH` (optional)
- `FOURJAW_CURRENT_STATUS_PATH` (optional)
- `FOURJAW_UTILISATION_SUMMARY_PATH` (optional)
- `FOURJAW_AUTH_CACHE_TTL` (optional, minutes)
- `FOURJAW_STATUS_PAGE_SIZE` (optional)
- `WALLBOARD_UTILISATION_DAYS` (optional, default 7)
- `WALLBOARD_UTILISATION_SHIFTS` (optional, default on_shift)
- `WALLBOARD_UTILISATION_MACHINE_SHIFTS` (optional, default on_shift)
- `WALLBOARD_MACHINES_SHOW_UTILISATION` (optional, default true)

Legacy env vars like `TRELLO_CF_SPRINT_STATUS` are no longer used; prefer the name-based registry field envs above.

Note on `TRELLO_REGISTRY_DONE_LIST_IDS_FIELD_NAME`:
This is an optional registry-board custom field that lets you override Done list ids per sprint. Use it if a sprint board has multiple Done lists or a non-standard Done column name. If you don't need per-sprint overrides, leave it unset and the system will infer Done lists from `TRELLO_DONE_LIST_NAMES` instead.

## Remakes labels

Remake labels can influence both the comparison stats and the reason breakdown:
- Labels listed in `trello_sync.remake_label_actions.remove` zero out remake points (e.g., test/accidental).
- Labels listed in `trello_sync.remake_label_actions.restore` restore original points after removal.
- Labels listed in `trello_sync.remake_reason_labels` are tracked for the remake reasons pie chart.
- `trello_sync.remake_label_points` provides explicit label => points mapping applied at snapshot time.

When labels are added/removed in Trello, polling picks up the action and updates the local `sprint_remakes` record accordingly.
If your Trello reason labels are prefixed with `RM `, the wallboard strips that prefix for display, and uses the Trello label color when available.

## FourJaw machines

The Machines card pulls live status from FourJaw. Configure credentials in `.env` using the `FOURJAW_*` vars above.

Machine IDs and display names live in `config/fourjaw.php`. Only those IDs are rendered on the wallboard.
Status colors and idle thresholds are controlled in `config/wallboard.php` under `machines`.

Example config (trim/rename to match your Trello labels):

```php
// config/trello_sync.php
'remake_label_actions' => [
    'remove' => [
        'rm test',
        'rm accidental',
        'rm duplicate',
    ],
    'restore' => [
        'rm restore',
    ],
],
'remake_label_points' => [
    'rm rejected' => 2,
    'rm investigation' => 1,
],
'remake_reason_labels' => [
    'wrong size',
    'missing',
    'not programmed',
    'incorrect quantity',
    'incorrect product range',
    'frame detail incorrect',
    'design change',
    'double punched/ no common line cut',
    'door/frame holes dont line up',
    'incorrect material/ thickness',
],
```
## Business logic notes

High-level rules applied across the app:

- Sprint selection: prefer exactly one `status=active` + open sprint, otherwise fall back to date window (`starts_at <= now <= ends_at`), otherwise no active sprint.
- Snapshot flow: ensure a `start` snapshot exists; reconcile drift when policy allows; take periodic `ad_hoc` snapshots at configured cadence.
- Remakes tracking: cards first seen in the Remakes list are persisted with timestamps; label actions can zero/restore points; reason labels are tracked for the pie chart.

### Wallboard widgets (data collation)

- Remakes card (counts + trends):
  - Scope: active sprint only.
  - “Requested” counts use `first_seen_at` and exclude any remakes with a remove-label (`trello_sync.remake_label_actions.remove`).
  - “Accepted” counts use `first_seen_at` and only include remakes with a reason label (`trello_sync.remake_reason_labels`).
  - Daily comparisons use calendar days (midnight–23:59:59 in app timezone).
- Remake reasons pie:
  - Scope: active sprint only.
  - Date filter: `first_seen_at` must fall on “today” (midnight–23:59:59).
  - Exclusions: any remake whose `label_name` matches a remove label is ignored.
  - Categories are derived from `reason_label` (the Trello reason label) and displayed in factory-flow order:
    1) Programming Related
    2) Punch
    3) Folding
    4) Welding
    5) Assembly
    6) Unlabelled (when `reason_label` is null)
  - The factory-flow order list lives in `app/Domains/Wallboard/Repositories/RemakeStatsRepository.php`.
  - Labels are displayed without the `RM` prefix and use Trello label colors where available.
- Machines card:
  - Source: FourJaw current status endpoint.
  - Duration text is derived from the status start timestamp.
  - Status/idle color thresholds live in `config/wallboard.php`.
- Utilisation pie:
  - Source: FourJaw utilisation summary endpoint.
  - Summary range is the last N working days (configurable).
  - Per-machine utilisation is “today to now.”

### Remake reasons admin page

- Route: `/remakes/reasons`
- Scope: active sprint only (if no active sprint is found, all sprints are included).
- Date filter: `first_seen_at` falls on the selected day.
- Exclusions: any remake whose `label_name` matches a remove label is ignored.
- Categories and ordering match the wallboard remake reasons pie (factory-flow order).
- Remakes stats: daily requested/accepted counts are based on first seen dates; sprint/month totals exclude “remove” labels.
- Polling: Trello actions are incrementally ingested using cursors and applied to local records; delete actions mark remakes as removed.
- Wallboard refresh: syncs and snapshots before reload so totals update on every refresh.
- Revision badge: resolves revision from `APP_REVISION` or git HEAD; published timestamp from `APP_RELEASED_AT` or build timestamps.

## Useful commands

- `php artisan trello:run-dashboard` (master command)
- `php artisan trello:health-check` (connectivity + config sanity check)
- `php artisan trello:sprints:sync-registry` (registry sync only)
- `php artisan sprint:snapshot {sprintId} {start|end|ad_hoc}` (manual snapshot)
- `php artisan trello:inspect-board {boardId}` (debug: prints board metadata)
- `php artisan trello:find-control-card {boardId} --name="Sprint Control"` (debug)
- `php artisan trello:poc {boardId}` (debug/proof-of-concept)
- `php artisan sprint:create {name} {boardId} {startsAt} {endsAt} {doneListIds*}` (legacy helper)
- `php artisan sprint:configure-close {sprintId} {controlCardId} {statusFieldId} {closedOptionId}` (legacy helper)
- `php artisan trello:remake-labels:sync-missing {--sprint=} {--limit=}` (backfill missing remake labels from Trello)
- `php artisan trello:remake-labels:set {remakeId} {label}` (set a remake label on Trello + local)

## Data stored locally

Tables (key ones):
- `sprints` (registry + board metadata)
- `sprint_snapshots` (snapshot events)
- `sprint_snapshot_cards` (card state per snapshot)
- `cards` (card cache)
- `sprint_remakes` (remake tracking)
- `trello_actions` (label/action polling cache)
- `board_sync_cursors` (polling cursors)
- `report_definitions`
- `report_schedules`
- `report_runs`

## Troubleshooting

- No sprints after sync:
  - Check `TRELLO_REGISTRY_BOARD_ID` and custom field IDs in `config/trello_sync.php`.
- Wrong Done list:
  - Update `TRELLO_DONE_LIST_NAMES`.
- Divergence label not applied:
  - Ensure control card has the relevant custom fields filled in.
- Wallboard shows “No active sprint”:
  - Set exactly one `status=active` in the registry, or ensure dates span now.

## Project notes

- The wallboard is public by default; the `wallboard.access` middleware is currently not used.
- Keep Trello custom field naming consistent, or override via env.
