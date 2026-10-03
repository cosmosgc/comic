# TODO — Comic Features

> Shipped changes move to [CHANGELOG.md](CHANGELOG.md) — add one entry per PR.

> Current state (checked 2026-10-02): `Collection` model + `collections` / `collection_comic`
> tables exist, but collections are **global** (no owner, no public/private, no favorites).
> No `Comment` or `Like` models exist yet.

## 1. Comments on comics

- [ ] Migration: `create_comments_table`
  - `id`, `user_id` (FK → `users`, cascade on delete)
  - `comic_id` (FK → `comics`, cascade on delete)
  - `body` (text), timestamps
- [ ] Model: `App\Models\Comment` (`$fillable = ['body']`, `user()` + `comic()` belongsTo)
- [ ] Relations: `Comic::comments()` (hasMany, newest first), `User::comments()` (hasMany)
- [ ] Controller: `CommentController` (`store`, `update`, `destroy`; auth required)
- [ ] Policies: only the author (or admin, `admin_level >= 1`) can edit/delete
- [ ] Validation: `body` required, string, max 2000
- [ ] UI: comment list + form on the comic page (paginated, e.g. 10 per page)
- [ ] Optional: comment count on comic cards, edit-inline, delete confirm

## 2. Likes on comics

- [ ] Migration: `create_comic_user_likes_table` (or `likes` polymorphic)
  - `user_id` + `comic_id` (both FK, cascade on delete), unique composite index
  - timestamps
- [ ] Relations: `Comic::likedByUsers()` (belongsToMany), `User::likedComics()` (belongsToMany),
      `Comic::likesCount()` (or `withCount('likedByUsers')`)
- [ ] Controller: `LikeController` (`toggle` — auth required, idempotent)
- [ ] Routes: `POST /comics/{comic}/like` (toggle), return JSON for AJAX
- [ ] UI: like button with live count on comic page + comic cards (no full reload)
- [ ] Rules: one like per user per comic (DB unique constraint), guests see count but must log in to like
- [ ] Optional: "most liked" sort/filter on the index page

## 3. User collections (incl. Favorites)

> Existing `collections` table has only `name`/`description` — no owner.
> This upgrades it to per-user collections with a built-in Favorites.

- [ ] Migration: `add_owner_to_collections_table`
  - `user_id` (FK → `users`, cascade on delete, nullable for legacy rows → backfill or drop)
  - `is_public` (boolean, default `true`), `is_favorites` (boolean, default `false`)
  - unique index on (`user_id`, `is_favorites`) — or enforce single-favorites in code
- [ ] Model: `Collection` — add `user_id`, `is_public`, `is_favorites` to `$fillable`,
      `user()` belongsTo, scope `public()`, scope `forUser($user)`
- [ ] Auto-create "Favorites" collection on user registration (`is_favorites = true`,
      `is_public = false`, name = "Favorites")
- [ ] Controller: `CollectionController` (CRUD: `index`, `store`, `show`, `update`, `destroy`;
      auth required for write actions)
- [ ] Controller: add/remove comics — `POST /collections/{collection}/comics/{comic}`
      and `DELETE /collections/{collection}/comics/{comic}` (verify collection belongs to user)
- [ ] Quick-add UI: "Add to collection" dropdown on comic page (checkbox list + "new collection"
      inline), "♥ Favorite" shortcut toggling the Favorites collection
- [ ] Policies: only the owner (or admin) can edit/delete a collection or change its comics;
      private collections visible to owner only
- [ ] Validation: `name` required, max 100; `description` nullable, max 1000
- [ ] Show user's public collections on their profile; comic page shows "in N collections"
- [ ] Optional: drag-to-reorder comics (uses existing `collection_comic.order` pivot column),
      public collection sharing page, collection cover (first comic's cover)

## 4. Shared / cross-cutting

- [ ] Add `like` / `comment` / `collection` counts to comic cards via `withCount` (avoid N+1)
- [ ] API or Livewire components consistent with the existing stack (`livewire/livewire ^3.5`)
- [ ] Feature tests: commenting, liking (incl. duplicate-like guard), collection CRUD + add/remove,
      private-collection visibility, favorites auto-creation
