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

use phpbbmodders\wiki\notification\article_watch;
use phpbbmodders\wiki\wiki\watch;

/**
 * Watching an article: who may watch, and the notification's words.
 */
class watch_test extends \PHPUnit\Framework\TestCase
{
	private function watch(int $user_id, bool $is_bot = false, bool $can_view = true): watch
	{
		if (!defined('ANONYMOUS'))
		{
			define('ANONYMOUS', 1);
		}

		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($option) use ($can_view) {
			return $option === 'u_wiki_view' && $can_view;
		});

		$user = $this->createMock(\phpbb\user::class);
		$user->data = array('user_id' => $user_id, 'is_bot' => $is_bot);

		return new watch(
			$auth,
			$this->createMock(\phpbb\db\driver\driver_interface::class),
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\request\request::class),
			$this->createMock(\phpbb\template\template::class),
			$user,
			'phpbb_wiki_watch',
			'phpbb_wiki_article'
		);
	}

	public function test_a_signed_in_member_who_may_view_the_wiki_can_watch()
	{
		$this->assertTrue($this->watch(5)->can_watch());
	}

	public function test_a_guest_cannot_watch()
	{
		$this->assertFalse($this->watch(1)->can_watch());
	}

	public function test_a_bot_cannot_watch()
	{
		$this->assertFalse($this->watch(5, true)->can_watch());
	}

	public function test_a_member_who_may_not_view_the_wiki_cannot_watch()
	{
		$this->assertFalse($this->watch(5, false, false)->can_watch());
	}

	public function test_the_notification_is_its_own_type_with_the_version_as_its_item()
	{
		$this->assertSame('phpbbmodders.wiki.notification.type.article_watch', (new \ReflectionClass(article_watch::class))->newInstanceWithoutConstructor()->get_type());
		$this->assertSame(17, article_watch::get_item_id(array('article_id' => '17')));
		$this->assertSame(0, article_watch::get_item_parent_id(array('article_id' => '17')));
		$this->assertSame('WIKI_WATCHED_ARTICLE', article_watch::$notification_option['lang']);
	}

	/** @dataProvider languages */
	public function test_the_notification_has_its_words_and_an_email_in_each_language(string $language)
	{
		$lang = array();
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		include __DIR__ . '/../../language/' . $language . '/link.php';

		foreach (array('WIKI_WATCHED_ARTICLE', 'WIKI_WATCHED_ARTICLE_LIVE') as $key)
		{
			$this->assertArrayHasKey($key, $lang, $language . ' ' . $key);
		}

		$email = file_get_contents(__DIR__ . '/../../language/' . $language . '/email/mail_article_watch.txt');
		foreach (array('Subject:', '{NOTIFICATION_SUBJECT}', '{U_LINK}', '{EMAIL_SIG}', '{USERNAME}') as $needle)
		{
			$this->assertStringContainsString($needle, $email, $language . ' email ' . $needle);
		}
	}

	public static function languages(): array
	{
		return array('en' => array('en'), 'de' => array('de'));
	}

	public function test_the_existing_awaiting_approval_type_has_an_english_label_too()
	{
		$lang = array();
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		include __DIR__ . '/../../language/en/link.php';

		$this->assertArrayHasKey('WIKI_ARTICLE_UPDATE', $lang);
	}
}
