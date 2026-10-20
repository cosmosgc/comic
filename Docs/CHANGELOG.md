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
- **Importação — aviso de duplicatas**: ao buscar uma galeria, a pré-visualização procura o mesmo gid e títulos parecidos no banco; a galeria já importada é bloqueada e os títulos prováveis aparecem com percentual de semelhança e link para conferir antes de publicar
- **Login social e contatos**: entrar/cadastrar com Google, Discord, GitHub e X/Twitter (OAuth 2.0), conectar/desconectar provedores em **Editar perfil → Contas conectadas**, várias formas de contato salvas (WhatsApp, Telegram, Discord, e-mail e redes) com opção de ocultar do perfil público, e seleção de múltiplos contatos no passo **6 · Contato** do anúncio do Mercado
- **Mercado**: área de classificados separada do site, com comics à venda, itens físicos/digitais, serviços, fotos, tags, favoritos, chat de contato, denúncias e painel de anúncios — preço apenas de referência, sem pagamento ou intermediação. Moderação completa em **Admin → Mercado** (categorias, aprovação, destaques, denúncias e configurações)
- Comics **exclusivos para membros** (marca-se na edição do comic ou na lista do admin; visitante vê o card com cadeado e cai no login) e **pré-estreia de uma semana**: membros leem os agendados até 7 dias antes do lançamento
- Selos nos cards: **Novo** (últimos 7 dias), **Em breve** (agendado) e **Exclusivo**, mais a aba **Exclusivos** na home para membros; os admins passam a ver todo o agendamento nas listas do site
- Manutenção no painel (Admin → Migrações): botão **Limpar caches**, que roda `view:clear`, `config:clear`, `route:clear`, `cache:clear` e `event:clear` no servidor sem SSH — é o que destrava o erro 500 depois de um deploy que remove um pacote, quando as views compiladas do host continuam chamando as classes antigas (os manifests de pacotes/serviços são preservados)
- Gamificação: XP por interação (upload, post, resposta, comentário, curtida, coleção, busca, login) com aviso em tela, níveis por curva configurável, conquistas, sequência diária com bônus, notificações de nível/conquista, vitrine no perfil e página pública de **Ranking**
- Menu do site reorganizado: links agrupados em **Explorar** (Comics, Ranking, Novidades) e **Comunidade** (Comunidade, Fórum), botão **Publicar** sempre visível e um único ☰ no celular com grupos
- Entradas do changelog público (`changelogs/*.json`) para fórum, rede social (seguir/@usuário/salvos/hashtags), gamificação e o novo menu
- Atividades: novo registro "Iniciou leitura" — o primeiro acesso de um usuário logado a um comic aparece em Admin → Atividades (visitantes e comics agendados não geram registro; reaproveita o mesmo link "Ver" do comic)
- Admin de comics: data de lançamento editável direto na lista (campo `datetime-local`), badge "⏳ Em breve" enquanto o comic não foi publicado e atalho "Publicar agora" (`POST /admin/comics/{comic}/schedule`)
- **Fórum** (plano em `Docs/forum-plan.md`): área de discussão completa, separada do feed de comics e das rotas do site
- Fórum — estrutura: seções e quadros em dois níveis (criados pelo admin), tópicos do tipo discussão ou pergunta e respostas com citação, tudo renderizado em Markdown sanitizado (HTML bruto é escapado)
- Fórum — interação: reações idempotentes (👍😂😮😢), inscrição por tópico com aviso de novos posts, anexos de imagem, marcação de lidos/não lidos, busca (título, corpo, autor e tag), tags por tópico e respostas aceitas (solução) creditando XP a quem respondeu
- Fórum — UI afastada: layout próprio (`layouts.forum-layout`) com navbar violeta distinta e banner de "modo fórum"; a rota nomeada `forum.exit` (`GET /forum/exit`, declarada antes do catch-all `/{categorySlug}`) volta ao site normal a partir do banner, do navbar, do rodapé e do menu mobile; hover cards, toggle de seguir e toast de XP foram extraídos para `layouts.partials.interactions`, reutilizado pelos dois layouts
- Fórum — moderação: denúncias de tópicos e posts, fila de aprovação de posts, trancar/fixar/mover/aprovar, moderadores por quadro ou globais, banimentos temporários/permanentes (com aviso por notificação) e registro de auditoria em `forum_moderation_log`
- Fórum — admin (`/admin/forum`): painel com gráfico de atividade de 30 dias (Chart.js), CRUD da árvore de categorias com toggles de visível/trancado e painel de configurações (`forum_settings`) sobreposto ao `config/forum.php`; `ForumSeeder` cria a estrutura padrão (idempotente: `php artisan db:seed --class=ForumSeeder`)
- Fórum — gamificação e notificações: XP por tópico/resposta/reação/resposta aceita, conquistas `first_thread`, `conversationalist`, `helpful` e `moderator`, e `ForumNotification` entregue pelo sino/`notifications` já existente
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
- Posts: **excluir o próprio post** pelo menu de ações (autor ou admin, com confirmação) — as respostas/citações e as imagens do post somem junto; e **silenciar usuários** pelo perfil, escondendo os posts e as respostas da pessoa do feed, das hashtags e das listas de respostas, sem avisá-la
- Fórum — enquetes: tópico do tipo **Enquete** com pergunta, 2 a 10 opções, escolha única ou múltipla e data de encerramento opcional; voto com resultado em barras e troca de voto enquanto a enquete estiver aberta
- Fórum — **resumo semanal por e-mail** (opção no perfil, desligada por padrão) com os tópicos mais movimentados, e **reordenação por arrastar** de seções e quadros em Admin → Fórum → Categorias, com salvamento automático
- Fórum — **banner do tópico**: o autor envia uma imagem na criação ou depois pelo próprio tópico, exibida atrás do título com degradê horizontal (recurso público por enquanto; será premium)
- Fórum — **banner dos quadros**: configurado em Admin → Fórum → Categorias, aparece como herói grande na página do quadro e como miniatura na lista do fórum
- Admin → Fórum → Tópicos: ações de **editar** (título, quadro, tipo, banner e status) e **excluir** (lixeira reversível, com restauração pelo filtro "Removidos")
- Fórum — **enquadramento dos banners**: controles de horizontal/vertical com prévia ao vivo nos formulários de tópico e de quadro (admin), salvos como `object-position` no cabeçalho, no herói e nas miniaturas
- Importar galeria: ao concluir o download, a tela de progresso mostra **Enviar p/ remoto** (mesma transferência de Admin → Comics), oculto enquanto houver páginas falhadas
- Novas migrations: `edited_at` em `posts`, `mutes`, `forum_polls` + `forum_poll_options` + `forum_poll_votes` e `forum_digest` em `users`

### Changed
- Listas do site (home, busca e painel dos mais vistos) passam a ordenar pela **data de lançamento**, não pela data de upload — um comic agendado semanas antes aparece no topo no dia em que é liberado
- Navbar e painéis laterais reorganizados: menu completo só a partir de 1024px (abaixo disso vira o botão de hambúrguer, que antes aparecia só até 768px), busca adaptável e painéis laterais **encostados nas bordas da tela** — o layout virou full-width (o limite de 1280px deixava ~120px de margem em cada lado em telas largas), com os painéis fixos ao rolar e rolagem própria
- Descrição de comic importado: em vez do carimbo de origem, fica só o **link da galeria com o nome de quem publicou**; qualquer URL solta numa descrição agora também vira link clicável
- Lista de comics do admin: miniatura da capa ao lado do nome, com marcador “Sem capa” quando o comic ainda não tem imagem

### Fixed
- Fórum — anexos: as imagens de tópicos e respostas eram gravadas em `storage/app/public` e a URL dava 404 no host (sem symlink); agora usam `public/storage`, e a migration move os arquivos antigos
- Rolagem horizontal da página em telas médias: a navbar completa não cabia abaixo de ~1024px e os painéis de largura fixa ficavam presos dentro das colunas de 2/12 da grade
- Zoom da página no leitor: agora amplia até a largura da coluna (antes continuava limitado a 820px pelo `max-width` do container e estourava 100vw em telas pequenas)
- CSS morto/duplicado: `nav-link`, `admin-alert-info` e `admin-toggle-count` sem uso, componente `side-panel` nunca renderizado, estilos duplicados no leitor (`background-color` repetido, `100vw` na tela de login) e `px-4 py-6` repetido nas views que já recebem o container do layout
- `config/database.php` no longer fatals with `Class "Pdo\Mysql" not found` on PHP < 8.4 (SSL CA key resolved per version)
- `PageController@store` redirected to non-existent `comics.show` route (now `comics.showById`) — single-page adds 500'd after saving
- Migration table listing scoped to the project database (previously listed every DB on shared MySQL servers) and tolerant of Laravel 11 vs 12 schema shapes
- PHP upload limits raised via `public/.user.ini`; per-file/total caps enforced in validation and pre-checked in the browser
- Selo "editado" nos posts só aparece quando o texto muda de verdade — o contador de visualizações (`increment`) também atualizava `updated_at` e marcava posts como editados sem ninguém ter editado
- Acessibilidade: foco visível no teclado em links/botões/campos (site e admin), textos alternativos descritivos nas imagens de posts e avatares citados, e `type="button"`/`type="submit"` explícito nos botões
- Avatar padrão e imagem de compartilhamento agora vêm de `resources/images` pelo Vite (não somem em instalações novas); nomes de arquivo no upload são escapados antes de entrar na lista
