# Wiki update screenshots, 2026-10-12

Taken with Playwright on a throwaway phpBB 3.3.19 board (prosilver, MySQL 8.0), filled by `seed-wiki-fixtures.php` from seed-forum.

## Headings and contents

An article with headings, the contents box, and `[[Article#Section]]` and `[[#Section]]` links.

![Article with headings, a contents box and section links](01-headings-and-contents.png)

## File uploads

An uploaded diagram embedded with `[[File:name.png]]`.

![Article with an embedded uploaded image](02-file-uploads.png)

## Watch and discussion buttons

The article header: edit, history, watch (bell) and discussion.

![Article header with edit, history, watch and discussion buttons](03-watch-and-discussion-buttons.png)

## Recent changes

Every saved version, with RSS and Atom feed links.

![Recent changes page](04-recent-changes.png)

## Contributions

A member's saved versions.

![Contributions page for a member](05-contributions.png)

## Overview

The overview page with pending articles, as a moderator sees it.

![Wiki overview with pending articles](06-overview.png)

## ACP settings

ACP > Extensions > Wiki > Settings.

![Wiki settings in the ACP](07-acp-settings.png)

## Tables

A table written as HTML in an article, with a caption, a `colspan` header, a `rowspan` cell, BBCode and a link in the cells.

![An article with a table](08-tables.png)

## Templates

An article that uses two templates: a notice (`{{Out of date|reason=...}}`) and an infobox (`{{Extension infobox|name=...|version=...}}`) whose rows appear only when a value is given. The template pages are `Template:Out of date` and `Template:Extension infobox`, and each lists the articles that use it under **What links here**.

![An article with a notice and an infobox from templates](09-template-in-article.png)

## Parser functions

Each row shows a call, written in a `[code]` block, and what the wiki makes of it: `#if`, `#switch`, `#expr`, `#time`, `#ifexist`, `#sub`, `#replace`, `padleft` and `#iferror`.

![A table of parser function calls and their results](10-parser-functions.png)
