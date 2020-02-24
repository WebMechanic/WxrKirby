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
use WebMechanic\Converter\Converter;
use WebMechanic\Converter\Transform;
use WebMechanic\Converter\Kirby\Author;
use WebMechanic\Converter\Kirby\Site;

class Item
{
	/** @var int wp:post_id */
	protected $id = 0;
	/** @var string */
	protected $title = '';
	/** @var string */
	protected $link = '';
	/** @var string wp:post_type, redundant */
	protected $type = '';
	/** @var string */
	protected $description = '';

	/** @var array misc. text nodes */
	protected $data = [];
	/** @var array custom fields collected on the go */
	protected $fields = [];

	/** @var WXR */
	protected $XML;

	/** @var array preg_replace node name prefixes to simplify setters */
	protected $prefixFilter = '/^_wp_?/';

	/**
	 * Wordpress Item constructor.
	 *
	 * @param DOMNode $node
	 * @param WXR     $XML |null
	 */
	function __construct(DOMNode $node, WXR $XML = null)
	{
		$this->XML = $XML;

		$this->parse($node);
	}

	/**
	 * @param DOMNode $item
	 * @return Item
	 */
	function parse(DOMNode $item)
	{
		$item->normalize();

		foreach ($item->childNodes as $elt) {
			/* only deal with this if there's some element content.
			 * Empty nodes or <![CDATA[]]> is not. */
			if (XML_ELEMENT_NODE == $elt->nodeType && $elt->firstChild) {
				$this->set($elt);
			}
		}
		return $this;
	}

	/**
	 * Find and call a setter for the element value in a subclass and delegate
	 * to the corresponding methods. For elements with a common prefix like,
	 * page_, post_, attachment_, author_, _wp_, that prefix is removed to
	 * normalize property names.
	 *
	 * Handles <content:encoded>, <excerpt:encoded>.
	 * Unknown element names are saved in $store fields[].
	 *
	 * @param DOMNode $elt
	 * @param string  $store 'data|meta', property to store unknown elements
	 *
	 * @return Item
	 * @see  Post::setMeta()
	 * @see  Attachment::setMetadata(), Attachment::setImageAlt()
	 * @todo apply Transforms when setting a property @see Kirby\Content::set()
	 */
	public function set(DOMNode $elt, $store = 'fields'): Item
	{
		$prop = $elt->localName;

		/* setContent() <content:encoded>, setExcerpt() <excerpt:encoded> */
		if ($elt->localName == 'encoded') {
			$prop = $elt->prefix;
		}

		/* _wp_attached_file = attached_file, post_parent = parent, postmeta = meta */
		$prop   = preg_replace('/^_wp_?/', '', $prop);
		$prop   = preg_replace($this->prefixFilter, '', $prop);
		$method = 'set' . ucwords($prop, '_');
		$method = str_replace('_', '', $method);

		$transform = $this->transform($elt->nodeName);
		$transform->apply($elt, $this);

		// element setter
		if (method_exists($this, $method)) {
			$this->$method($elt);
			return $this;
		}

		// vanilla assignment
		if (isset($this->{$prop})) {
			$this->{$prop} = $elt->textContent;
		} else {
			$setter = 'set' . ucfirst($store);
			if (method_exists($this, $setter)) {
				$this->$setter($prop, $elt->textContent);
				return $this;
			}
			$this->{$store}[$prop] = $elt->textContent;
		}

		return $this;
	}

	public function __get($name)
	{
		$method = 'get' . ucfirst($name);
		if (method_exists($this, $method)) {
			return $this->$method();
		}

		return (isset($this->{$name})) ? $this->{$name} : null;
	}

	public function setType(DOMNode $type): Item
	{
		$this->type = ucfirst($type->textContent);
		return $this;
	}

	/**
	 * @param DOMNode $title
	 * @return Item
	 */
	public function setTitle(DOMNode $title): Item
	{
		$this->title = $this->clean($title->textContent);
		return $this;
	}

	/**
	 * Applies to <link> (channel, item), <wp:base_site_url>, <wp:base_blog_url>.
	 *
	 * @param DOMNode $link
	 *
	 * @return Item
	 * @see  Channel::setBaseSiteUrl().
	 */
	public function setLink(DOMNode $link): Item
	{
		$link->textContent = $this->cleanUrl($link->textContent);
		$this->link        = $link->textContent;

		return $this;
	}

	/**
	 * Have base URLs use HTTPS and drop 'www' hostname.
	 * Checks $options['resolveUrls'] to enable HTTPS, enable www removal.
	 *
	 * @param string $url
	 * @return string
	 * @var array $config   how to resolveUrls'
	 * @var string $host    the Site host incl. subdomain
	 * @var string $domain  the Site host excl. subdomain
	 */
	public function cleanUrl(string $url): string
	{
		static $config = null, $host = null, $domain = null;

		if ($config === null) {
			$config = (object) Converter::getOption('resolveUrls');
		}
		if ($host === null) {
			$host  = $this->channel()->host;
			/* this might fail i.e. with british URLs w/o subdomain like 'domain.co.uk' */
			$parts = explode('.', $host);
			array_shift($parts);
			$domain = implode('.', $parts);
		}

		$parts = parse_url($url);

		// does this URL belong to us?
		if (strpos($parts['host'], $domain, 1) > 1) {
			// HTTPS
			if (true === $config->link->https) {
				$parts['scheme'] = 'https';
			}

			// drop subdomain
			if (false === $config->link->www) {
				$parts['host'] = $domain;
			} elseif (true === $config->link->www) {
				$parts['host'] = $host;
			}
		}

		$url = '';
		foreach (['scheme'=>'%s://', 'host'=>'%s', 'port'=>':%d', 'path'=>'%s', 'query'=>'?%s', 'fragment'=>'#%s'] as $part => $glue) {
			if (isset($parts[$part])) {
				$url .= sprintf($glue, $parts[$part]);
			}
		}

		return $url;
	}

	/**
	 * Simple HTML special chars cleaner.
	 *
	 * @param string $string
	 *
	 * @return string
	 * @see cleanUrl()
	 */
	protected function clean(string $string): string
	{
		return htmlspecialchars($string, ENT_NOQUOTES | ENT_HTML5, 'UTF-8', false);
	}

	/**
	 * @return Converter
	 * @uses WXR::getConverter()
	 */
	public function converter(): Converter
	{
		return $this->XML->getConverter();
	}

	/**
	 * @return Channel
	 * @uses WXR::getChannel()
	 */
	public function channel(): Channel
	{
		return $this->XML->getChannel();
	}

	/**
	 * @return Site
	 * @uses Converter::getSite()
	 */
	public function site(): Site
	{
		return $this->XML->getConverter()->getSite();
	}

	/**
	 * @param string $username
	 * @return Author
	 * @uses Converter::getAuthor()
	 */
	public function author(string $username): Author
	{
		return $this->XML->getConverter()->getAuthor($username);
	}

	/**
	 * @param string $elementName
	 * @return Transform
	 * @uses Converter::getTransform()
	 */
	public function transform(string $elementName): Transform
	{
		return $this->XML->getConverter()->getTransform($elementName);
	}

	/**
	 * @param string $fieldname
	 * @param string $value
	 * @return Item
	 * @see setFields(), getField()
	 */
	public function addField(string $fieldname, string $value): Item
	{
		$fieldname                = ucfirst($fieldname);
		$this->fields[$fieldname] = $value;

		return $this;
	}

	/**
	 * This is a misnomer as it only sets a single $fields entry.
	 * Serves as proxy to addField().
	 *
	 * @param string $fieldname
	 * @param string $value
	 * @return Item
	 * @uses addField()
	 * @see getField()
	 */
	public function setFields(string $fieldname, string $value): Item
	{
		return $this->addField($fieldname, $value);
	}

	/**
	 * @param string $fieldname
	 * @return string
	 * @see addField(), setFields()
	 */
	public function getField(string $fieldname): string
	{
		return isset($this->fields[$fieldname]) ? $this->fields[$fieldname] : '';
	}

}
