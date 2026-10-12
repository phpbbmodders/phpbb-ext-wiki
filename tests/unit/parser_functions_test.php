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

	protected function setUp(): void
	{
		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(function ($key, ...$args) {
			return $key . ($args ? '(' . implode(',', $args) . ')' : '');
		});

		$this->templates = new class($this->createMock(\phpbb\db\driver\driver_interface::class), $language, 'phpbb_wiki_article') extends \phpbbmodders\wiki\wiki\templates {
			/** @var array Template page text by article name */
			public $pages = array(
				'Template:Hello'	=> 'Hi {{{1|you}}}',
				'Template:Count'	=> '{{#if: {{#expr: {{{1}}} > 0}} | {{{1}}} {{Count|{{#expr: {{{1}}} - 1}}}} | }}',
				'Template:Loop'		=> 'again {{Loop}}',
				'Template:Ping'		=> '{{Pong}}',
				'Template:Pong'		=> '{{Ping}}',
				'Template:Doc'		=> '<noinclude>Docs</noinclude>Body{{{x|}}}',
				'Template:Deep'		=> '{{#if: {{{1|}}} | {{Deep|{{{1}}}}} | end}}',
				'Main Page'			=> 'x',
			);

			/** @var string|null */
			public $article = 'Template:Hello';

			protected function source($article)
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

	public function test_a_flood_of_calls_is_cut_off()
	{
		$out = $this->run_text(str_repeat('{{#if: x | a }}', 3000));

		$this->assertStringContainsString('WIKI_TEMPLATE_TOO_DEEP', $out);
	}
}
