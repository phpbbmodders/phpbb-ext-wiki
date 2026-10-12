<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\wiki;

/**
 * Templates: {{Name}}, {{Name|first|second}} and {{Name|key=value}} in a wiki article
 * include the live version of the article "Template:Name" at that place. Inside the
 * template, {{{1}}}, {{{key}}} and {{{key|default}}} stand for the parameters.
 *
 * The call is stored in the article as a WIKITEMPLATE tag (see wikilink_listener) and
 * expanded when the article is shown, so editing a template changes every article that
 * uses it. The template's text is parsed and rendered like any article text, so it can
 * bring in nothing an article could not.
 */
class templates
{
	/** Templates inside templates stop here. */
	const MAX_DEPTH = 10;

	/** Most templates expanded for one article, so a template calling itself twice cannot run away. */
	const MAX_EXPANSIONS = 500;

	/** Article names of templates start with this. */
	const PREFIX = 'Template:';

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $article_table;

	/** @var int Template expansions in progress, from the article down */
	protected $depth = 0;

	/** @var int Templates expanded since the outermost one began */
	protected $expansions = 0;

	/** @var array Names of the templates being expanded, outermost first */
	protected $stack = array();

	/** @var array Raw template text by article name, for this request */
	protected $sources = array();

	/**
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param \phpbb\language\language			$language		Language object
	 * @param string								$article_table	Wiki article table
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, $article_table)
	{
		$this->db = $db;
		$this->language = $language;
		$this->article_table = $article_table;
	}

	/**
	 * Whether a template is being expanded, that is, whether the text now being
	 * rendered is a template's and not the article's.
	 *
	 * @return bool
	 */
	public function is_expanding()
	{
		return $this->depth > 0;
	}

	/**
	 * Replace every template placeholder in rendered text with the template's output.
	 *
	 * @param string $html Rendered text that holds <span class="wikitemplate"> placeholders
	 * @return string The text with the templates in place
	 */
	public function expand_all($html)
	{
		$pattern = '/<span class="wikitemplate" data-wiki-template="([^"]*)" data-wiki-call="([^"]*)"><\/span>/';

		return preg_replace_callback($pattern, function ($match) {
			return $this->expand(
				htmlspecialchars_decode($match[1], ENT_QUOTES),
				htmlspecialchars_decode($match[2], ENT_QUOTES)
			);
		}, $html);
	}

	/**
	 * Expand one template call.
	 *
	 * @param string $name The template's name as written, without the "Template:" prefix
	 * @param string $call What stood between the braces: the name and the parameters
	 * @return string Rendered text, or a link or a message when there is nothing to include
	 */
	public function expand($name, $call)
	{
		$name = self::normalise_name($name);
		$article = self::PREFIX . $name;

		if ($this->depth === 0)
		{
			$this->expansions = 0;
		}

		if ($this->depth >= self::MAX_DEPTH || ++$this->expansions > self::MAX_EXPANSIONS)
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_TOO_DEEP', $name));
		}

		if (in_array($article, $this->stack, true))
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_LOOP', $name));
		}

		$source = $this->source($article);
		if ($source === null)
		{
			// A red link to the missing template, like [[Template:Name]]
			$escaped = htmlspecialchars($article, ENT_QUOTES, 'UTF-8');

			return '<a class="wikilink" data-wiki-article="' . $escaped . '" data-wiki-section="">' . $escaped . '</a>';
		}

		list(, $positional, $named) = self::split_call($call);
		$text = trim(self::substitute(self::transclusion_text($source), $positional, $named));
		if ($text === '')
		{
			return '';
		}

		$this->depth++;
		$this->stack[] = $article;
		try
		{
			return $this->render($text);
		}
		finally
		{
			array_pop($this->stack);
			$this->depth--;
		}
	}

	/**
	 * Name of a template as it is stored: trimmed, without a leading "Template:", and
	 * with a capital first letter, as on Wikipedia, so {{out of date}} finds "Out of date".
	 *
	 * @param string $name The name as written
	 * @return string
	 */
	public static function normalise_name($name)
	{
		$name = trim($name);
		if (stripos($name, self::PREFIX) === 0)
		{
			$name = ltrim(substr($name, strlen(self::PREFIX)));
		}

		return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
	}

	/**
	 * Split what stood between {{ and }} into the template's name and its parameters.
	 * Bars inside [[links]], {{templates}} and {{{parameters}}} belong to those.
	 *
	 * @param string $call For example "Infobox|name=Wiki|1.1.0"
	 * @return array The name, the positional parameters (numbered from 1) and the named ones
	 */
	public static function split_call($call)
	{
		$parts = array();
		$part = '';
		$braces = $brackets = 0;
		$length = strlen($call);
		for ($i = 0; $i < $length; $i++)
		{
			$pair = substr($call, $i, 2);
			if ($pair === '{{' || $pair === '}}' || $pair === '[[' || $pair === ']]')
			{
				$braces += ($pair === '{{') - ($pair === '}}');
				$brackets += ($pair === '[[') - ($pair === ']]');
				$part .= $pair;
				$i++;
			}
			else if ($call[$i] === '|' && $braces <= 0 && $brackets <= 0)
			{
				$parts[] = $part;
				$part = '';
			}
			else
			{
				$part .= $call[$i];
			}
		}
		$parts[] = $part;

		$name = trim(array_shift($parts));
		$positional = $named = array();
		$next = 1;
		foreach ($parts as $part)
		{
			// key=value, as long as the = comes before any link or template in the part
			if (preg_match('/^\s*([^=\[\]{}<>\n]+?)\s*=(.*)$/s', $part, $match))
			{
				$key = $match[1];
				$value = trim($match[2]);
				if (ctype_digit($key))
				{
					$positional[(int) $key] = $value;
				}
				else
				{
					$named[$key] = $value;
				}
			}
			else
			{
				$positional[$next++] = $part;
			}
		}

		return array($name, $positional, $named);
	}

	/**
	 * Fill {{{1}}}, {{{key}}} and {{{key|default}}} with the parameters. A parameter that
	 * was not given and has no default stays as it was written.
	 *
	 * @param string $text The template's text
	 * @param array $positional Parameters by position, from 1
	 * @param array $named Parameters by name
	 * @return string
	 */
	public static function substitute($text, array $positional, array $named)
	{
		// The innermost {{{ }}} first, so a default can hold another parameter
		$pattern = '/\{\{\{\s*([^{}|]*?)\s*(?:\|((?:[^{}]|\{\{(?!\{)[^{}]*\}\})*))?\}\}\}/';
		$replace = function ($match) use ($positional, $named) {
			$key = $match[1];
			if (ctype_digit($key) && isset($positional[(int) $key]))
			{
				return $positional[(int) $key];
			}
			if (isset($named[$key]))
			{
				return $named[$key];
			}

			return isset($match[2]) ? $match[2] : $match[0];
		};

		for ($pass = 0; $pass < self::MAX_DEPTH; $pass++)
		{
			$changed = preg_replace_callback($pattern, $replace, $text);
			if ($changed === $text)
			{
				break;
			}
			$text = $changed;
		}

		return $text;
	}

	/**
	 * The part of a template's text that is included: not what is inside
	 * <noinclude>, and what is inside <includeonly> without the tags.
	 *
	 * @param string $text The template page's text
	 * @return string
	 */
	public static function transclusion_text($text)
	{
		$text = preg_replace('#<noinclude>.*?</noinclude>#is', '', $text);

		return preg_replace('#</?includeonly>#i', '', $text);
	}

	/**
	 * The text of the live version of a template page, as an editor would see it.
	 *
	 * @param string $article The template's article name, with the prefix
	 * @return string|null Null when there is no approved version
	 */
	protected function source($article)
	{
		if (array_key_exists($article, $this->sources))
		{
			return $this->sources[$article];
		}

		$sql = 'SELECT article_text, bbcode_uid
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND article_approved = 1
			ORDER BY article_last_edit DESC, article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return $this->sources[$article] = null;
		}

		$edit = generate_text_for_edit($row['article_text'], $row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);

		return $this->sources[$article] = htmlspecialchars_decode($edit['text'], ENT_COMPAT);
	}

	/**
	 * Parse and render text the way an article's is, with the wiki's own markup on.
	 *
	 * @param string $text Template text with its parameters filled in
	 * @return string
	 */
	protected function render($text)
	{
		$uid = $bitfield = '';
		$flags = 0;

		$was_active = markup::is_active();
		markup::set_active(true);
		try
		{
			generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);
		}
		finally
		{
			markup::set_active($was_active);
		}

		return generate_text_for_display($text, $uid, $bitfield, $flags);
	}

	/**
	 * @param string $message A message to show where a template could not be included
	 * @return string
	 */
	protected function error($message)
	{
		return '<strong class="error wikitemplate-error">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</strong>';
	}
}
