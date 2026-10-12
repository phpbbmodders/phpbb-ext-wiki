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

/**
 * When the "Start discussion" button shows on an article.
 */
class discussion_test extends \PHPUnit\Framework\TestCase
{
	private const FORUM_ID = 4;

	/**
	 * @param int  $forum_id      Value of the wiki_discussion_forum_id setting
	 * @param bool $can_edit_topic Whether the viewer holds u_wiki_edit_topic
	 * @param bool $can_post       Whether the viewer may post in the discussion forum
	 */
	private function discussion(int $forum_id = self::FORUM_ID, bool $can_edit_topic = true, bool $can_post = true): \phpbbmodders\wiki\wiki\discussion
	{
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($option, $forum = 0) use ($can_edit_topic, $can_post) {
			if ($option === 'u_wiki_edit_topic')
			{
				return $can_edit_topic;
			}

			return $option === 'f_post' && $forum === self::FORUM_ID && $can_post;
		});

		return new \phpbbmodders\wiki\wiki\discussion(
			$auth,
			new \phpbb\config\config(['wiki_discussion_forum_id' => $forum_id]),
			$this->createMock(\phpbb\db\driver\driver_interface::class),
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\request\request::class),
			$this->createMock(\phpbb\user::class),
			'phpbb_wiki_article',
			'./',
			'php'
		);
	}

	private function article(array $overrides = []): array
	{
		return $overrides + [
			'article_topic_id'	=> 0,
			'article_redirect'	=> '',
			'article_approved'	=> 1,
		];
	}

	public function test_the_button_shows_on_a_live_article_without_a_topic()
	{
		$this->assertTrue($this->discussion()->can_start($this->article()));
	}

	public function test_the_button_is_hidden_when_no_forum_is_chosen()
	{
		$this->assertFalse($this->discussion(0)->can_start($this->article()));
	}

	public function test_the_button_is_hidden_once_the_article_has_a_topic()
	{
		$this->assertFalse($this->discussion()->can_start($this->article(['article_topic_id' => 12])));
	}

	public function test_the_button_is_hidden_on_a_redirect()
	{
		$this->assertFalse($this->discussion()->can_start($this->article(['article_redirect' => 'Other_Article'])));
	}

	public function test_the_button_is_hidden_on_a_version_awaiting_approval()
	{
		$this->assertFalse($this->discussion()->can_start($this->article(['article_approved' => 0])));
	}

	public function test_the_button_needs_the_edit_topic_permission()
	{
		$this->assertFalse($this->discussion(self::FORUM_ID, false)->can_start($this->article()));
	}

	public function test_the_button_needs_permission_to_post_in_the_forum()
	{
		$this->assertFalse($this->discussion(self::FORUM_ID, true, false)->can_start($this->article()));
	}
}
