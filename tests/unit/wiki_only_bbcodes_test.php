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

use phpbbmodders\wiki\wiki\wiki_only_bbcodes;

/**
 * The list of the admin's BBCodes kept to the wiki, and the parser names worked out for them.
 */
class wiki_only_bbcodes_test extends \PHPUnit\Framework\TestCase
{
	public function test_ids_are_read_from_a_comma_separated_list()
	{
		$this->assertSame(array(13, 15), wiki_only_bbcodes::parse_ids('13,15'));
		$this->assertSame(array(13, 15), wiki_only_bbcodes::parse_ids(' 13 , 15,13,0,-2,x'));
		$this->assertSame(array(), wiki_only_bbcodes::parse_ids(''));
	}

	public function test_tag_names_match_the_parser()
	{
		$this->assertSame('YOUTUBE', wiki_only_bbcodes::tag_name('youtube'));
		$this->assertSame('FONT', wiki_only_bbcodes::tag_name('font='));
	}

	public function test_save_keeps_only_custom_bbcodes()
	{
		$config = new \phpbb\config\config(array('wiki_only_bbcodes' => ''));
		$bbcodes = new class($config, $this->createMock(\phpbb\db\driver\driver_interface::class), 'phpbb_bbcodes') extends wiki_only_bbcodes {
			public function custom_bbcodes()
			{
				return array(
					array('bbcode_id' => '13', 'bbcode_tag' => 'youtube', 'bbcode_helpline' => ''),
					array('bbcode_id' => '15', 'bbcode_tag' => 'font=', 'bbcode_helpline' => ''),
				);
			}
		};

		$bbcodes->save(array(15, 99, 13, 15));
		$this->assertSame('13,15', $config['wiki_only_bbcodes']);
		$this->assertSame(array(13, 15), $bbcodes->ids());

		$bbcodes->save(array());
		$this->assertSame('', $config['wiki_only_bbcodes']);
		$this->assertSame(array(), $bbcodes->tag_names(), 'no query when nothing is wiki-only');
	}

	public function test_a_wiki_only_bbcode_is_read_only_in_wiki_articles()
	{
		$bbcodes = $this->createMock(wiki_only_bbcodes::class);
		$bbcodes->method('tag_names')->willReturn(array('YOUTUBE'));
		$listener = new \phpbbmodders\wiki\event\wikilink_listener(
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbbmodders\wiki\wiki\links::class),
			$this->createMock(\phpbbmodders\wiki\wiki\files::class),
			$this->createMock(\phpbbmodders\wiki\wiki\templates::class),
			$bbcodes
		);

		$configurator = new \s9e\TextFormatter\Configurator;
		$configurator->BBCodes->addCustom('[youtube]{URL}[/youtube]', '<a href="{URL}">{URL}</a>');
		$configurator->BBCodes->addCustom('[note]{TEXT}[/note]', '<i>{TEXT}</i>');
		$listener->configure_wikilink(array('configurator' => $configurator));
		extract($configurator->finalize());
		$wrapper = new class($parser) {
			private $parser;
			public function __construct($parser)
			{
				$this->parser = $parser;
			}
			public function get_parser()
			{
				return $this->parser;
			}
		};
		$text = '[youtube]https://example.com/v[/youtube] [note]n[/note]';

		$listener->toggle_wiki_markup(array('parser' => $wrapper));
		$xml = $parser->parse($text);
		$this->assertStringNotContainsString('<YOUTUBE', $xml, 'off in a post');
		$this->assertStringContainsString('<NOTE', $xml, 'other BBCodes still work');

		\phpbbmodders\wiki\wiki\markup::set_active(true);
		try
		{
			$listener->toggle_wiki_markup(array('parser' => $wrapper));
			$this->assertStringContainsString('<YOUTUBE', $parser->parse($text), 'on in a wiki article');
		}
		finally
		{
			\phpbbmodders\wiki\wiki\markup::set_active(false);
		}
	}

	public function test_wiki_only_buttons_are_left_out_of_other_editors()
	{
		$bbcodes = $this->createMock(wiki_only_bbcodes::class);
		$bbcodes->method('ids')->willReturn(array(13, 15));
		$listener = new \phpbbmodders\wiki\event\wikilink_listener(
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbbmodders\wiki\wiki\links::class),
			$this->createMock(\phpbbmodders\wiki\wiki\files::class),
			$this->createMock(\phpbbmodders\wiki\wiki\templates::class),
			$bbcodes
		);

		$event = new \phpbb\event\data(array('sql_ary' => array('WHERE' => 'b.display_on_posting = 1')));
		$listener->hide_wiki_only_buttons($event);
		$this->assertSame('(b.display_on_posting = 1) AND b.bbcode_id NOT IN (13, 15)', $event['sql_ary']['WHERE']);

		\phpbbmodders\wiki\wiki\markup::set_active(true);
		try
		{
			$event = new \phpbb\event\data(array('sql_ary' => array('WHERE' => 'b.display_on_posting = 1')));
			$listener->hide_wiki_only_buttons($event);
			$this->assertSame('b.display_on_posting = 1', $event['sql_ary']['WHERE'], 'the wiki editor keeps them');
		}
		finally
		{
			\phpbbmodders\wiki\wiki\markup::set_active(false);
		}
	}
}
