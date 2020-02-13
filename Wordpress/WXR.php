<?php
/**
 * Parse Wordpress XML RSS data (WXR) from an export dump.
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMNode;
use DOMElement;
use DOMDocument;

use WebMechanic\Converter\Converter;
use WebMechanic\Converter\Kirby\Author;

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

	/** @var array List of XML namespaces found in the RSS root element of the WXR export.
	 * private $namespaces = [
	 * "excerpt"=>"http://wordpress.org/export/1.2/excerpt/",
	 * "content"=>"http://purl.org/rss/1.0/modules/content/",
	 * "wfw"=>"http://wellformedweb.org/CommentAPI/",
	 * "dc"=>"http://purl.org/dc/elements/1.1/",
	 * "wp"=>"http://wordpress.org/export/1.2/"
	 * ];
	 */

	/**
	 * Parse XML RSS data from a Wordpress export dump located in $xmlpath
	 *
	 * @param string $xmlpath
	 * @param string $targetPath for Kirby output file
	 */
	function __construct(string $xmlpath)
	{
		$xmlpath = realpath($xmlpath);
		/** @noinspection PhpDynamicAsStaticMethodCallInspection */
		$this->document = DOMDocument::load($xmlpath, LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NOCDATA);
	}

	/**
	 * WXR destructor close open handles
	 */
	function __destruct()
	{
		unset ($this->document);
	}

	public function parse(Converter $converter)
	{
		$this->converter = $converter;
		$channel         = $this->document->getElementsByTagName('channel')->item(0);
		$this->parseChannel($channel);

		foreach ($this->document->getElementsByTagName('author') as $item) {
			$this->parseAuthor($item);
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
	 * @param DOMNode $channel
	 * @return WXR
	 * @uses Item
	 */
	protected function parseChannel(DOMNode $channel): WXR
	{
		$this->converter->setSite(new Channel($channel, $this));
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
			case 'display_type':
			case 'gal_display_source':
			case 'ngg_pictures':
			case 'ngg_gallery':
			case 'lightbox_library':
			case 'wooframework':
			case 'slide':
				break;

			default:
				// @todo throw UnexpectedValueException?
				echo "** WXR::parseItem UNKNOWN type '{$node->textContent}' **", PHP_EOL;
				break;
			}
		}

		return $this;
	}

	/**
	 * @param DOMElement $wp_author
	 *
	 * @return WXR
	 * @uses \WebMechanic\Converter\Kirby\Author
	 */
	protected function parseAuthor(DOMElement $wp_author): WXR
	{
		$this->converter->setAuthor(new Author($wp_author));
		return $this;
	}
}

