# Project Review — Suggestions

Date: 2026-10-12. Non-style functional suggestions (style-only items live in `style-changes.md`).
Already applied in this pass: models `$casts` → `casts()` (+ relation/scope/slug return types), `User` casts merged, `ComicCard` typed + constructor promotion, `LogUserLogin` constructor promotion + `handle(): void`, `TrackPageView`/`AdminMiddleware` `handle(): Response` + ordered imports, `TrackPageView` middleware alias in `bootstrap/app.php`, 2 named migrations → anonymous `return new class` + `: void` (incl. collections `down()` fix), deleted `webpack.mix.js`, added global `[x-cloak]` rule. Tests: 129 passed, 1 skipped.

## Backend

1. **Adopt FormRequests + Policies.** Create `app/Http/Requests/` (Comic/Page/Collection/Post/Profile/Analytics) with `exists`, `max`, `dimensions` rules; `app/Policies/` (ComicPolicy, CollectionPolicy, CommentPolicy, PostPolicy) keyed on owner-or-admin; replace inline `admin_level` checks and `authorizeCollection*` helpers. Register `AdminMiddleware` on the admin group (fixes `admin.widgets.*` hole).
2. **Secure routes.** Add `->middleware('auth')` to all comic/page mutation routes; scope page queries to parent comic (`$comic->pages()->findOrFail($id)`); wrap reorder/sync in transactions; paginate + throttle API routes; consider `auth:sanctum` for API.
3. **Harden uploads.** Switch to `Storage` (`store()`/`hashName()`), sanitize filenames, enforce `image|mimes|dimensions|max` + count limits, fix perms (`0755`, never `0777`), delete replaced avatars/covers, dedupe with `Str::uuid()` instead of `md5(now().rand())`.
4. **Fix analytics pipeline.** Derive `user_id`/`ip`/`user_agent` server-side; add validation `max:` lengths, `auth` + `throttle`; paginate `referralAnalytics`; sample/queue `TrackPageView` (or keep disabled) with a retention policy for PII.
5. **Introduce enums.** `AnalyticsEventType` (`PageView`, `Login`), `DeployState` (`Queued/Running/Cancelling/Cancelled`), `ChangelogCategory` (`Added/...`) — TitleCase keys per project rule; migrate string comparisons.
6. **Query hygiene.** Eager-load relations on index/show/admin lists, paginate admin tables, consolidate `SidebarComposer` queries (cache `Widget::all()`/popular tags/recommended), fix `coverComic()` N+1 (eager-load `orderedComics` or cache per collection set), batch the post-ancestor walk.
7. **Clean routing.** Remove duplicate `/` + `/comics` definitions; convert admin closures to `Route::controller(AdminController::class)->group(...)`; use implicit binding (`{user}`, `{comic}`) with typed controller params; `Route::middleware('auth')` (string form) for single middleware.
8. **Remove stale `Post.$fillable.username`** (verify column; if unused, drop from fillable via migration review).
9. **Lock test gaps.** Add feature tests: guest/non-owner comic mutation, page store, non-admin `admin.widgets.*`, analytics spoof + throttle, bogus collection IDs, deploy/migration authorization, avatar replacement/orphan.

## Frontend / Build

10. **[DONE 2026-10-18]** Frontend toolchain upgraded with the owner's approval: `vite@8.3.4` (was 5.x, EOL), `laravel-vite-plugin@3.2.0` (requires Vite 8), `axios@1.20.0`, `@tailwindcss/vite`/`tailwindcss@4.3.3`; `npm audit` clean. `npm run build` verified (`public/build/manifest.json` now lists `resources/js/admin.js` too). Bootstrap 5 + Popper were dropped instead of adopted — no view uses Bootstrap JS since the Tailwind rebuild.
11. **[DONE 2026-10-18]** All CDN tags removed, libraries bundled and pinned in `package.json`: `alpinejs@3`, `sortablejs@1.15` (imported by `resources/js/app.js` for `comics/edit` + `collections/edit`), and the new admin entry `resources/js/admin.js` bundles `jquery@3.7` + `datatables.net@3` + `chart.js@4` + `d3@7` + Alpine, exposing the globals the admin pages bootstrap inline. Per-page inline blocks became `type="module"` so they run after the bundle (deferred modules execute in document order). FontAwesome was **not** bundled: it was used for a single `<i class="fa-solid fa-tag">` in the sidebar, now an inline SVG. `AssetBundlingTest` fails the suite if any page loads a script/stylesheet from another origin again.
12. **Adopt stacks.** Layouts: `@stack('styles')`/`@stack('scripts')`; children: `@push(...)` instead of `@section('scripts')` overrides.
13. **Resolve Sass duplication.** Delete `resources/css/app.scss` or wire it as a real Vite Sass entry (`npm i -D sass`); single source of truth.
14. **Delete dead views** (`welcome.blade.php`, `old.index.blade.php` incl. `dd()`) if unrouted; move static images to `resources/images` + `Vite::asset()`, keep `asset('storage/...')` for uploads only.
15. **Decide Livewire.** If unused, remove the dependency; if adopted, add `<livewire:styles/>`/`<livewire:scripts/>` (v3 syntax, never `@livewireStyles`) and build one component (e.g. comic search) to replace jQuery `fetch` autocomplete.
16. **Dark mode: one strategy.** Either Tailwind v4 `@custom-variant dark` + Alpine store/`localStorage`/`prefers-color-scheme` toggle, or delete `.dark-mode`/`.toggle-dark-mode` dead CSS.
17. **Extract inline JS.** Move per-page `<script>` blocks to `resources/js/*.js` Vite entries; pass URLs via `data-*` attributes instead of `{{ url('/') }}` globals; avoid `innerHTML` string building (XSS-prone).
18. **Add `pint.json`** to pin style rules and run Pint in CI so formatting stops drifting.
