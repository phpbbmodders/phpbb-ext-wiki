<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\tests\wiki;

use phpbbmodders\wiki\wiki\protection;

/**
 * Per-article edit protection: who may edit a protected article, and how protection is set,
 * lifted, moved with the article and removed with it.
 */
class protection_test extends \phpbb_database_test_case
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public static function setup_extensions()
	{
		return array('phpbbmodders/wiki');
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/protection.xml');
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = $this->new_dbal();
	}

	/**
	 * @param int $user_id The viewer
	 * @param array $permissions The permissions the viewer has
	 * @return protection
	 */
	protected function protection($user_id, array $permissions = array('u_wiki_edit'))
	{
		global $table_prefix;

		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($option) use ($permissions) {
			return in_array($option, $permissions, true);
		});
		$user = $this->createMock(\phpbb\user::class);
		$user->data = array('user_id' => $user_id);

		$user->method('lang')->willReturnCallback(function ($key, ...$args) {
			return $key . ':' . implode('|', $args);
		});
		$group_helper = $this->createMock(\phpbb\group\helper::class);
		$group_helper->method('get_name')->willReturnArgument(0);

		return new protection($auth, $this->db, $group_helper, $user, $table_prefix . 'wiki_article_protect');
	}

	public function test_an_unprotected_article_is_open_to_editors_only()
	{
		$this->assertSame(array(), $this->protection(12)->groups('Other Page'));
		$this->assertTrue($this->protection(12)->may_edit('Other Page'));
		$this->assertFalse($this->protection(12, array())->may_edit('Other Page'), 'protection never widens the edit permission');
	}

	public function test_only_members_of_its_groups_may_edit_a_protected_article()
	{
		$this->assertSame(array(5, 7), $this->protection(10)->groups('Main Page'));
		$this->assertTrue($this->protection(10)->may_edit('Main Page'), 'a member of group 5');
		$this->assertFalse($this->protection(11)->may_edit('Main Page'), 'a pending member of group 7 is not yet a member');
		$this->assertFalse($this->protection(12)->may_edit('Main Page'), 'a member of another group');
		$this->assertFalse($this->protection(10, array())->may_edit('Main Page'), 'still needs the edit permission');
	}

	public function test_who_may_protect_may_always_edit()
	{
		$this->assertTrue($this->protection(12, array('u_wiki_edit', 'u_wiki_protect'))->may_edit('Main Page'));
	}

	public function test_setting_replaces_and_none_lifts_protection()
	{
		$protection = $this->protection(12);
		$protection->set('Main Page', array(2, 2, '9', 0));
		$this->assertSame(array(2, 9), $this->protection(12)->groups('Main Page'));
		$this->assertTrue($this->protection(12)->may_edit('Main Page'));

		$protection->set('Main Page', array());
		$this->assertSame(array(), $this->protection(11)->groups('Main Page'));
		$this->assertTrue($this->protection(11)->may_edit('Main Page'));
	}

	public function test_lists_label_protected_articles_with_their_groups()
	{
		$protection = $this->protection(12);
		$this->assertSame('WIKI_PROTECTED_NOTE:Authors, Wiki editors', $protection->list_label('Main Page'), 'groups A to Z');
		$this->assertSame('', $protection->list_label('Other Page'));

		$protection->set('Other Page', array(7));
		$this->assertSame('WIKI_PROTECTED_NOTE:Authors', $protection->list_label('Other Page'), 'a change shows at once');
	}

	public function test_protection_moves_with_the_article_and_goes_with_it()
	{
		$protection = $this->protection(12);
		$protection->move('Main Page', 'Home');
		$this->assertSame(array(), $this->protection(12)->groups('Main Page'));
		$this->assertSame(array(5, 7), $this->protection(12)->groups('Home'));

		$protection->delete_for_article('Home');
		$this->assertSame(array(), $this->protection(12)->groups('Home'));
	}
}
