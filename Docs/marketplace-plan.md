# Marketplace — Implementation Plan

> Status: **planning** (not started). Tables are prefixed `marketplace_` so
> they sort together and are easy to spot/manage by hand.
> Shipped work moves to [CHANGELOG.md](CHANGELOG.md).

## 1. Goals

An **eBay-style classifieds board** where members list items for sale or
trade — both **comics hosted on this site** and **anything else** (figures,
prints, merch, original art, services, etc.).

- **No currency exchange.** No payments, no checkout, no escrow, no
  commission. A price is shown as a **reference/asking price**, nothing more.
- **Contact only for now.** The whole transaction happens off-platform:
  interested buyers contact the seller (in-app inquiry + optional contact
  info the seller chooses to expose). We never move money.
- Browse/search/filter, watch lists, seller profiles, reports and a full
  admin moderation surface.

### Non-goals (for now)

- Payments / cart / checkout / shipping labels (out of scope, maybe never).
- Auctions and bidding (that is a currency feature — classic eBay). Possible
  later as a clearly separate module.
- Automatic currency conversion / conversion rates.
- Order disputes/refunds (there are no orders).

## 2. Conventions & integration points

| Concern | Where it plugs in |
| --- | --- |
| Interactions | `App\Support\ActivityLogger::log()` + new keys in `ActivityLog::actions()` |
| Exp / badges | `config/gamification.php`; `Gamification::contentTarget()` for backfill |
| Notifications | `MarketplaceNotification` (database channel), fed by `NotificationComposer` |
| Admin layout | `admin-layout.blade.php` nav + `admin-*` Tailwind components |
| Settings | `marketplace_settings` overlay table (same pattern as `GamificationSetting`) |
| Communications | Reuse a lightweight conversation model (see §4) |
| HTTP | Controllers + **FormRequests** + **Policies** |

Reuse patterns already in the codebase: `Comic` model + `comics` table for
the "sell a hosted comic" path, the existing upload rules (harden per
`issues.md` #6), soft deletes, and named routes with `route()`.

## 3. Item model — comics **and** everything else

A listing has a **`kind`** so the same UI covers both:

- `comic` — points at one or more rows in `comics` (the seller's uploads).
  The listing shows the cover, page count and tags; the comic page shows a
  **"À venda"** badge linking back to the listing.
- `physical` — a real-world item (figure, print, shirt, book…). Uses its
  own images + fields (condition, dimensions, quantity).
- `digital` — a downloadable asset the seller delivers manually (art pack,
  PSD, etc.). Still contact-only; no automated delivery.
- `service` — commissions/edits/etc. (price reference, contact to arrange).
- `other` — catch-all.

Every listing can also carry a **free-form title/description** regardless
of kind, so `comic` listings can bundle extras ("comic + signed print").

## 4. Database schema (`marketplace_*`)

### `marketplace_categories`

Self-referential tree (sections → categories), like the forum plan.

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `parent_id` | nullable FK → self |
| `name`, `slug` | `slug` unique |
| `icon`, `color` | nullable presentation hints |
| `sort_order` | int, default 0 |
| `is_visible` | bool, default 1 |
| `listing_count` | cached counter |
| timestamps | |

Index `(parent_id, sort_order)`.

### `marketplace_listings`

| Column | Notes |
| --- | --- |
| `id` | bigint PK |
| `user_id` | FK → `users.id`, cascade (seller) |
| `marketplace_category_id` | FK → `marketplace_categories.id`, `restrictOnDelete` |
| `kind` | enum `comic,physical,digital,service,other` |
| `title` | string |
| `slug` | unique |
| `description` | mediumText (sanitized) |
| `condition` | enum `new,like_new,good,fair,poor,na` (na for services/digital) |
| `listing_type` | enum `sale,trade,giveaway,wanted` |
| `price` | decimal(10,2) nullable — **reference/asking only** |
| `currency` | char(3), display only (default from `marketplace_settings`) |
| `price_negotiable` | bool |
| `quantity` | int, default 1 |
| `location_city`, `location_region`, `location_country` | nullable strings (coarse only; never precise address) |
| `shipping` | enum `pickup,ships,digital,arrange`, default `arrange` |
| `status` | enum `draft,active,reserved,sold,expired,hidden`, default `draft` |
| `is_featured`, `is_approved` | bool (admin curation + moderation gate) |
| `view_count`, `watch_count`, `inquiry_count` | cached counters |
| `contact_mode` | enum `in_app,in_app_and_email,email_only` (seller's choice) |
| `contact_email` | nullable, only if seller opts in (encrypted or shown via relay) |
| `published_at`, `expires_at`, `bumped_at`, `sold_at` | lifecycle timestamps |
| timestamps, `deleted_at` | soft delete |

Indexes: `(status, published_at)`, `(marketplace_category_id, status)`,
`(user_id, status)`, `(kind)`, `slug` unique, `(price)`.

### `marketplace_listing_images`

- `id`, `marketplace_listing_id` (cascade), `path` (Storage, hashed name),
  `original_name`, `mime`, `size`, `width`, `height`, `position`, timestamps.
- Index `(marketplace_listing_id, position)`. First image = cover.

### `marketplace_listing_comics`

Bridges listings to hosted comics (`kind = comic`, or a bonus item).

- `id`, `marketplace_listing_id` (cascade), `comic_id` (FK → `comics.id`,
  cascade), `position`, timestamps, unique `(marketplace_listing_id, comic_id)`.
- Reverse: `Comic::listings()` / `Comic::activeListing()` powers the
  "À venda" badge on the comic page (single active listing shown).

### `marketplace_tags` + `marketplace_listing_tag`

- `marketplace_tags`: `id`, `name`, `slug` unique, `use_count`.
- `marketplace_listing_tag`: `marketplace_listing_id`, `marketplace_tag_id`
  (cascade), unique composite, timestamps.

### `marketplace_watches`

Buyer watchlist ("favoritos").

- `id`, `user_id` (cascade), `marketplace_listing_id` (cascade), timestamps,
  unique `(user_id, marketplace_listing_id)`, index `(user_id)`.

### `marketplace_conversations` + `marketplace_messages`

Contact-only messaging between buyer and seller, one thread per
(listing, buyer). No attachments in v1 (keep it simple + safe).

- `marketplace_conversations`: `id`, `marketplace_listing_id` (cascade),
  `buyer_id` FK → users (cascade), `seller_id` FK → users (cascade),
  `subject` nullable, `last_message_at`, `buyer_unread_count`,
  `seller_unread_count`, `status` enum `open,archived,blocked` default
  `open`, timestamps, unique `(marketplace_listing_id, buyer_id)`.
- `marketplace_messages`: `id`, `marketplace_conversation_id` (cascade),
  `sender_id` FK → users (cascade), `body` text, `read_at` nullable,
  timestamps, index `(marketplace_conversation_id, created_at)`.

Guest inquiries (no account): handled by `contact_mode = email_only`
relay form → notifies seller by email; optionally stored as a
`marketplace_messages` row with a null sender + stored contact text.
Decision in §12.

### `marketplace_reports`

- `id`, `reporter_id` FK → users (cascade),
  `marketplace_listing_id` nullable FK (cascade),
  `marketplace_message_id` nullable FK (cascade),
  `reason` enum `prohibited,spam,scam,stolen,counterfeit,other`,
  `details` nullable, `status` enum `pending,reviewed,dismissed`,
  `reviewed_by` nullable FK → users, `reviewed_at`, timestamps.
- Index `(status, created_at)`.

### `marketplace_settings`

Overlay table (`key` string PK, `value`, timestamps), same boot pattern.
Keys: `enabled`, `require_approval` (bool), `default_currency`,
`default_expiry_days`, `max_images`, `max_active_listings_per_user`,
`allowed_kinds`, `prohibited_words`, `contact_modes`, `bump_cooldown_hours`,
`show_prices` (bool), `commission_note` (static text = "contact only,
no payments").

### Explicitly **not** created (yet)

`marketplace_offers` (price negotiation), `marketplace_orders`,
`marketplace_transactions`, `marketplace_auctions`. These are currency
features; documented here as future/`never` to avoid scope creep.

## 5. Models & relations

Flat models to match the existing layout:

- `MarketplaceCategory`: `parent()`, `children()`, `listings()`,
  `scopeVisible()`.
- `MarketplaceListing`: `seller()`, `category()`, `images()`, `comics()`,
  `tags()`, `watchers()`, `conversations()`, `scopeVisible()`,
  `scopeActive()`, `scopeExpiringSoon()`, helpers `isActive()`,
  `isSold()`, `askPrice()`, `coverImage()`, `contactLabel()`.
- `MarketplaceListingImage`, `MarketplaceTag`, `MarketplaceWatch`,
  `MarketplaceConversation`, `MarketplaceMessage`, `MarketplaceReport`,
  `MarketplaceSetting`.

`User` gets: `marketplaceListings()`, `marketplaceWatches()`,
`marketplaceConversations()`, and `activeListingsCount()`.
`Comic` gets: `marketplaceListings()`, `activeMarketplaceListing()`.

## 6. Routes

Public:

```
GET  /marketplace                       marketplace.index        featured + latest + categories
GET  /marketplace/browse                marketplace.browse       filters + sort + pagination
GET  /marketplace/category/{slug}       marketplace.category
GET  /marketplace/{listing:slug}        marketplace.show
GET  /marketplace/search                marketplace.search
```

Authenticated:

```
GET  /marketplace/sell                  marketplace.create       new listing form
POST /marketplace                       marketplace.store
GET  /marketplace/{listing:slug}/edit   marketplace.edit
PUT  /marketplace/{listing:slug}        marketplace.update
DEL  /marketplace/{listing:slug}        marketplace.destroy
POST /marketplace/{listing}/status      marketplace.status       reserve/sold/renew/hide
POST /marketplace/{listing}/watch       marketplace.watch
POST /marketplace/{listing}/report      marketplace.report
GET  /marketplace/mine                  marketplace.mine         my listings + drafts
GET  /marketplace/watching              marketplace.watching
POST /marketplace/{listing}/contact     marketplace.contact      start conversation / relay email
GET  /marketplace/messages              marketplace.messages     inbox
GET  /marketplace/messages/{conversation}  marketplace.messages.show
POST /marketplace/messages/{conversation}  marketplace.messages.store
```

Admin (`/admin/marketplace`, `AdminMiddleware`):

```
GET  /admin/marketplace                          admin.marketplace            dashboard + stats
GET  /admin/marketplace/categories               admin.marketplace.categories
... CRUD/ordering/toggle (mirror forum/tag-categories admin) ...
GET  /admin/marketplace/listings                 admin.marketplace.listings
POST /admin/marketplace/listings/{listing}/approve|feature|hide|remove
GET  /admin/marketplace/moderation               admin.marketplace.moderation  reports queue
POST /admin/marketplace/moderation/{report}/resolve
POST /admin/marketplace/settings                 admin.marketplace.settings
```

## 7. Public features

- **Home**: featured listings, latest, category tiles, "closing soon",
  `wanted` posts (people looking to buy/trade).
- **Browse**: filter by category, kind, condition, listing type
  (sale/trade/giveaway/wanted), price range, location, shipping mode,
  has-comic; sort by newest / price / most watched / ending soon; paginate.
- **Listing page**: image gallery, linked comic cover + metadata (for
  `comic` kind), description, condition, reference price (or "Grátis" /
  "Troca" / "A combinar"), seller card (avatar, level/badge, member since,
  active listing count), **"Falar com o vendedor"** button, watch, share,
  report.
- **Sell flow**: multi-step form (category → kind/item → photos → price/
  contact → preview → publish), drafts, edit, mark reserved/sold, bump,
  renew, expiry.
- **Seller profile tab**: active + sold listings, ratings later (not v1).
- **Comic integration**: "À venda" badge + link on the comic reader/show
  page when an active listing references it; the listing links back.
- **Watchlist** and **Messages** inbox with unread counts.
- **Safety**: prominent "contact only, never send money up front, meet in
  public" notices; report button everywhere.

## 8. Admin tooling

- **Dashboard**: totals (active/draft/sold/expired/reported), 30-day
  created/sold chart (Chart.js already loaded), latest listings, queue counts.
- **Categories**: CRUD tree + drag order (Sortable already loaded),
  visibility/lock, counters.
- **Listings**: searchable DataTable, filter by status/category/kind/seller,
  bulk approve/feature/hide/remove, view as member, edit contact fields.
- **Moderation queue**: reports + pending-approval listings; resolve/
  dismiss; optional auto-hide at N reports; **prohibited-items** reference.
- **Settings**: `marketplace_settings` form (approval, expiry, image caps,
  active-listing cap, currency label, contact modes, prohibited words).
- **Bulk lifecycle**: run expiry command manually, cleanup orphaned images.

## 9. Lifecycle & scheduled jobs

Artisan commands (register in `app/Console/Commands/`):

- `marketplace:expire` — flip `active` past `expires_at` → `expired`,
  notify sellers, optionally send reminders first.
- `marketplace:notify-expiring` — warn sellers `N` days before expiry.
- `marketplace:cleanup` — purge orphaned images / soft-deleted listings
  past retention.

Scheduling in `bootstrap/app.php` (Laravel 11+/12 `withSchedule`).

## 10. Notifications & gamification

- `MarketplaceNotification` (database channel) for: new inquiry, new
  message, listing approved/rejected, listing expiring soon, listing
  watched milestone, report resolved.
- `ActivityLog::actions()` additions: `marketplace.listing.created`,
  `marketplace.listing.sold`, `marketplace.inquiry.sent`,
  `marketplace.reported`.
- `config/gamification.php` `exp`: listing created 15, sold 30,
  inquiry sent 2 (never farmable: unique-subject on listing created).
- Badges: `first_listing`, `trader` (10 sold), `collector_seller`
  (5 comic listings).
- Extend `Gamification::contentTarget()` for retroactive exp on existing
  listings (idempotent, additive).

## 11. Suggested order

1. Migrations + models + factories (`marketplace_categories`,
   `marketplace_listings`, `marketplace_listing_images`,
   `marketplace_listing_comics`).
2. Policies/FormRequests + index/browse/listing read views.
3. Sell flow (create/edit/status/photos) + comic linking + comic badge.
4. Watch, in-app conversations, notifications.
5. Reports + admin category CRUD + listing moderation.
6. Settings overlay + scheduled lifecycle commands.
7. Search, tags, seller profile, SEO.
8. (Optional/never) offers, auctions — separate decision.

## 12. Tests

Feature tests (isolated sqlite `:memory:`):

- Listing CRUD auth: guest cannot create; owner can edit/delete own; others
  cannot; admin can moderate.
- `kind` variants render correctly (comic-linked vs physical vs service).
- Comic page shows the "À venda" badge only for an active approved listing.
- Watch uniqueness; inquiry creates a conversation + notifies seller.
- Report queue + auto-hide; settings persistence; expiry command flips state.
- Sanitization: script/`onerror` stripped from description.
- Gamification exp awards + idempotent backfill.

## 13. Open questions

- **Guest contact**: allow a name/email relay form for logged-out buyers,
  or require an account to message? (Recommend: relay form, heavily
  rate-limited + spammed-checked, so buyers can reach a seller without an
  account.)
- **Price display**: show a reference price always, or hide it when the
  seller picks "A combinar"? (Recommend: allow both via `show_prices` +
  per-listing `price_negotiable`.)
- **Adult/age-restricted goods**: the site is adult — do we need an age
  gate and a restricted-category list before allowing `physical` listings?
- **Currency**: single site currency label, or per-listing free text?
  (No conversion either way — recommend a single label in settings.)
- **Location granularity**: country/region/city only, or add a coarse
  "distance" filter? (Recommend country+region at launch; never precise
  addresses.)
