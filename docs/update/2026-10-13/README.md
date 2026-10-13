# Wiki update screenshots, 2026-10-13

Taken with Playwright on a throwaway phpBB 3.3.19 board (prosilver, MySQL 8.0), filled by `seed-wiki-fixtures.php` from seed-forum.

## Wiki matches in the board search

A search for `example` in phpBB's own search: the **Wiki (4)** block above the forum results, with a link to all of the matches. The forum results below are the board's own.

![The board's search results with a Wiki block above the forum results](01-board-search-wiki-block.png)

## The wiki's own search

`/wiki/search`: match all or any words, search titles only or text only, sort by last edit or title. Results show the title, description, a snippet with the words marked, and the date.

![The wiki's search page with highlighted results](02-wiki-search-results.png)

## Also search the Wiki

The advanced search has an **Also search the Wiki** option, ticked by default.

![The advanced search form with the Also search the Wiki option](03-also-search-the-wiki.png)

## ACP search settings

ACP > Extensions > Wiki > Search: switch the wiki search on or off, results per page, whether the board search shows the wiki block and how many articles it lists, the backend, and each backend's index.

![The wiki search settings in the ACP](04-acp-search-settings.png)

## Templates

`/wiki/templates` lists every template with its description, how many articles use it, and when it was last edited.

![The list of templates](05-templates-list.png)

## Start from a template

On a new article, **Start from** offers the templates whose name starts with `Start `; **Use** fills the text box with the template's text.

![A new article started from the Start Extension template](06-start-from.png)

## Category page

`/wiki/Category:Extensions`: the category's description and the articles in it.

![A category page](07-category-page.png)

## Categories

`/wiki/categories` lists every category with the number of articles in it.

![The list of categories](08-categories-list.png)
