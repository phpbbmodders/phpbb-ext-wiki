<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\tests\unit;

use phpbbmodders\wiki\wiki\search_sync;

/**
 * The plain text the wiki search indexes, made from an article's text.
 */
class search_sync_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider texts */
	public function test_markup_is_reduced_to_the_words_it_holds(string $raw, string $expected)
	{
		$this->assertSame($expected, search_sync::plain_text($raw));
	}

	public static function texts(): array
	{
		return array(
			'plain'					=> array('Just some words.', 'Just some words.'),
			'bbcode'				=> array('A [b]bold[/b] and [url=https://example.org]a link[/url] and [list][*]one[*]two[/list]', 'A bold and a link and one two'),
			'wiki links'			=> array('See [[Main Page]] and [[Getting Started#Editing|the editing part]].', 'See Main Page and the editing part .'),
			'a file keeps its alt'	=> array('[[File:diagram.png|The release diagram]] and [[File:notes.zip]]', 'The release diagram and'),
			'categories vanish'		=> array('Text [[Category:Boxes]] more [[category:old_things|key]]', 'Text more'),
			'templates'				=> array('{{Out of date|reason=old text}} and {{{1|default}}}', 'Out of date reason=old text and 1 default'),
			'tables'				=> array("<table>\n<tr><th>Name</th><td>[b]Wiki[/b]</td></tr>\n</table>", 'Name Wiki'),
			'headings'				=> array("== History ==\nText\n=== Early ===\nMore", 'History Text Early More'),
			'noinclude'				=> array('<noinclude>Docs</noinclude>Body', 'Docs Body'),
			'escaped characters'	=> array('Fish &amp; chips &lt;3', 'Fish & chips <3'),
			'lines and tabs'		=> array("one\n\n\ttwo   three", 'one two three'),
			'empty'					=> array('', ''),
		);
	}
}
