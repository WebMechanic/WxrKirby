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
use WebMechanic\Converter\Converter;

/**
 * Transform a Wordpress content <item> into a Kirby content Site, File,
 * Page or Author.
 */
abstract class Content
{
	/** @var string Kirby content folder; updated at runtime */
	protected $contentPath = '/content/';
	/** @var string relative path in $contentPath of this file */
	protected $filepath = '/';
	/** @var string this file's base filename */
	protected $filename = 'default';
	/** @var string file extension; updated at runtime */
	protected $ext = '.txt';
	/** @var resource file handle */
	protected $fh = null;

	/** @var string link element value of the WP <item> or <channel> */
	protected $link = '';

	/** @var integer Page-ID of the parent <item> (to recreate menus) */
	protected $parent = 0;

	/** @var array */
	protected $content = [];

	/** @var string preg_replace to normalize element name prefixes */
	protected $prefixFilter = '//';

	/**
	 * Override in subclasses.
	 *
	 * @param DOMNode $node
	 * @return Content
	 */
	public function parseNode(DOMNode $node)
	{
		$node->normalize();
		foreach ($node->childNodes as $elt) {
			$this->set($elt->localName, $elt->textContent);
		}
		return $this;
	}

	/**
	 * Takes a Wordpress <item> and reads properties based on item type.
	 *
	 * @param mixed $item some Item derivative
	 * @return mixed
	 */
	abstract public function assign($item);

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
		if (empty($value)) {
			return $this;
		}

		if (empty(trim($this->prefixFilter, '/'))) {
			$f = explode('\\', strtolower(get_class($this)));
			$this->prefixFilter = '/^(' . array_pop($f) . ')_?/';
		}

		// turn author_id > id, post_parent > parent etc.
		$prop   = preg_replace($this->prefixFilter, '', $prop);
		$method = 'set' . ucwords($prop, '_');
		$method = str_replace('_', '', $method);

		if (method_exists($this, $method)) {
			return $this->$method($value);
		}

		// vanilla assignment
		if (isset($this->{$prop})) {
			$this->{$prop} = $value;
		} else {
			$this->content[$prop] = $value;
		}
		return $this;
	}

	/**
	 * Gets an object property if set or delegates to getContent().
	 *
	 * @param $name
	 * @return array|string|Content
	 * @uses getContent()
	 */
	public function __get($name)
	{
		$method = 'get' . ucfirst($name);
		if (method_exists($this, $method)) {
			return $this->$method();
		}

		return (isset($this->{$name})) ? $this->{$name} : $this->getContent($name);
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
		$this->filename = $filename . $this->ext;
		return $this;
	}

	/**
	 * Sets the filepath, usually within `content/` of the (currently) active
	 * migration content. Applies to Page and File (Image) migrations.
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
	 * Returns the fully qualified path to one of Kirby's content folders
	 * as of $options['paths'].
	 *
	 * @param string $folder one of kirby, content, site, assets
	 * @return string
	 * @uses Converter::$options
	 */
	public function getContentPath($folder = 'content'): string
	{
		$paths             = Converter::getOption('paths');
		$this->contentPath = $paths[$folder];
		return $this->contentPath;
	}

	/**
	 * @param $subfolders
	 * @return string
	 * @throws \RuntimeException "Error creating content path"
	 */
	public function createContentPath($subfolders): string
	{
		$contentPath = $this->getContentPath() . $subfolders;
		@mkdir($contentPath, 0750, true);
		$contentPath = realpath($contentPath);
		if (!is_dir($contentPath)) {
			throw new \RuntimeException('Error creating content path [' . $this->getContentPath() . $subfolders . ']');
		}

		return $contentPath;
	}

	/**
	 * Returns the fully qualified content filepath for Kirby. Does not check
	 * if the file actually exists.
	 *
	 * @return string
	 */
	public function getContentFile(): string
	{
		return $this->getContentPath() . $this->filepath . $this->filename;
	}

	/**
	 * Sets a content field that will also appear in the output file.
	 *
	 * @param string       $fieldname
	 * @param string|array $value if NULL removes the content entry
	 * @return Content
	 * @see Post::setContent()
	 */
	public function setContent(string $fieldname, $value): Content
	{
		if (null === $value) {
			unset($this->content[$fieldname]);
		} else {
			$this->content[$fieldname] = $value;
		}
		return $this;
	}

	/**
	 * Gets one of all fields from the $content array.
	 *
	 * @param string $fieldname a content field or NULL for the whole thing
	 * @return string|array|Content
	 */
	public function getContent(string $fieldname = null)
	{
		if ($fieldname === null) {
			return $this->content;
		}
		return isset($this->content[$fieldname]) ? $this->content[$fieldname] : '';
	}

	abstract public function writeOutput();

	protected function write($fieldname, $value)
	{
		if (empty($value)) {
			return;
		}
		if (is_array($value)) {
			$value = @implode(', ', $value);
		}

		$nl   = strlen($value) > 64 ? "\n" : ' ';
		$line = sprintf("%s:{$nl}%s\n\n----\n\n", ucfirst($fieldname), $value);

		if ($this->debug) {
			echo $line;
		} else {
			fwrite($this->fh, $line);
		}
	}

	/**
	 * Take the $link and $filepath of the ressource to create Apache Redirect
	 * rules for some `.htaccess`. Uses a `permanent` redirect status (301) by
	 * default to please and update conforming search engines.
	 *
	 * - permanent: Returns a permanent redirect status (301) indicating that the resource has moved permanently.
	 *      Used by default for virtually all posts, pages and images to log their new URI.
	 * - temp: Returns a temporary redirect status (302).
	 *      Not used by default anywhere.
	 * - seeother: Returns a "See Other" status (303) indicating that the resource has been replaced.
	 *      If you once had a blog and replaced it with an "article archive" or alike.
	 *      Needs manual intervention i.e. using a Transform.
	 * - gone: Returns a "Gone" status (410) indicating that the resource has been permanently removed.
	 *      For stuff you removed entirely. Needs manual intervention i.e. using a Transform.
	 *
	 * @param string $status one of the four status names or a valid status code number
	 * @return string The "Rewrite code old-url new-uri" line for Apache
	 */
	protected function rewriteApache($status = 'permanent')
	{
		static $states = [
			'permanent' => 301,
			'temp' => 302,
			'seeother' => 303,
			'gone' => 410,
		];

		$status = isset($states[$status]) ? $states[$status] : (int)$status;
		if ($status < 300) $status = $states['permanent'];

		$redirect = "Redirect $status " . $this->link;
		if ($status >= 300 || $status < 400) {
			$redirect .= ' ' . $this->filepath;
		}

		echo $redirect; // @fixme write to redirect.log file

		return $redirect;
	}
}
