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
			$this->createMock(\phpbbmodders\wiki\wiki\files::class),
			$this->createMock(\phpbbmodders\wiki\wiki\templates::class)
		);

		$configurator = new \s9e\TextFormatter\Configurator;
		$listener->configure_wikilink(array('configurator' => $configurator));
		extract($configurator->finalize());

		$tags = array('WIKIHEADING', 'WIKIMAGIC', 'WIKITEMPLATE', 'WIKICATEGORY');
		foreach (\phpbbmodders\wiki\event\wikilink_listener::TABLE_ELEMENTS as $element)
		{
			$tags[] = 'html:' . $element;
		}

		foreach ($tags as $tag)
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

	public function test_a_category_mark_becomes_a_tag_with_a_normalised_name()
	{
		$xml = $this->parse('Text [[Category:old_extensions]] and [[category:Boxes|sort key]] and [[Category:  Boxes  ]].');

		$this->assertSame(3, substr_count($xml, '<WIKICATEGORY'), $xml);
		$this->assertStringContainsString('<WIKICATEGORY name="Old extensions">', $xml);
		$this->assertSame(2, substr_count($xml, '<WIKICATEGORY name="Boxes">'), $xml);
		$this->assertStringNotContainsString('<WIKILINK', $xml, 'a category is not a link');
	}

	public function test_a_category_mark_stays_text_outside_a_wiki_article_and_when_empty()
	{
		$this->assertStringNotContainsString('<WIKICATEGORY', $this->parse('[[Category:Boxes]]', false));
		$this->assertStringNotContainsString('<WIKICATEGORY', $this->parse('[[Category:]] [[Category:   ]]'));
	}

	public function test_a_category_mark_renders_as_an_empty_marker()
	{
		$this->assertSame('a <span class="wikicategory" data-wiki-category="Boxes"></span> b', $this->render('a [[Category:boxes]] b'));
	}

	public function test_template_calls_become_tags_with_their_name_and_call()
	{
		$xml = $this->parse('Hi {{Out of date}} and {{Infobox|name=Wiki|v={{Ver|1}}|[[A|b]]}} and {{  Spaced  |x}}.');

		$this->assertSame(3, substr_count($xml, '<WIKITEMPLATE'), $xml);
		$this->assertStringContainsString('<WIKITEMPLATE call="Out of date" name="Out of date">', $xml);
		$this->assertStringContainsString('<WIKITEMPLATE call="Infobox|name=Wiki|v={{Ver|1}}|[[A|b]]" name="Infobox">', $xml);
		$this->assertStringContainsString('name="Spaced"', $xml);
	}

	public function test_things_that_are_not_template_calls_stay_text()
	{
		foreach (array('{{{1}}}', '{{ }}', '{{|x}}', '{{A|x}}}', 'a {b} c', '{{', '{{A') as $text)
		{
			$this->assertStringNotContainsString('<WIKITEMPLATE', $this->parse($text), $text);
		}
	}

	public function test_parser_functions_and_magic_words_are_calls_too()
	{
		$xml = $this->parse('{{#if: {{Foo}} | a | b}} {{#expr: 2 + 3}} {{lc:Hello}} {{ucfirst:{{Foo}}}} {{PAGENAME}} {{Foo:bar|x}}');

		$this->assertSame(6, substr_count($xml, '<WIKITEMPLATE'), $xml);
		$this->assertStringContainsString('name="#if"', $xml);
		$this->assertStringContainsString('call="#expr: 2 + 3"', $xml);
		$this->assertStringContainsString('name="lc"', $xml);
		$this->assertStringContainsString('name="PAGENAME"', $xml);
	}

	public function test_a_parameter_inside_a_call_does_not_end_it()
	{
		$xml = $this->parse('{{A|{{{1}}}}} {{B|{{C|{{D}}}}}}');

		$this->assertSame(2, substr_count($xml, '<WIKITEMPLATE'), $xml);
		$this->assertStringContainsString('call="A|{{{1}}}"', $xml);
		$this->assertStringContainsString('call="B|{{C|{{D}}}}"', $xml);
	}

	public function test_template_calls_stay_plain_text_outside_a_wiki_article()
	{
		$this->assertStringNotContainsString('<WIKITEMPLATE', $this->parse('{{Out of date}}', false));
	}

	public function test_a_template_call_renders_as_a_placeholder()
	{
		$html = $this->render('{{Out of date|reason=x "quoted"}}');

		$this->assertStringContainsString('<template class="wikitemplate" data-wiki-template="Out of date" data-wiki-call="Out of date|reason=x &quot;quoted&quot;"></template>', $html);
	}

	private const TABLE = "<table>\n<caption>Versions</caption>\n<tr><th>Name</th><th colspan=\"2\">Notes</th></tr>\n<tr><td>[b]Wiki[/b]</td><td rowspan=\"2\">1.1.0</td><td>[[Main Page]]</td></tr>\n</table>";

	public function test_an_html_table_becomes_tags_in_a_wiki_article()
	{
		$xml = $this->parse(self::TABLE);

		foreach (array('table', 'caption', 'tr', 'th', 'td') as $element)
		{
			$this->assertStringContainsString('<html:' . $element, $xml, $element);
		}
		$this->assertStringContainsString('colspan="2"', $xml);
		$this->assertStringContainsString('rowspan="2"', $xml);
		$this->assertStringContainsString('<WIKILINK name="Main Page">', $xml, 'wiki links work inside a cell');
	}

	public function test_an_html_table_stays_plain_text_outside_a_wiki_article()
	{
		$xml = $this->parse(self::TABLE, false);

		$this->assertStringNotContainsString('<html:', $xml);
		$this->assertStringContainsString('&lt;table&gt;', $xml);
	}

	public function test_only_the_table_elements_and_attributes_are_allowed()
	{
		$xml = $this->parse('<script>alert(1)</script><iframe src="x"></iframe><table><tr><td onclick="x()" style="color:red" class="a">cell</td></tr></table>');

		$this->assertStringNotContainsString('<html:script', $xml);
		$this->assertStringNotContainsString('<html:iframe', $xml);
		$this->assertStringContainsString('<html:td>', $xml, 'the cell has none of the attributes');
		$this->assertStringNotContainsString('onclick', str_replace('&lt;', '', preg_replace('#<s>.*?</s>#s', '', $xml)));
	}

	public function test_a_table_renders_as_html_without_stray_line_breaks()
	{
		$html = $this->render(self::TABLE);

		$this->assertStringContainsString('<table>', $html);
		$this->assertStringContainsString('<th colspan="2">Notes</th>', $html);
		$this->assertStringContainsString('<td rowspan="2">1.1.0</td>', $html);
		$this->assertStringNotContainsString('<script', $html);
	}

	private function render(string $text): string
	{
		$listener = new \phpbbmodders\wiki\event\wikilink_listener(
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$this->createMock(\phpbbmodders\wiki\wiki\links::class),
			$this->createMock(\phpbbmodders\wiki\wiki\files::class),
			$this->createMock(\phpbbmodders\wiki\wiki\templates::class)
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

	public function test_entities_in_captured_text_are_decoded_only_while_wiki_markup_is_on()
	{
		$text = 'Head&mdash;ing &nbsp;x &#32;y &lt;b&gt; &amp;lt; &bogus; &#10;';
		try
		{
			// A forum post: left as written
			markup::set_active(false);
			$this->assertSame($text, markup::decode_entities($text));

			// Text as written in an article: decoded, unknown and control entities kept
			markup::set_active(true);
			$this->assertSame("Head\u{2014}ing \u{00A0}x  y <b> &lt; &bogus; &#10;", markup::decode_entities($text));

			// Template text, already decoded once on its way to the parser: left alone
			markup::set_entities(false);
			$this->assertSame($text, markup::decode_entities($text));
		}
		finally
		{
			markup::set_active(false);
			markup::set_entities(true);
		}
	}

	public function test_a_non_breaking_space_in_an_article_name_is_a_space()
	{
		markup::set_active(true);
		try
		{
			$this->assertSame('Main Page', markup::decode_entities_in_name('Main&nbsp;Page'));
			$this->assertSame("A\u{2014}B", markup::decode_entities_in_name('A&mdash;B'));
		}
		finally
		{
			markup::set_active(false);
		}
	}
}
