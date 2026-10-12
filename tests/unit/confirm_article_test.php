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

use phpbbmodders\wiki\wiki\functions;

/**
 * The article name used once a confirm box has been answered.
 */
class confirm_article_test extends \PHPUnit\Framework\TestCase
{
	private function request(?string $posted): \phpbb\request\request_interface
	{
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(function ($name, $default, $multibyte) use ($posted) {
			$this->assertSame('article', $name);
			$this->assertTrue($multibyte, 'names can hold any character');

			return $posted ?? $default;
		});

		return $request;
	}

	public function test_the_name_the_box_carried_wins_over_the_double_encoded_route_name()
	{
		$this->assertSame('Some Article', functions::confirmed_article($this->request('Some Article'), 'Some%20Article'));
	}

	public function test_before_the_box_the_route_name_is_used()
	{
		$this->assertSame('Some Article', functions::confirmed_article($this->request(null), 'Some Article'));
	}

	public function test_the_html_escaping_of_the_posted_value_is_undone()
	{
		// request->variable() escapes what it returns; article names are stored as typed
		$this->assertSame('Tom & "Jerry"', functions::confirmed_article($this->request('Tom &amp; &quot;Jerry&quot;'), 'x'));
	}

	public function test_unicode_names_survive()
	{
		$this->assertSame('Übersicht ✓', functions::confirmed_article($this->request('Übersicht ✓'), 'x'));
	}
}
