# TODO — Comic Features

> Shipped changes move to [CHANGELOG.md](CHANGELOG.md) — add one entry per PR.

> Done and removed: comments on comics (§1), likes on comics (§2),
> user collections incl. Favorites (§3) — policies enforced inline
> (author-or-admin), single-favorites enforced in code.
> Done from §4: collection counts on cards, most-liked sort, collection covers.
> Closed 2026-10-19: the public read endpoints moved to `App\Http\Resources` and
> Livewire was removed as unused (see `issues.md` #9 and #19).

## 4. Remaining polish

_Nothing open._

## User requests

- [x] Add a public calendar route for upcoming comics, with a list view of scheduled comics.
  - Use one explicit “far ahead” publication threshold for both the calendar/list and sidebar.
  - For comics beyond that threshold, blur or obscure the comic card and do not reveal its name.
  - Show the normal card and name when the comic is within the visible scheduling window.
- [x] add comics/calendario route link in "explorar" hamburger, in "Recentes Mais curtidos 🔒 Exclusivos" above the comic listing in the default page
- [ ] On the main page, add blur or obscure comics that guests cannot access and prevent guests who are not logged in from opening them; provide an appropriate prompt or link to log in.
- [ ] Make upcoming comics easy to discover by linking to the calendar/list from the default page, Novidades, and other relevant public entry points. (only if user cant have access to the upcoming comic, if so, keep the default link to comic view)
- [ ] Add an upcoming-comics list to the sidebar, applying the same far-ahead visibility rules as the calendar/list.
- [ ] Add admin filters in `admin/comics` for comics that need attention:
  - Comics without tags.
  - Potential duplicate comics.
  - User-reported comics, when report data is available.
