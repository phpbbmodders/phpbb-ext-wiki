# TODO

Ideas not yet built, practical and speculative alike.

## Article creation templates

Right now a new article always starts blank. Offer a starting-point picker
when creating an article:

- **Blank** — today's behavior, an empty edit form.
- **Generic (Wikipedia-style)** — pre-fill the message body with a common
  encyclopedia-article skeleton (e.g. an intro paragraph placeholder,
  `Overview`, `History`, `References` section headings) so new articles
  don't start from a truly empty box.

The template picker lives on the edit page itself (not the overview page's
quick-create box, which still just takes you straight to a blank edit form
and lets you pick there).

Templates are admin-configurable: admins can add new templates beyond the
built-in "Blank" and "Generic" ones, via an ACP page.

Still needs deciding before implementation:

- What a template is allowed to contain (plain text with placeholders?
  BBCode? anything the message parser accepts?) and how that's authored in
  the ACP form.
- What sanitization that admin-authored template content needs before it's
  inserted into the edit form's message body — it's admin-authored rather
  than end-user input, but still ends up in content regular members see and
  can build on, so it shouldn't get a free pass on the same checks normal
  article content goes through.

## Move/rename an article

The URL slug is fixed forever once an article is created — only the title
can change. There's no way to rename a page's URL while keeping its version
history attached; today that means manually recreating the article under a
new URL and losing the connection to its history. Needs a "move" action
(permission-gated, presumably `u_wiki_set_redirect`-adjacent or its own new
permission) that renames `article_url` across all of an article's versions
and optionally leaves a redirect stub at the old URL.

## Categories/tags

Browsing is flat today — Popular/Latest/All/Sticky/Pending on the overview
page, nothing that groups articles by topic. Would need a taxonomy (even a
simple one-tag-per-article model to start) and a browse-by-category view.

## Wiki search

Confirmed: wiki content is **not** in phpBB's board search at all. Checked
both ways — no search-related code anywhere in the extension, and live on
the test board, searching for a word that only exists in a live, approved
article's text ("trivial", from "Getting Started") returns zero matches.
`wiki_article` is a non-standard table outside phpBB's normal post/topic
search index, so this isn't surprising, but it does mean articles are
currently undiscoverable except by browsing the overview page or already
knowing the URL.

Chosen direction (confirmed 10/12/2026): first port the Knowledge Base's search engine to the
wiki, then show wiki matches in a block above the results of phpBB's normal board search
(grouped results, not one mixed list), with an "Also search the Wiki" checkbox on Advanced
search. Searches respect the wiki's viewing permissions, use only the current approved article text
and need no core change. The full plan, scope and verification list are in
[`TODO/todo-forum-search-integration.md`](TODO/todo-forum-search-integration.md).

## A wiki-style UI, not just reskinned forum templates

Every wiki page right now is built from phpBB's forum-listing template
patterns — `forumbg`, `topiclist`, the same row/list markup a subforum
index uses (see `overview.html`, `article_versions.html`). It reads as "a
forum forced to hold wiki data," not as a wiki. Offer a genuinely
wiki-shaped presentation as an alternative: article-first layout (content
front and center instead of nested in `forumbg`/`topiclist` list rows),
sidebar-style navigation instead of listing everything as forum rows, more
MediaWiki-like article chrome in general.

A per-board style choice, set from an ACP page for this extension (admin
picks "Forum-style" or "Wiki-style" for how the extension's own templates
render) rather than ripping out the current look — some boards will prefer
wiki content to visually match the rest of the forum, others will want it
to look like an actual wiki. Scope depends heavily on the table of contents
item above landing first, since a real wiki layout wants one.

## Per-article edit protection

Not built yet. Permissions are board-wide today: `u_wiki_edit` lets a user
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

## Edit-conflict detection and page locking

Nothing currently warns two people editing the same article at once —
they'd just end up with two competing pending versions and no idea the
other happened until they check the pending-approval queue. Even a simple
"someone else started editing this article at HH:MM" notice on the edit
form would help; phpBB's own posting flow has similar double-submission
awareness to look at for the pattern.

A stronger option is page locking: opening the edit form locks the
article for that user for a limited time (for example 30 minutes, renewed
while they keep editing). Others see who holds the lock and until when,
and can't save until it's released or expires. Moderators can break a
lock. Expiry matters, since people often close the browser without
saving. This is separate from per-article edit protection above, which
limits *who* may edit, not *when*.

## Wiki markup: headings, section links, tables and templates

Add `== Section ==` headings, `[[Article#Section]]` links, HTML tables and
reusable templates and infoboxes, in wiki articles only, using s9e's
allow-list so no unsafe HTML gets through. Full plan, including the syntax not to add:
[`docs/TODO/todo-wiki-markup.md`](TODO/todo-wiki-markup.md).

## Readable version compare

The compare page shows raw BBCode with changes marked in red and green.
Comparing the rendered text would be easier for editors who don't read
BBCode comfortably.

## Restore an old version

A one-click "Restore this version" in the version history. Today the only
way is to approve the old version again.
