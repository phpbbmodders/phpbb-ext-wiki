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

class article_reparser_test extends \PHPUnit\Framework\TestCase
{
	/**
	 * What the reparser decides about an article row.
	 *
	 * @param array $record The row as the reparser reads it
	 * @return array The row with its enable_* fields
	 */
	private function complete(array $record): array
	{
		$plugin = new \phpbbmodders\wiki\textreparser\plugins\article_text(
			$this->createMock(\phpbb\db\driver\driver_interface::class),
			'phpbb_wiki_article'
		);
		$method = new \ReflectionMethod($plugin, 'add_missing_fields');
		$method->setAccessible(true);

		return $method->invoke($plugin, $record);
	}

	public function test_an_article_stored_as_xml_keeps_its_bbcode()
	{
		// Stored as XML with no bbcode_uid, which is how the wiki saves a version: core would guess "no BBCode" here
		$record = $this->complete(array(
			'id'			=> 1,
			'text'			=> '<r><B><s>[b]</s>bold<e>[/b]</e></B> <WIKILINK name="A">[[A]]</WIKILINK></r>',
			'bbcode_uid'	=> '',
		));

		foreach (array('bbcode', 'smilies', 'magic_url', 'flash_bbcode', 'img_bbcode', 'quote_bbcode', 'url_bbcode') as $field)
		{
			$this->assertTrue($record['enable_' . $field], $field);
		}
	}

	public function test_an_article_with_plain_text_is_parsed_the_same_way()
	{
		$record = $this->complete(array('id' => 2, 'text' => '<t>Plain [b]text[/b]</t>', 'bbcode_uid' => ''));

		$this->assertTrue($record['enable_bbcode']);
	}
}
