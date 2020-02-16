<?php
/**
 * Provides mappings of XML elements to Markdown fields.
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter;

use DOMNode;
use WebMechanic\Converter\Wordpress\Item;

class Transform
{
	/** @var null replacement or callable */
	private $handler;

	/**
	 * Transform constructor takes a $handler function that does the job.
	 * The Closure itself must accept a `DOMNode` or `DOMElement` as its
	 * 1st argument and an optional `Item` (or derivative) to `apply()`
	 * the transformation.
	 *
	 * There's no requirement to use the `Closure` class. using a vanilla
	 * `new Transform( function(DOMNode $node, Item $item) {...} )`
	 * will do.
	 *
	 * @param \Closure|null $handler
	 * @see apply(), Meta::addHandler()
	 */
	function __construct(\Closure $handler = null)
	{
		$this->handler = $handler;
	}

	/**
	 * Apply the transformation handler.
	 *
	 * Since a transformation is virtually applicable to any public property
	 * of the two objects incl. der DOMNode's child nodes, a callback's
	 * return value (if any) is unpredictable and thus ignored.
	 * Something like this inside an Item-ish class' custom setWhatever()
	 * method is usually fine:
	 * <listing>
	 *     $this->transform($node->nodeName)->apply($node);
	 *     $this->url = $node->textContent;
	 * </listing>
	 *
	 * See Item::set() and Meta::apply() for a more complex examples.
	 *
	 * @param DOMNode $node the XML node
	 * @param Item    $post usually a Wordpress\Page|Attachment object (so far)
	 * @see Item::set(), Meta::apply(), Meta::addHandler()
	 */
	function apply(DOMNode $node, Item $post = null): void
	{
		if (is_callable($this->handler)) {
			call_user_func($this->handler, $node, $post);
		}
	}

}