<?php
/**
 *
 * @package phpBB Extension - Wiki
 * @copyright (c) 2026 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\wiki\tests\migrations;

class add_article_description_test extends \phpbb_database_test_case
{
	/** @var \phpbb\db\tools\tools_interface */
	protected $db_tools;

	/** @var string */
	protected $table_prefix;

	public static function setup_extensions()
	{
		return array('tas2580/wiki');
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/fixtures/add_article_description.xml');
	}

	public function setUp(): void
	{
		parent::setUp();

		// phpBB 4.0+ moved the DBAL to Doctrine; \phpbb\db\tools\factory::get()
		// there requires a Doctrine\DBAL\Connection (built via
		// \phpbb\db\doctrine\connection_factory) instead of the legacy
		// \phpbb\db\driver\driver_interface this test framework's new_dbal()
		// still returns. Skip rather than guess at those still-changing
		// internals; the schema assertions below are verified on 3.3.x.
		if (class_exists('\phpbb\db\doctrine\connection_factory'))
		{
			$this->markTestSkipped('db_tools construction differs on phpBB 4.0+ (Doctrine DBAL); schema is still verified on 3.3.x.');
		}

		global $table_prefix;

		$this->table_prefix = $table_prefix;
		$db = $this->new_dbal();
		$this->db_tools = (new \phpbb\db\tools\factory())->get($db);
	}

	public function test_wiki_article_table_exists()
	{
		$this->assertTrue($this->db_tools->sql_table_exists($this->table_prefix . 'wiki_article'), 'Asserting that table "' . $this->table_prefix . 'wiki_article" exists');
	}

	public function test_article_description_column_exists()
	{
		$this->assertTrue($this->db_tools->sql_column_exists($this->table_prefix . 'wiki_article', 'article_description'), 'Asserting that column "article_description" exists');
	}

	public function test_article_toc_column_exists()
	{
		$this->assertTrue($this->db_tools->sql_column_exists($this->table_prefix . 'wiki_article', 'article_toc'), 'Asserting that column "article_toc" exists');
	}
}
