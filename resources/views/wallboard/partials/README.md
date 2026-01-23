# Wallboard Partials

This folder contains reusable Blade components for the wallboard layout. Each component receives data from `WallboardController@sprint`.

## Layout pieces

- `header.blade.php`
  - Top title bar (sprint dates, status, sync button, revision badge).
- `remakes-card.blade.php`
  - Remakes KPIs (current list count, requested/accepted today, comparisons, sprint/month pace).
- `machines-card.blade.php`
  - Machine status panel with FourJaw status + utilisation.
- `burndown-card.blade.php`
  - Burndown chart container/markup.
- `remake-reasons-card.blade.php`
  - Remake reasons donut + legend.
- `utilisation-card.blade.php`
  - Utilisation donut (last N working days).
- `progress-card.blade.php`
  - Progress donut (done vs remaining). Currently not used on the wallboard.

## Scripts

JS is co-located with each partial and pushed into the `scripts` stack using `@push('scripts')` so each component owns its logic while rendering scripts at the bottom of the page.
