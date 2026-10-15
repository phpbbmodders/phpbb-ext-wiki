# TODO

Ideas not yet built, practical and speculative alike.

## Article creation templates

Built: when someone creates a new article, a **Start from** choice on the edit page
offers every template whose name starts with `Start ` (for example
`Template:Start Extension`) and fills the text box with its text. The templates are
ordinary wiki pages, so they have versions, approval and permissions, and there is no
separate admin page. See the README. An encyclopedia-style skeleton, `Template:Start Article`
(Overview, History, See also, References), comes from the starter templates repository,
[`phpbbmodders/wiki-templates`](https://github.com/phpbbmodders/wiki-templates), and boards
install it from the ACP like the other starter templates.

Not built:

- Starting from an existing article, or a template that asks for values first.

## Categories

Built: `[[Category:Name]]` in an article puts it in a category, a category page
(`/wiki/Category:Name`) lists the articles in it, and `/wiki/categories` lists them all. See
the README.

Also built (10/15/2026): categories added by a template, sub-categories (paged on their own,
with how many articles each holds), `[[:Category:Name]]` links and sort keys, from a stored
membership table that a cron task fills after the update. Nothing more is planned here.

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

## Wiki markup: headings, section links, tables and templates

Headings, `[[Article#Section]]` links, HTML tables, templates (`{{Name|parameter}}`) and the
parser functions (`#if`, `#switch`, `#expr`, `#time`, string functions and others) are built, in
wiki articles only, using s9e's allow-list so no unsafe HTML gets through. Full plan, including the syntax not to add:
[`docs/TODO/todo-wiki-markup.md`](TODO/todo-wiki-markup.md).
