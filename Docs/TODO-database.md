# TODO (database) — New features that need schema changes

> Shipped changes move to [CHANGELOG.md](CHANGELOG.md) — add one entry per PR.

> Already possible WITHOUT migrations (done): quote posts
> (`posts.referenced_post_id` existed, now wired in `PostController@store`,
> counted via `Post::quotes()` + `withCount`, shown in the feed).
> Everything below needs a new table/column.

## 3. Follows + "Following" feed

- [ ] Migration: `create_follows_table`
  - `follower_id` + `followed_id` (FKs → `users`, cascade), unique composite, timestamps
- [ ] Relations: `User::following()`, `User::followers()` (belongsToMany self)
- [ ] Follow/unfollow button on profiles + on hover cards
- [ ] Feed tabs: "For you" (all, current) vs "Following" (`whereIn(author_id, following)`)
- [ ] Follower/following counts + lists on profiles (`withCount`)

## 4. User handles

- [ ] Migration: `add_handle_to_users_table` — `handle` (string, unique, nullable for backfill)
- [ ] Backfill from slugs of `name`; validate `^[a-z0-9_]{3,20}$`, reserve on register
- [ ] Replace the derived `@slug(name)` in `posts/content.blade.php` and profile URLs
      (`/profile/{username}` → handle) with the real column
- [ ] Needed for reliable mentions below

## 5. Mentions + notifications

- [ ] Parse `@handle` on post create (needs §4); store or compute on read
- [ ] Migration: `create_notifications_table` — or use Laravel's built-in
      `notifications` table (`php artisan notifications:table`) + `Notifiable`
- [ ] Notify on: mention, quote of your post, like milestone, new follower, post liked
- [ ] Bell icon with unread count in navbar + notifications page; mark-read on view

## 6. Bookmarks

- [ ] Migration: `create_bookmarks_table`
  - `user_id` + `post_id` (FKs, cascade), unique composite, timestamps
- [ ] Bookmark icon on posts + "Bookmarks" page (private per user)

## 7. Hashtags + trending

- [ ] Parse `#tag` on post create; migration: `create_hashtags_table` (`tag`, unique)
      + `hashtag_post` pivot
- [ ] `GET /hashtag/{tag}` timeline; "Trending" sidebar (count last 24h/7d)

## 9. Moderation / safety

- [ ] Migration: `create_reports_table`
  - `reporter_id` (FK → `users`), `post_id` (nullable FK → `posts`),
    `reason`, `status` (pending/reviewed/dismissed), timestamps
- [ ] Report button (··· menu per post) + admin review queue (`admin.*` routes exist)
- [ ] Optional later: mute/block — `create_mutes_table` (`user_id`, `muted_id`, unique),
      filter muted authors out of the feed query
- [ ] Post edit history? (currently no edit/delete at all — needs `update`/`destroy`
      on `PostController` + policies first, no schema change for basic edit)

## Suggested order

> Done and removed: likes (§1), replies + single-post view (§2),
> post view counts (§8). Section numbers below are kept stable.

1. Handles (§4) → mentions/notifications (§5)
2. Follows + Following tab (§3)
3. Bookmarks (§6), hashtags (§7), moderation (§9)
