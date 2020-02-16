<?php
/**
 * A singe "post" item from the Wordpress XML export from `<wp:post_type>post`
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMNode;

class Channel extends Item
{
	/** @var string incl. protocol */
	private $url;

	/** @var string incl. protocol */
	private $blogUrl;

	/**
	 * Extract some elements of Wordpress <channel> for Kirby's site file.
	 * Only deals with its simple elements.
	 * Author <wp:author> and Item <item> are handled separately in WXR.
	 *
	 * @param DOMNode $node
	 * @return Channel
	 */
	public function parse(DOMNode $node): Channel
	{
		$node->normalize();

		foreach ($node->childNodes as $elt) {
			if ($elt->nodeType !== XML_ELEMENT_NODE) continue;

			switch ($elt->localName) {
			case 'pubDate';
			case 'wxr_version';
			case 'image':
				/* skip these */
				break;

			case 'author':
			case 'item':
				/* we leave and let WXR::parse <item> and <wp:author> */
				return $this;
				break;

			case 'link':
				$this->setBaseSiteUrl($elt);
				break;

			case 'title':
				/* @see Item::setTitle() */
			case 'description':
			case 'language':
			case 'base_blog_url':
				/* @see setBaseBlogUrl() */
			case 'generator':
				$this->set($elt);
				break;
			}
		}

		return $this;
	}


	/**
	 * Sets the site's URL.
	 *
	 * @param string $url
	 *
	 * @return Channel
	 */
	public function setBaseSiteUrl(DOMNode $url): Channel
	{
		$this->transform($url->nodeName)->apply($url);
		$this->url = $url->textContent;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * Sets $data.blog to $value (URL) otherwise delegates to setBaseSiteUrl();
	 *
	 * @param string $url
	 *
	 * @return Channel
	 */
	public function setBaseBlogUrl(DOMNode $url): Channel
	{
		if (isset($this->url)) {
			$this->transform($url->nodeName)->apply($url);
			$this->blogUrl = $url->textContent;
		}
		return $this;
	}

}
