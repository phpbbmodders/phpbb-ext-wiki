# Archive of completed TODOs

## Wiki search and forum search integration (completed 10/13/2026)

Status: both steps built. Step 1 (the wiki's own search) was merged on 10/13/2026; step 2 (the board search block and option) followed the same day, with settings in the ACP to switch it off and to set how many articles the block lists. Companion to the Knowledge Base's plan in
`phpbbmodders/knowledgebase` (`docs/TODO/todo-forum-search-integration.md`).

### Goal

Wiki articles can be found with a search, and a member who searches the board with phpBB's normal
search also sees matching wiki articles beside the forum results ("option 1": grouped results, not
one mixed list). Today the wiki has no search at all: its articles are undiscoverable except by
browsing the overview page or knowing the URL.

### Step 1 (built): give the wiki a search by porting the Knowledge Base's

The Knowledge Base already has a complete search: its own copies of phpBB's three search engines
(native, MySQL fulltext, PostgreSQL fulltext) behind a small interface and a factory, its own index
tables, an ACP search page (choose the engine, build, delete and rebuild the index), and a results
page with highlighting. Port that to the wiki, adapted to wiki articles:

- Index the article title and the current approved text only; leave out pending edits, inactive
  versions and old revisions.
- Index tables, settings and migrations of its own (named for the wiki), and an ACP page to choose
  the engine and to build or rebuild the index for existing articles.
- Keep the index current when an article is approved, edited, deleted or restored.
- A wiki results page: results respect the wiki's viewing permissions, with a snippet and the
  matched words highlighted, plus pagination and the usual sort options.
- Decide whether to copy the engine or to move it into one shared package used by both extensions,
  so a fix lands in both (the engine is about 4,600 lines). Both extensions now require PHP 8.2 or
  newer (the wiki since 10/12/2026), so the Knowledge Base code can be ported as it is, with no
  rewrite for older PHP syntax.

### Step 2: tie it into the board search (option 1)

- Same design as the Knowledge Base: a listener on the core search events (the same on 3.3.x and
  4.0) runs the wiki's own search with the keywords of the board search and shows a "Wiki (N)"
  block, with the top few matches and a "View all N" link, in the template event
  `search_results_header_after`; an "Also search the Wiki" checkbox, ticked by default, is added to
  Advanced search through `search_body_search_options_append`.
- Only keyword searches show it. Nothing is copied into phpBB's forum tables, no core file is
  changed, and a member who may not view any matching article sees no block and no count.
- If an extension point is missing, write the request up under `docs/events/` instead of patching core.

### Verify

Indexing lifecycle (create, edit, approve, delete, restore, rebuild), permissions in results and
counts, the block next to forum results, pagination of the post results untouched, the checkbox,
non-keyword searches, and the extension disabled; on phpBB 3.3.x and 4.0 with SQLite, MySQL and
PostgreSQL. Record what was and was not covered.

### Not in scope

One mixed, paginated list of posts and articles (would need a hidden "shadow" topic per article and a
copy of the wiki permissions into a forum; rejected for the risk of leaking restricted text),
Sphinx, and changing how phpBB indexes posts.

### As built and verified (10/13/2026)

- The block and the "Also search the Wiki" option follow the plan. The option is a ticked box with a hidden marker field, so a search that does not come from the advanced form (the header search box) includes the wiki by default, and an unticked box stays unticked through the result pages. The block shows on the first page of results only, and not for searches by author, within one topic, or the pre-made ones. Nothing was needed from phpBB's core; no `docs/events/` request was written.
- ACP: the wiki's Search page has "Show wiki matches in the board search" (on or off) and "Articles in that block" (1 to 50, default 5), beside the results-per-page setting for the wiki's own search page. All three backends are listed; the ones the database cannot run are shown but cannot be chosen.
- A bug in the port was found and fixed: on MySQL the native backend reported a total of 1 for titles-only and text-only searches, because the Knowledge Base's copy of the count query always counted one row. The wiki now counts as phpBB core does.
- Tested with the browser test (27 checks for the board search, 22 or 23 for the wiki's own search and ACP) on phpBB 3.3.19 with SQLite, MySQL and PostgreSQL, and phpBB 4.0.0-a3-dev with SQLite, MySQL and PostgreSQL, each with the native backend, and MySQL and PostgreSQL also with their fulltext backend. Unit tests: 256 tests, 563 assertions. The code sniffer is clean.
- Not covered locally: the Windows and MSSQL combinations that run in CI, and styles other than prosilver.

## Starter templates repository (completed 10/14/2026)

Status: built. The repository holds 29 templates: hatnotes, message boxes, stub boxes, inline helpers, Blockquote, Template link, Infobox, Cite web and Navbox. The ACP **Starter templates** page (#69) installs them. Center, the infobox float and Navbox needed small additions to the wiki's markup (`<center>` #71, `class="infobox"` #72, `<details>` #73), all merged on 10/14/2026.

A separate repository of ready-made wiki templates, adapted from Wikipedia's
for what this wiki can do, so a board can install a useful set instead of
writing every template from nothing. It depends on the template system in
[Wiki markup](../todo-wiki-markup.md), which is built.

### Why a separate repository

Wikipedia's templates are wiki text, licensed CC BY-SA 4.0 (and GFDL unless a
page says otherwise). This extension is GPL-2.0. As far as we know CC BY-SA 4.0
is one-way compatible with GPLv3 but not GPLv2, so the copies must not live in
the extension's repository. Keeping them in their own repository, under CC BY-SA
4.0, keeps the two licences apart; the extension only reads them at runtime.

### What Wikipedia asks of a reuser

Checked on Wikipedia's "Reusing Wikipedia content" page and the Wikimedia Terms
of Use (section 7); this is not legal advice, and both pages say reusers must work
out how the licences apply to them.

- **Licence.** Modified or added material must be CC BY-SA 4.0 or later.
- **Attribution.** Link to the page copied, or to a stable copy that credits the
  authors, or list the authors.
- **Changes.** Say that the copy was modified. A note in the page or file history
  is enough on a wiki.
- **Notice.** Every distributed copy states the licence and links to its text.
- Neither page says anything about templates, Lua modules or CSS. Templates are
  page text, so the rules above should apply to them. Lua modules are code and
  are out of scope (see below).

### Layout

Built on 10/14/2026 in [`phpbbmodders/wiki-templates`](https://github.com/phpbbmodders/wiki-templates), which holds 29 templates:

- The CC BY-SA 4.0 text as its `LICENSE` and a README that explains the rules above.
- One file per template, named after the Wikipedia template (`Main.wiki`,
  `Ambox.wiki`, ...), so pasted Wikipedia text finds the same names and parameters.
- A header at the top of each file, inside `<noinclude>`, with the source page
  URL and its history URL, "adapted by phpBB Modders", a list of what was removed
  or changed (for example "no Lua, no `{{#if}}`"), and the licence notice.
- A CI check that fails when a template is missing its source URL or licence
  notice (`tools/check_headers.py`, which also requires the history URL and the
  list of changes).

### Using it from the wiki

Built 10/14/2026: **ACP → Extensions → Wiki → Starter templates** (see the README).

- The admin gets the templates by uploading the repository's zip, pressing **Download latest**
  (the extension fetches GitHub's zip; the admin's button press, never automatic), or copying
  the files by FTP into `store/phpbbmodders_wiki/templates/`. The zip is read without being
  extracted, and only `templates/<name>.wiki` entries are taken.
- A file without its source and licence header, or still marked as not adapted, is listed
  and cannot be installed.
- Each installed template is saved as a new, live version of its `Template:` page with the
  admin as author, so the header shows the attribution on the page and the install can be
  compared and undone like any edit. A table keeps a checksum of each page as installed:
  a page still matching it is offered as an update (ticked); a page edited on the board since
  is offered unticked, with a warning.
- An optional check (off by default; every 1 to 365 days, as the admin chooses) compares the
  repository's file list on GitHub with the files the board has, and notifies admins once per
  new set of changes. It downloads and installs nothing.

### What goes in

Only templates that work as plain wiki text with parameters:

- hatnotes (`{{Main}}`, `{{See also}}`, `{{Further}}`, `{{About}}`),
- message boxes ("needs references", "out of date", "under construction", stub),
- small inline helpers (`{{nowrap}}`, `{{small}}`, `{{center}}`),
- simplified versions of infobox, citation and navbox templates, written to take
  the same parameter names as Wikipedia's.

### What stays out

- Lua modules (`{{#invoke:}}`), and templates that need them: Wikipedia's real
  infobox, cite web, convert and navbox.
- TemplateStyles (CSS shipped inside a template).
- Anything whose licence notice on the source page says it is not plain CC BY-SA.

### Decided

- The repository is named `phpbbmodders/wiki-templates` and lives under the `phpbbmodders`
  organisation (10/13/2026).
- It was created on 10/14/2026, after the go-ahead, now that the template system exists.
- The templates reach a board only by an admin's action: uploading the repository's zip,
  copying the files into `store/phpbbmodders_wiki/templates/` by FTP (10/13/2026), or the
  **Download latest** button (10/14/2026). Installing is always a separate button.
- Installed versions go live straight away with the installing admin as author; pages edited
  on the board are offered unticked (10/14/2026).
- The check for new templates is a cron task, off by default, with the days between checks
  set by the admin, and notifies admins; it never downloads or installs (10/14/2026).

## Edit-conflict detection and page locking (completed 10/15/2026)

Status: built. The ACP setting **When two people edit an article** chooses Nothing, Notice (the default after updating) or Lock, with **Minutes an editor holds an article** (30 by default, 5 to 240). The edit form records its editor in `wiki_edit_lock`, renewed by a ping every four minutes while it is open and by Preview. In lock mode the first editor to start keeps the article, a moderator (`u_wiki_set_active`) can break the lock from the blocked page, the moderators are notified once per lock, and a save refused because the lock changed hands keeps the editor's text. Tested on the seed-forum phpBB 3.3 and 4.0 boards.

### The plan


Before this, nothing warned two people editing the same article at once —
they'd just end up with two competing pending versions and no idea the
other happened until they check the pending-approval queue. Even a simple
"someone else started editing this article at HH:MM" notice on the edit
form would help; phpBB's own posting flow has similar double-submission
awareness to look at for the pattern.

Decided 10/13/2026: build both, and let the admin choose in the ACP between the notice
alone and the lock. When the lock option is on, moderators get a notification when a lock
blocks someone from editing (not each time a lock is placed), so they can break it if needed.

The lock works like this: opening the edit form locks the
article for that user for a limited time (for example 30 minutes, renewed
while they keep editing). Others see who holds the lock and until when,
and can't save until it's released or expires. Moderators can break a
lock. Expiry matters, since people often close the browser without
saving. This is separate from per-article edit protection (below), which
limits *who* may edit, not *when*.

## Per-article edit protection (completed 10/15/2026)

Status: built. **Protect article** in the moderator controls stores the chosen groups in `wiki_article_protect`. Protection covers saving, restoring, uploading and moving; a note under a protected article names the groups; the permission `u_wiki_protect` goes to moderators (`m_`) and its holders may always edit. Tested on the seed-forum phpBB 3.3 and 4.0 boards.

### The plan


Before this, permissions were board-wide: `u_wiki_edit` lets a user
edit *any* article or none of them, so a specific high-traffic or important
article can't yet be limited to a smaller group, the way MediaWiki protects
frequently vandalized pages.

Until then, the approval queue already keeps unwanted edits off the live
site: an edit by someone without `u_wiki_set_active` is saved as a pending
version and only goes live once a moderator approves it.

Plan: a **group-based** article-level setting naming which phpBB group(s)
can edit it, set by moderators from the article's moderator controls.
It's checked in addition to (not instead of) the existing `u_wiki_edit`
permission, and falls back to "any group with `u_wiki_edit`" when no
restriction is set on a given article, so this is opt-in per article rather
than a behavior change for existing ones.
