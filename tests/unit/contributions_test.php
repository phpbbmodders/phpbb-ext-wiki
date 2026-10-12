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

use phpbbmodders\wiki\controller\contributions;
use phpbbmodders\wiki\wiki\author;

/**
 * The member contributions page: how a version is labelled, and the author link.
 */
class contributions_test extends \PHPUnit\Framework\TestCase
{
	public function test_the_live_version_is_live()
	{
		$this->assertSame(contributions::STATUS_LIVE, contributions::status(1, 200, 200));
	}

	public function test_a_version_older_than_the_live_one_was_replaced()
	{
		$this->assertSame(contributions::STATUS_REPLACED, contributions::status(0, 100, 200));
	}

	public function test_a_version_newer_than_the_live_one_is_pending()
	{
		$this->assertSame(contributions::STATUS_PENDING, contributions::status(0, 300, 200));
	}

	public function test_a_version_of_an_article_with_no_live_version_is_pending()
	{
		$this->assertSame(contributions::STATUS_PENDING, contributions::status(0, 100, 0));
	}

	public function test_a_version_saved_at_the_same_moment_as_the_live_one_is_not_called_replaced()
	{
		$this->assertSame(contributions::STATUS_PENDING, contributions::status(0, 200, 200));
	}

	private function link(string $username, string $colour, ?string &$route_username = null): string
	{
		if (!defined('ANONYMOUS'))
		{
			define('ANONYMOUS', 1);
		}

		$helper = $this->createMock(\phpbb\controller\helper::class);
		$helper->method('route')->willReturnCallback(function ($route, $params) use (&$route_username) {
			$this->assertSame('phpbbmodders_wiki_contributions', $route);
			$route_username = $params['username'];

			return '/wiki/contributions/' . rawurlencode($params['username']);
		});

		return author::link($helper, 5, $username, $colour);
	}

	public function test_a_coloured_name_links_to_the_contributions_page()
	{
		$this->assertSame('<a href="/wiki/contributions/kbmod" class="username-coloured" style="color: #00AA00;">kbmod</a>', $this->link('kbmod', '00AA00'));
	}

	public function test_a_name_without_a_colour_gets_the_plain_username_class()
	{
		$this->assertSame('<a href="/wiki/contributions/kbmod" class="username">kbmod</a>', $this->link('kbmod', ''));
	}

	public function test_the_stored_html_escaping_is_undone_for_the_url_and_kept_in_the_text()
	{
		$html = $this->link('O&#039;Neil &amp; Co', '', $route_username);

		$this->assertSame("O'Neil & Co", $route_username);
		$this->assertStringContainsString('>O&#039;Neil &amp; Co</a>', $html);
	}
}
