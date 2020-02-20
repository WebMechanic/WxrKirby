<?php
/**
 * A single "attachment" item from the Wordpress XML export from `<wp:post_type>attachment`
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Wordpress;

use DOMNode;

class Attachment extends Post
{
	/** @var string wp:attachment_url */
	public $url;

	/** @var string _wp_attached_file */
	protected $image;

	/**
	 * Gets a `<wp:postmeta>` element for use as image sidecar files.
	 *
	 * The element consists of two children. Their value goes into $meta:
	 * - firstChild: <wp:meta_key> '_wp_attached_file|_wp_attachment_metadata'
	 * - lastChild : <wp:meta_value>  string|serialized array of an image attachment
	 *
	 * @param DOMNode $meta
	 * @return Attachment
	 */
	public function setMetadata(DOMNode $meta): Attachment
	{
		/** @var string <wp:meta_key> => '_wp_attached_file', '_wp_attachment_metadata' */
		$key = $meta->firstChild->textContent;

		if (method_exists($this, $key)) {
			/** @var string <wp:meta_value> */
			$value = $meta->lastChild->textContent;
			$this->{$key}($value);
		}
		return $this;
	}

	/**
	 * Attachments in WP reside in `wp-content/uploads/YYYY/MMM/`. This removes
	 * the domain name. The relative upload path is also given in the
	 * `_wp_attached_file` metadata element, @param DOMNode $elt
	 *
	 * @return Attachment
	 * @see _wp_attached_file().
	 */
	public function setAttachmentUrl(DOMNode $elt): Attachment
	{
		$baseUrl     = $this->site()->url;
		$this->image = str_replace($baseUrl, '', $elt->textContent);
		return $this;
	}

	/**
	 * Find an 'attachment_id' or delegate to Post.
	 *
	 * @param string $url
	 * @return Post
	 */
	public function setFilepath(string $url): Post
	{
		// attachment_id=5
		$parts = parse_url($url);
		if (isset($parts['query'])) {
			parse_str($parts['query'], $query);
			if (isset($query['attachment_id'])) {
				$this->id = (int)$query['attachment_id'];
				$this->filepath = $parts['path'] === '/' ? '' : $parts['path'];
			}
		} else {
			parent::setFilepath($url);
		}

		return $this;
	}

	/**
	 * Usually the relative upload path of an attachment/uploaded file.
	 * A full qualified URL is also given in @param string $value
	 *
	 * @return Attachment
	 * @see setAttachmentUrl().
	 *
	 */
	private function _wp_attached_file(string $value): Attachment
	{
		$this->filepath = $value;
		return $this;
	}
	public function setAttachedFile(string $value): Attachment
	{
		return $this->_wp_attached_file($value);
	}

	/**
	 * unserialize array in CDATA $value
	 * - width
	 * - height
	 * - file
	 * - custom_property_1-n
	 * - sizes
	 *   - thumbnail
	 *     - file
	 *     - width
	 *     - height
	 *   - 'Some Custom Name 1-n'
	 *     - file
	 *     - width
	 *     - height
	 * - image_meta  (IPTC etc.)
	 *
	 * @param string $value
	 *
	 * @return Attachment
	 */
	private function _wp_attachment_metadata(string $value): Attachment
	{
		// @todo split into sidecar file (Transform?), IPTC etc.
		$this->meta['metadata']  = unserialize($value);

		return $this;
	}
	public function setAttachmentMetadata(string $value): Attachment
	{
		return $this->_wp_attachment_metadata($value);
	}

	public function toKirby()
	{
		return sprintf('# Kirbyfy Attachment: %d "%s"' . PHP_EOL . '  %s' . PHP_EOL,
			$this->id, $this->title, $this->filepath);
	}

}
