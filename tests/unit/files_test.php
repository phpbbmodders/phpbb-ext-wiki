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

use phpbbmodders\wiki\wiki\files;

/**
 * Uploaded files: the names they are given, what the browser may show, and the
 * [[File:name]] syntax.
 */
class files_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider names */
	public function test_a_name_is_made_safe_for_the_file_syntax_and_for_urls(string $sent, string $expected)
	{
		$this->assertSame($expected, files::clean_name($sent));
	}

	public static function names(): array
	{
		return array(
			'plain'				=> array('Diagram.png', 'Diagram.png'),
			'spaces kept'		=> array('my diagram.png', 'my diagram.png'),
			'path removed'		=> array('C:\\Users\\me\\Diagram.png', 'Diagram.png'),
			'unix path removed'	=> array('../../etc/passwd.png', 'passwd.png'),
			'link characters'	=> array('a[b]|c<d>"e#f?g%h.png', 'a_b_c_d_e_f_g_h.png'),
			'control characters'=> array("a\x00b\nc.png", 'a_b_c.png'),
			'edge dots'			=> array('  ..hidden.png.. ', 'hidden.png'),
			'only junk'			=> array('...', ''),
			'long name'			=> array(str_repeat('a', 300) . '.png', str_repeat('a', 255)),
			'unicode kept'		=> array('Übersicht ✓.png', 'Übersicht ✓.png'),
		);
	}

	public function test_only_real_web_images_are_shown_inside_the_browser()
	{
		foreach (array('image/png', 'image/jpeg', 'image/gif', 'image/webp') as $type)
		{
			$this->assertTrue(files::is_inline_image($type), $type);
		}

		foreach (array('image/svg+xml', 'text/html', 'application/pdf', 'application/octet-stream', 'IMAGE/PNG', '') as $type)
		{
			$this->assertFalse(files::is_inline_image($type), $type);
		}
	}

	public function test_the_article_being_rendered_is_remembered_and_can_be_cleared()
	{
		files::set_context('Some_Article');
		$this->assertSame('Some_Article', files::get_context());

		files::set_context(null);
		$this->assertNull(files::get_context());
	}

	private function parse(string $text): string
	{
		$listener = new \phpbbmodders\wiki\event\wikilink_listener(
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbbmodders\wiki\wiki\links::class),
			$this->createMock(files::class),
			$this->createMock(\phpbbmodders\wiki\wiki\templates::class)
		);

		$configurator = new \s9e\TextFormatter\Configurator;
		$listener->configure_wikilink(array('configurator' => $configurator));
		extract($configurator->finalize());

		return $parser->parse($text);
	}

	public function test_a_file_embed_is_its_own_tag_and_not_a_link()
	{
		$xml = $this->parse('See [[File:Diagram.png]] and [[File:Diagram.png|the diagram]].');

		$this->assertSame(2, substr_count($xml, '<WIKIFILE'), $xml);
		$this->assertStringContainsString('<WIKIFILE name="Diagram.png"', $xml);
		$this->assertStringContainsString('alt="the diagram"', $xml);
		$this->assertStringNotContainsString('<WIKILINK', $xml);
	}

	public function test_an_ordinary_article_link_is_still_a_link()
	{
		$xml = $this->parse('See [[Other article]] and [[Other article|this]], and [[Files overview]].');

		$this->assertSame(3, substr_count($xml, '<WIKILINK'), $xml);
		$this->assertStringNotContainsString('<WIKIFILE', $xml);
	}
}
