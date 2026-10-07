# Wiki markup: headings, section links and tables

phpBB already has BBCode, so every extra syntax is a second way to write
something. This plan adds only what BBCode can't do, and only inside wiki
articles.

## Scope: wiki articles only

`[[Article name]]` links are safe everywhere and stay enabled in forum
posts. Everything below works only in wiki articles: `== text ==` or a
line starting with `#REDIRECT` in a normal forum post would surprise
people.

s9e TextFormatter plugins are configured once for the whole board, so the
wiki switches the new markup off for normal parsing and back on only when
it saves an article. Checked against phpBB 3.3's parser code:

- In `core.text_formatter_s9e_parser_setup`, call
  `$parser->disable_bbcode('name')` for each wiki-only BBCode, and
  `disablePlugin('HTMLElements')` on the underlying s9e parser for tables.
  That covers posts, private messages, signatures and previews.
- When the wiki saves an article, call `enable_bbcode()` (and
  `enablePlugin('HTMLElements')`) just for that save, then switch them off
  again.
- phpBB's posting code can't re-enable these by accident. Its "BBCode on"
  switch only re-enables the BBCodes plugin, not single tags, and its
  per-tag switches only touch `img`, `flash`, `quote` and `url`.
- In a forum post, a wiki-only BBCode shows as plain text, like an unknown
  BBCode does today.

Admins could also make their own ACP-created BBCodes wiki-only, matched by
name, through an ACP setting listing them. The editor buttons for wiki-only
BBCodes appear only on the wiki edit page.

Limits:

- This stops new use only. Text saved before a BBCode was made wiki-only
  keeps its formatting.
- Quoting an article into a forum post turns wiki-only tags into plain
  text there.

Still to confirm on the test board before building on it.

## Syntax to add

| Syntax | What it does | Notes |
|---|---|---|
| `== Section ==` | Headings | phpBB has no heading tag. Also needed for the table of contents. |
| `[[Article#Section]]` | Link to a heading in an article | `#` isn't allowed inside `[[ ]]` today, so this shows as plain text. |
| HTML tables | Tables | See below. |

## Optional syntax

| Syntax | What it does | Notes |
|---|---|---|
| `#REDIRECT [[Other article]]` | Redirect set in the article text | Redirects already exist as an editor field; this is a familiar shortcut. |
| `[[Category:Name]]` | Put an article in a category | Only useful once categories are built (see the master TODO). |

## Syntax not to add

| Syntax | Why not |
|---|---|
| `'''bold'''`, `''italic''`, `*` lists, `[http://… text]` | BBCode and the editor buttons already do these. |
| MediaWiki tables, `{| … |}` | Fiddly and needs a custom parser; HTML tables are simpler. |
| Templates, `{{Name}}` | A big feature, not worth it for a forum wiki. |

## HTML tables

Checked against the copy of s9e TextFormatter bundled with phpBB 3.3: its
built-in **HTMLElements** plugin allows only the HTML elements and
attributes the extension names. Anything else a user types shows as plain
text.

```php
// in core.text_formatter_s9e_configure_after
$html = $event['configurator']->HTMLElements;
foreach (['table', 'caption', 'thead', 'tbody', 'tr', 'th', 'td'] as $element)
{
	$html->allowElement($element);
}
foreach (['th', 'td'] as $cell)
{
	$html->allowAttribute($cell, 'colspan');
	$html->allowAttribute($cell, 'rowspan');
}
```

- **Built-in safety.** s9e refuses unsafe elements such as `script` and
  `iframe`, the `style` attribute, and every `on…` event attribute.
  Allowing them needs `allowUnsafeElement()` or `allowUnsafeAttribute()`,
  which the wiki must never call.
- **No separate HTML sanitizer.** phpBB stores text as parsed XML and only
  renders tags it knows, so a tool like HTML Purifier would add nothing.
- **BBCode works inside cells**, including `[b]`, `[url]` and
  `[[Article]]` links.
- **Bad nesting is cleaned up** using HTML's own content rules, so a stray
  `<td>` can't break the page layout.

Other table options considered:

| Option | For | Against |
|---|---|---|
| HTML tables (HTMLElements) | Familiar, least new code, safety from s9e itself | Needs the wiki-only setup above |
| `[table]` BBCodes | Fits phpBB's style; could get editor buttons | Four BBCodes (`table`, `tr`, `th`, `td`); verbose to type |
| MediaWiki `{| … |}` | Familiar to Wikipedia editors | Custom parser; easy to get wrong |

## Still to decide

- Heading levels: only `==` and `===`, or the full MediaWiki range.
- Anchor names for headings, so `[[Article#Section]]` matches reliably
  (spaces, case, non-ASCII titles).
- Whether existing articles need reparsing after the change, using the
  extension's reparser (`php bin/phpbbcli.php reparser:reparse`).
