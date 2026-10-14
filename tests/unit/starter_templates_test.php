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

use phpbbmodders\wiki\wiki\starter_templates;

/**
 * Starter templates: which files may be installed, how the check tells new templates from
 * the ones the board has, and how often it may run.
 */
class starter_templates_test extends \PHPUnit\Framework\TestCase
{
	/** A header like those of the wiki-templates repository */
	const HEADER = "<noinclude>\nSource: https://en.wikipedia.org/wiki/Template:Small\nChanges: rewritten with BBCode.\nLicence: CC BY-SA 4.0, https://creativecommons.org/licenses/by-sa/4.0/\n</noinclude>";

	public function test_an_adapted_template_with_its_header_is_accepted()
	{
		$this->assertSame('', starter_templates::problem('Small', self::HEADER . '[size=85]{{{1}}}[/size]'));
	}

	public function test_a_template_without_source_or_licence_is_refused()
	{
		$this->assertSame('WIKI_STARTER_NO_ATTRIBUTION', starter_templates::problem('Small', '[size=85]{{{1}}}[/size]'));
		$this->assertSame('WIKI_STARTER_NO_ATTRIBUTION', starter_templates::problem('Small', str_replace("Source: https://en.wikipedia.org/wiki/Template:Small\n", '', self::HEADER)));
		$this->assertSame('WIKI_STARTER_NO_ATTRIBUTION', starter_templates::problem('Small', str_replace('Licence: CC BY-SA 4.0', 'Licence: CC BY 4.0', self::HEADER)));
		// The header must open the file
		$this->assertSame('WIKI_STARTER_NO_ATTRIBUTION', starter_templates::problem('Small', 'x' . self::HEADER));
	}

	public function test_a_template_not_adapted_yet_is_refused()
	{
		$this->assertSame('WIKI_STARTER_NOT_ADAPTED', starter_templates::problem('Small', str_replace('Changes: rewritten with BBCode.', 'Changes: TODO', self::HEADER)));
		$this->assertSame('WIKI_STARTER_NOT_ADAPTED', starter_templates::problem('Small', str_replace('Changes: rewritten with BBCode.', 'Needs adapting: Lua', self::HEADER)));
	}

	public function test_names_a_page_cannot_have_are_refused()
	{
		foreach (array('', '.hidden', 'a/b', 'a\\b', 'a#b', ' Small') as $name)
		{
			$this->assertSame('WIKI_STARTER_BAD_NAME', starter_templates::problem($name, self::HEADER), $name);
		}
		$this->assertTrue(starter_templates::valid_name('More citations needed'));
	}

	public function test_text_is_compared_without_windows_line_breaks_or_trailing_space()
	{
		$this->assertSame("a\nb", starter_templates::normalise("a\r\nb\r\n  \n"));
	}

	public function test_git_hash_matches_git()
	{
		// git hash-object of a file holding "hello\n"
		$this->assertSame('ce013625030ba8dba906f756967f9e9ca394464a', starter_templates::git_hash("hello\n"));
	}

	public function test_changed_upstream_lists_new_and_changed_templates_only()
	{
		$tree = array(
			array('path' => 'templates', 'type' => 'tree', 'sha' => 'x'),
			array('path' => 'templates/Same.wiki', 'type' => 'blob', 'sha' => 'aaa'),
			array('path' => 'templates/changed.wiki', 'type' => 'blob', 'sha' => 'bbb'),
			array('path' => 'templates/New.wiki', 'type' => 'blob', 'sha' => 'ccc'),
			array('path' => 'tools/fetch_template.py', 'type' => 'blob', 'sha' => 'ddd'),
			array('path' => 'templates/sub/Deep.wiki', 'type' => 'blob', 'sha' => 'eee'),
			array('path' => 'templates/.hidden.wiki', 'type' => 'blob', 'sha' => 'fff'),
		);
		$local = array('Same' => 'aaa', 'changed' => 'old', 'Gone' => 'ggg');

		$this->assertSame(array('changed', 'New'), starter_templates::changed_upstream($tree, $local));
		$this->assertSame(array(), starter_templates::changed_upstream(array(), $local));
	}

	/**
	 * The service with only its settings set; the check never reaches the rest here.
	 *
	 * @param array $settings
	 * @return starter_templates
	 */
	protected function service(array $settings)
	{
		return new starter_templates(
			new \phpbb\config\config($settings),
			$this->createMock(\phpbb\db\driver\driver_interface::class),
			$this->createMock(\phpbb\file_downloader::class),
			$this->createMock(\phpbb\filesystem\filesystem_interface::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbb\log\log_interface::class),
			$this->createMock(\phpbb\notification\manager::class),
			$this->createMock(\phpbbmodders\wiki\wiki\edit::class),
			'phpbb_wiki_article',
			'phpbb_wiki_template_install',
			'./'
		);
	}

	public function test_days_between_checks_stay_within_range()
	{
		$this->assertSame(starter_templates::CHECK_DAYS_DEFAULT, $this->service(array())->check_days());
		$this->assertSame(7, $this->service(array('wiki_starter_check_days' => '7'))->check_days());
		$this->assertSame(starter_templates::CHECK_DAYS_MIN, $this->service(array('wiki_starter_check_days' => '0'))->check_days());
		$this->assertSame(starter_templates::CHECK_DAYS_MAX, $this->service(array('wiki_starter_check_days' => '5000'))->check_days());
	}

	public function test_the_check_is_off_by_default_and_waits_the_chosen_days()
	{
		$this->assertFalse($this->service(array('wiki_starter_last_check' => 0))->check_due());

		$on = array('wiki_starter_check' => 1, 'wiki_starter_check_days' => 3);
		$this->assertTrue($this->service($on + array('wiki_starter_last_check' => 0))->check_due());
		$this->assertFalse($this->service($on + array('wiki_starter_last_check' => time() - 2 * 86400))->check_due());
		$this->assertTrue($this->service($on + array('wiki_starter_last_check' => time() - 3 * 86400 - 60))->check_due());
	}
}
