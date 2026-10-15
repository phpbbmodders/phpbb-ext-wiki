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
 * The template nesting setting, and the queue that reparses saved articles (see
 * wiki\article_reparse). The parser now reads template calls eight levels deep (it read three)
 * and the {{formatnum:}}, {{plural:}}, {{gender:}} and {{#rel2abs:}} forms, so every article is
 * queued once: a cron task reparses them after the update, and calls that stayed text become
 * calls. The reparse cannot run here, as the wiki's listeners are not loaded during an enable.
 */
class reparse_template_calls extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_only_bbcodes',
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_template_nesting', 8)),
			array('config.add', array('wiki_reparse_next', 0)),
			array('custom', array(array($this, 'queue_reparse'))),
		);
	}

	/**
	 * Queue every article version, from the newest.
	 *
	 * @return void
	 */
	public function queue_reparse()
	{
		$sql = 'SELECT MAX(article_id) AS max_id
			FROM ' . $this->table_prefix . 'wiki_article';
		$result = $this->db->sql_query($sql);
		$max_id = (int) $this->db->sql_fetchfield('max_id');
		$this->db->sql_freeresult($result);

		$this->config->set('wiki_reparse_next', $max_id);
	}
}
