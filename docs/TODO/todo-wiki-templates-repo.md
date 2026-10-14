# Starter templates repository

A separate repository of ready-made wiki templates, adapted from Wikipedia's
for what this wiki can do, so a board can install a useful set instead of
writing every template from nothing. It depends on the template system in
[Wiki markup](todo-wiki-markup.md), which is built.

## Why a separate repository

Wikipedia's templates are wiki text, licensed CC BY-SA 4.0 (and GFDL unless a
page says otherwise). This extension is GPL-2.0. As far as we know CC BY-SA 4.0
is one-way compatible with GPLv3 but not GPLv2, so the copies must not live in
the extension's repository. Keeping them in their own repository, under CC BY-SA
4.0, keeps the two licences apart; the extension only reads them at runtime.

## What Wikipedia asks of a reuser

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

## Layout

Built on 10/14/2026 in [`phpbbmodders/wiki-templates`](https://github.com/phpbbmodders/wiki-templates), which now holds 14 templates:

- The CC BY-SA 4.0 text as its `LICENSE` and a README that explains the rules above.
- One file per template, named after the Wikipedia template (`Main.wiki`,
  `Ambox.wiki`, ...), so pasted Wikipedia text finds the same names and parameters.
- A header at the top of each file, inside `<noinclude>`, with the source page
  URL and its history URL, "adapted by phpBB Modders", a list of what was removed
  or changed (for example "no Lua, no `{{#if}}`"), and the licence notice.
- A CI check that fails when a template is missing its source URL or licence
  notice (`tools/check_headers.py`, which also requires the history URL and the
  list of changes).

## Using it from the wiki

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

## What goes in

Only templates that work as plain wiki text with parameters:

- hatnotes (`{{Main}}`, `{{See also}}`, `{{Further}}`, `{{About}}`),
- message boxes ("needs references", "out of date", "under construction", stub),
- small inline helpers (`{{nowrap}}`, `{{small}}`, `{{center}}`),
- simplified versions of infobox, citation and navbox templates, written to take
  the same parameter names as Wikipedia's.

## What stays out

- Lua modules (`{{#invoke:}}`), and templates that need them: Wikipedia's real
  infobox, cite web, convert and navbox.
- TemplateStyles (CSS shipped inside a template).
- Anything whose licence notice on the source page says it is not plain CC BY-SA.

## Decided

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
