# TODO (database) — New features that need schema changes

> Shipped changes move to [CHANGELOG.md](CHANGELOG.md) — add one entry per PR.

> Already possible WITHOUT migrations (done): quote posts
> (`posts.referenced_post_id` existed, now wired in `PostController@store`,
> counted via `Post::quotes()` + `withCount`, shown in the feed).
> Everything below needs a new table/column.

## 3. Follows + "Following" feed

- [x] Migration: `create_follows_table`
  - `follower_id` + `followed_id` (FKs → `users`, cascade), unique composite, timestamps
- [x] Relations: `User::following()`, `User::followers()` (belongsToMany self)
- [x] Follow/unfollow button on profiles + on hover cards
- [x] Feed tabs: "For you" (all, current) vs "Following" (`whereIn(author_id, following)`)
- [x] Follower/following counts + lists on profiles (`withCount`)

## 4. User handles

- [x] Migration: `add_handle_to_users_table` — `handle` (string, unique, nullable for backfill)
- [x] Backfill from slugs of `name`; validate `^[a-z0-9_]{3,20}$`, reserve on register
- [x] Replace the derived `@slug(name)` in `posts/content.blade.php` and profile URLs
      (`/profile/{username}` → handle) with the real column
- [x] Needed for reliable mentions below

## 5. Mentions + notifications

- [x] Parse `@handle` on post create (needs §4); store or compute on read
- [x] Migration: `create_notifications_table` — or use Laravel's built-in
      `notifications` table (`php artisan notifications:table`) + `Notifiable`
- [x] Notify on: mention, quote of your post, like milestone, new follower, post liked
- [x] Bell icon with unread count in navbar + notifications page; mark-read on view

## 6. Bookmarks

- [x] Migration: `create_bookmarks_table`
  - `user_id` + `post_id` (FKs, cascade), unique composite, timestamps
- [x] Bookmark icon on posts + "Bookmarks" page (private per user)

## 7. Hashtags + trending

- [x] Parse `#tag` on post create; migration: `create_hashtags_table` (`tag`, unique)
      + `hashtag_post` pivot
- [x] `GET /hashtag/{tag}` timeline; "Trending" sidebar (count last 24h/7d)

## 9. Moderation / safety

- [x] Migration: `create_reports_table`
  - `reporter_id` (FK → `users`), `post_id` (nullable FK → `posts`),
    `reason`, `status` (pending/reviewed/dismissed), timestamps
- [x] Report button (··· menu per post) + admin review queue (`admin.*` routes exist)
- [x] Mute/block (2026-10-19): `create_mutes_table` (`user_id`, `muted_id`, unique) plus
      `User::mutedUsers()`/`mutedBy()`; `Post::scopeNotMutedBy()` hides muted authors from
      the feed, the hashtag timeline and the reply lists; `MuteController` toggles it from
      the profile (`POST /users/{user}/mute`). Reversible and silent (the muted person is
      never notified).
- [x] Post edit/delete (2026-10-19): `PostController@destroy` (owner or admin via
      `PostPolicy@delete`) hard-deletes the post, its media files and the cascaded
      replies/quotes; `update` stamps `posts.edited_at` only when the text changes (the
      "editado" badge used to read `updated_at`, which the view counter also bumped).

## Suggested order

> Done and removed: likes (§1), replies + single-post view (§2),
> post view counts (§8). Section numbers below are kept stable.

1. Handles (§4) → mentions/notifications (§5)
2. Follows + Following tab (§3)
3. Bookmarks (§6), hashtags (§7), moderation (§9)
