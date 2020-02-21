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
use WebMechanic\Converter\Kirby\Author;
use WebMechanic\Converter\Transform;

class Item
{
	/** @var int wp:post_id */
	protected $id;
	/** @var string */
	protected $title;
	/** @var string */
	protected $link;
	/** @var string wp:post_type, redundant */
	protected $type;
	/** @var string */
	protected $description;

	/** @var array misc. text nodes */
	protected $data = [];
	/** @var array custom fields collected on the go */
	protected $fields = [];

	/** @var DOMNode current element */
	protected $node;
	/** @var WXR */
	protected $XML;
	/** @var Transform the current element's transform, if any */
	protected $transform;

	/** @var array reg_replace node prefixes to simplify setters */
	protected $prefixFilter = '//';

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
	 * @param DOMNode $node
	 * @return Item
	 */
	function parse(DOMNode $node)
	{
		$node->normalize();
		foreach ($node->childNodes as $elt) {
			if (XML_ELEMENT_NODE == $elt->nodeType) {
				$this->set($elt);
			}
		}
		return $this;
	}

	/**
	 * Find and call a setter for the element in a subclass and delegates
	 * to the corresponding methods. For elements with the`wp:post_` prefix,
	 * the prefix is removed, i.e. post_id = id, post_parent = parent, postmeta = meta.
	 * Handles <content:encoded>, <excerpt:encoded>.
	 * Unknown element names are saved in $store (data[]).
	 *
	 * @param DOMNode $elt
	 * @param string  $store 'data|meta', property to store unknown elements
	 *
	 * @return Item
	 * @todo apply Transforms when setting a property @see Kirby\Content::set()
	 */
	public function set(DOMNode $elt, $store = 'data'): Item
	{
		$prop = $elt->localName;

		/* setContent() <content:encoded>, setExcerpt() <excerpt:encoded> */
		if ($elt->localName == 'encoded') {
			$prop = ucfirst($elt->prefix);
		}

		/* author_id/post_id = id, post_parent = parent, postmeta = meta */
		$method = preg_replace($this->prefixFilter, '', $prop);

		$method = 'set' . ucwords($method, '_');
		$method = str_replace('_', '', $method);

		/* only deal with this if there's some content.
		 * Empty nodes or <![CDATA[]]> is not. */
		if ($elt->firstChild) {
			$this->transform = $this->transform($elt->nodeName);

			// element data setter
			if (method_exists($this, $method)) {
				$this->$method($elt);
				$this->transform->apply($elt, $this);
				return $this;
			}

			// vanilla assignment
			$this->transform->apply($elt, $this);
			$this->{$store}[$prop] = $elt->textContent;
//		} else {
//			echo "~ $prop ** EMPTY ** [", $elt->textContent, "]", PHP_EOL;
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
	 * @param DOMNode $desc
	 * @return Item
	 */
	public function setDescription(DOMNode $desc): Item
	{
		$this->description = $this->clean($desc->textContent);
		return $this;
	}

	/**
	 * Have base URLs use HTTPS and drop 'www' hostname.
	 * Applies to <link> (channel, item), <wp:base_site_url>, <wp:base_blog_url>.
	 * Set `$transformOnly = true` to use this method as a general utility
	 * to ONLY modify the `$link->textContent` w/o changing the $link property
	 * of $this object; i.e. @param DOMNode $link
	 *
	 * @return Item
	 * @see  Channel::setBaseSiteUrl().
	 *
	 * @todo use config options to: enable HTTPS, enable www removal, replace domainname
	 */
	public function setLink(DOMNode $link, $transformOnly = false): Item
	{
		// HTTPS
		$link->textContent = str_replace('http:', 'https:', $link->textContent);
		// no 'www'
		$link->textContent = str_replace('://www.', '://', $link->textContent);

		if ($transformOnly) return $this;

		$this->link = $link->textContent;
		return $this;
	}

	/**
	 * @param DOMNode $elt
	 * @return Item
	 */
	public function setType(DOMNode $elt): Item
	{
		$this->type = $elt->textContent;
		return $this;
	}

	/**
	 * Simple HTML special chars cleaner.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	protected function clean(string $string): string
	{
		return htmlspecialchars($string, ENT_NOQUOTES | ENT_HTML5, 'UTF-8', false);
	}

	public function converter(): Converter
	{
		return $this->XML->getConverter();
	}

	public function site(): Item
	{
		return $this->XML->getConverter()->getSite();
	}

	public function author(string $username): Author
	{
		return $this->XML->getConverter()->getAuthor($username);
	}

	public function transform(string $elementName): Transform
	{
		return $this->XML->getConverter()->getTransform($elementName);
	}

	/**
	 * @param string $fieldname
	 * @param string $value
	 * @return string
	 * @todo collect all fields and replace with Kirby's native Content class to render the target file?
	 */
	public function addField(string $fieldname, string $value): Item
	{
		$fieldname = ucfirst($fieldname);
		$content   = $value;
		$content   = str_replace(array('<![CDATA[', ']]>'), '', $content);
		$content   = <<<MD
{$fieldname} :
{$content}

----

MD;
		$this->fields[$fieldname] = [$value, $content];

		return $this;
	}
}
