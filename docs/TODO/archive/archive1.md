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
