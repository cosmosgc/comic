# TODO (database) — New features that need schema changes

> Already possible WITHOUT migrations (done): quote posts
> (`posts.referenced_post_id` existed, now wired in `PostController@store`,
> counted via `Post::quotes()` + `withCount`, shown in the feed).
> Everything below needs a new table/column.

## 1. Post likes

- [ ] Migration: `create_post_likes_table`
  - `user_id` (FK → `users`, cascade), `post_id` (FK → `posts`, cascade)
  - unique composite (`user_id`, `post_id`), timestamps
- [ ] Relations: `Post::likedByUsers()` (belongsToMany), `User::likedPosts()` (belongsToMany)
- [ ] Add `withCount('likedByUsers')` to the feed query; wire the heart button
      in `posts/content.blade.php` (currently reply / repost / copy-text only)
- [ ] `POST /posts/{post}/like` toggle endpoint (auth, idempotent, returns JSON)
- [ ] Optional: "Liked" tab on profiles

## 2. Replies (threads)

- [ ] Migration: `add_parent_id_to_posts_table`
  - `parent_id` (nullable FK → `posts`, cascade) — distinct from `referenced_post_id` (quotes)
- [ ] `Post::replies()` (hasMany) + `Post::parent()` (belongsTo), `withCount('replies')`
- [ ] Show reply counts in the feed action bar; clicking opens the thread
- [ ] `GET /posts/{post}` single-post view with parent chain + reply list
      (the copy-text share button can then copy the permalink instead)
- [ ] Reply composer pre-fills `parent_id` (same hidden-field pattern as quotes)

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

## 8. Post view counts

- [ ] Migration: `add_view_count_to_posts_table` (unsigned big int, default 0)
- [ ] Increment once per viewer (like `Comic::view_count`); show eye icon + count
      in the action bar (currently omitted — no data)

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

1. Likes (§1) — smallest, completes the action bar
2. Replies + single-post view (§2) — unlocks threads + real share links
3. Handles (§4) → mentions/notifications (§5)
4. Follows + Following tab (§3)
5. Bookmarks (§6), hashtags (§7), view counts (§8), moderation (§9)
