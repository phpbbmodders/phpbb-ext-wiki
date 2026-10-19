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


	/** HTML elements a wiki article may use to write a table. */
	const TABLE_ELEMENTS = array('table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td');

	/** Classes a wiki table may have, each styled by the extension: "infobox" floats it to the right. */
	const TABLE_CLASSES = array('infobox');

	/** HTML elements for a box the reader opens and closes, without JavaScript. */
	const DETAILS_ELEMENTS = array('details', 'summary');

	/** Classes a <details> box may have: "navbox" is a box of links at the end of an article. */
	const DETAILS_CLASSES = array('navbox');

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

	/** @var \phpbbmodders\wiki\wiki\wiki_only_bbcodes */
	protected $wiki_only_bbcodes;

	/**
	 * @param \phpbb\controller\helper			$helper		Controller helper object
	 * @param \phpbb\language\language			$language	Language object
	 * @param \phpbbmodders\wiki\wiki\links		$links		Wiki links service
	 * @param \phpbbmodders\wiki\wiki\files		$files		Wiki files service
	 * @param \phpbbmodders\wiki\wiki\templates	$templates	Wiki templates service
	 * @param \phpbbmodders\wiki\wiki\wiki_only_bbcodes	$wiki_only_bbcodes	The admin's BBCodes kept to the wiki
	 */
	public function __construct(\phpbb\controller\helper $helper, \phpbb\language\language $language, \phpbbmodders\wiki\wiki\links $links, \phpbbmodders\wiki\wiki\files $files, \phpbbmodders\wiki\wiki\templates $templates, \phpbbmodders\wiki\wiki\wiki_only_bbcodes $wiki_only_bbcodes)
	{
		$this->wiki_only_bbcodes = $wiki_only_bbcodes;
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
			'core.display_custom_bbcodes_modify_sql'	=> 'hide_wiki_only_buttons',
		);
	}

	/**
	 * The pattern for {{Name|parameters}}. s9e cannot read a recursive pattern, so
	 * templates inside the braces are matched down to a fixed depth.
	 *
	 * @param int $nesting How many levels of calls a call may hold
	 * @return string
	 */
	protected static function template_pattern($nesting)
	{
		// Anything but a brace, a {{{parameter}}}, or a brace on its own
		$plain = '[^{}]++|\\{\\{\\{[^{}]*+\\}\\}\\}|\\{(?![{])|\\}(?![}])';
		$inner = '(?:' . $plain . ')*?';
		for ($level = 0; $level < \phpbbmodders\wiki\wiki\templates::nesting_in_range($nesting); $level++)
		{
			$inner = '(?:' . $plain . '|\\{\\{(?![{])' . $inner . '\\}\\})*?';
		}

		return '/(?<![{])\\{\\{(?![{])(?=\\s*(?<name>#[a-z][a-z0-9]*(?=\\s*:)|(?:lc|uc|lcfirst|ucfirst|urlencode|anchorencode|padleft|padright|formatnum|plural|gender)(?=\\s*:)|[^|{}\\[\\]<>"#\\s][^|{}\\[\\]<>"\\n]{0,254}?(?=\\s*(?:\\||\\}\\}))))(?<call>' . $inner . ')\\}\\}(?![}])/';
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

		// [[Category:Name]] puts the article in a category and shows nothing where it stands, and
		// [[Category:Name|Key]] files it under Key in that category's list. The parser reads it
		// only while a wiki article is saved (see toggle_wiki_markup()).
		$category = $configurator->tags->add('WIKICATEGORY');
		$category_name = $category->attributes->add('name');
		$category_name->filterChain->append('\phpbbmodders\wiki\wiki\markup::decode_entities_in_name');
		$category_name->filterChain->append('\phpbbmodders\wiki\wiki\categories::normalise');
		$category_key = $category->attributes->add('key');
		$category_key->required = false;
		$category_key->filterChain->append('\phpbbmodders\wiki\wiki\markup::decode_entities');
		$category->template = '<span class="wikicategory" data-wiki-category="{@name}"><xsl:if test="@key"><xsl:attribute name="data-wiki-sort"><xsl:value-of select="@key"/></xsl:attribute></xsl:if></span>';

		$configurator->Preg->match(
			'/\[\[\s*Category:\s*(?=[^\s\]|])(?<name>' . self::NAME_CLASS . '{1,255}?)\s*(?:\|\s*(?<key>[^\[\]\n]{0,255}?)\s*)?\]\]/i',
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

		// The board's nesting setting; changing it rebuilds this configuration
		$configurator->Preg->match(self::template_pattern($this->templates->nesting()), 'WIKITEMPLATE');

		$tag = $configurator->tags->add('WIKILINK');
		foreach (array('name' => 'decode_entities_in_name', 'section' => 'decode_entities', 'text' => 'decode_entities') as $attribute => $decode)
		{
			$link_attribute = $tag->attributes->add($attribute);
			$link_attribute->required = false;
			$link_attribute->filterChain->append('\phpbbmodders\wiki\wiki\markup::' . $decode);
		}
		// [[:Category:Name]] links to a category's page without putting the article in it
		$tag->attributes['name']->filterChain->append('\phpbbmodders\wiki\wiki\markup::strip_link_colon');
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
		// <table class="infobox">, as on Wikipedia. Only the listed classes are kept, so a table
		// cannot borrow the board's own classes (to hide itself, for example)
		$html->allowAttribute('table', 'class')->filterChain->append(new \s9e\TextFormatter\Configurator\Items\AttributeFilters\ChoiceFilter(self::TABLE_CLASSES));

		// <details><summary>Title</summary>...</details>: a box the reader opens and closes, which
		// the browser does by itself. "open" starts it opened; the "navbox" class gives it the look
		// of Wikipedia's navigation boxes. Any other attribute is dropped, as for tables.
		foreach (self::DETAILS_ELEMENTS as $element)
		{
			$html->allowElement($element);
		}
		$html->allowAttribute('details', 'open');
		$html->allowAttribute('details', 'class')->filterChain->append(new \s9e\TextFormatter\Configurator\Items\AttributeFilters\ChoiceFilter(self::DETAILS_CLASSES));

		// <center>...</center> centres its content, as on MediaWiki. It becomes a <div> with a
		// class, since s9e lets no style attribute through and <center> is obsolete HTML.
		$center = $configurator->tags->add('WIKICENTER');
		$center->template = '<div class="wikicenter"><xsl:apply-templates/></div>';
		$html->aliasElement('center', 'WIKICENTER');
	}

	/**
	 * The tags the parser reads only while a wiki article is saved.
	 *
	 * @return string[]
	 */
	public static function wiki_tags()
	{
		$tags = array('WIKIHEADING', 'WIKIMAGIC', 'WIKITEMPLATE', 'WIKICATEGORY', 'WIKICENTER');
		foreach (array_merge(self::TABLE_ELEMENTS, self::DETAILS_ELEMENTS) as $element)
		{
			$tags[] = 'html:' . $element;
		}

		return $tags;
	}

	/**
	 * Let the parser read the wiki's own tags, and the BBCodes the admin made wiki-only, only
	 * while a wiki article is saved.
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 */
	public function toggle_wiki_markup($event)
	{
		$parser = $event['parser']->get_parser();

		foreach (array_merge(self::wiki_tags(), $this->wiki_only_bbcodes->tag_names()) as $tag)
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

	/**
	 * Leave the wiki-only BBCodes' buttons off every editor but the wiki's.
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 */
	public function hide_wiki_only_buttons($event)
	{
		$ids = $this->wiki_only_bbcodes->ids();
		if (!$ids || \phpbbmodders\wiki\wiki\markup::is_active())
		{
			return;
		}

		$sql_ary = $event['sql_ary'];
		$sql_ary['WHERE'] = '(' . $sql_ary['WHERE'] . ') AND b.bbcode_id NOT IN (' . implode(', ', $ids) . ')';
		$event['sql_ary'] = $sql_ary;
	}

	/**
	 * Point rendered wiki links at their articles, and mark the ones whose
	 * article doesn't exist yet.
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 */
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
			// Note the categories (and their sort keys), for the article's page to list and the
			// category lists to file it under, and take the marks out
			if (preg_match_all('/<span class="wikicategory" data-wiki-category="([^"]*)"(?: data-wiki-sort="([^"]*)")?><\/span>/', $html, $marks, PREG_SET_ORDER))
			{
				foreach ($marks as $mark)
				{
					\phpbbmodders\wiki\wiki\categories::add(htmlspecialchars_decode($mark[1], ENT_QUOTES), isset($mark[2]) ? htmlspecialchars_decode($mark[2], ENT_QUOTES) : '');
				}
			}
			$html = preg_replace('/<span class="wikicategory" data-wiki-category="[^"]*"(?: data-wiki-sort="[^"]*")?><\/span>/', '', $html);
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
				. utf8_htmlspecialchars($this->language->lang('WIKI_LINK_NEW_ARTICLE')) . '">';
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
				return '<span class="wikifile wikifile-missing" title="' . utf8_htmlspecialchars($this->language->lang('WIKI_FILE_MISSING', $name)) . '">' . $alt . '</span>';
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
