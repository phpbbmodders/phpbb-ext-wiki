# Wiki search and forum search integration

Status: direction confirmed 10/12/2026, not started. Companion to the Knowledge Base's plan in
`phpbbmodders/knowledgebase` (`docs/TODO/todo-forum-search-integration.md`).

## Goal

Wiki articles can be found with a search, and a member who searches the board with phpBB's normal
search also sees matching wiki articles beside the forum results ("option 1": grouped results, not
one mixed list). Today the wiki has no search at all: its articles are undiscoverable except by
browsing the overview page or knowing the URL.

## Step 1: give the wiki a search by porting the Knowledge Base's

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

## Step 2: tie it into the board search (option 1)

- Same design as the Knowledge Base: a listener on the core search events (the same on 3.3.x and
  4.0) runs the wiki's own search with the keywords of the board search and shows a "Wiki (N)"
  block, with the top few matches and a "View all N" link, in the template event
  `search_results_header_after`; an "Also search the Wiki" checkbox, ticked by default, is added to
  Advanced search through `search_body_search_options_append`.
- Only keyword searches show it. Nothing is copied into phpBB's forum tables, no core file is
  changed, and a member who may not view any matching article sees no block and no count.
- If an extension point is missing, write the request up under `docs/events/` instead of patching core.

## Verify

Indexing lifecycle (create, edit, approve, delete, restore, rebuild), permissions in results and
counts, the block next to forum results, pagination of the post results untouched, the checkbox,
non-keyword searches, and the extension disabled; on phpBB 3.3.x and 4.0 with SQLite, MySQL and
PostgreSQL. Record what was and was not covered.

## Not in scope

One mixed, paginated list of posts and articles (would need a hidden "shadow" topic per article and a
copy of the wiki permissions into a forum; rejected for the risk of leaking restricted text),
Sphinx, and changing how phpBB indexes posts.
