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

use phpbbmodders\wiki\wiki\text_tools;

/**
 * The text and date helpers behind the string and time parser functions.
 */
class text_tools_test extends \PHPUnit\Framework\TestCase
{
	public function test_time_formats_translate_to_php_and_escape_the_rest()
	{
		$this->assertSame('Y\\-m\\-d', text_tools::time_format('Y-m-d'));
		$this->assertSame('\\o\\n\\ j', text_tools::time_format('"on" j'));
		$this->assertSame('\\d', text_tools::time_format('\\d'));
		$this->assertSame('\\q\\k', text_tools::time_format('qk'), 'letters that are not time formats stay text');
	}

	public function test_a_time_comes_out_in_utc()
	{
		$now = gmmktime(23, 30, 0, 12, 31, 2026);

		$this->assertSame('2026-12-31 23:30', text_tools::time('Y-m-d H:i', '', $now));
		$this->assertSame('2027-01-01', text_tools::time('Y-m-d', '+1 day', $now));
		$this->assertNull(text_tools::time('Y', 'garbage in', $now));
	}

	public function test_date_words_are_known_by_name()
	{
		$now = gmmktime(1, 2, 3, 4, 5, 2026);

		$this->assertSame('2026', text_tools::date_word('CURRENTYEAR', $now));
		$this->assertSame('01:02', text_tools::date_word('CURRENTTIME', $now));
		$this->assertNull(text_tools::date_word('SOMETHINGELSE', $now));
		$this->assertTrue(text_tools::is_date_word('CURRENTDAY2'));
		$this->assertFalse(text_tools::is_date_word('PAGENAME'));
	}

	public function test_padding_stops_at_a_sane_length_and_ignores_an_empty_fill()
	{
		$this->assertSame(500, mb_strlen(text_tools::pad('x', 100000, '0', true)));
		$this->assertSame('x', text_tools::pad('x', 5, '', true));
		$this->assertSame('ééabc', text_tools::pad('abc', 5, 'é', true));
	}

	public function test_replace_cannot_grow_without_limit()
	{
		$this->assertSame(text_tools::MAX_LENGTH, mb_strlen(text_tools::replace(str_repeat('a', 5000), 'a', 'bbbb')));
	}

	public function test_positions_count_characters_not_bytes()
	{
		$this->assertSame('2', text_tools::pos('ééx', 'x'));
		$this->assertSame('', text_tools::pos('abc', ''));
		$this->assertSame('-1', text_tools::rpos('abc', ''));
		$this->assertSame('1', text_tools::pos('é b', ''));
		$this->assertSame('3', text_tools::rpos('é b c', ''));
		$this->assertSame('x', text_tools::sub('éxé', 1, 1));
	}
}
