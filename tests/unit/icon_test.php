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

use phpbbmodders\wiki\wiki\icon;

/**
 * The icon next to the Wiki link in the board header.
 */
class icon_test extends \PHPUnit\Framework\TestCase
{
	public function test_the_default_is_the_wikipedia_icon()
	{
		$this->assertSame('wikipedia-w', icon::DEFAULT_ICON);
		$this->assertStringContainsString('fa-wikipedia-w', icon::classes(icon::DEFAULT_ICON));
	}

	public function test_a_brand_icon_gets_the_brand_style_class_for_phpbb_4()
	{
		$this->assertSame('icon o-icon o-icon-font fa-brands fa-wikipedia-w fa-fw', icon::classes('wikipedia-w'));
	}

	public function test_any_other_icon_gets_the_solid_style_class_for_phpbb_4()
	{
		$this->assertSame('icon o-icon o-icon-font fas fa-book fa-fw', icon::classes('book'));
	}

	public function test_a_blank_value_means_no_icon()
	{
		$this->assertTrue(icon::is_valid(''));
		$this->assertSame('', icon::classes(''));
	}

	/** @dataProvider names_that_are_not_icons */
	public function test_a_name_with_other_characters_is_refused(string $name)
	{
		$this->assertFalse(icon::is_valid($name));
		$this->assertSame('', icon::classes($name));
	}

	public static function names_that_are_not_icons(): array
	{
		return array(
			'space'			=> array('Bad Name'),
			'upper case'	=> array('Book'),
			'markup'		=> array('x"><script>'),
			'prefix'		=> array('fa-book fa-spin'),
		);
	}

	/** @dataProvider language_files */
	public function test_the_acp_page_has_text_for_the_setting_in_each_language(string $file)
	{
		$lang = array();
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}

		include __DIR__ . '/../../language/' . $file . '/acp_wiki.php';

		foreach (array('WIKI_ICON', 'WIKI_ICON_EXPLAIN', 'WIKI_ICON_INVALID') as $key)
		{
			$this->assertArrayHasKey($key, $lang, $file . ' ' . $key);
		}
	}

	public static function language_files(): array
	{
		return array('en' => array('en'), 'de' => array('de'));
	}
}
