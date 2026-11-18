# Foundry ERP

Laravel 13 on PHP 8.4, Livewire, PHPUnit. Check a package's version (`composer show <pkg>`, `package.json`) before relying on its API.

## Working
- Session handoff lives in `docs/HANDOFF.md` — read it only when asked. Project status is in `docs/STATUS.md`.
- Follow sibling files for structure and naming; reuse existing components.
- Create files with `php artisan make:* --no-interaction`.
- No new dependencies or base folders without approval. No docs unless asked.
- Do not run Pint.
- Prefer Boost MCP tools: `database-schema`, `database-query`, `search-docs` (before version-specific framework changes), `get-absolute-url`, `last-error`.
- If a frontend change doesn't show, run `npm run build`.

## Lists and pages
- Every page is Livewire, new ones and ones you touch. Tabs, search, filters and paging change in place with no page load, and their state stays in the URL (`#[Url]`). A controller only returns the view that hosts `<livewire:…>`; downloads such as CSV stay on routes.
- A list with a side panel copies `app/Domains/Tooling/resources/views/livewire/core-list.blade.php` and the enquiries list: `use ListDetails` and `WithPagination`, a `$focused` id with `focus()`, and `list-details-toggle` / `list-hide-panel` for the toggle.
- Panel open: a row click shows that record in the panel (first row by default), double-click or Enter opens its page, and the panel has an Open button. Panel closed: a row click goes to the record page. Key rows `wire:key="…-{{ $details ? 'p' : 'f' }}"`.
- Share the filtered query between the list and its export (a static `filtered()` on the controller or a service) so they cannot drift.
- Add a Livewire feature test for tab and filter changes and for the panel focus.

## PHP
- Curly braces on every control structure.
- Constructor property promotion; explicit parameter and return types.
- PHPDoc with array shapes over inline comments.
- TitleCase enum keys.

## Tests
- Add or update a feature test for behaviour changes (not for copy or styling). Use factories and their states.
- Run only the related files: `php artisan test --compact <files>` — never the full suite.
