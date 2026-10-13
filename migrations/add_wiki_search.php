<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\migrations;

/**
 * The tables and settings of the wiki's own search, and its ACP page.
 *
 * The search keeps a plain-text copy of the live version of each article
 * (wiki_search_text), which the three search backends index, and the tables of the
 * native backend (a word list, and which words an article holds).
 */
class add_wiki_search extends \phpbb\db\migration\migration
{
	/** The search backend a new install starts with. */
	const DEFAULT_BACKEND = 'phpbbmodders\\wiki\\search\\backend\\wiki_fulltext_native';

	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_categories',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_search_text'	=> array(
					'COLUMNS'		=> array(
						'article_id'			=> array('UINT', 0),
						'article_category_id'	=> array('UINT', 0),
						'approved'				=> array('BOOL', 1),
						'article_title'			=> array('VCHAR:255', ''),
						'article_description'	=> array('VCHAR:255', ''),
						'article_date'			=> array('UINT:11', 0),
						'author_id'				=> array('UINT', 0),
						'author'				=> array('VCHAR:255', ''),
						'article_body'			=> array('MTEXT_UNI', ''),
					),
					'PRIMARY_KEY'	=> 'article_id',
					'KEYS'			=> array(
						'author_id'	=> array('INDEX', 'author_id'),
						'author'	=> array('INDEX', 'author'),
					),
				),
				$this->table_prefix . 'wiki_src_wrdlist'	=> array(
					'COLUMNS'		=> array(
						'word_id'		=> array('UINT', null, 'auto_increment'),
						'word_text'		=> array('VCHAR_UNI', ''),
						'word_common'	=> array('BOOL', 0),
						'word_count'	=> array('UINT', 0),
					),
					'PRIMARY_KEY'	=> 'word_id',
					'KEYS'			=> array(
						'word_text'		=> array('UNIQUE', 'word_text'),
						'word_count'	=> array('INDEX', 'word_count'),
					),
				),
				$this->table_prefix . 'wiki_src_wrdmtch'	=> array(
					'COLUMNS'		=> array(
						'article_id'	=> array('UINT', 0),
						'word_id'		=> array('UINT', 0),
						'title_match'	=> array('BOOL', 0),
						'descr_match'	=> array('BOOL', 0),
					),
					'KEYS'			=> array(
						// A word in an article's text and in its description is two rows that differ only in
						// descr_match, so that column is part of the key
						'un_mtch'		=> array('UNIQUE', array('article_id', 'word_id', 'title_match', 'descr_match')),
						'word_id'		=> array('INDEX', 'word_id'),
						'article_id'	=> array('INDEX', 'article_id'),
					),
				),
				$this->table_prefix . 'wiki_search_results'	=> array(
					'COLUMNS'		=> array(
						'search_key'		=> array('VCHAR:32', ''),
						'search_time'		=> array('UINT:11', 0),
						'search_keywords'	=> array('MTEXT_UNI', ''),
						'search_authors'	=> array('MTEXT_UNI', ''),
					),
					'PRIMARY_KEY'	=> 'search_key',
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_search_text',
				$this->table_prefix . 'wiki_src_wrdlist',
				$this->table_prefix . 'wiki_src_wrdmtch',
				$this->table_prefix . 'wiki_search_results',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_search', 1)),
			array('config.add', array('wiki_search_type', self::DEFAULT_BACKEND)),
			array('config.add', array('wiki_per_page_search', 10)),
			array('config.add', array('wiki_num_articles', 0)),

			array('module.add', array('acp', 'ACP_WIKI_TITLE', array(
				'module_basename'	=> '\phpbbmodders\wiki\acp\search_module',
				'modes'				=> array('settings'),
			))),
		);
	}
}
