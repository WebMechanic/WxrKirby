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
use WebMechanic\Converter\Converter;

class Channel extends Item
{
	/**
	 * @see $link
	 * @var string incl. protocol
	 */
	protected $url;

	/**
	 * @see $link
	 * @var string incl. protocol
	 */
	protected $blogUrl;

	/**
	 * Extract some elements of Wordpress <channel> for Kirby's site file.
	 * Only deals with its simple elements.
	 * Author <wp:author> and Item <item> are handled separately in WXR.
	 *
	 * @param DOMNode $channel
	 * @return Channel
	 */
	public function parse(DOMNode $channel): Channel
	{
		$channel->normalize();

		/** @var DOMElement $elt */
		foreach ($channel->childNodes as $elt) {
			if ($elt->nodeType !== XML_ELEMENT_NODE) continue;

			switch ($elt->localName) {
			case 'pubDate';
			case 'wxr_version';
				/* ignored */
			case 'author';
				/* @see Converter::setAuthor() */
				break;

			case 'image':
				/* pick <image><url> */
				$this->data['favicon'] = $elt->firstChild->textContent;
				break;

			case 'item':
				/* we leave and let WXR::parse <item> and <wp:author> */
				return $this;
				break;

			case 'title':
				/* @see Item::setTitle() */
			case 'description':
			case 'language':
			case 'link':
			case 'base_site_url':
			case 'base_blog_url':
				/* @see setBaseSiteUrl(), setBaseBlogUrl() */
			case 'generator':
				$this->set($elt);
				break;
			}
		}

		return $this;
	}

	/**
	 * Sets the site's URL using <wp:base_site_url> and applies the same URL
	 * transformation as Item::setLink().
	 * This element usually follows <channel><link> in a standard WXR export.
	 *
	 * @param DOMNode $url
	 *
	 * @return Channel
	 * @see  setBaseBlogUrl()
	 * @uses Item::setLink()
	 */
	public function setBaseSiteUrl(DOMNode $url): Channel
	{
		// the "original" <channel><link> is already done, but
		// this will use <wp:base_site_url> for the transform
		$this->url = $this->cleanUrl($url->textContent);
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

}
