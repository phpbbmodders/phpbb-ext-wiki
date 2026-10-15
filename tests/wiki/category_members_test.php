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

use phpbbmodders\wiki\wiki\category_members;

/**
 * The stored category memberships: members sorted by key or title, sub-categories apart,
 * counts, removal, and the cron fill that marks the table built. Working out an article's
 * categories renders it, so these tests give compute() its answers.
 */
class category_members_test extends \phpbb_database_test_case
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	public static function setup_extensions()
	{
		return array('phpbbmodders/wiki');
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/category_members.xml');
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = $this->new_dbal();
		$this->config = new \phpbb\config\config(array('wiki_category_members_built' => 0, 'wiki_category_members_cursor' => ''));
	}

	/**
	 * @param array $answers Article names with the categories (and keys) compute() gives them
	 * @return category_members
	 */
	protected function members(array $answers)
	{
		global $table_prefix;

		$templates = new \phpbbmodders\wiki\wiki\templates($this->config, $this->db, $this->createMock(\phpbb\language\language::class), $table_prefix . 'wiki_article');
		$members = new class($this->config, $this->db, $this->createMock(\phpbbmodders\wiki\wiki\links::class), $templates, $table_prefix . 'wiki_article', $table_prefix . 'wiki_category_member') extends category_members {
			/** @var array */
			public $answers = array();

			public function compute($article)
			{
				return $this->answers[$article] ?? array();
			}
		};
		$members->answers = $answers;

		return $members;
	}

	public function test_members_sort_by_key_or_title_with_sub_categories_apart()
	{
		$members = $this->members(array(
			'Zebra'				=> array('Fruit' => 'Aardvark'),
			'Apple'				=> array('Fruit' => ''),
			'Mango'				=> array('Fruit' => '', 'Tropical' => ''),
			'Category:Tropical'	=> array('Fruit' => ''),
		));
		foreach (array('Zebra', 'Apple', 'Mango', 'Category:Tropical') as $article)
		{
			$members->update($article);
		}

		$lists = $members->members('fruit');
		$this->assertSame(array('Zebra', 'Apple', 'Mango'), array_column($lists['articles'], 'article_url'), 'Zebra is filed under its key');
		$this->assertSame(array('Category:Tropical'), array_column($lists['subcategories'], 'article_url'));
		$this->assertSame(array('Fruit' => 4, 'Tropical' => 1), $members->in_use());
	}

	public function test_an_article_that_is_not_live_is_not_listed()
	{
		$members = $this->members(array('Draft' => array('Fruit' => '')));
		$members->update('Draft');

		$this->assertSame(array(), $members->members('Fruit')['articles']);
	}

	public function test_removing_an_article_takes_it_out_of_its_categories()
	{
		$members = $this->members(array('Apple' => array('Fruit' => '')));
		$members->update('Apple');
		$members->remove('Apple');

		$this->assertSame(array(), $members->in_use());
	}

	public function test_the_cron_fills_the_table_in_batches_then_marks_it_built()
	{
		$members = $this->members(array('Apple' => array('Fruit' => ''), 'Zebra' => array('Fruit' => '')));
		$this->assertFalse($members->built());

		$this->assertSame(2, $members->build_batch(2));
		$this->assertFalse($members->built(), 'a full batch means there may be more');
		$this->assertSame(2, $members->build_batch(2));
		$this->assertSame(1, $members->build_batch(2));
		$this->assertTrue($members->built());
		$this->assertSame('', (string) $this->config['wiki_category_members_cursor']);
		$this->assertSame(array('Fruit' => 2), $members->in_use());
	}
}
