<?php
/**
 * A singe "post" item from the Wordpress XML export from `<wp:post_type>post`
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMElement;
use DOMNode;

class Channel extends Item
{
	protected $type = 'channel';

	/**
	 * @see $link
	 * @var string incl. protocol
	 */
	protected $blogUrl = '';

	/** @var string the Hostname from <channel><link> */
	protected $host = '';

	/**
	 * Extract some elements of Wordpress <channel> for Kirby's site file.
	 * Only deals with its simple elements and ignores some with redundant
	 * information.
	 * Author <wp:author> and Item <item> are handled separately in WXR.
	 *
	 * @param DOMNode $channel
	 * @return Channel
	 */
	public function parse(DOMNode $channel): Channel
	{
		$channel->normalize();

		/* we need a reference to this early on in helpers */
		$this->XML->setChannel($this);

		/** @var DOMElement $elt */
		foreach ($channel->childNodes as $elt) {
			/* only deal with this if there's some element content.
			 * Empty nodes or <![CDATA[]]> is not. */
			if (XML_ELEMENT_NODE !== $elt->nodeType && !$elt->firstChild) {
				continue;
			}

			switch ($elt->localName) {
			case 'image':
				/* pick <image><url> */
				$this->data['favicon'] = $elt->firstChild->textContent;
				break;

			case 'item':
				/* we leave and let WXR::parse handle this */
				return $this;
				break;

			case 'title':           /* @see Item::setTitle() */
			case 'description':
			case 'language':
			case 'link':
			case 'base_blog_url':   /* @see setBaseBlogUrl() */
			case 'category':        /* @see setCategory() */
			case 'term':            /* @see setTerm() */
				$this->set($elt);
				break;
			}
		}

		return $this;
	}

	/**
	 * Sets the site's Blog URL using <wp:base_blog_url> and applies the same
	 * URL transformation as Item::setLink().
	 * This element usually follows <channel><link> in a standard WXR export.
	 *
	 * @param DOMNode $url
	 *
	 * @return Channel
	 * @see  setBaseSiteUrl()
	 * @uses Item::setLink()
	 */
	public function setBaseBlogUrl(DOMNode $url): Channel
	{
		// the "original" <channel><link> is already done, but
		// this will use <wp:base_site_url> for the transform
		$this->blogUrl = $this->cleanUrl($url->textContent);

		return $this;
	}

	/**
	 * <wp:category><wp:term_id>1</wp:term_id><wp:category_nicename>allgemein</wp:category_nicename><wp:category_parent></wp:category_parent><wp:cat_name><![CDATA[Allgemein]]></wp:cat_name></wp:category>
	 * @param DOMNode $cat
	 * @return Channel
	 */
	public function setCategory(DOMNode $cat): Channel
	{
		settype($this->data['categories'], 'array');
		$id = 0;
		$nicename = '';
		$parent = 0;
		$name = '';
		/** @var DOMElement $elt */
		foreach ($cat->childNodes as $elt) {
			switch ($elt->localName) {
			case 'term_id';
				$id = (int)$elt->textContent;
				break;
			case 'category_nicename';
				$nicename = $elt->textContent;
				break;
			case 'category_parent';
				$parent = (int)$elt->textContent;
				break;
			case 'cat_name';
				$name = $elt->textContent;
				break;
			}
		}
		$this->data['categories'][$nicename] = ['name'=>$name, $id => $parent];
		return $this;
	}

	/**
	 * <wp:term><wp:term_id>3</wp:term_id><wp:term_taxonomy>nav_menu</wp:term_taxonomy><wp:term_slug>hauptnavigation</wp:term_slug><wp:term_name><![CDATA[Hauptnavigation]]></wp:term_name></wp:term>
	 * @param DOMNode $term
	 * @return Channel
	 */
	public function setTerm(DOMNode $term): Channel
	{
		settype($this->data['terms'], 'array');
		$id = 0;
		$taxonomy = '';
		$slug = 0;
		$name = '';
		/** @var DOMElement $elt */
		foreach ($term->childNodes as $elt) {
			switch ($elt->localName) {
			case 'term_id';
				$id = (int)$elt->textContent;
				break;
			case 'term_taxonomy';
				$taxonomy = $elt->textContent;
				break;
			case 'term_slug';
				$slug = $elt->textContent;
				break;
			case 'term_name';
				$name = $elt->textContent;
				break;
			}
		}
		$this->data['categories'][$id] = [$taxonomy=>$name, $id => $slug];
		return $this;
	}

	/**
	 * Extract the hostname from the link URL.
	 *
	 * @param DOMNode $link
	 * @return Item
	 */
	public function setLink(DOMNode $link): Item
	{
		$parts = parse_url($link->textContent);
		$this->host = $parts['host'];
		return parent::setLink($link);
	}
}
