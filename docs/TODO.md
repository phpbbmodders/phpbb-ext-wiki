# TODO

Ideas not yet built, practical and speculative alike.

## Article creation templates

Built: when someone creates a new article, a **Start from** choice on the edit page
offers every template whose name starts with `Start ` (for example
`Template:Start Extension`) and fills the text box with its text. The templates are
ordinary wiki pages, so they have versions, approval and permissions, and there is no
separate admin page. See the README.

Not built:

- A starter skeleton that comes with the extension (an encyclopedia-style article with
  `Overview`, `History` and `References` headings). It would fit the starter-templates
  repository below better than code in the extension.
- Starting from an existing article, or a template that asks for values first.

## Move/rename an article

The URL slug is fixed forever once an article is created — only the title
can change. There's no way to rename a page's URL while keeping its version
history attached; today that means manually recreating the article under a
new URL and losing the connection to its history. Needs a "move" action that
renames `article_url` across all of an article's versions.

Decided 10/13/2026:

- It gets its own permission, named like the existing ones (`u_wiki_move`),
  rather than reusing `u_wiki_set_redirect`.
- A move carries the whole article with it: every version, the uploaded files
  and the watchers.
- The move form has a "leave a redirect at the old address" box, ticked by default.
- Moving a `Template:` page that articles use shows a warning with the number of articles,
  and asks for a second confirmation before it goes ahead, because every one of them would
  need fixing by hand if no redirect is left.
- Links in other articles are not rewritten. The redirect left at the old address covers
  them, so it has to stay in place for those links to keep working.

## Categories

Built: `[[Category:Name]]` in an article puts it in a category, a category page
(`/wiki/Category:Name`) lists the articles in it, and `/wiki/categories` lists them all. See
the README.

Not built:

- A category added by a template (`<includeonly>[[Category:Stubs]]</includeonly>`) shows under
  the articles that use the template, but those articles are not in the category's member list,
  which is found from the articles' own text. Listing them needs a table of memberships that is
  worked out when a version goes live and again for every article that uses a template when the
  template changes.
- Sub-categories, `[[:Category:Name]]` (a link to a category without joining it), and sort keys
  (a sort key after a bar is accepted and ignored).
- The member list reads every live article that holds a category mark, so it is meant for wikis
  of moderate size; a membership table would also fix that.

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
to look like an actual wiki. A real wiki layout wants the table of contents, which now exists, as a sidebar
or floating box, so the contents box would move too.

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

Headings, `[[Article#Section]]` links, HTML tables, templates (`{{Name|parameter}}`) and the
parser functions (`#if`, `#switch`, `#expr`, `#time`, string functions and others) are built. The
starter-templates repository is next, in wiki articles only, using s9e's
allow-list so no unsafe HTML gets through. Full plan, including the syntax not to add:
[`docs/TODO/todo-wiki-markup.md`](TODO/todo-wiki-markup.md).

## Starter templates repository

A separate CC BY-SA 4.0 repository of templates adapted from Wikipedia's for what
this wiki can do, kept out of the GPL-2.0 extension, with an ACP button that
installs them as `Template:` pages. The template system it needs is built. To be named
`phpbbmodders/wiki-templates` under the phpbbmodders organisation (agreed 10/13/2026), and
created on 10/14/2026 once the go-ahead is given. Admins upload its zip in the ACP or copy the
files into a folder in `store/`. Plan, licence rules and decisions:
[`docs/TODO/todo-wiki-templates-repo.md`](TODO/todo-wiki-templates-repo.md).

## Readable version compare

The compare page shows raw BBCode with changes marked in red and green.
Comparing the rendered text would be easier for editors who don't read
BBCode comfortably.

## Restore an old version

A one-click "Restore this version" in the version history. Today the only
way is to approve the old version again.
