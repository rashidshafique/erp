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

## PHP
- Curly braces on every control structure.
- Constructor property promotion; explicit parameter and return types.
- PHPDoc with array shapes over inline comments.
- TitleCase enum keys.

## Tests
- Add or update a feature test for behaviour changes (not for copy or styling). Use factories and their states.
- Run only the related files: `php artisan test --compact <files>` — never the full suite.
