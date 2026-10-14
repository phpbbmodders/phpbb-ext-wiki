<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * [[Article name]] and [[Article name|link text]] in wiki articles and
 * forum posts.
 *
 * The text formatter turns the markup into a WIKILINK tag that renders as
 * <a class="wikilink" data-wiki-article="...">. The link address is added
 * here after rendering, not in the tag's template, so it comes from the
 * wiki's own route; links to articles that don't exist yet are marked so
 * they can be styled and lead to the editor.
 */
class wikilink_listener implements EventSubscriberInterface
{
	/** Characters an article name in [[ ]] may not contain. */
	const NAME_CLASS = '[^\[\]|<>"#?%\/\\\\\n]';

	const NAME_PATTERN = self::NAME_CLASS . '{1,255}';

	/** How many {{templates}} deep a template call may hold others in its parameters. */
	const TEMPLATE_NESTING = 3;

	/** HTML elements a wiki article may use to write a table. */
	const TABLE_ELEMENTS = array('table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td');

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbbmodders\wiki\wiki\links */
	protected $links;

	/** @var \phpbbmodders\wiki\wiki\files */
	protected $files;

	/** @var \phpbbmodders\wiki\wiki\templates */
	protected $templates;

	/**
	 * @param \phpbb\controller\helper			$helper		Controller helper object
	 * @param \phpbb\language\language			$language	Language object
	 * @param \phpbbmodders\wiki\wiki\links		$links		Wiki links service
	 * @param \phpbbmodders\wiki\wiki\files		$files		Wiki files service
	 * @param \phpbbmodders\wiki\wiki\templates	$templates	Wiki templates service
	 */
	public function __construct(\phpbb\controller\helper $helper, \phpbb\language\language $language, \phpbbmodders\wiki\wiki\links $links, \phpbbmodders\wiki\wiki\files $files, \phpbbmodders\wiki\wiki\templates $templates)
	{
		$this->templates = $templates;
		$this->files = $files;
		$this->helper = $helper;
		$this->language = $language;
		$this->links = $links;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.text_formatter_s9e_configure_after'	=> 'configure_wikilink',
			'core.text_formatter_s9e_parse_before'		=> 'toggle_wiki_markup',
			'core.text_formatter_s9e_render_after'		=> 'link_wikilinks',
		);
	}

	/**
	 * The pattern for {{Name|parameters}}. s9e cannot read a recursive pattern, so
	 * templates inside the braces are matched down to a fixed depth.
	 *
	 * @return string
	 */
	protected static function template_pattern()
	{
		// Anything but a brace, a {{{parameter}}}, or a brace on its own
		$plain = '[^{}]++|\\{\\{\\{[^{}]*+\\}\\}\\}|\\{(?![{])|\\}(?![}])';
		$inner = '(?:' . $plain . ')*?';
		for ($level = 0; $level < self::TEMPLATE_NESTING; $level++)
		{
			$inner = '(?:' . $plain . '|\\{\\{(?![{])' . $inner . '\\}\\})*?';
		}

		return '/(?<![{])\\{\\{(?![{])(?=\\s*(?<name>#[a-z]+(?=\\s*:)|(?:lc|uc|lcfirst|ucfirst|urlencode|anchorencode|padleft|padright)(?=\\s*:)|[^|{}\\[\\]<>"#\\s][^|{}\\[\\]<>"\\n]{0,254}?(?=\\s*(?:\\||\\}\\}))))(?<call>' . $inner . ')\\}\\}(?![}])/';
	}

	/**
	 * Teach the text formatter [[Article name]] and [[Article name|text]].
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 */
	public function configure_wikilink($event)
	{
		$configurator = $event['configurator'];
		if (isset($configurator->tags['WIKILINK']))
		{
			return;
		}

		// [[Category:Name]] puts the article in a category and shows nothing where it stands. The
		// parser reads it only while a wiki article is saved (see toggle_wiki_markup()); a sort key
		// after a bar is accepted and ignored.
		$category = $configurator->tags->add('WIKICATEGORY');
		$category_name = $category->attributes->add('name');
		$category_name->filterChain->append('\phpbbmodders\wiki\wiki\markup::decode_entities_in_name');
		$category_name->filterChain->append('\phpbbmodders\wiki\wiki\categories::normalise');
		$category->template = '<span class="wikicategory" data-wiki-category="{@name}"></span>';

		$configurator->Preg->match(
			'/\[\[\s*Category:\s*(?=[^\s\]|])(?<name>' . self::NAME_CLASS . '{1,255}?)\s*(?:\|[^\[\]\n]*)?\]\]/i',
			'WIKICATEGORY'
		);

		// [[File:name]] and [[File:name|alt text]] embed one of the article's uploaded files.
		// It is added first, and WIKILINK leaves "File:" names to it.
		$file_tag = $configurator->tags->add('WIKIFILE');
		$file_tag->attributes->add('name');
		$file_alt = $file_tag->attributes->add('alt');
		$file_alt->required = false;
		$file_alt->filterChain->append('\phpbbmodders\wiki\wiki\markup::decode_entities');
		$file_tag->template = '<span class="wikifile" data-wiki-file="{@name}" data-wiki-alt="{@alt}"></span>';

		$configurator->Preg->match(
			'/\[\[\s*File:\s*(?<name>' . self::NAME_PATTERN . '?)\s*(?:\|\s*(?<alt>[^\[\]\n]{1,255}?)\s*)?\]\]/',
			'WIKIFILE'
		);

		// == Heading == to ====== Heading ======, one per line. The parser reads these only
		// while a wiki article is saved (see toggle_wiki_markup()); the anchors and the
		// table of contents are added when the article is shown (see markup::finish()).
		$heading = $configurator->tags->add('WIKIHEADING');
		$heading->attributes->add('marks');
		// Entities in captured text are decoded here, since the entity plugin never sees it
		$heading->attributes->add('title')->filterChain->append('\phpbbmodders\wiki\wiki\markup::decode_entities');
		$heading->template = '<xsl:choose>'
			. '<xsl:when test="string-length(@marks) = 2"><h2 class="wikiheading"><xsl:value-of select="@title"/></h2></xsl:when>'
			. '<xsl:when test="string-length(@marks) = 3"><h3 class="wikiheading"><xsl:value-of select="@title"/></h3></xsl:when>'
			. '<xsl:when test="string-length(@marks) = 4"><h4 class="wikiheading"><xsl:value-of select="@title"/></h4></xsl:when>'
			. '<xsl:when test="string-length(@marks) = 5"><h5 class="wikiheading"><xsl:value-of select="@title"/></h5></xsl:when>'
			. '<xsl:otherwise><h6 class="wikiheading"><xsl:value-of select="@title"/></h6></xsl:otherwise>'
			. '</xsl:choose>';

		$configurator->Preg->match(
			'/^(?<marks>={2,6})[ \t]*(?<title>[^=\n][^\n]*?)[ \t]*\k<marks>[ \t]*$/m',
			'WIKIHEADING'
		);

		// __TOC__, __NOTOC__ and __FORCETOC__ steer the table of contents
		$magic = $configurator->tags->add('WIKIMAGIC');
		$magic->attributes->add('word');
		$magic->template = '<span class="wikimagic" data-wiki-magic="{@word}"></span>';

		$configurator->Preg->match('/__(?<word>FORCETOC|NOTOC|TOC)__/', 'WIKIMAGIC');

		// {{Name}}, {{Name|parameter}} and {{Name|key=value}} include the page "Template:Name".
		// The braces may hold other {{templates}} and {{{parameters}}}. The call is kept as
		// written and expanded when the article is shown (see templates::expand_all()).
		$template = $configurator->tags->add('WIKITEMPLATE');
		$template->attributes->add('name');
		$template->attributes->add('call');
		// A <template> element, because HTML allows it between table tags where a <span> would not
		// be, so a template can give a table row
		$template->template = '<template class="wikitemplate" data-wiki-template="{@name}" data-wiki-call="{@call}"></template>';

		$configurator->Preg->match(self::template_pattern(), 'WIKITEMPLATE');

		$tag = $configurator->tags->add('WIKILINK');
		foreach (array('name' => 'decode_entities_in_name', 'section' => 'decode_entities', 'text' => 'decode_entities') as $attribute => $decode)
		{
			$link_attribute = $tag->attributes->add($attribute);
			$link_attribute->required = false;
			$link_attribute->filterChain->append('\phpbbmodders\wiki\wiki\markup::' . $decode);
		}
		$tag->template = '<a class="wikilink" data-wiki-article="{@name}" data-wiki-section="{@section}">'
			. '<xsl:choose><xsl:when test="@text"><xsl:value-of select="@text"/></xsl:when>'
			. '<xsl:otherwise><xsl:value-of select="@name"/><xsl:if test="@section">#<xsl:value-of select="@section"/></xsl:if></xsl:otherwise></xsl:choose></a>';

		$configurator->Preg->match(
			'/\[\[\s*(?!(?:File|Category):)(?=[^\]|])(?<name>' . self::NAME_CLASS . '{0,255}?)\s*(?:#\s*(?<section>[^\[\]|<>"\n]{1,255}?)\s*)?(?:\|\s*(?<text>[^\[\]\n]{1,255}?)\s*)?\]\]/',
			'WIKILINK'
		);

		// HTML entities written in an article, such as &nbsp;, &#32; or &mdash;, become
		// the characters they stand for, as on MediaWiki. The character is output as
		// text, so &lt; shows as "<" and never becomes markup. The parser reads them
		// only while a wiki article is saved (see toggle_wiki_markup()).
		$configurator->HTMLEntities;

		// <table> written as HTML. Only these elements and the colspan and rowspan of a cell
		// are allowed; s9e refuses scripts, styles and event attributes itself. The parser
		// reads them only while a wiki article is saved (see toggle_wiki_markup()).
		$html = $configurator->HTMLElements;
		foreach (self::TABLE_ELEMENTS as $element)
		{
			$html->allowElement($element);
		}
		foreach (array('th', 'td') as $cell)
		{
			$html->allowAttribute($cell, 'colspan');
			$html->allowAttribute($cell, 'rowspan');
		}
	}

	/**
	 * Point rendered wiki links at their articles, and mark the ones whose
	 * article doesn't exist yet.
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 */
	public function toggle_wiki_markup($event)
	{
		$parser = $event['parser']->get_parser();

		$tags = array('WIKIHEADING', 'WIKIMAGIC', 'WIKITEMPLATE', 'WIKICATEGORY');
		foreach (self::TABLE_ELEMENTS as $element)
		{
			$tags[] = 'html:' . $element;
		}

		foreach ($tags as $tag)
		{
			if (\phpbbmodders\wiki\wiki\markup::is_active())
			{
				$parser->enableTag($tag);
			}
			else
			{
				$parser->disableTag($tag);
			}
		}

		// Entities, only in text as written in an article (see markup::set_entities())
		if (\phpbbmodders\wiki\wiki\markup::is_active() && \phpbbmodders\wiki\wiki\markup::entities())
		{
			$parser->enableTag('HE');
		}
		else
		{
			$parser->disableTag('HE');
		}
	}

	public function link_wikilinks($event)
	{
		$html = $event['html'];

		if (strpos($html, 'class="wikitemplate"') !== false)
		{
			$html = $this->templates->expand_all($html);
			$event['html'] = $html;
		}

		// A template's text is part of the article it sits in: that article's pass below
		// finishes it (headings, files, links) once everything is in place
		if ($this->templates->is_expanding())
		{
			return;
		}

		if (strpos($html, 'class="wikicategory"') !== false)
		{
			// Note the categories, for the article's page to list, and take the marks out
			if (preg_match_all('/<span class="wikicategory" data-wiki-category="([^"]*)"><\/span>/', $html, $marks))
			{
				foreach ($marks[1] as $mark)
				{
					\phpbbmodders\wiki\wiki\categories::add(htmlspecialchars_decode($mark, ENT_QUOTES));
				}
			}
			$html = preg_replace('/<span class="wikicategory" data-wiki-category="[^"]*"><\/span>/', '', $html);
			$event['html'] = $html;
		}

		if (strpos($html, 'class="wikiheading"') !== false || strpos($html, 'class="wikimagic"') !== false)
		{
			$html = \phpbbmodders\wiki\wiki\markup::finish($html, $this->language->lang('WIKI_TOC'));
			$event['html'] = $html;
		}

		if (strpos($html, 'class="wikifile"') !== false)
		{
			$html = $this->embed_files($html);
			$event['html'] = $html;
		}

		if (strpos($html, 'class="wikilink"') === false)
		{
			return;
		}

		$pattern = '/<a class="wikilink" data-wiki-article="([^"]*)" data-wiki-section="([^"]*)">/';
		if (!preg_match_all($pattern, $html, $matches))
		{
			return;
		}

		$names = array_map(function ($name) {
			return htmlspecialchars_decode($name, ENT_QUOTES);
		}, $matches[1]);
		$existing = $this->links->existing_articles(array_filter($names, 'strlen'));

		$event['html'] = preg_replace_callback($pattern, function ($match) use ($existing) {
			$name = htmlspecialchars_decode($match[1], ENT_QUOTES);
			$section = htmlspecialchars_decode($match[2], ENT_QUOTES);
			$fragment = ($section !== '') ? '#' . htmlspecialchars(\phpbbmodders\wiki\wiki\markup::anchor($section), ENT_QUOTES, 'UTF-8') : '';

			// [[#Section]] points into the page it is on
			if ($name === '')
			{
				return '<a class="wikilink" href="' . $fragment . '" data-wiki-article="">';
			}

			$url = $this->helper->route('phpbbmodders_wiki_article', array('article' => $name)) . $fragment;
			if (isset($existing[$name]))
			{
				return '<a class="wikilink" href="' . $url . '" data-wiki-article="' . $match[1] . '">';
			}

			return '<a class="wikilink wikilink-new" href="' . $url . '" data-wiki-article="' . $match[1] . '" title="'
				. htmlspecialchars($this->language->lang('WIKI_LINK_NEW_ARTICLE'), ENT_COMPAT, 'UTF-8') . '">';
		}, $html);
	}

	/**
	 * Replace each [[File:name]] placeholder with the file's image or link.
	 *
	 * Files are looked up among those of the article being rendered. Anywhere else
	 * (a forum post, a preview with no article) and for names that were never
	 * uploaded, the text stays as plain text.
	 *
	 * @param string $html
	 * @return string
	 */
	protected function embed_files($html)
	{
		$pattern = '/<span class="wikifile" data-wiki-file="([^"]*)" data-wiki-alt="([^"]*)"><\/span>/';
		if (!preg_match_all($pattern, $html, $matches))
		{
			return $html;
		}

		$article = $this->files->get_context();
		$names = array_map(function ($name) {
			return htmlspecialchars_decode($name, ENT_QUOTES);
		}, $matches[1]);
		$found = ($article !== null) ? $this->files->get_files_by_name($article, $names) : array();

		return preg_replace_callback($pattern, function ($match) use ($article, $found) {
			$name = htmlspecialchars_decode($match[1], ENT_QUOTES);
			$alt = ($match[2] !== '') ? $match[2] : $match[1];

			if (!isset($found[$name]))
			{
				return '<span class="wikifile wikifile-missing" title="' . htmlspecialchars($this->language->lang('WIKI_FILE_MISSING', $name), ENT_COMPAT, 'UTF-8') . '">' . $alt . '</span>';
			}

			$url = $this->files->get_url($article, $found[$name]['file_id']);

			if ($found[$name]['is_image'])
			{
				return '<img class="postimage wikifile-image" src="' . $url . '" alt="' . $alt . '">';
			}

			return '<a class="wikifile-link" href="' . $url . '" download>' . $alt . '</a>';
		}, $html);
	}
}
