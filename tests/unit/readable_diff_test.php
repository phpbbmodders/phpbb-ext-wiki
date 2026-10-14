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

use phpbbmodders\wiki\wiki\readable_diff;

/**
 * The readable compare: two rendered versions compared block by block, and word by word
 * inside a block that changed.
 */
class readable_diff_test extends \PHPUnit\Framework\TestCase
{
	public static function setUpBeforeClass(): void
	{
		global $phpbb_root_path, $phpEx;

		require_once $phpbb_root_path . 'includes/diff/diff.' . $phpEx;
		require_once $phpbb_root_path . 'includes/diff/engine.' . $phpEx;
	}

	public function test_a_changed_line_shows_its_changed_words()
	{
		$html = readable_diff::render('Hello world<br>Second line', 'Hello wide world<br>Second line');

		$this->assertSame('<div class="wiki-diff-line">Hello <ins class="wiki-diff-ins">wide</ins> world</div><div class="wiki-diff-line">Second line</div>', $html);
	}

	public function test_an_added_heading_is_marked_whole_and_the_rest_is_unchanged()
	{
		$html = readable_diff::render('Intro<br>End', 'Intro<h2>New</h2>End');

		$this->assertSame('<div class="wiki-diff-line">Intro</div><h2 class="wiki-diff-ins">New</h2><div class="wiki-diff-line">End</div>', $html);
	}

	public function test_a_list_is_compared_item_by_item()
	{
		$html = readable_diff::render('<ul><li>one</li><li>two</li></ul>', '<ul><li>one</li><li>two!</li><li>three</li></ul>');

		$this->assertSame('<ul><li>one</li><li><del class="wiki-diff-del">two</del> <ins class="wiki-diff-ins">two!</ins></li><li class="wiki-diff-ins">three</li></ul>', $html);
	}

	public function test_a_table_row_is_compared_cell_by_cell()
	{
		$html = readable_diff::render(
			'<table><tr><th>A</th><td>1</td></tr><tr><th>B</th><td>2</td></tr></table>',
			'<table><tr><th>A</th><td>1</td></tr><tr><th>B</th><td>3</td></tr></table>'
		);

		$this->assertSame('<table><tr><th>A</th><td>1</td></tr><tr><th>B</th><td><del class="wiki-diff-del">2</del> <ins class="wiki-diff-ins">3</ins></td></tr></table>', $html);
	}

	public function test_a_change_of_formatting_alone_is_marked_as_changed()
	{
		$this->assertSame('<div class="wiki-diff-line wiki-diff-changed"><em>plain</em> text</div>', readable_diff::render('plain text', '<em>plain</em> text'));
	}

	public function test_a_line_with_an_image_is_shown_whole_not_as_words()
	{
		$html = readable_diff::render('See <a href="x">Getting Started</a>.<br>End', '<img src="logo.png" alt="logo"><br>End');

		$this->assertSame('<div class="wiki-diff-line wiki-diff-del">See <a href="x">Getting Started</a>.</div><div class="wiki-diff-line wiki-diff-ins"><img src="logo.png" alt="logo"></div><div class="wiki-diff-line">End</div>', $html);
	}

	public function test_text_is_escaped_and_unicode_kept()
	{
		$this->assertSame('<div class="wiki-diff-line">Grüße &lt;aus&gt; <del class="wiki-diff-del">Köln</del> <ins class="wiki-diff-ins">Berlin</ins></div>', readable_diff::render('Grüße &lt;aus&gt; Köln', 'Grüße &lt;aus&gt; Berlin'));
	}

	public function test_removing_everything_and_comparing_nothing()
	{
		$this->assertSame('<div class="wiki-diff-line wiki-diff-del">gone</div>', readable_diff::render('gone', ''));
		$this->assertSame('', readable_diff::render('', ''));
	}
}
