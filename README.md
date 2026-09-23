## phpbbmodders Wiki

This is an extension for the phpBB forums software. It adds a simple wiki to
your forum. On each edit the old version is saved, so you can compare the
difference between versions and approve or reject a pending edit before it
goes live.

This fork carries forward and repairs [tas2580](https://tas2580.net)'s
original Wiki extension by way of
[Crizz0/phpBB.de's 3.2.x fork](https://github.com/phpbb-de/wiki) — see
Acknowledgments below.

DESCRIPTION
-------
Adds a simple wiki to your forum. On each edit the old version is saved and
you can compare the difference between versions.

To add a new article, use the "New article title" box on the Wiki overview
page, or write a link like
```[url=https://your-domain.tld/app.php/wiki/new-article]New article[/url]```
and click it — either way you land on the edit form for that article.

Articles with unapproved edits show up in a "Pending articles" section on
the overview page (visible to users with the `m_wiki_view_inactive`
permission), with direct Approve/Reject actions. Approving a pending version
that is older than the currently live one is blocked in favor of a forced
review, so a stale draft can't silently overwrite newer content.

INSTALLATION
----------
To install this extension, download it and upload the files to your forum
under <b>/ext/tas2580/wiki</b>. Then go to the Admin panel of your forum and
navigate to Customise -> Extension Management -> Extensions. Find this
extension in the list and click Enable.

COMPATIBILITY
-------
Verified working, end to end (create/edit/versioning/compare/approve-reject/
notifications), on:

- phpBB 3.2.x / 3.3.x (tested on 3.3.18-dev)
- phpBB 4.0.0-a3-dev

phpBB 3.3.x and 4.0 both bundle a stricter Symfony YAML parser than this
extension originally targeted, and phpBB 4.0 additionally ships a much
newer Symfony (7.x) and removes Flash BBCode support. Both are accounted
for in this fork.

One known forward-compatibility note: `\phpbb\user::__get('lang')` (the
`$user->lang['KEY']` array-access shorthand this extension used to rely on
throughout) is marked `@deprecated ... To be removed: 4.0.0` in phpBB core
itself. This fork already uses the non-deprecated `$user->lang('KEY')` method
call everywhere instead, so it isn't exposed to that removal whenever it
happens in a later 4.0 build.

## Contributing

Contributions are welcome!

- **Bug reports**: [Open an issue](https://github.com/phpbbmodders/phpbb-ext-wiki/issues).
- **Everything else** (questions, feature requests, ideas, general discussion): [Use Discussions](https://github.com/phpbbmodders/phpbb-ext-wiki/discussions).
- Pull requests are welcome for bug fixes or discussed features.

ACKNOWLEDGMENTS
-------
- Original extension by [tas2580](https://tas2580.net).
- Updated for phpBB 3.2.x (notification API fixes, textreparser support) by
  Christian Schnegelberger ([Crizz0](https://www.crizzo.de)) in the
  [phpBB.de fork](https://github.com/phpbb-de/wiki).
- This fork builds on Crizz0's phpBB.de branch: fixed phpBB 3.3.x/4.0
  compatibility (routing, YAML parsing, notification API, Flash BBCode
  removal), completed missing English translations, added an approve/reject
  moderation queue for pending articles, and general bug fixes.
- Code review, bug fixes, and documentation assisted by [Claude](https://www.anthropic.com/claude).

LICENSE
-------
<a href="http://opensource.org/licenses/gpl-2.0.php">GNU General Public License v2</a>
