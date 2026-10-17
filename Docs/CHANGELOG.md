# Changelog — Comic

> Shipped website changes, one entry per PR. New features are tracked in
> [TODO.md](TODO.md) and [TODO-database.md](TODO-database.md) until they merge —
> once merged, they move here. Full history (pre-changelog) lives in `git log`.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) categories
(`Added`, `Changed`, `Fixed`, `Removed`) + the PR link at the end of each line.

## How to add an entry (PR authors)

1. Under `## [Unreleased]` below, add one bullet per user-visible change.
2. Pick the right category. Internal refactors with no visible effect go under
   `Changed` only if they matter to deploys; otherwise skip them.
3. End the line with the PR link: `([#NNN](https://github.com/cosmosgc/comic/pull/NNN))`.
4. On release, rename `## [Unreleased]` to `## [YYYY-MM-DD]` (or a version tag)
   and start a fresh empty `## [Unreleased]` on top.

Example:

```markdown
## [Unreleased]

### Fixed
- Comic upload no longer fails with nginx 413 on large comics ([#NNN](https://github.com/cosmosgc/comic/pull/NNN))
```

## [Unreleased]

### Added
- **Fórum** (§forum-plan): seções/quadros, tópicos e respostas com Markdown, citações, reações, inscrições, anexos, tópicos não lidos, busca, tags e respostas aceitas (perguntas)
- Fórum — UI afastada: layout próprio (`forum-layout`) com navbar violeta distinta, banner de "modo fórum" e rota `forum.exit` para voltar ao site normal; scripts compartilhados (hover cards, toggle de seguir, toast de XP) extraídos para `layouts.partials.interactions`
- Fórum — moderação: denúncias, aprovação de posts, trancar/fixar/mover/aprovar, moderadores por quadro, banimentos (temporários/permanentes) e linha do tempo de auditoria
- Fórum — admin: painel com gráfico de atividade de 30 dias, CRUD da árvore de categorias e painel de configurações (`forum_settings`) sobreposto ao `config/forum.php`; `ForumSeeder` cria a estrutura padrão (idempotente, rode com `db:seed --class=ForumSeeder`)
- Fórum — gamificação/notificações: XP por tópico/resposta/reação/resposta aceita, conquistas (`first_thread`, `conversationalist`, `helpful`, `moderator`) e `ForumNotification` no sino existente
- Novas migrations (prefixo `forum_`): `forum_categories`, `forum_threads`, `forum_posts`, `forum_post_reactions`, `forum_tags` + `forum_tag_thread`, `forum_attachments`, `forum_subscriptions`, `forum_thread_reads`, `forum_reports`, `forum_bans`, `forum_moderators`, `forum_moderation_log`, `forum_settings`
- Follows (§3): follow/unfollow button on profiles, hover cards and user lists; "Seguindo" feed tab; follower/following counts and paginated lists
- User handles (§4): unique `@handle` reserved on register, backfilled for existing accounts, used for profile URLs and reliable mentions
- Mentions + notifications (§5): `@handle` parsing notifies on mention, reply, quote, post like and new follower — delivered through the existing navbar bell and notifications feed
- Bookmarks (§6): per-post save button and a private "Salvos" page (own bookmarks only)
- Hashtags + trending (§7): `#tag` parsing on create/edit, a `/hashtag/{tag}` timeline, and an "Em alta" trending widget in the sidebar
- Moderation (§9): report a post from its action bar; new admin "Denúncias" review queue with review/dismiss actions
- New migrations: `follows`, `handle` on `users`, `bookmarks`, `hashtags` + `hashtag_post`, `reports`
- Admin Migrations panel: migration files vs. database comparison with one-click `migrate --force` (no console needed)
- Admin Deploy panel + `deploy:host` artisan command: incremental FTPS code sync with project verification gate, dry-run, cancel, vendor opt-in, and quick/full modes
- Upload page: live per-image sizes, totals, and remove-before-send list
- Sequential single-file comic uploads with in-browser image optimization (survives small nginx `client_max_body_size` caps)
- FTP Deploy skips user content (`public/storage/`) and never deletes remotely

### Fixed
- `config/database.php` no longer fatals with `Class "Pdo\Mysql" not found` on PHP < 8.4 (SSL CA key resolved per version)
- `PageController@store` redirected to non-existent `comics.show` route (now `comics.showById`) — single-page adds 500'd after saving
- Migration table listing scoped to the project database (previously listed every DB on shared MySQL servers) and tolerant of Laravel 11 vs 12 schema shapes
- PHP upload limits raised via `public/.user.ini`; per-file/total caps enforced in validation and pre-checked in the browser
