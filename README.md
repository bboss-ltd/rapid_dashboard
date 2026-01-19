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

Legacy env vars like `TRELLO_CF_SPRINT_STATUS` are no longer used; prefer the name-based registry field envs above.

Note on `TRELLO_REGISTRY_DONE_LIST_IDS_FIELD_NAME`:
This is an optional registry-board custom field that lets you override Done list ids per sprint. Use it if a sprint board has multiple Done lists or a non-standard Done column name. If you don't need per-sprint overrides, leave it unset and the system will infer Done lists from `TRELLO_DONE_LIST_NAMES` instead.
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

## Data stored locally

Tables (key ones):
- `sprints` (registry + board metadata)
- `sprint_snapshots` (snapshot events)
- `sprint_snapshot_cards` (card state per snapshot)
- `cards` (card cache)

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
