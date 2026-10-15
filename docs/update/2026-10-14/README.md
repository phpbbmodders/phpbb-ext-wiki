# Wiki update screenshots, 2026-10-14

Taken with `phpbb-test-board/screenshots.py` (phpbbmodders/phpbb-release-tools) on a throwaway phpBB 3.3.19 board (prosilver, SQLite). The images are in [`docs/images/`](../../images/), shared with the README and the Screenshots wiki page.

## Starter templates

ACP > Extensions > Wiki > Starter templates: get the templates of [phpbbmodders/wiki-templates](https://github.com/phpbbmodders/wiki-templates) (upload its zip, download it from GitHub, or copy the files by FTP), choose which to install, and optionally be told when the repository has new ones. A template the board already has, and one whose page was edited on the board, are listed but not ticked.

![The Starter templates page in the ACP](../../images/wiki-acp-starter-templates.png)

## Infobox, navbox and centred text

An article with `{{Infobox}}` floating at the right, a line in `<center>`, and `{{Navbox}}`, a box of links the reader can close with its title bar. Plain `<details><summary>…</summary>…</details>` makes a box the reader opens, for spoilers.

![An article with an infobox, centred text and a navbox](../../images/wiki-infobox-navbox.png)

## Comparing versions

The compare page opens in a readable view: the newer version as readers see it, with added text in green and removed text struck through. **Source** shows the BBCode diff.

![The readable compare view](../../images/wiki-compare.png)

## Restore and move

The version history has a **Restore** link on every older version, and **Move article** in the moderator controls gives an article a new name, leaving a redirect at the old one.

![Restore links and Move article in the moderator controls](../../images/wiki-moderator-controls.png)

![The Move article form](../../images/wiki-move.png)

## Templates and categories

Template and category pages are no longer listed on the overview; `/wiki/templates` and `/wiki/categories` list them, each with a box that explains how to use them.

![The list of templates](../../images/wiki-templates.png)
