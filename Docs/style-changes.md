# Project Review — Style-Change Suggestions

Date: 2026-10-12 (UI pass 2026-10-12, see notes). Style-only items, kept separate per request. Nothing here changes behavior.
Applied in this pass: Pint run on dirty files (`AdminMiddleware`: `! Auth` spacing, blank line after namespace; `LogUserLogin`: brace position, single-line empty body, ordered imports).

## PHP (Pint / AGENTS.md rules)

1. **Run `vendor/bin/pint` repo-wide.** ~30 files still drift: dominant fixers are `braces_position` (K&R), `not_operator_with_successor_space` (`! Auth`, `! is_string`), `ordered_imports`, `blank_line_before_statement`, `fully_qualified_strict_types` (use imports, not FQCNs), `concat_space`, `cast_spaces`, `single_quote`, `blank_line_after_namespace`, trailing commas. Affected: controllers (`Analytics`, `Login`, `Page`), middleware, listeners, models (`Analytics`, `Tag`), `Services/Deploy/*`, `MigrationInspector`, `ComicCard`, `bootstrap/app|providers`, `config/auth|database|sanctum`, `database/factories/UserFactory`, 9 migrations, `routes/api|web`, `ComicUploadLimitsTest`.
2. **Explicit return + param types everywhere.** Project rule requires `function foo(...): bool`-style signatures; ~87 controller methods lack them — add `: View|RedirectResponse|JsonResponse|Response` and type route params (`string|int $id`, or model binding `Comic $comic`, `User $user`). Same for remaining model relations/scopes and `readerEngagement(): array`, `popularTags`, `recommended`, `likedPostsFor`.
3. **Constructor promotion.** Remaining manual assignments (`CurlFtpTransport` host/port/user/pass/options, any `$this->x = $x`) → `public function __construct(protected string $host, ...)`. No empty zero-param `__construct()`.
4. **Enum keys TitleCase** (`FavoritePerson`-style) when enums are introduced (see `suggestions.md`).
5. **PHPDoc over narration.** Keep array-shape PHPDocs (`ChangelogReader`, `FtpDeployer`, `CurlFtpTransport`, `SidebarComposer`, `AdminController`) and extend `@param`/`@return` shapes to uncovered methods; remove trailing inline narrations (`// Default to false`, `// Create the base slug`, emoji comments in `ComicController`, Portuguese string in `PostController`).
6. **Unify conditionals/spacing.** `} elseif` (not `} else if`), `! Auth` / `! file_exists` spacing, single quotes for strings, `single_line_comment_spacing`.

## Blade / CSS / Tailwind

7. **[FIXED 2026-10-12] One design system per surface.** `layouts/app.blade.php` no longer loads Bootstrap/jQuery/Popper (verified unused in main views); search results use Tailwind items; auth views (`register`, `password/*`) rebuilt as Tailwind dark cards matching login; `collections/index|create` and `comics/search` converted. Admin was already rebuilt on Tailwind.
8. **[FIXED 2026-10-12] Inline `style=` removed from app views.** `auth/register|password/*`, `collections/*`, `comics/search` converted to utilities. Remaining `style=` occurrences are JS-driven only (deploy progress width, D3 dimensions, `comics/show` comment templates).
9. **[SUPERSEDED 2026-10-12] Bootstrap removed instead of upgraded.** No BS5 renames needed — Bootstrap is gone from both layouts; remaining `btn`/`badge`/`form` classes converted to Tailwind/`admin-*` in the touched views.
10. **[FIXED 2026-10-12] Custom CSS layered; dead code deleted.** `app.css` rebuilt Tailwind-first (base body, `@layer components`, kept `.markdown-body`); conflicting `.comic-card`/`.comics-grid`/`.avatar`/`.post-*`/`.dark-mode` rules deleted (unlayered CSS was overriding utilities app-wide); `comics/index` `<style>` overrides + dead cookie-progress JS removed.
11. **[PARTIALLY FIXED 2026-10-12] Accessibility pass on shell + cards.** Done: skip-to-content link, search `label` + `combobox`/`listbox` roles + keyboard nav (arrows/Enter/Esc) + empty state, `aria-expanded`/`aria-haspopup` on toggles, `type="submit"` on logouts, like buttons with `aria-label`/`aria-pressed` (live-updated), lazy images with descriptive alts, footer landmark. Remaining: icon-only comment buttons, generic post-media alts.
12. **Blade hygiene.** Replace `@yield/@section('styles'|'scripts')` with `@stack/@push`; move FontAwesome `<link>` out of `left-panel` partial into `<head>` (or bundle); add `type="button"` consistency and focus states.
