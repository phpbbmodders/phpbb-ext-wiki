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

use phpbbmodders\wiki\wiki\markup;

/**
 * Headings, section anchors, the table of contents and the redirect shortcut,
 * plus the parser configuration they rely on (read with the real s9e library).
 */
class markup_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider anchors */
	public function test_an_anchor_is_made_from_the_heading_text(string $text, string $expected)
	{
		$this->assertSame($expected, markup::anchor($text));
	}

	public static function anchors(): array
	{
		return array(
			'plain'			=> array('History', 'History'),
			'spaces'		=> array('Early life and career', 'Early_life_and_career'),
			'edge spaces'	=> array('  Padded  ', 'Padded'),
			'punctuation'	=> array('Tom & Jerry: "the end"?', 'Tom__Jerry:_the_end'),
			'kept marks'	=> array('Version 1.2 (beta), part-two', 'Version_1.2_(beta),_part-two'),
			'unicode'		=> array('Übersicht ✓', 'Übersicht'),
			'nothing left'	=> array('!!!', 'section'),
			'empty'			=> array('', 'section'),
		);
	}

	/** @dataProvider redirects */
	public function test_a_redirect_first_line_names_its_target(string $text, string $expected)
	{
		$this->assertSame($expected, markup::redirect_target($text));
	}

	public static function redirects(): array
	{
		return array(
			'plain'				=> array('#REDIRECT [[Main Page]]', 'Main Page'),
			'lower case'		=> array('#redirect [[Main Page]]', 'Main Page'),
			'colon'				=> array('#REDIRECT: [[Main Page]]', 'Main Page'),
			'leading space'		=> array("  \n#REDIRECT [[Main Page]]\nextra", 'Main Page'),
			'section dropped'	=> array('#REDIRECT [[Main Page#History]]', 'Main Page'),
			'piped'				=> array('#REDIRECT [[Main Page|home]]', 'Main Page'),
			'not first'			=> array("Text first\n#REDIRECT [[Main Page]]", ''),
			'no link'			=> array('#REDIRECT nowhere', ''),
			'ordinary text'		=> array('Hello', ''),
		);
	}

	private function heading(int $level, string $title): string
	{
		return '<h' . $level . ' class="wikiheading">' . $title . '</h' . $level . '>';
	}

	public function test_text_without_headings_or_markers_is_left_alone()
	{
		$html = '<p>Hello</p>';
		$this->assertSame($html, markup::finish($html, 'Contents'));
	}

	public function test_headings_get_anchors_and_repeats_are_numbered()
	{
		$html = $this->heading(2, 'Intro') . '<p>x</p>' . $this->heading(2, 'Intro') . $this->heading(3, 'A &amp; B');
		$out = markup::finish($html, 'Contents');

		$this->assertStringContainsString('<h2 id="Intro" class="wikiheading">Intro</h2>', $out);
		$this->assertStringContainsString('<h2 id="Intro_2" class="wikiheading">Intro</h2>', $out);
		$this->assertStringContainsString('<h3 id="A__B" class="wikiheading">A &amp; B</h3>', $out);
		$this->assertStringNotContainsString('wikitoc', $out, 'three headings do not bring up a table of contents');
	}

	public function test_four_headings_bring_up_the_table_of_contents_before_the_first_one()
	{
		$html = '<p>Intro</p>' . $this->heading(2, 'One') . $this->heading(3, 'One A') . $this->heading(3, 'One B') . $this->heading(2, 'Two');
		$out = markup::finish($html, 'Contents');

		$this->assertSame(1, substr_count($out, 'class="wikitoc"'));
		$this->assertLessThan(strpos($out, '<h2 id="One"'), strpos($out, 'class="wikitoc"'), 'the box comes before the first heading');
		$this->assertGreaterThan(strpos($out, '<p>Intro</p>'), strpos($out, 'class="wikitoc"'), 'and after the intro');
		$this->assertStringContainsString('<strong>Contents</strong>', $out);
		$this->assertStringContainsString('<a href="#One_A"><span class="wikitoc-number">1.1</span> One A</a>', $out);
		$this->assertStringContainsString('<a href="#One_B"><span class="wikitoc-number">1.2</span> One B</a>', $out);
		$this->assertStringContainsString('<a href="#Two"><span class="wikitoc-number">2</span> Two</a>', $out);
	}

	public function test_the_table_of_contents_markup_is_balanced_when_levels_jump()
	{
		$html = $this->heading(2, 'A') . $this->heading(4, 'B') . $this->heading(3, 'C') . $this->heading(2, 'D');
		$out = markup::finish($html, 'Contents');

		$dom = new \DOMDocument();
		$this->assertTrue($dom->loadXML('<root>' . $out . '</root>'), 'the box must be well-formed');
		$this->assertSame(substr_count($out, '<ul>'), substr_count($out, '</ul>'));
		$this->assertSame(substr_count($out, '<li>'), substr_count($out, '</li>'));
	}

	public function test_notoc_hides_the_box_and_toc_places_it()
	{
		$four = $this->heading(2, 'A') . $this->heading(2, 'B') . $this->heading(2, 'C') . $this->heading(2, 'D');
		$notoc = markup::finish('<span class="wikimagic" data-wiki-magic="NOTOC"></span>' . $four, 'Contents');
		$this->assertStringNotContainsString('wikitoc', $notoc);
		$this->assertStringNotContainsString('wikimagic', $notoc, 'the marker is removed');

		$placed = markup::finish($this->heading(2, 'A') . '<p>middle</p><span class="wikimagic" data-wiki-magic="TOC"></span><p>after</p>' . $this->heading(2, 'B'), 'Contents');
		$this->assertStringContainsString('class="wikitoc"', $placed, '__TOC__ shows the box for two headings');
		$this->assertGreaterThan(strpos($placed, '<p>middle</p>'), strpos($placed, 'class="wikitoc"'));
		$this->assertLessThan(strpos($placed, '<p>after</p>'), strpos($placed, 'class="wikitoc"'));
		$this->assertSame(1, substr_count($placed, 'class="wikitoc"'));
	}

	public function test_forcetoc_shows_the_box_for_a_single_heading()
	{
		$out = markup::finish('<span class="wikimagic" data-wiki-magic="FORCETOC"></span>' . $this->heading(2, 'Only'), 'Contents');
		$this->assertStringContainsString('class="wikitoc"', $out);
	}

	public function test_wiki_markup_is_off_unless_switched_on()
	{
		$this->assertFalse(markup::is_active());
		markup::set_active(true);
		$this->assertTrue(markup::is_active());
		markup::set_active(false);
		$this->assertFalse(markup::is_active());
	}

	private function parse(string $text, bool $wiki_markup = true): string
	{
		$listener = new \phpbbmodders\wiki\event\wikilink_listener(
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbbmodders\wiki\wiki\links::class),
			$this->createMock(\phpbbmodders\wiki\wiki\files::class)
		);

		$configurator = new \s9e\TextFormatter\Configurator;
		$listener->configure_wikilink(array('configurator' => $configurator));
		extract($configurator->finalize());

		foreach (array('WIKIHEADING', 'WIKIMAGIC') as $tag)
		{
			$wiki_markup ? $parser->enableTag($tag) : $parser->disableTag($tag);
		}

		return $parser->parse($text);
	}

	public function test_headings_of_each_level_become_tags_with_their_marks()
	{
		$xml = $this->parse("== Two ==\n=== Three ===\n====== Six ======");

		$this->assertSame(3, substr_count($xml, '<WIKIHEADING'), $xml);
		$this->assertStringContainsString('<WIKIHEADING marks="==" title="Two">', $xml);
		$this->assertStringContainsString('<WIKIHEADING marks="===" title="Three">', $xml);
		$this->assertStringContainsString('<WIKIHEADING marks="======" title="Six">', $xml);
	}

	public function test_a_line_that_is_not_a_heading_stays_text()
	{
		foreach (array('a == b', '== unfinished', "text\n==x==y", '= one =', '=======seven=======') as $text)
		{
			$this->assertStringNotContainsString('<WIKIHEADING', $this->parse($text), $text);
		}
	}

	public function test_headings_are_text_when_wiki_markup_is_off()
	{
		$xml = $this->parse("== Heading ==\n__TOC__", false);

		$this->assertStringNotContainsString('<WIKIHEADING', $xml);
		$this->assertStringNotContainsString('<WIKIMAGIC', $xml);
	}

	public function test_magic_words_become_tags()
	{
		$xml = $this->parse("__NOTOC__ and __TOC__ and __FORCETOC__ and __toc__");

		$this->assertSame(3, substr_count($xml, '<WIKIMAGIC'), $xml);
		$this->assertStringContainsString('word="NOTOC"', $xml);
		$this->assertStringContainsString('word="FORCETOC"', $xml);
	}

	public function test_section_links_carry_their_section()
	{
		$xml = $this->parse('[[Article#Early life]] [[Article#History|the history]] [[#Local part]] [[Plain]] [[Plain|text]]');

		$this->assertSame(5, substr_count($xml, '<WIKILINK'), $xml);
		$this->assertStringContainsString('<WIKILINK name="Article" section="Early life">', $xml);
		$this->assertStringContainsString('<WIKILINK name="Article" section="History" text="the history">', $xml);
		$this->assertStringContainsString('section="Local part"', $xml);
		$this->assertStringContainsString('<WIKILINK name="Plain">', $xml);
	}

	public function test_broken_links_are_still_not_links()
	{
		foreach (array('[[]]', '[[|text]]', '[[ ]]', '[[a#]]') as $text)
		{
			$xml = $this->parse($text);
			$this->assertStringNotContainsString('<WIKILINK name=""', $xml, $text);
		}
	}

	public function test_file_embeds_are_still_their_own_tag()
	{
		$xml = $this->parse('[[File:a.png]] [[File:b.zip|the zip]] [[Other]]');

		$this->assertSame(2, substr_count($xml, '<WIKIFILE'));
		$this->assertSame(1, substr_count($xml, '<WIKILINK'));
	}

	private function render(string $text): string
	{
		$listener = new \phpbbmodders\wiki\event\wikilink_listener(
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbbmodders\wiki\wiki\links::class),
			$this->createMock(\phpbbmodders\wiki\wiki\files::class)
		);

		$configurator = new \s9e\TextFormatter\Configurator;
		// The PHP renderer, like phpBB's own: the default XSLT one needs ext-xsl, which some CI runners lack
		$configurator->rendering->setEngine('PHP');
		$listener->configure_wikilink(array('configurator' => $configurator));
		extract($configurator->finalize());

		return $renderer->render($parser->parse($text));
	}

	/** @dataProvider link_texts */
	public function test_a_link_shows_a_readable_text(string $text, string $expected)
	{
		$html = $this->render($text);

		$this->assertMatchesRegularExpression('/<a class="wikilink"[^>]*>' . preg_quote($expected, '/') . '<\/a>/', $html, $html);
	}

	public static function link_texts(): array
	{
		return array(
			'article'				=> array('[[Main Page]]', 'Main Page'),
			'article with section'	=> array('[[Main Page#History]]', 'Main Page#History'),
			'same-page section'		=> array('[[#History]]', '#History'),
			'own text'				=> array('[[Main Page#History|the history]]', 'the history'),
			'own text, same page'	=> array('[[#History|below]]', 'below'),
		);
	}
}
