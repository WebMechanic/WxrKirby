<?php
/**
 * Manages core XML and file handling to create Kirby Site, Content, and Files.
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Kirby;

use DOMNode;

abstract class Content
{
	protected $contentPath = 'content/';
	protected $filepath = '';
	protected $filename = '';

	/** @var array */
	protected $content = [];

	/** @var DOMNode */
	protected $node;

	/** @var array reg_replace node prefixes */
	protected $prefixFilter = '//';

	/**
	 * Transform a Wordpress content item into a Kirby content file, page or author.
	 *
	 * @param DOMNode $node
	 */
	public function __construct(DOMNode $node)
	{
		$this->parseNode($node);
	}

	/**
	 * Override in subclasses.
	 *
	 * @param DOMNode $node
	 * @return Content
	 */
	public function parseNode(DOMNode $node)
	{
		$node->normalize();
		$this->node = $node;
		foreach ($node->childNodes as $elt) {
			$this->set($elt->localName, $elt->textContent);
		}
		return $this;
	}

	/**
	 * Sets a property value, delegates to available setters provided by
	 * subclasses like Item, Page or Author.
	 * Unhandled properties go into `content[$prop] = $value`
	 *
	 * @param string $prop A content property and potential Kirby field name
	 * @param        $value
	 *
	 * @return Content
	 * @todo apply Transforms when setting a property @see Wordpress\Item::set()
	 */
	public function set(string $prop, $value): Content
	{
		// turn author_id > id, post_parent > parent etc.
		$method = preg_replace($this->prefixFilter, '', $prop);
		$method = 'set' . ucwords($method, '_');
		$method = str_replace('_', '', $method);

		if (method_exists($this, $method)) {
			return $this->$method($value);
		}

		// vanilla assignment
		$this->content[$prop] = $value;
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
	 * @return string
	 */
	public function getFilename(): string
	{
		return $this->filename;
	}

	/**
	 * Sets the filename of the (currently) active migration content.
	 * Varies as per settings in the Site, Page, File, Author classes.
	 *
	 * @param string $filename
	 *
	 * @return Content
	 */
	public function setFilename(string $filename): Content
	{
		$this->filename = $filename;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFilepath(): string
	{
		return $this->filepath;
	}

	/**
	 * Sets the filepath, usually within `content/` of the (currently) active
	 * migration content. Applies to Page and File (Image) migrations.
	 * Varies as per settings in the Page and File classes.
	 *
	 * @param string $filepath
	 *
	 * @return Content
	 */
	public function setFilepath(string $filepath): Content
	{
		$this->filepath = $filepath;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getContentPath(): string
	{
		return $this->contentPath;
	}

	/**
	 * The target folder for all the lovely migrated content files.
	 *
	 * @param string $contentPath
	 *
	 * @return Content
	 */
	public function setContentPath(string $contentPath): Content
	{
		$this->contentPath = $contentPath;
		return $this;
	}

	/**
	 * Copy files associated with the WP post to $targetPath.
	 *
	 * @param string $filepath
	 *
	 * @return Content
	 * @todo implement copy {$filepath} somewhere
	 */
	protected function copyFile(string $filepath): Content
	{
		echo __METHOD__ . " -- copy {$filepath} somewhere", PHP_EOL;
		return $this;
	}
}
