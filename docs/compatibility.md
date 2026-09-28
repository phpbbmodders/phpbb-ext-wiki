# Compatibility

Verified working, end to end (create/edit/versioning/compare/approve-reject/
notifications), on:

- phpBB 3.3.x (tested on 3.3.18-dev)
- phpBB 4.0.0-a3-dev

phpBB 3.2.x compatibility is **not independently verified by this fork** —
it's carried forward from Crizz0/phpBB.de's original claim for their branch.
The `pattern:`/`%parameter%` YAML fixes and other changes here target 3.3.x
and 4.0 specifically; 3.2.x may or may not still work as-is.

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
