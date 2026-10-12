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
 * Wikipedia-style markup for wiki articles: == headings ==, a table of contents,
 * and the section anchors that [[Article#Section]] links point to.
 *
 * The text parser only recognises this markup while an article is being saved or
 * previewed (see set_active()), so forum posts keep treating "== text ==" as plain
 * text. The HTML the parser leaves behind is finished here, when the article is
 * shown: each heading gets an anchor, and the table of contents is built from them.
 */
class markup
{
	/** Fewest headings that bring up a table of contents by themselves, as in MediaWiki */
	const TOC_MINIMUM = 4;

	/** @var bool Whether the text parser should read wiki markup right now */
	protected static $active = false;

	/**
	 * Switch wiki markup on for the next parse (an article save or preview), or off.
	 *
	 * @param bool $active
	 * @return void
	 */
	public static function set_active($active)
	{
		self::$active = (bool) $active;
	}

	/**
	 * @return bool
	 */
	public static function is_active()
	{
		return self::$active;
	}

	/**
	 * The anchor a heading or a [[Article#Section]] link uses: spaces become
	 * underscores and anything that is not a letter, digit or one of _-.:,() is
	 * dropped, so the same text always gives the same anchor.
	 *
	 * @param string $text Heading text, as plain text
	 * @return string
	 */
	public static function anchor($text)
	{
		$anchor = preg_replace('/\s+/u', '_', trim((string) $text));
		$anchor = preg_replace('/[^\p{L}\p{N}_\-.:,()]/u', '', (string) $anchor);
		$anchor = trim((string) $anchor, '_');

		return $anchor === '' ? 'section' : $anchor;
	}

	/**
	 * The article a "#REDIRECT [[Article]]" first line names.
	 *
	 * @param string $text The article text as typed, not HTML-escaped
	 * @return string The target, or '' when the text does not start with a redirect
	 */
	public static function redirect_target($text)
	{
		if (preg_match('/\A\s*#REDIRECT\s*:?\s*\[\[\s*([^\[\]|#<>"\n]{1,255}?)\s*(?:#[^\]|\n]*)?(?:\|[^\]\n]*)?\]\]/iu', (string) $text, $match))
		{
			return $match[1];
		}

		return '';
	}

	/**
	 * Finish the headings in rendered article HTML: give each an anchor, drop the
	 * __TOC__, __NOTOC__ and __FORCETOC__ markers, and add the table of contents.
	 *
	 * The contents box shows after the intro, before the first heading, when the
	 * article has TOC_MINIMUM or more headings. __TOC__ places it elsewhere and
	 * shows it for any number of headings, __FORCETOC__ shows it for any number,
	 * and __NOTOC__ hides it.
	 *
	 * @param string $html      Rendered HTML
	 * @param string $toc_title Heading of the contents box
	 * @return string
	 */
	public static function finish($html, $toc_title)
	{
		$has_headings = strpos($html, 'class="wikiheading"') !== false;
		$has_magic = strpos($html, 'class="wikimagic"') !== false;
		if (!$has_headings && !$has_magic)
		{
			return $html;
		}

		$flags = array('TOC' => false, 'NOTOC' => false, 'FORCETOC' => false);
		$placed = false;
		$html = preg_replace_callback('/<span class="wikimagic" data-wiki-magic="(TOC|NOTOC|FORCETOC)"><\/span>/', function ($match) use (&$flags, &$placed) {
			$flags[$match[1]] = true;
			if ($match[1] === 'TOC' && !$placed)
			{
				$placed = true;
				return '<!--wikitoc-->';
			}

			return '';
		}, $html);

		$headings = array();
		$used = array();
		$html = preg_replace_callback('/<h([2-6]) class="wikiheading">(.*?)<\/h\1>/s', function ($match) use (&$headings, &$used) {
			$text = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			$base = self::anchor($text);
			$anchor = $base;
			for ($n = 2; isset($used[$anchor]); $n++)
			{
				$anchor = $base . '_' . $n;
			}
			$used[$anchor] = true;
			$headings[] = array('level' => (int) $match[1], 'anchor' => $anchor, 'text' => $text);

			return '<h' . $match[1] . ' id="' . htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') . '" class="wikiheading">' . $match[2] . '</h' . $match[1] . '>';
		}, $html);

		$show = $headings && !$flags['NOTOC'] && ($flags['TOC'] || $flags['FORCETOC'] || count($headings) >= self::TOC_MINIMUM);
		$toc = $show ? self::toc($headings, $toc_title) : '';

		if (strpos($html, '<!--wikitoc-->') !== false)
		{
			return str_replace('<!--wikitoc-->', $toc, $html);
		}

		if ($toc !== '')
		{
			$html = preg_replace('/<h[2-6] id="/', $toc . '$0', $html, 1);
		}

		return $html;
	}

	/**
	 * The contents box: nested lists, numbered 1, 1.1, 1.2, 2 and so on.
	 *
	 * @param array  $headings Each: level (2 to 6), anchor, text
	 * @param string $title
	 * @return string
	 */
	protected static function toc(array $headings, $title)
	{
		$html = '<div class="wikitoc"><p class="wikitoc-title"><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>';
		$stack = array();
		$counters = array();

		foreach ($headings as $heading)
		{
			$level = $heading['level'];

			// Close the lists the previous entry left open, down to this level
			while ($stack && end($stack) > $level)
			{
				$html .= '</li></ul>';
				array_pop($stack);
				array_pop($counters);
			}

			if ($stack && end($stack) === $level)
			{
				$html .= '</li>';
				$counters[count($counters) - 1]++;
			}
			else
			{
				// A deeper level than the one open (or the first entry) starts a list
				$html .= '<ul>';
				$stack[] = $level;
				$counters[] = 1;
			}

			$html .= '<li><a href="#' . htmlspecialchars($heading['anchor'], ENT_QUOTES, 'UTF-8') . '"><span class="wikitoc-number">' . implode('.', $counters) . '</span> '
				. htmlspecialchars($heading['text'], ENT_QUOTES, 'UTF-8') . '</a>';
		}

		while ($stack)
		{
			$html .= '</li></ul>';
			array_pop($stack);
		}

		return $html . '</div>';
	}
}
