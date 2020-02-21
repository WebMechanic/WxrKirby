<?php
/**
 * A singe "page" item from the Wordpress XML export from `<wp:post_type>page`
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMNode;
use WebMechanic\Converter\Meta;

class Post extends Item
{
	/** @var int wp:post_parent */
	protected $parent;

	/** @var string dc:creator and username */
	protected $creator;

	/** @var string content:encoded */
	protected $content;
	protected $content_html;
	/** @var string excerpt:encoded */
	protected $excerpt;
	protected $excerpt_html;

	/** @var string GMT date string wp:post_date_gmt */
	protected $date;
	/** @var string wp:post_name */
	protected $name;

	/** @var string */
	public $filepath;

	/** @var string wp:status publish|draft|inherit */
	protected $status;

	/** @var array wp:postmeta > <wp:meta_key> <wp:meta_value>CDATA */
	protected $meta;
	/** @var array */
	protected $tags;
	/** @var array */
	protected $categories;

	/** @var array reg_replace node prefixes to simplify setters */
	protected $prefixFilter = '/^post_?/';

	/** @var int bit mask for HTML parser hints */
	protected $hints = 0;
	/** @internal int parse for any HTML */
	const PARSE_HTML = 1;
	/** @internal int parse for a and link and strip href for Attachment */
	const PARSE_LINK = 2;
	/** @internal int parse for image elements and strip src for Attachment */
	const PARSE_IMG = 4;

	/**
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setId(DOMNode $elt): Post
	{
		$this->id = (int)$elt->textContent;
		// @todo HTML Markup Filter Sample Post "Stylsheet" [sic]
		// <wp:post_id>337</wp:post_id>
		if ($this->id == 337) {
			$this->hints = self::PARSE_HTML;
			$this->htmlConvert();
		}
		return $this;
	}

	/**
	 * Convert the HTML in `content_html` and `excerpt_html` into Markdown
	 * and store the result in `content` and `excerpt` respectively.
	 *
	 * @param string $what
	 * @return $this
	 */
	private function htmlConvert($what = 'content')
	{
		if (!$this->hints) return $this;

		$HTML = $this->converter()->getHtml();

		// <br>, two spaces at the line end in output Markdown
		$HTML->getConfig()->setOption('hard_break', false);

		if ($what == 'content' && ($this->content)) $this->content = $HTML->convert($this->content_html);
		if ($what == 'excerpt' && ($this->excerpt)) $this->excerpt = $HTML->convert($this->excerpt_html);

		return $this;
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
	 * @param string $prop
	 * @return Post
	 */
	private function hintHtml(string $prop): Post
	{
		if (preg_match('/<[a-z]+\s?/', $prop)) {
			$this->hints = self::PARSE_HTML;
			if (strpos($prop, 'href=')) $this->hints |= self::PARSE_LINK;
			if (strpos($prop, '<img')) $this->hints |= self::PARSE_IMG;
			if (strpos($prop, 'srcset=')) $this->hints |= self::PARSE_IMG;
		} else { $this->hints = 0; }
		return $this;
	}

	/**
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	public function setContent(DOMNode $elt): Post
	{
		$this->content_html = $elt->textContent;
		$this->hintHtml($this->content_html);
		$this->htmlConvert('content');
		return $this;
	}

	public function getContent($original = true)
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
		$this->excerpt_html = $elt->textContent;
		$this->hintHtml($this->excerpt_html);
		$this->htmlConvert('excerpt');
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
	 * Directly set the $content or $excerpt properties with some $markdown.
	 *
	 * @param string $markdown
	 * @param string $prop      'content|excerpt'
	 * @return Post
	 */
	public function setMarkdown(string $markdown, $prop = 'content'): Post
	{
		if ($prop == 'content') $this->content = $markdown;
		if ($prop == 'excerpt') $this->excerpt = $markdown;

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
		return $this;
	}

	/**
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
	 * @param bool    $transformOnly
	 * @return Item
	 */
	public function setLink(DOMNode $link, $transformOnly = false): Item
	{
		parent::setLink($link, $transformOnly);

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
	 * Keys:
	 * - @see _wp_page_template()  potential blueprint filename in Kirby
	 * - @see Attachment::_wp_attached_file()  relative file path
	 * - @see Attachment::_wp_attachment_metadata()  serialized array with image meta data
	 * - _edit_last         edit history count?
	 * - seo_follow         false|true
	 * - seo_noindex        false|true
	 * - "Custom Fieldname" @see Meta
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

		// Attachment
		if (method_exists($this, 'setMetadata')) {
			return $this->setMetadata($elt);
		} else {
			$this->meta[$key] = $value;
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
	 * Get the category and tags of this post.
	 * `<category domain="category" nicename="blog-kategoriename1"><![CDATA[Kategoriename 1]]></category>`
	 * `<category domain="category" nicename="blog-kategoriename2"><![CDATA[Kategoriename 2]]></category>`
	 *
	 * @param DOMNode $elt
	 *
	 * @return Post
	 */
	protected function setCategory(DOMNode $elt): Post
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
	 * Called on `<wp:postmeta>`, stores the template as a potential blueprint
	 * name for the Kirby\Page.
	 *
	 * @param string $value
	 * @return Post
	 */
	private function _wp_page_template(string $value): Post
	{
		$this->data['blueprint'] = $value;
		return $this;
	}

}
