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

use phpbbmodders\wiki\wiki\move;

/**
 * Which names an article can be moved to: the same names a [[link]] can reach.
 */
class move_test extends \PHPUnit\Framework\TestCase
{
	public function test_ordinary_names_are_valid()
	{
		foreach (array('Main Page', 'Template:Out of date', 'Category:Extensions', 'Grüße aus Köln', 'C++ & friends', "It's 1.0") as $name)
		{
			$this->assertTrue(move::valid_name($name), $name);
		}
	}

	public function test_names_with_characters_links_cannot_hold_are_refused()
	{
		foreach (array('a/b', 'a\\b', 'a#b', 'a?b', 'a%b', 'a|b', 'a[b', 'a]b', 'a<b', 'a>b', 'a"b', "a\nb") as $name)
		{
			$this->assertFalse(move::valid_name($name), $name);
		}
	}

	public function test_empty_padded_and_too_long_names_are_refused()
	{
		$this->assertFalse(move::valid_name(''));
		$this->assertFalse(move::valid_name(' Main Page'));
		$this->assertFalse(move::valid_name('Main Page '));
		$this->assertTrue(move::valid_name(str_repeat('ä', 255)));
		$this->assertFalse(move::valid_name(str_repeat('ä', 256)));
	}
}
