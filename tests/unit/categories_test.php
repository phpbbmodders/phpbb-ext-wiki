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

use phpbbmodders\wiki\wiki\categories;

/**
 * The names behind [[Category:Name]].
 */
class categories_test extends \PHPUnit\Framework\TestCase
{
	protected function setUp(): void
	{
		categories::reset();
	}

	/** @dataProvider names */
	public function test_a_name_is_kept_the_same_whichever_way_it_was_written(string $written, string $kept)
	{
		$this->assertSame($kept, categories::normalise($written));
	}

	public static function names(): array
	{
		return array(
			'lower case'		=> array('boxes', 'Boxes'),
			'already right'		=> array('Boxes', 'Boxes'),
			'underscores'		=> array('old_extensions', 'Old extensions'),
			'extra spaces'		=> array("  old   extensions  ", 'Old extensions'),
			'mixed'				=> array("old _ extensions", 'Old extensions'),
			'not the rest'		=> array('phpBB extensions', 'PhpBB extensions'),
			'accent'			=> array('écrits', 'Écrits'),
			'empty'				=> array('   ', ''),
		);
	}

	public function test_names_found_while_rendering_are_listed_once_in_order()
	{
		categories::add('zebra');
		categories::add('Alpha');
		categories::add('Zebra');
		categories::add('');

		$this->assertSame(array('Zebra', 'Alpha'), categories::found());

		categories::reset();
		$this->assertSame(array(), categories::found());
	}

	public function test_the_names_in_stored_text_are_read_back()
	{
		$stored = '<r>Text <WIKICATEGORY name="Boxes">[[Category:boxes]]</WIKICATEGORY> and '
			. '<WIKICATEGORY name="Q&amp;A &quot;x&quot;">[[Category:Q&A "x"]]</WIKICATEGORY> '
			. '<WIKICATEGORY name="Boxes">[[Category:Boxes]]</WIKICATEGORY></r>';

		$this->assertSame(array('Boxes', 'Q&A "x"'), categories::names_in($stored));
		$this->assertSame(array(), categories::names_in('<t>No categories here</t>'));
	}

	public function test_a_template_page_counts_for_what_it_says_outside_includeonly()
	{
		$raw = "<noinclude>Docs [[Category:Infobox templates]]</noinclude>Body<includeonly>[[Category:Boxes]] [[category:old_stuff|key]]</includeonly> [[Category:Always]]";

		$this->assertSame(array('Infobox templates', 'Always'), categories::names_outside_includeonly($raw));
		$this->assertSame(array(), categories::names_outside_includeonly('<includeonly>[[Category:Boxes]]</includeonly>'));
		$this->assertSame(array(), categories::names_outside_includeonly('No marks'));
	}
}
