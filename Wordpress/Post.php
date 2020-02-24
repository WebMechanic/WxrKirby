<?php
/**
 * A singe "page" item from the Wordpress XML export from `<wp:post_type>page`
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMElement;
use DOMNode;
use WebMechanic\Converter\Meta;

class Post extends Item
{
	protected $type = 'post';

	/** @var int wp:post_parent */
	protected $parent = 0;

	/** @var string dc:creator and username */
	protected $creator = '';

	/** @var string content:encoded */
	protected $content = '';
	protected $content_html = '';

	/** @var string excerpt:encoded */
	protected $excerpt = '';
	protected $excerpt_html = '';

	/** @var string GMT date string wp:post_date_gmt */
	protected $date = '';
	/** @var string wp:post_name */
	protected $name = '';

	/** @var string */
	protected $filepath = '';

	/** @var string Post template for a potential Kirby Blueprint */
	protected $template = 'default';

	/** @var string wp:status publish|draft|inherit */
	protected $status = 'publish';

	/** @var array wp:postmeta > <wp:meta_key> <wp:meta_value>CDATA */
	protected $meta;

	/** @var array */
	protected $tags;

	/** @var array */
	protected $categories;

	/** @var array preg_replace node name prefixes to simplify setters */
	protected $prefixFilter = '/^(post|page)_?/';

	/** @var int bit mask for HTML parser hints */
	protected $hints = 0;
	/** @internal int source fields being hinted */
	const PARSE_DESCRIPTION  = 16;
	const PARSE_CONTENT      = 32;
	const PARSE_EXCERPT      = 64;
	/** @internal int parse for any HTML */
	/** @internal int parse for a and link and strip href for Attachment */
	const HINT_LINK = 1;
	/** @internal int parse for image elements and strip src for Attachment */
	const HINT_IMG = 2;
	/** @internal int parse for srcset attributes for Attachment */
	const HINT_SRCSET = 4;

	/**
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setId(DOMNode $elt): Post
	{
		$this->id = (int)$elt->textContent;

		return $this;
	}

	/**
	 * Convert the HTML in `content_html` and `excerpt_html` into Markdown
	 * and store the result in `content` and `excerpt` respectively.
	 *
	 * To check if a conversion is "recommended" call `hintHtml()` and test `$hints`.
	 *
	 * The default option for the HtmlConverter is `hard_break=false`. You can
	 * configure it with Converter::$options['html2md'].
	 *
	 * @param string $markup
	 * @return string  presumably Markdown
	 * @see hintHtml(), $hints, Converter::$options
	 */
	public function htmlConvert(string $markup): string
	{
		return $this->converter()->getHtml()->convert($markup);
	}

	/**
	 * Simple test for the existence of any HTML markup and links or images
	 * in particular (and only). Sets the $hints bitmask if there are matches
	 * so subsequent conversions can parse for inline images or links to
	 * uploaded attachments.
	 *
	 * This fails if somebody believes adding spaces around an equal sign is
	 * useful. In such cases: subclass and use regular expressions.
	 *
	 * @param string $html
	 * @param int    $source one of HINT_CONTENT, HINT_EXCERPT, HINT_DESCRIPTION
	 * @return Post
	 */
	public function hintHtml(string $html, int $source): Post
	{
		if (preg_match('/<[a-z]+\s?/', $html)) {
			$this->hints = $source;
			if (strpos($html, 'href=')) $this->hints |= self::HINT_LINK;
			if (strpos($html, '<img')) $this->hints |= self::HINT_IMG;
			if (strpos($html, 'srcset=')) $this->hints |= self::HINT_SRCSET;
		} else {
			$this->hints = 0;
		}
		return $this;
	}

	/**
	 * Test if any of the PARSE_xxx or HINT_xxx flags is set.
	 *
	 * @param int $flag
	 * @return int
	 */
	public function hasFlag(int $flag) {
		return $this->hints & $flag;
	}

	/**
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setContent(DOMNode $elt): Post
	{
		if (!empty($elt->textContent)) {
			$this->content_html = $elt->textContent;
			if ($this->hintHtml($this->content_html, static::PARSE_CONTENT)->hints) {
				$this->extractInlineTags($this->content_html);
				$this->hints ^= static::PARSE_CONTENT;
				$this->content = $this->htmlConvert($this->content_html);
			} else {
				$this->content = $this->content_html;
			}
		}
		return $this;
	}

	public function getContent($original = false)
	{
		if ($original) {
			return $this->content_html;
		}
		return $this->content;
	}

	/**
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setExcerpt(DOMNode $elt): Post
	{
		if (!empty($elt->textContent)) {
			$this->excerpt_html = $elt->textContent;
			if ($this->hintHtml($this->excerpt_html, static::PARSE_EXCERPT)->hints) {
				$this->excerpt = $this->htmlConvert($this->excerpt_html);
			} else {
				$this->excerpt = $this->excerpt_html;
			}
		}
		return $this;
	}

	public function getExcerpt($original = true)
	{
		if ($original) {
			return $this->excerpt_html;
		}
		return $this->excerpt;
	}

	/**
	 * @param DOMNode $desc
	 * @return Item
	 */
	public function setDescription(DOMNode $desc): Item
	{
		if (!empty($desc->textContent)) {
			$this->description = $desc->textContent;
			if ($this->hintHtml($this->description, static::PARSE_DESCRIPTION)->hints) {
				$this->description = $this->htmlConvert($this->description);
			}
		}
		return $this;
	}

	/**
	 * The GMT date is used as the publishing date in Kirby.
	 * It is also used to "touch" the generated Kirby file.
	 *
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setDateGmt(DOMNode $elt): Post
	{
		$this->date = $elt->textContent;
		unset($this->fields['pubDate']);
		unset($this->fields['date']);
		return $this;
	}

	/**
	 * The `<wp:post_name>` is essentially identical with the lowercase version
	 * of the <link>` URL last part.
	 * For Attachments it is also the suffix-free basename of its filename.
	 *
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setName(DOMNode $elt): Post
	{
		$this->name = $elt->textContent;
		return $this;
	}

	/**
	 * @param DOMNode $link
	 * @return Item
	 */
	public function setLink(DOMNode $link): Item
	{
		parent::setLink($link);
		$this->setFilepath($this->link);
		return $this;
	}

	/**
	 * Take the 'path' from $url. Since $url may represent a virtual ressource
	 * such as a slide or gallery, cleanup and data transform has to be done
	 * in the _site specific Converter subclass_ and the Kirby side of things
	 * using custom filters and Transforms.
	 *
	 * @param string $url
	 * @return Post
	 */
	public function setFilepath(string $url): Post
	{
		$parts = parse_url($url);
		$this->filepath = $parts['path'];
		return $this;
	}

	/**
	 * Called on `<wp:postmeta>` and it's childNodes <wp:meta_key>, <wp:meta_value>
	 * This will NOT handle plugin fields. Create a Meta transform to catch them.
	 *
	 * Known Keys:
	 * - _edit_last         edit history count?
	 * - _wp_xyz            misc. WordPress internals
	 * - seo_follow         false|true
	 * - seo_noindex        false|true
	 * - "Custom Fieldname" @see Meta
	 * - @see Attachment::setAttachedFile()  relative file path
	 * - @see Attachment::setMetadata()  serialized array with image meta data
	 *
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setMeta(DOMNode $elt): Post
	{
		/** @var string <wp:meta_key> */
		$key = $elt->childNodes->item(0)->textContent;
		/** @var string <wp:meta_value> */
		$value = $elt->childNodes->item(1)->textContent;

		switch ($key)
		{
		case '_wp_page_template':
		case '_wp_attachment_image_alt':
			$node = new DOMElement($key, $value);
			$this->set($node, 'meta');
			break;

		case '_wp_attached_file':
		case '_wp_attachment_metadata': # deserialize
		case '_wp_attachment_backup_sizes': # deserialize
			$node = new DOMElement($key, $value);
			$this->set($node, 'meta');
			break;

		default:
			// possible Plugin meta elements
			$transform = $this->transform($elt->nodeName);
			$transform->apply($elt, $this);
			break;
		}

		return $this;
	}

	/**
	 * @param DOMNode $elt
	 * @return Post
	 * @todo link creator name to Author!
	 */
	public function setCreator(DOMNode $elt): Post
	{
		$this->creator = $this->author($elt->textContent);
		return $this;
	}

	/**
	 * Get categories and tags of this post.
	 * `<category domain="category" nicename="blog-categoriename1"><![CDATA[Categorie Name 1]]></category>`
	 * `<category domain="category" nicename="blog-categoriename2"><![CDATA[Categorie Name 2]]></category>`
	 *
	 * Not handled here:
	 * `<category domain="nav_menu" nicename="mainmenu"><![CDATA[Main Menu]]></category>`
	 *
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setCategory(DOMNode $elt): Post
	{
		$value    = $elt->textContent;
		$domain   = $elt->attributes->getNamedItem('domain');
		$nicename = $elt->attributes->getNamedItem('nicename');

		if ($domain == "post_tag") {
			array_push($this->tags[$nicename], $value);
		} elseif ($domain == "category") {
			array_push($this->categories[$nicename], $value);
		}
		return $this;
	}

	/**
	 * Called on `<wp:postmeta>`, stores the template name as a potential
	 * blueprint name for the Kirby\Page.
	 *
	 * @param DOMNode $elt
	 * @return Post
	 */
	public function setTemplate(DOMNode $elt): Post
	{
		$value = basename($elt->textContent, '.php');
		$this->template = $value;

		// note as blueprint
		$value = str_replace('template-', '', $value);
		$this->site()->setBlueprint($this->filepath, $value);

		return $this;
	}

	public function extractInlineTags(string $html)
	{
		if ($this->hasFlag(Post::HINT_LINK)) {
			$m = preg_match_all('/href="(.*)"/', $html, $href);
			if ($m > 0) {
				$this->hints ^= Post::HINT_LINK;
				print_r($href[1]);
			}
		}
		if ($this->hasFlag(Post::HINT_IMG)) {
			$m = preg_match_all('/src="(.*)"/', $html, $src);
			if ($m > 0) {
				$this->hints ^= Post::HINT_IMG;
				print_r($src[1]);
			}
		}
		/* @todo parse srcset and store as Attachments */
		if ($this->hasFlag(Post::HINT_SRCSET)) {
			$m = preg_match_all('/srcset="(.*)"/', $html, $srcset);
			if ($m > 0) {
				$this->hints ^= Post::HINT_SRCSET;
				print_r($srcset[1]);
			}
		}
	}
}
