<?php
/**
 * Parse "WordPress eXtended RSS" (WXR) file from an export dump.
 *
 * @link    https://wordpress.org/support/article/tools-export-screen/
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMElement;
use DOMDocument;

use WebMechanic\Converter\Converter;

/**
 * Class WXR
 *
 * @package WebMechanic\Converter\Wordpress
 */
class WXR
{
	/** @var DOMDocument */
	private $document;

	/** @var Converter */
	private $converter;

	/** @var Channel */
	private $channel;

	/**
	 * Creates a DOMDocument from the $xmlPath.
	 * To do the parsing and start the conversion once custom options are set
	 * in your Converter instance call `$this->convert()`.
	 *
	 * @param string $xmlPath
	 */
	function __construct(string $xmlPath)
	{
		$xmlPath = realpath($xmlPath);
		/** @noinspection PhpDynamicAsStaticMethodCallInspection */
		$this->document = DOMDocument::load($xmlPath, LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NOCDATA);
	}

	/**
	 * WXR destructor close open handles
	 */
	function __destruct()
	{
		unset ($this->document);
	}

	/**
	 * Parse XML RSS data stored in $document.
	 *
	 * @param Converter $converter
	 */
	public function parse(Converter $converter)
	{
		$this->converter = $converter;
		$root            = $this->document->getElementsByTagName('channel')->item(0);
		$this->channel   = new Channel($root, $this);
		$this->converter->setSite($this->channel);

		foreach ($this->document->getElementsByTagName('author') as $item) {
			$this->converter->setAuthor($item);
		}

		foreach ($this->document->getElementsByTagName('item') as $item) {
			$this->parseItem($item);
		}
	}

	/**
	 * @return DOMDocument
	 */
	public function getDocument(): DOMDocument
	{
		return $this->document;
	}

	/**
	 * @return Converter
	 */
	public function getConverter(): Converter
	{
		return $this->converter;
	}

	/**
	 * @return Channel
	 */
	public function getChannel(): Channel
	{
		return $this->channel;
	}
	public function setChannel(Channel $channel): WXR
	{
		$this->channel = $channel;
		return $this;
	}

	/**
	 * Check the `post_type` of the element and create a Post or Attachment.
	 *
	 * @param DOMElement $item
	 *
	 * @return WXR
	 * @uses \WebMechanic\Converter\Kirby\Page
	 * @uses \WebMechanic\Converter\Kirby\Attachment
	 */
	protected function parseItem(DOMElement $item): WXR
	{
		static $discard = null;
		if ($discard === null) $discard = Converter::getOption('discard');

		$elms = $item->getElementsByTagName('post_type');

		if ($elms->length) {
			$node = $elms->item(0);
			switch ($node->textContent) {
			case 'page':
			case 'post':
				$this->converter->setPage(new Post($item, $this));
				break;
			case 'attachment':
				$this->converter->setFile(new Attachment($item, $this));
				break;

				/* arbitrary plugin stuff or WP internals we don't care about.
				 * @fixme move into a config option of the Converter */
			case 'nav_menu_item':
				// The WP Mainmenu. Only useful to recreate the same structure in Kirby.
				// Due to its complexity, this should be handled in a separate class,
				// rebuilding the node tree based on the `wp:postmeta` information.
				break;

			default:
				if (in_array($node->textContent, $discard)) {
					break;
				} else {
					// @todo throw UnexpectedValueException?
					echo "** WXR::parseItem UNKNOWN type '{$node->textContent}' **", PHP_EOL;
				}
				break;
			}
		}

		return $this;
	}

}

