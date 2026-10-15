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
  `Overview`, `History` and `References` headings). It would fit the starter templates
  repository, [`phpbbmodders/wiki-templates`](https://github.com/phpbbmodders/wiki-templates),
  better than code in the extension.
- Starting from an existing article, or a template that asks for values first.


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

## Wiki markup: headings, section links, tables and templates

Headings, `[[Article#Section]]` links, HTML tables, templates (`{{Name|parameter}}`) and the
parser functions (`#if`, `#switch`, `#expr`, `#time`, string functions and others) are built, in
wiki articles only, using s9e's allow-list so no unsafe HTML gets through. Full plan, including the syntax not to add:
[`docs/TODO/todo-wiki-markup.md`](TODO/todo-wiki-markup.md).
