# Forum — Implementation Plan

> Status: **shipped** (core through phase 7). New tables are all prefixed
> `forum_` so they sort together in the database and are easy to spot/manage
> by hand. Implemented work is summarised in [CHANGELOG.md](CHANGELOG.md).
>
> Shipped: migrations + models + factories; policies + FormRequests;
> public index/board/thread, posting/replies/quotes, reactions,
> subscriptions, unread tracking, attachments; reports + moderator
> actions; admin categories/moderation/moderators/bans/settings;
> notifications + gamification; search, tags and solved answers.
> Deferred (phase 8): polls, email digests, drag-ordering polish.

## 1. Goals

- Give members a place to talk **outside** the comic/post feed: sections,
  boards, threads and replies with quotes, reactions, mentions and
  subscriptions.
- Reuse what already exists instead of inventing parallel systems: the
  activity feed (`ActivityLogger`), gamification (exp/badges/streaks),
  the `notifications` table, the admin Tailwind layout and the
  settings-overlay pattern (`GamificationSetting` / `EhImportSetting`).
- Full admin tooling: category tree, moderation queue, bans, moderators,
  tunable forum settings — no console access needed.

### Non-goals (for now)

- Real-time websockets; refresh/poll is enough.
- Full-text search engine (MySQL `LIKE`/`MATCH` is enough at this scale).
- Anonymous posting (everyone must be logged in), private messaging
  (that is a separate feature), polls (phase 3).

## 2. Conventions & integration points

| Concern | Where it plugs in |
| --- | --- |
| Interactions | `App\Support\ActivityLogger::log()` + new keys in `ActivityLog::actions()` |
| Exp / badges | `config/gamification.php` `exp` + `badges`; `Gamification::contentTarget()` for backfill |
| Notifications | `App\Notifications\GamificationNotification` pattern → add `ForumNotification` (database channel) |
| Bell | `NotificationComposer` (new forum items already flow through it) |
| Admin layout | `resources/views/layouts/admin-layout.blade.php` nav + `admin-*` CSS components |
| Settings | `forum_settings` table overlaid at boot, mirroring `GamificationSetting` |
| HTTP | Controllers + **FormRequests** + **Policies** (follows `Docs/suggestions.md` §1) |

Rules to respect: FormRequests for validation, Policies for
owner-or-admin authorization, eager-loading on list pages, paginate every
listing, sanitize all user HTML before render (threads/posts are **the**
XSS surface).

## 3. Database schema (`forum_*`)

Suggested migration order is top-to-bottom; foreign keys cascade on the
parent (`ON DELETE CASCADE`) unless noted.

### `forum_categories`

Two levels: sections (`parent_id = null`) and boards (child). Instead of a
separate `forum_boards` table, self-reference keeps it simple.

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `parent_id` | nullable FK → `forum_categories.id`, `null` = section |
| `name`, `slug` | `slug` unique |
| `description` | nullable text |
| `icon` | nullable string (emoji or SVG key) |
| `color` | nullable string (accent) |
| `sort_order` | int, default 0 |
| `is_visible` | bool, default 1 |
| `is_locked` | bool, default 0 (no new threads) |
| `min_role` | enum `guest,member,moderator,admin`, default `member` (who may start threads) |
| `thread_count`, `post_count` | cached counters (updated on write) |
| timestamps | |

Index: `(parent_id, sort_order)`, `slug` unique.

### `forum_threads`

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `forum_category_id` | FK → `forum_categories.id`, cascade |
| `user_id` | FK → `users.id`, cascade |
| `title` | string |
| `slug` | unique per category (or global) |
| `type` | enum `discussion,question,poll`, default `discussion` |
| `is_pinned` | bool (ordered first) |
| `is_locked` | bool (no replies) |
| `is_approved` | bool, default 1 (moderation gate) |
| `is_deleted` *(soft)* | `deleted_at` for mod removal without losing data |
| `view_count` | int |
| `reply_count` | int (cached; excludes the OP) |
| `reaction_count` | int (cached) |
| `last_post_at` | timestamp, for "latest activity" sort |
| `last_post_user_id` | nullable FK → `users.id`, `nullOnDelete` |
| `solved_post_id` | nullable FK → `forum_posts.id` (for `question` type) |
| timestamps | |

Indexes: `(forum_category_id, is_pinned desc, last_post_at desc)`,
`(user_id)`, `slug` unique within category.

### `forum_posts`

The opening post of a thread is also row #1 here, so `forum_threads` has no
body column. `position = 1` marks the OP.

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `forum_thread_id` | FK → `forum_threads.id`, cascade |
| `user_id` | FK → `users.id`, cascade |
| `parent_id` | nullable FK → `forum_posts.id` (reply-to / quote), `nullOnDelete` |
| `body` | mediumText (sanitized HTML or Markdown-rendered) |
| `body_format` | enum `markdown,html`, default `markdown` |
| `position` | int, 1 = opening post |
| `is_approved` | bool, default 1 |
| `edited_at`, `edit_count` | nullable / int |
| `deleted_at` | soft delete |
| timestamps | |

Indexes: `(forum_thread_id, position)`, `(user_id)`, `(parent_id)`.

### `forum_post_reactions`

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `forum_post_id` | FK → `forum_posts.id`, cascade |
| `user_id` | FK → `users.id`, cascade |
| `reaction` | string, default `like` (room for `laugh`,`wow`,`sad`) |
| timestamps | |

Unique `(forum_post_id, user_id, reaction)`; index `(user_id)`.

### `forum_tags` + `forum_tag_thread`

Global `tags` is comic-scoped (`comic_tag`); keep forum tags separate.

- `forum_tags`: `id`, `name`, `slug` unique, `color` nullable, `use_count`.
- `forum_tag_thread`: `forum_thread_id`, `forum_tag_id` (both cascade),
  unique composite, timestamps.

### `forum_attachments`

Polymorphic so it can hang off a thread (OP) or a single post.

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `attachable_type`, `attachable_id` | morph (`forum_threads` / `forum_posts`) |
| `user_id` | FK → `users.id`, cascade |
| `path` | storage path (use `Storage`, hashed names — see issues.md #6) |
| `original_name`, `mime`, `size` | metadata |
| `image_width`, `image_height` | nullable (for layout) |
| timestamps | |

Index `(attachable_type, attachable_id)`.

### `forum_subscriptions`

Who gets notified about new posts in a thread.

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `user_id` | FK → `users.id`, cascade |
| `forum_thread_id` | FK → `forum_threads.id`, cascade |
| `notify` | enum `db,email,both,none`, default `db` |
| `last_notified_post_id` | nullable |
| timestamps | |

Unique `(user_id, forum_thread_id)`.

### `forum_thread_reads`

Unread tracking per user/thread (powers "new posts" dots).

- `id`, `user_id` (cascade), `forum_thread_id` (cascade),
  `last_read_post_id` nullable, `last_read_at` timestamp,
  unique `(user_id, forum_thread_id)`.

### `forum_reports` (moderation)

- `id`, `reporter_id` (FK → users, cascade),
  `forum_thread_id` nullable FK (cascade),
  `forum_post_id` nullable FK (cascade),
  `reason` enum `spam,abuse,offtopic,illegal,other`,
  `details` nullable text,
  `status` enum `pending,reviewed,dismissed`, default `pending`,
  `reviewed_by` nullable FK → users, `reviewed_at` nullable,
  timestamps.

Index `(status, created_at)`.

### `forum_bans`

- `id`, `user_id` (cascade), `forum_category_id` nullable FK (cascade),
  `banned_by` FK → users, `reason` nullable, `banned_until` nullable
  timestamp (`null` = permanent), timestamps.
- Index `(user_id, forum_category_id)`.

### `forum_moderators`

- `id`, `user_id` (cascade), `forum_category_id` nullable FK (cascade) —
  `null` = forum-wide, `granted_by` FK → users, timestamps,
  unique `(user_id, forum_category_id)`.

### `forum_moderation_log`

Audit trail for mod actions (lock, pin, move, delete, approve, ban).

- `id`, `moderator_id` FK → users, `action` string,
  `subject_type`, `subject_id` morph, `metadata` json nullable,
  `created_at`.

### `forum_settings`

Mirror of `GamificationSetting`: `key` (string PK), `value` (json/text),
timestamps. Keys: `enabled`, `approval_mode` (`none,first_post,all`),
`edit_grace_minutes`, `min_account_age_hours`, `max_attachments`,
`allowed_mime`, `posts_per_page`, `hot_window_days`, `auto_lock_days`,
`signature_enabled`, `prohibited_words` (list).

### Phase 3 (optional): polls

`forum_polls` (`forum_thread_id` unique, `question`, `multiple`, `closes_at`),
`forum_poll_options` (`forum_poll_id`, `label`, `position`, `votes_count`),
`forum_poll_votes` (`forum_poll_id`, `forum_poll_option_id`, `user_id`,
unique `(forum_poll_id, user_id)`).

## 4. Models & relations

`app/Models/Forum/` (or flat `app/Models/ForumCategory.php` etc. — match the
existing flat layout: keep it flat to stay consistent).

- `ForumCategory`: `parent()`, `children()`, `threads()`, `scopeVisible()`,
  `scopeSections()`, counter helpers.
- `ForumThread`: `category()`, `author()`, `posts()`, `openingPost()`,
  `lastPoster()`, `tags()`, `subscriptions()`, `reactionsCount()`,
  `scopeVisible()`, `scopeUnanswered()`, `scopePinnedFirst()`, `isUnreadFor(User)`.
- `ForumPost`: `thread()`, `author()`, `parent()`, `children()`,
  `reactions()`, `attachments()`.
- `ForumPostReaction`, `ForumTag`, `ForumAttachment` (morphTo),
  `ForumSubscription`, `ForumThreadRead`, `ForumReport`, `ForumBan`,
  `ForumModerator`, `ForumModerationLog`, `ForumSetting`.

`User` gets: `forumThreads()`, `forumPosts()`, `forumSubscriptions()`,
`forumBadges()`-style helpers, and a `forumPostCount()`.

## 5. Routes (`routes/web.php`)

Public (guest-readable, posting requires `auth`):

```
GET  /forum                         forum.index          sections + boards + stats
GET  /forum/{category:slug}         forum.category       thread list (filters/sort)
GET  /forum/{category:slug}/{thread:slug}  forum.thread  posts, paginated
POST /forum/{category:slug}         forum.threads.store  (auth, throttle)
POST /forum/threads/{thread}/reply  forum.posts.store    (auth, throttle)
PUT  /forum/posts/{post}            forum.posts.update   (auth, owner-or-mod)
DEL  /forum/posts/{post}            forum.posts.destroy  (auth, owner-or-mod)
POST /forum/posts/{post}/react      forum.posts.react    (auth)
POST /forum/threads/{thread}/subscribe   forum.threads.subscribe (auth)
POST /forum/threads/{thread}/report      forum.threads.report
POST /forum/posts/{post}/report      forum.posts.report
POST /forum/mark-solved/{post}      forum.threads.solve  (auth, OP/mod)
GET  /forum/search                  forum.search
GET  /forum/unread                  forum.unread (auth)
```

Moderation (moderator-or-admin, via policy):

```
POST /forum/threads/{thread}/pin|lock|move|approve|delete
POST /forum/posts/{post}/approve|delete
```

Admin (`/admin/forum`, `AdminMiddleware`):

```
GET  /admin/forum                          admin.forum            dashboard + stats
GET  /admin/forum/categories               admin.forum.categories
GET  /admin/forum/categories/create        admin.forum.categories.create
POST /admin/forum/categories               admin.forum.categories.store
GET  /admin/forum/categories/{id}/edit     admin.forum.categories.edit
PUT  /admin/forum/categories/{id}          admin.forum.categories.update
DEL  /admin/forum/categories/{id}          admin.forum.categories.destroy
POST /admin/forum/categories/{id}/toggle   admin.forum.categories.toggle
GET  /admin/forum/threads                  admin.forum.threads
GET  /admin/forum/moderation               admin.forum.moderation   reports + pending
POST /admin/forum/moderation/{report}/resolve  admin.forum.reports.resolve
GET  /admin/forum/moderators               admin.forum.moderators
POST /admin/forum/moderators               admin.forum.moderators.store
DEL  /admin/forum/moderators/{id}          admin.forum.moderators.destroy
GET  /admin/forum/bans                     admin.forum.bans
POST /admin/forum/bans                     admin.forum.bans.store
DEL  /admin/forum/bans/{id}                admin.forum.bans.destroy
POST /admin/forum/settings                 admin.forum.settings
```

## 6. Public features

- **Index**: sections → boards with thread/post counts, last-post preview,
  "Mark all read", forum stats (threads, posts, newest member).
- **Board**: pinned threads on top, then sortable by latest / newest /
  most replies / most reactions / unanswered; filters (mine, unread,
  solved, tag); pagination; "New thread" button gated by `min_role` +
  ban check.
- **Thread**: OP + replies, nested quote/highlight of `parent_id`,
  markdown editor with live preview, mention autocomplete (ties into the
  handles TODO), attachment upload (images, hashed names, mime/size caps),
  reactions, subscribe toggle, share, report, edit own post within grace
  window (shows "edited" stamp), OP can mark a reply as solved for
  `question` threads.
- **Unread**: per-thread "new posts" and a `/forum/unread` digest.
- **Search**: by title/body/author/tag within forum.
- **Profile**: forum stats on the public profile (threads started, posts,
  latest threads tab).
- **SEO**: clean slugs, `og:` tags, canonical URLs, sitemap entries.

## 7. Admin tooling

- **Dashboard**: totals (categories, threads, posts, members), 30-day
  activity chart (Chart.js, already loaded), latest threads, moderation
  queue counts.
- **Categories**: CRUD self-referential tree, drag order (Sortable is
  already loaded), visibility/lock/min_role toggles, counters.
- **Threads/posts**: searchable DataTable, bulk lock/pin/approve/delete,
  move thread between boards, view as member.
- **Moderation queue**: reports + pending-approval posts, resolve/dismiss
  with one click; optional auto-hide at N reports.
- **Moderators**: grant forum-wide or per-board, revoke.
- **Bans**: global or per-board, temporary/permanent, with reason.
- **Settings**: the `forum_settings` form (approval mode, edit grace,
  attachment caps, auto-lock, prohibited words) — same UX as the
  gamification panel.
- **Audit log**: latest moderator actions.

## 8. Permissions

- `ForumPolicy`, `ForumThreadPolicy`, `ForumPostPolicy`,
  `ForumCategoryPolicy` in `app/Policies/`.
- `is_moderator(User, ?ForumCategory)`: `admin_level >= 1` OR a
  `forum_moderators` row (forum-wide or matching board).
- Posting blocked when `ForumBan` matches (global or board) and
  `banned_until` is null/future.
- Guests can read (if `forum.enabled`), must log in to interact.

## 9. Notifications & gamification

- `ForumNotification` (database channel, `notifications` table) for:
  reply to your thread, reply to your post (quote), mention, thread
  moved/locked, report resolved, ban applied, badge/level (existing).
- Feed the existing bell via `NotificationComposer`.
- `ActivityLog::actions()` additions:
  `forum.thread.created`, `forum.post.replied`, `forum.post.reacted`,
  `forum.thread.solved`, `forum.reported`, `forum.moderated`.
- `config/gamification.php` `exp`: thread 20, reply 8, reaction 2,
  solved-answer 10 (never farmable: unique-subject on reaction).
- Badges: `first_thread`, `conversationalist` (100 posts),
  `helpful` (10 solved answers), `moderator` (earned manually).
- Extend `Gamification::contentTarget()` so the retroactive backfill can
  award exp for pre-existing forum content (idempotent, additive).

## 10. Suggested order

1. Migrations + models + factories (`forum_categories`,
   `forum_threads`, `forum_posts`).
2. Policies/FormRequests + read-only index/board/thread views.
3. Posting, replies, quotes, reactions, subscriptions, unread.
4. Reports + moderator actions + admin category CRUD.
5. Admin moderation queue, bans, moderators, settings.
6. Notifications + gamification wiring + backfill.
7. Search, tags, solved answers, profile tab.
8. (Optional) Polls, attachments polish, email digests.

## 11. Tests

Feature tests (isolated sqlite `:memory:` like `GamificationTest`):

- Category/thread/post CRUD auth: guest cannot post; owner can edit/delete
  own; non-owner/non-mod cannot; mod can lock/pin/move/delete.
- Quote/reply nesting, reaction uniqueness, subscription notifications.
- Approval gate + report queue + ban enforcement (global & per-board).
- Sanitization: script/`onerror` stripped from rendered body.
- Admin: category CRUD, moderator grant/revoke, settings persistence.
- Gamification: thread/reply awards exp, idempotent backfill, badges.

## 12. Open questions

- Do boards need per-board moderators on day one, or is admin-only enough
  for launch?
- Plain Markdown or a rich-text editor? (Recommend Markdown + preview,
  store rendered-cached HTML sanitized.)
- Should the forum appear in the main nav, or under a "Comunidade" hub
  alongside the existing `/posts` feed?
- Retention for soft-deleted content (purge after N days?).
