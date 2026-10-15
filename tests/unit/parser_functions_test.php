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

/**
 * {{#if:}}, {{#switch:}}, {{#expr:}}, templates inside templates, and the rest of what
 * the template service works out in text, with the template pages held in an array.
 */
class parser_functions_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbbmodders\wiki\wiki\templates */
	protected $templates;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\config\config */
	protected $config;

	protected function setUp(): void
	{
		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(function ($key, ...$args) {
			return $key . ($args ? '(' . implode(',', $args) . ')' : '');
		});

		$config = new \phpbb\config\config(array('sitename' => 'Test Board', 'board_timezone' => 'Pacific/Auckland'));
		$this->language = $language;
		$this->config = $config;

		$this->templates = new class($config, $this->createMock(\phpbb\db\driver\driver_interface::class), $language, 'phpbb_wiki_article') extends \phpbbmodders\wiki\wiki\templates {
			/** @var array Template page text by article name */
			public $pages = array(
				'Template:Hello'	=> 'Hi {{{1|you}}}',
				'Template:Count'	=> '{{#if: {{#expr: {{{1}}} > 0}} | {{{1}}} {{Count|{{#expr: {{{1}}} - 1}}}} | }}',
				'Template:Loop'		=> 'again {{Loop}}',
				'Template:Ping'		=> '{{Pong}}',
				'Template:Pong'		=> '{{Ping}}',
				'Template:Doc'		=> '<noinclude>Docs</noinclude>Body{{{x|}}}',
				'Template:Big'		=> null,
				'Template:Mid'		=> '{{Big}}{{Big}}{{Big}}{{Big}}{{Big}}{{Big}}{{Big}}{{Big}}{{Big}}{{Big}}',
				'Template:Wide'		=> '{{Mid}}{{Mid}}{{Mid}}{{Mid}}{{Mid}}{{Mid}}{{Mid}}{{Mid}}{{Mid}}{{Mid}}',
				'Template:Deep'		=> '{{#if: {{{1|}}} | {{Deep|{{{1}}}}} | end}}',
				'Template:Edge'		=> '[{{{1}}}][{{{n|}}}]',
				'Template:Show'		=> '[{{{issue|}}}]',
				// A parameter holding a branch that ends in a run of closing braces, then another call
				'Template:Pick'		=> '{{#switch: a | {{{k|{{{1|a}}}}}} = hit | #default = miss}}',
				'Template:Runs'		=> '{{Show|issue={{#if:{{{1|}}}|with: {{{w|{{{1}}}}}}}}{{#if:{{{p|}}}||.}}}}',
				'Main Page'			=> 'x',
			);

			/** @var string|null */
			public $article = 'Template:Hello';

			protected function source($article, $follow_redirect = true)
			{
				return $this->pages[$article] ?? null;
			}

			protected function page_exists($article)
			{
				return isset($this->pages[$article]);
			}

			protected function current_article()
			{
				return $this->article;
			}

			/** @var array The live version the REVISION words read */
			public $revision = array('article_id' => 42, 'article_last_edit' => 1772892309, 'username' => 'Editor');

			protected function live_revision($article)
			{
				return $this->revision;
			}

			/** Saturday 7 March 2026, 14:05:09 UTC */
			protected function now()
			{
				return gmmktime(14, 5, 9, 3, 7, 2026);
			}
		};
	}

	private function run_text(string $text): string
	{
		return $this->templates->expand_text($text);
	}

	public function test_if_picks_the_branch_by_whether_the_test_is_empty()
	{
		$this->assertSame('yes', $this->run_text('{{#if: x | yes | no}}'));
		$this->assertSame('no', $this->run_text('{{#if:   | yes | no}}'));
		$this->assertSame('', $this->run_text('{{#if: | yes}}'));
		$this->assertSame('yes', $this->run_text('{{#if: {{lc:A}} | yes | no}}'));
	}

	public function test_a_run_of_closing_braces_closes_the_parameters_and_calls_it_should()
	{
		$this->assertSame('[.]', $this->run_text('{{Runs}}'));
		$this->assertSame('[with: x.]', $this->run_text('{{Runs|x}}'));
		$this->assertSame('[with: y.]', $this->run_text('{{Runs|x|w=y}}'));
		$this->assertSame('[with: y]', $this->run_text('{{Runs|x|w=y|p=1}}'));
		// The = after a case that ends in a run of braces still separates it from its result
		$this->assertSame('hit', $this->run_text('{{Pick}}'));
		$this->assertSame('miss', $this->run_text('{{Pick|z}}'));
	}

	public function test_text_around_a_call_is_kept()
	{
		$this->assertSame('a yes b', $this->run_text('a {{#if: x | yes }} b'));
		$this->assertSame('{{ unclosed', $this->run_text('{{ unclosed'));
		$this->assertSame('{{{1}}} stays', $this->run_text('{{{1}}} stays'));
	}

	public function test_ifeq_compares_numbers_as_numbers_and_the_rest_as_text()
	{
		$this->assertSame('same', $this->run_text('{{#ifeq: 1.0 | 1 | same | diff}}'));
		$this->assertSame('diff', $this->run_text('{{#ifeq: a | A | same | diff}}'));
		$this->assertSame('same', $this->run_text('{{#ifeq: abc | abc | same | diff}}'));
	}

	public function test_switch_finds_its_case_and_falls_through()
	{
		$this->assertSame('B', $this->run_text('{{#switch: b | a = A | b = B | #default = D}}'));
		$this->assertSame('XY', $this->run_text('{{#switch: x | y | x = XY | z = Z}}'));
		$this->assertSame('D', $this->run_text('{{#switch: q | a = A | #default = D | b = B}}'));
		$this->assertSame('last', $this->run_text('{{#switch: q | a = A | last}}'));
		$this->assertSame('', $this->run_text('{{#switch: q | a = A}}'));
		$this->assertSame('two', $this->run_text('{{#switch: 2.0 | 1 = one | 2 = two}}'));
		$this->assertSame('1', $this->run_text('{{#switch: [[a|b]] | [[a|b]] = 1 | 2}}'));
	}

	public function test_ifexist_checks_the_article()
	{
		$this->assertSame('there', $this->run_text('{{#ifexist: Main Page | there | gone}}'));
		$this->assertSame('gone', $this->run_text('{{#ifexist: Nowhere | there | gone}}'));
		$this->assertSame('gone', $this->run_text('{{#ifexist: | there | gone}}'));
	}

	public function test_expr_and_ifexpr()
	{
		$this->assertSame('14', $this->run_text('{{#expr: 2 + 3 * 4}}'));
		$this->assertSame('big', $this->run_text('{{#ifexpr: 3 > 2 | big | small}}'));
		$this->assertSame('small', $this->run_text('{{#ifexpr: 1 > 2 | big | small}}'));
		$this->assertStringContainsString('WIKI_EXPR_ERROR(WIKI_EXPR_DIV_ZERO)', $this->run_text('{{#expr: 1 / 0}}'));
		$this->assertStringContainsString('WIKI_EXPR_ERROR', $this->run_text('{{#ifexpr: 1 / 0 | a | b}}'));
		$this->assertSame('', $this->run_text('{{#expr: }}'));
	}

	public function test_an_error_is_bold_red_text_with_no_brackets_of_its_own()
	{
		$out = $this->run_text('{{#foo: x}}');

		$this->assertStringStartsWith('[color=red][b]', $out);
		$this->assertStringContainsString('WIKI_TEMPLATE_UNKNOWN_FUNCTION(#foo)', $out);
		$this->assertStringEndsWith('[/b][/color]', $out);
	}

	public function test_text_functions()
	{
		$this->assertSame('hello world', $this->run_text('{{lc: Hello WORLD }}'));
		$this->assertSame('HELLO', $this->run_text('{{uc:hello}}'));
		$this->assertSame('hELLO', $this->run_text('{{lcfirst:HELLO}}'));
		$this->assertSame('Hello', $this->run_text('{{ucfirst:hello}}'));
		$this->assertSame('Écrit', $this->run_text('{{ucfirst:écrit}}'));
	}

	public function test_magic_words_name_the_article_being_shown()
	{
		$this->templates->article = 'Template:Hello';
		$this->assertSame('Template:Hello', $this->run_text('{{FULLPAGENAME}}'));
		$this->assertSame('Hello', $this->run_text('{{PAGENAME}}'));
		$this->assertSame('Template', $this->run_text('{{NAMESPACE}}'));

		$this->templates->article = 'Main Page';
		$this->assertSame('Main Page', $this->run_text('{{PAGENAME}}'));
		$this->assertSame('', $this->run_text('{{NAMESPACE}}'));

		$this->templates->article = null;
		$this->assertSame('', $this->run_text('{{PAGENAME}}'));
	}

	public function test_a_template_gets_its_parameters_worked_out_first()
	{
		$this->assertSame('Hi Bob', $this->run_text('{{Hello|Bob}}'));
		$this->assertSame('Hi you', $this->run_text('{{Hello}}'));
		$this->assertSame('Hi bob', $this->run_text('{{Hello|{{lc:BOB}}}}'));
		$this->assertSame('Hi Bob', $this->run_text('{{Template:Hello|Bob}}'));
		$this->assertSame('Hi Bob', $this->run_text('{{hello|Bob}}'));
	}

	public function test_a_missing_template_becomes_a_link_to_it()
	{
		$this->assertSame('[[Template:Nope]]', $this->run_text('{{Nope}}'));
	}

	public function test_noinclude_text_is_left_out()
	{
		$this->assertSame('Body!', $this->run_text('{{Doc|x=!}}'));
	}

	public function test_only_the_chosen_branch_is_worked_out()
	{
		// The branch that is not taken holds a loop, which is never reached
		$this->assertSame('ok', $this->run_text('{{#if: | {{Loop}} | ok}}'));
		$this->assertSame('ok', $this->run_text('{{#switch: a | a = ok | b = {{Loop}}}}'));
		$this->assertSame('ok', $this->run_text('{{#ifeq: 1 | 1 | ok | {{Loop}}}}'));
	}

	public function test_a_template_that_includes_itself_is_stopped()
	{
		$this->assertStringContainsString('WIKI_TEMPLATE_LOOP(Loop)', $this->run_text('{{Loop}}'));
		$this->assertStringContainsString('WIKI_TEMPLATE_LOOP', $this->run_text('{{Ping}}'));
	}

	public function test_templates_inside_templates_stop_at_a_depth()
	{
		$this->assertSame('end', $this->run_text('{{Deep}}'));
		$this->assertStringContainsString('WIKI_TEMPLATE_', $this->run_text('{{Deep|1}}'));
	}

	public function test_iferror_tells_a_working_call_from_a_failed_one()
	{
		$this->assertSame('bad', $this->run_text('{{#iferror: {{#expr: 1/0}} | bad | good}}'));
		$this->assertSame('good', $this->run_text('{{#iferror: {{#expr: 1+1}} | bad | good}}'));
		$this->assertSame('2', $this->run_text('{{#iferror: {{#expr: 1+1}} | bad}}'), 'without a third part the test itself comes back');
		$this->assertSame('', $this->run_text('{{#iferror: {{#expr: 1/0}}}}'));
		$this->assertSame('bad', $this->run_text('{{#iferror: {{#nosuch: x}} | bad | good}}'));
		$this->assertSame('bad', $this->run_text('{{#iferror: {{Loop}} | bad | good}}'));
		$this->assertSame('good', $this->run_text('{{#iferror: fine | bad | good}}'));
	}

	public function test_time_formats_a_date()
	{
		$this->assertSame('2026-03-07', $this->run_text('{{#time: Y-m-d}}'));
		$this->assertSame('7 March 2026, 14:05', $this->run_text('{{#time: j F Y, H:i}}'));
		$this->assertSame('1 May 2024', $this->run_text('{{#time: j F Y | 2024-05-01}}'));
		$this->assertSame('Wednesday', $this->run_text('{{#time: l | 2026-03-04}}'));
		$this->assertSame('2026', $this->run_text('{{#time: Y | @1772903109}}'));
		$this->assertSame('on 7  at Y', $this->run_text('{{#time: "on" j " at Y"}}'), 'quoted text and a backslash come out as written');
		$this->assertSame('7 d', $this->run_text('{{#time: j \\d}}'));
		$this->assertSame('Saturday', $this->run_text('{{#time: l | now}}'));
		$this->assertStringContainsString('WIKI_TIME_INVALID', $this->run_text('{{#time: Y | not a date at all}}'));
	}

	public function test_string_functions()
	{
		$this->assertSame('5', $this->run_text('{{#len: hello}}'));
		$this->assertSame('3', $this->run_text('{{#len: héé}}'));
		$this->assertSame('2', $this->run_text('{{#pos: hello | l}}'));
		$this->assertSame('', $this->run_text('{{#pos: hello | z}}'));
		$this->assertSame('3', $this->run_text('{{#pos: hello | l | 3}}'));
		$this->assertSame('3', $this->run_text('{{#rpos: hello | l}}'));
		$this->assertSame('-1', $this->run_text('{{#rpos: hello | z}}'));
		$this->assertSame('ell', $this->run_text('{{#sub: hello | 1 | 3}}'));
		$this->assertSame('llo', $this->run_text('{{#sub: hello | 2}}'));
		$this->assertSame('lo', $this->run_text('{{#sub: hello | -2}}'));
		$this->assertSame('he', $this->run_text('{{#sub: hello | 0 | -3}}'));
		$this->assertSame('heLLo', $this->run_text('{{#replace: hello | l | L}}'));
		$this->assertSame('heo', $this->run_text('{{#replace: hello | l}}'));
		$this->assertSame('hello', $this->run_text('{{#replace: hello | | x}}'));
		$this->assertSame('b', $this->run_text('{{#explode: a,b,c | , | 1}}'));
		$this->assertSame('c', $this->run_text('{{#explode: a,b,c | , | -1}}'));
		$this->assertSame('', $this->run_text('{{#explode: a,b,c | , | 9}}'));
		$this->assertSame('b,c', $this->run_text('{{#explode: a,b,c | , | 1 | 2}}'));
	}

	public function test_an_empty_search_text_is_a_space()
	{
		$this->assertSame('a&nbsp;b&nbsp;c', $this->run_text('{{#replace: a b c || &nbsp;}}'));
		$this->assertSame('a_b_c', $this->run_text('{{#replace: a b c | | _}}'), 'a space written there is trimmed away, which leaves the space too');
		$this->assertSame('abc', $this->run_text('{{#replace: a b c }}'));
		$this->assertSame('1', $this->run_text('{{#pos: a b c | }}'));
		$this->assertSame('3', $this->run_text('{{#rpos: a b c | }}'));
		$this->assertSame('b', $this->run_text('{{#explode: a b c | | 1}}'));
		$this->assertSame('a b c', $this->run_text('{{#replace: a b c | &#32; | _}}'), 'as in MediaWiki, the search text is matched as written, not as an entity');
	}

	/**
	 * On a board the text of a call reaches the template service with entities as written
	 * (see templates::expand_all()), so &#32; is not whitespace to the trim and is shown as
	 * a space once the result is rendered, as on MediaWiki.
	 */
	public function test_an_entity_space_survives_the_trim_of_a_result()
	{
		$this->assertSame('AandB', $this->run_text('A{{#if: x | and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#if: x |&#32;and}}B'));
		$this->assertSame('Aand&#32;B', $this->run_text('A{{#if: | x | and&#32;}}B'));
		$this->assertSame('A&#10;andB', $this->run_text('A{{#if: x |&#10;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#ifeq: 1 | 1 |&#32;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#switch: a | a =&#32;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#switch: z | a = no |&#32;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#switch: z | a = no | #default =&#32;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#ifexpr: 1 > 0 |&#32;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#iferror: x | no |&#32;and}}B'));
		$this->assertSame('A&#32;andB', $this->run_text('A{{#iferror:&#32;and}}B'));
	}

	public function test_named_parameters_are_trimmed_and_positional_ones_are_not()
	{
		$this->assertSame('[ a ][b]', $this->run_text('{{Edge| a | n = b }}'));
		$this->assertSame('[ a ][&#32;b]', $this->run_text('{{Edge| a | n = &#32;b }}'));
	}

	public function test_padding_and_encoding()
	{
		$this->assertSame('00042', $this->run_text('{{padleft: 42 | 5}}'));
		$this->assertSame('__xyz', $this->run_text('{{padleft: xyz | 5 | _}}'));
		$this->assertSame('xyz-=', $this->run_text('{{padright: xyz | 5 | -=}}'));
		$this->assertSame('longer', $this->run_text('{{padleft: longer | 3}}'));
		$this->assertSame('a+b%26c', $this->run_text('{{urlencode: a b&c}}'));
		$this->assertSame('Some_Heading', $this->run_text('{{anchorencode: Some Heading}}'));
	}

	public function test_date_words_and_the_site_name()
	{
		$this->assertSame('2026', $this->run_text('{{CURRENTYEAR}}'));
		$this->assertSame('03', $this->run_text('{{CURRENTMONTH}}'));
		$this->assertSame('March', $this->run_text('{{CURRENTMONTHNAME}}'));
		$this->assertSame('Mar', $this->run_text('{{CURRENTMONTHABBREV}}'));
		$this->assertSame('7', $this->run_text('{{CURRENTDAY}}'));
		$this->assertSame('07', $this->run_text('{{CURRENTDAY2}}'));
		$this->assertSame('Saturday', $this->run_text('{{CURRENTDAYNAME}}'));
		$this->assertSame('6', $this->run_text('{{CURRENTDOW}}'));
		$this->assertSame('14:05', $this->run_text('{{CURRENTTIME}}'));
		$this->assertSame('14', $this->run_text('{{CURRENTHOUR}}'));
		$this->assertSame('20260307140509', $this->run_text('{{CURRENTTIMESTAMP}}'));
		$this->assertSame('Test Board', $this->run_text('{{SITENAME}}'));
	}

	public function test_an_upper_case_name_that_is_not_a_magic_word_is_still_a_template()
	{
		$this->templates->pages['Template:INFOBOX'] = 'box {{{1}}}';

		$this->assertSame('box x', $this->run_text('{{INFOBOX|x}}'));
		$this->assertSame('[[Template:NOTHING]]', $this->run_text('{{NOTHING}}'));
	}

	public function test_a_template_that_fans_out_is_cut_off_by_the_output_limit()
	{
		$this->templates->pages['Template:Big'] = str_repeat('a', 100000);

		$start = microtime(true);
		$out = $this->run_text('{{Wide}}');

		$this->assertLessThan(2.0, microtime(true) - $start, 'it stops early');
		$this->assertStringContainsString('WIKI_TEMPLATE_TOO_DEEP', $out);
		$this->assertLessThanOrEqual(\phpbbmodders\wiki\wiki\templates::MAX_LENGTH, strlen($out));
	}

	public function test_the_limits_count_all_the_calls_of_one_article_together()
	{
		// Many calls that each do a little add up to the limit; the counters are not reset between them
		$out = $this->run_text(str_repeat('{{#expr: 1+1}} ', 2500));

		$this->assertStringContainsString('WIKI_TEMPLATE_TOO_DEEP', $out);
	}

	public function test_rows_and_cells_are_parsed_inside_a_table_that_is_taken_off_again()
	{
		$class = \phpbbmodders\wiki\wiki\templates::class;

		$this->assertSame(array('<table>', '</table>'), $class::table_wrapper('<tr><td>x</td></tr>'));
		$this->assertSame(array('<table>', '</table>'), $class::table_wrapper("\n <TBODY>"));
		$this->assertSame(array('<table><tr>', '</tr></table>'), $class::table_wrapper('<td>x</td><td>y</td>'));
		$this->assertSame(array('', ''), $class::table_wrapper('<table><tr><td>x</td></tr></table>'));
		$this->assertSame(array('', ''), $class::table_wrapper('plain <tr>'));
		$this->assertSame(array('', ''), $class::table_wrapper('<track>'));

		$this->assertSame('<th>r</th><td>c</td>', $class::unwrap_table("<table>\n<tr><th>r</th><td>c</td></tr>\n</table>", '<table><tr>'));
		$this->assertSame('<tr><td>x</td></tr>', $class::unwrap_table("<table>\n<tr><td>x</td></tr>\n</table>\n", '<table>'), 'a row keeps its own tr');
	}

	public function test_a_new_article_can_start_from_a_start_template_only()
	{
		$this->templates->pages['Template:Start Extension'] = "<noinclude>Docs</noinclude>== Overview ==\nText [b]bold[/b] {{{1}}}";

		$this->assertSame("== Overview ==\nText [b]bold[/b] {{{1}}}", $this->templates->start_text('Start Extension'));
		$this->assertSame("== Overview ==\nText [b]bold[/b] {{{1}}}", $this->templates->start_text('Template:Start Extension'));
		$this->assertSame("== Overview ==\nText [b]bold[/b] {{{1}}}", $this->templates->start_text('start Extension'));
		$this->assertNull($this->templates->start_text('Start Nothing'), 'no such page');
		$this->assertNull($this->templates->start_text('Hello'), 'a template that is not a start template is not offered');
		$this->assertNull($this->templates->start_text('Main Page'));
		$this->assertNull($this->templates->start_text(''));
	}

	public function test_a_flood_of_calls_is_cut_off()
	{
		$out = $this->run_text(str_repeat('{{#if: x | a }}', 3000));

		$this->assertStringContainsString('WIKI_TEMPLATE_TOO_DEEP', $out);
	}

	public function test_titleparts_and_rel2abs()
	{
		$this->templates->article = 'Guide/Install/Linux';

		$this->assertSame('Guide/Install', $this->run_text('{{#titleparts: Guide/Install/Linux | 2 }}'));
		$this->assertSame('Install', $this->run_text('{{#titleparts: Guide/Install/Linux | 1 | 2 }}'));
		$this->assertSame('Guide/Install/Windows', $this->run_text('{{#rel2abs: ../Windows }}'));
		$this->assertSame('Other/Page', $this->run_text('{{#rel2abs: ../Page | Other/X }}'));
	}

	public function test_formatnum_plural_and_gender()
	{
		$this->assertSame('1,234', $this->run_text('{{formatnum: 1234 }}'));
		$this->assertSame('1234', $this->run_text('{{formatnum: 1,234 | R }}'));
		$this->assertSame('3 pages', $this->run_text('3 {{plural: 3 | page | pages }}'));
		$this->assertSame('1 page', $this->run_text('1 {{plural: {{#expr: 2 - 1}} | page | pages }}'));
		$this->assertSame('they', $this->run_text('{{gender: Someone | he | she | they }}'));
		$this->assertSame('he', $this->run_text('{{gender: Someone | he }}'));
	}

	public function test_tag_writes_an_html_tag()
	{
		$this->assertSame('<center>Hi</center>', $this->run_text('{{#tag: center | Hi }}'));
		$this->assertSame('<td colspan="2">x</td>', $this->run_text('{{#tag: td | x | colspan = 2 }}'));
		$this->assertSame('<span title="a &quot;b">x</span>', $this->run_text('{{#tag: span | x | title=a "b}}'));
		$this->assertSame('<br></br>', $this->run_text('{{#tag: br }}'));
		$this->assertStringContainsString('WIKI_TEMPLATE_UNKNOWN_FUNCTION', $this->run_text('{{#tag: bad name | x }}'));
	}

	public function test_local_and_revision_words()
	{
		// 14:05 UTC on 7 March is 03:05 on 8 March in Auckland (daylight time)
		$this->assertSame('8', $this->run_text('{{LOCALDAY}}'));
		$this->assertSame('7', $this->run_text('{{CURRENTDAY}}'));
		$this->assertSame('03', $this->run_text('{{LOCALHOUR}}'));

		$this->assertSame('42', $this->run_text('{{REVISIONID}}'));
		$this->assertSame('Editor', $this->run_text('{{REVISIONUSER}}'));
		$this->assertSame('2026', $this->run_text('{{REVISIONYEAR}}'));
		$this->assertSame('20260307140509', $this->run_text('{{REVISIONTIMESTAMP}}'));

		$this->templates->revision = array();
		$this->assertSame('', $this->run_text('{{REVISIONID}}'), 'an article not saved yet');
	}

	public function test_timel_uses_the_readers_zone_and_language()
	{
		$this->language->method('lang_raw')->willReturn(array('Saturday' => 'Samstag', 'Sunday' => 'Sonntag', 'March' => 'März'));
		$user = $this->createMock(\phpbb\user::class);
		$user->timezone = new \DateTimeZone('Asia/Tokyo');
		$class = get_class($this->templates);
		$templates = new $class($this->config, $this->createMock(\phpbb\db\driver\driver_interface::class), $this->language, 'phpbb_wiki_article', $user);

		// 14:05 UTC is 23:05 in Tokyo, the same Saturday
		$this->assertSame('Samstag 23:05, 7 März', $templates->expand_text('{{#timel: l H:i, j F | now}}'));
		$this->assertSame('Saturday 14:05', $templates->expand_text('{{#time: l H:i | now}}'), '#time stays in UTC and English');
	}
}
