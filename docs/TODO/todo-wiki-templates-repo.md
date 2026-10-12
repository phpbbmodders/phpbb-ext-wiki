# Starter templates repository

A separate repository of ready-made wiki templates, adapted from Wikipedia's
for what this wiki can do, so a board can install a useful set instead of
writing every template from nothing. It depends on the template system in
[Wiki markup](todo-wiki-markup.md), which is not built yet.

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

- Repository name to be chosen (for example `phpbbmodders/wiki-templates`), with
  the CC BY-SA 4.0 text as its `LICENSE` and a README that explains the rules above.
- One file per template, named after the Wikipedia template (`Main.wiki`,
  `Ambox.wiki`, ...), so pasted Wikipedia text finds the same names and parameters.
- A header at the top of each file, inside `<noinclude>`, with the source page
  URL and its history URL, "adapted by phpBB Modders", a list of what was removed
  or changed (for example "no Lua, no `{{#if}}`"), and the licence notice.
- A CI check that fails when a template is missing its source URL or licence
  notice.

## Using it from the wiki

An ACP button, "Install starter templates", reads the files and creates one
`Template:` page per file. Each created page keeps the header, so the board shows
the attribution on the template page itself. Installing is optional and can be
repeated; an existing template page is not overwritten without asking.

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

## Still to decide

- The repository's name, and whether it lives under the `phpbbmodders` organisation.
- Whether to create it now, with a few hand-written templates, or after the
  template system exists so every file can be tested against it. Creating a public
  repository is an outward-facing step and needs a go-ahead first.
- How the ACP import is packaged: files read from a downloaded copy of the
  repository, or a release archive the extension fetches.
