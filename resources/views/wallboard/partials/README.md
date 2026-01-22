# Wallboard Partials

This folder contains reusable Blade components for the wallboard layout. Each component receives data from `WallboardController@sprint`.

## Layout pieces

- `header.blade.php`
  - Top title bar (sprint dates, status, sync button, revision badge).
- `remakes-card.blade.php`
  - Remakes KPIs (current list count, requested/accepted today, comparisons, sprint/month pace).
- `machines-card.blade.php`
  - Demo machine status/load panel (uses config-based status colors).
- `burndown-card.blade.php`
  - Burndown chart container/markup.
- `remake-reasons-card.blade.php`
  - Remake reasons donut + legend.
- `progress-card.blade.php`
  - Progress donut (done vs remaining).

## Scripts

JS is co-located with each partial and pushed into the `scripts` stack using `@push('scripts')` so each component owns its logic while rendering scripts at the bottom of the page.
