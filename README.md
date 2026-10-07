# phpBB Modders Wiki

[![Tests](https://github.com/phpbbmodders/phpbb-ext-wiki/actions/workflows/tests.yml/badge.svg)](https://github.com/phpbbmodders/phpbb-ext-wiki/actions/workflows/tests.yml) [![Lint](https://github.com/phpbbmodders/phpbb-ext-wiki/actions/workflows/lint.yml/badge.svg)](https://github.com/phpbbmodders/phpbb-ext-wiki/actions/workflows/lint.yml)

A wiki for phpBB with version history, side-by-side compare, and a moderation queue for edits.

## Features

- A wiki at `/wiki`, with an overview page listing its articles.
- Start an article from the overview page's **New article title** box, or by linking to `/wiki/<article-name>` and following the link.
- Link to another article by writing `[[Article name]]`, in articles and in forum posts; links to articles that don't exist yet are shown in red and open the editor. Each article lists the articles that link to it under **What links here**. See [Linking between articles](#linking-between-articles).
- Every edit is saved as a new version; compare any two versions side by side.
- Edits can wait for approval: moderators get a **Pending articles** list with Approve and Reject. Approving a version older than the live one is blocked, so a stale draft can't overwrite newer content.
- Moderators can take an article offline or delete it, and articles can be made sticky or set to redirect.
- Notifications when an edit is waiting for approval.
- Separate permissions for viewing, editing, version history, deleting and moderating.

## Screenshots

| Overview | Article |
|---|---|
| [![Wiki overview page](docs/images/wiki-overview.png)](docs/images/wiki-overview.png) | [![Article view](docs/images/wiki-article-view.png)](docs/images/wiki-article-view.png) |

| Editing | Pending review |
|---|---|
| [![Edit form](docs/images/wiki-edit.png)](docs/images/wiki-edit.png) | [![Pending articles awaiting approval](docs/images/wiki-pending-articles.png)](docs/images/wiki-pending-articles.png) |

Approving or rejecting a pending edit shows a diff against the currently
active version:

[![Version diff with approve/reject actions](docs/images/wiki-compare.png)](docs/images/wiki-compare.png)

The version history page also has a "Moderator controls" dropdown for taking
an active article offline or deleting it entirely:

[![Moderator controls dropdown: set article inactive, delete article](docs/images/wiki-moderator-controls.png)](docs/images/wiki-moderator-controls.png)

## Linking between articles

Write an article's name in double square brackets to link to it, in wiki articles and in forum posts:

- `[[Installation]]` links to the article **Installation**.
- `[[Installation|how to install]]` links to the same article but shows *how to install*.

Links to articles that don't exist yet are shown in red, and following one opens the editor to create that article. The **Wiki link** button in the editor toolbar inserts the brackets. Inside a `[code]` block the brackets stay plain text.

Below each article, **What links here** lists the other articles whose current version links to it.

<table>
  <tr>
    <td align="center"><a href="docs/images/wiki-article-links.png"><img src="docs/images/wiki-article-links.png" width="420" alt="An article with links to other articles; links to articles that don't exist yet are red"></a><br>Links, with missing articles in red</td>
    <td align="center"><a href="docs/images/wiki-what-links-here.png"><img src="docs/images/wiki-what-links-here.png" width="280" alt="An article with its What links here list"></a><br>What links here</td>
    <td align="center"><a href="docs/images/wiki-link-button.png"><img src="docs/images/wiki-link-button.png" width="280" alt="The Wiki link button in the editor toolbar, outlined"></a><br>The editor button (outlined)</td>
  </tr>
</table>

Articles and posts written before this feature existed get their links the next time they are edited. To convert them all at once, run phpBB's reparser from the board's folder: `php bin/phpbbcli.php reparser:reparse phpbbmodders_wiki_article` for articles and `php bin/phpbbcli.php reparser:reparse post_text` for forum posts.

## Requirements

- phpBB 3.3.19 or later (also tested on phpBB 4.0)
- PHP 7.4 or later

Tested versions and compatibility notes: [docs/compatibility.md](docs/compatibility.md).

## Installation

1. Copy the extension to `/ext/phpbbmodders/wiki`
2. In the Administration Control Panel, go to **Customise → Manage extensions**
3. Enable the **phpBB Modders Wiki** extension
4. Set who can view and edit the wiki under the usual Permissions screens

### Upgrading from `tas2580/wiki`

This extension used to be installed as `tas2580/wiki`. It is now
`phpbbmodders/wiki`. To switch an existing board without losing any
articles, versions, permissions or notification settings:

1. Disable the old **tas2580 Wiki** extension in the ACP. Do **not** delete its data.
2. Delete the `/ext/tas2580/wiki` folder.
3. Upload this version to `/ext/phpbbmodders/wiki` and enable it. The old
   install's migration history, notification type and users' notification
   settings are moved to the new name automatically; the wiki tables
   themselves never included the old name, so they are used as they are.
4. Purge the board cache.

If you disable the old extension from the command line (`bin/phpbbcli.php`)
instead of the ACP, run `bin/phpbbcli.php cache:purge` before enabling the
new one; the command-line disable doesn't clear the cache.

## TODO

Ideas not yet built, practical and speculative alike: [`docs/TODO.md`](docs/TODO.md).

## Contributing

Contributions are welcome!

- **Bug reports**: [Open an issue](https://github.com/phpbbmodders/phpbb-ext-wiki/issues).
- **Everything else** (questions, feature requests, ideas, general discussion): [Use Discussions](https://github.com/orgs/phpbbmodders/discussions), or the [community forum](https://www.phpbbmodders.com/community/).
- Pull requests are welcome for bug fixes or discussed features.

## Acknowledgments

- Original extension by [tas2580](https://tas2580.net).
- Updated for phpBB 3.2.x (notification API fixes, textreparser support) by
  Christian Schnegelberger ([Crizz0](https://www.crizzo.de)) in the
  [phpBB.de fork](https://github.com/phpbb-de/wiki).
- This fork builds on Crizz0's phpBB.de branch: fixed phpBB 3.3.x/4.0
  compatibility (routing, YAML parsing, notification API, Flash BBCode
  removal), completed missing English translations, added an approve/reject
  moderation queue for pending articles, and general bug fixes.
- Code review, bug fixes, and documentation assisted by [Claude](https://www.anthropic.com/claude).

## License

This extension is licensed under the **GNU General Public License v2.0**.

See [license.txt](license.txt) for more information.
