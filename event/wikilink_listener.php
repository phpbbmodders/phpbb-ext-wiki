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

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbbmodders\wiki\wiki\links */
	protected $links;

	/** @var \phpbbmodders\wiki\wiki\files */
	protected $files;

	/**
	 * @param \phpbb\controller\helper			$helper		Controller helper object
	 * @param \phpbb\language\language			$language	Language object
	 * @param \phpbbmodders\wiki\wiki\links		$links		Wiki links service
	 */
	public function __construct(\phpbb\controller\helper $helper, \phpbb\language\language $language, \phpbbmodders\wiki\wiki\links $links, \phpbbmodders\wiki\wiki\files $files)
	{
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

		// [[File:name]] and [[File:name|alt text]] embed one of the article's uploaded files.
		// It is added first, and WIKILINK leaves "File:" names to it.
		$file_tag = $configurator->tags->add('WIKIFILE');
		$file_tag->attributes->add('name');
		$file_tag->attributes->add('alt')->required = false;
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
		$heading->attributes->add('title');
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

		$tag = $configurator->tags->add('WIKILINK');
		$tag->attributes->add('name')->required = false;
		$tag->attributes->add('section')->required = false;
		$tag->attributes->add('text')->required = false;
		$tag->template = '<a class="wikilink" data-wiki-article="{@name}" data-wiki-section="{@section}">'
			. '<xsl:choose><xsl:when test="@text"><xsl:value-of select="@text"/></xsl:when>'
			. '<xsl:otherwise><xsl:value-of select="@name"/></xsl:otherwise></xsl:choose></a>';

		$configurator->Preg->match(
			'/\[\[\s*(?!File:)(?=[^\]|])(?<name>' . self::NAME_CLASS . '{0,255}?)\s*(?:#\s*(?<section>[^\[\]|<>"\n]{1,255}?)\s*)?(?:\|\s*(?<text>[^\[\]\n]{1,255}?)\s*)?\]\]/',
			'WIKILINK'
		);
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

		foreach (array('WIKIHEADING', 'WIKIMAGIC') as $tag)
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
	}

	public function link_wikilinks($event)
	{
		$html = $event['html'];

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
