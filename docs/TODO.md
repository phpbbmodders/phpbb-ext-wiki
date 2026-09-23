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

## Table of contents

The `article_toc` column has existed in the schema since early on, but
`wiki/edit.php` always inserts an empty string into it and nothing anywhere
reads it — it's scaffolded and abandoned. Finish it: auto-generate a TOC
from the article's heading structure and render it at the top of the
article (likely only worth showing past some minimum heading count, so
short articles don't get a token one-entry TOC).

## Internal wiki links

A lightweight `[[Article Title]]`-style shortcut (MediaWiki's signature
syntax) instead of requiring a full `[url=...]` BBCode every time you want
to link one article to another. Would need a custom BBCode or a message-parse
step that resolves `[[...]]` to the right article URL, including handling
the "target article doesn't exist yet" case (MediaWiki renders those as a
distinct "red link" style).

## Move/rename an article

The URL slug is fixed forever once an article is created — only the title
can change. There's no way to rename a page's URL while keeping its version
history attached; today that means manually recreating the article under a
new URL and losing the connection to its history. Needs a "move" action
(permission-gated, presumably `u_wiki_set_redirect`-adjacent or its own new
permission) that renames `article_url` across all of an article's versions
and optionally leaves a redirect stub at the old URL.

## Watch/subscribe to an article

The existing notification type only fires for "pending approval" (aimed at
moderators). Regular readers have no way to ask "notify me when this
specific article changes" the way forum topic-watching works. Would need a
second notification type plus a subscribe/unsubscribe action on the article
view page.

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

Two ways to close this, not mutually exclusive:

- Hook wiki content into phpBB's existing search backends, so it shows up
  in normal board search alongside posts.
- A dedicated wiki-scoped search page/box, simpler to build but a second,
  separate search UI for members to learn.
