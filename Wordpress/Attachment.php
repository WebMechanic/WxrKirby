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
	protected $type = 'attachment';

	/**
	 * WP creates a public "attachment URL" for each upload.
	 * These URLs are not automatically displayed or exposed per se but may
	 * appear in manually entered image or download links in the HTML.
	 * Review and configure Rewrite rules is Kirby\Content as you see fit.
	 *
	 * @var string wp:attachment_url
	 */
	protected $url = '';

	/** @var array preg_replace node name prefixes to simplify setters */
	protected $prefixFilter = '/^(post|page|attachment)_?/';

	/**
	 * Attachments in WP reside in `/wp-content/uploads/YYYY/MMM/file.jpg`
	 * and their URL stored in `<wp:attachment_url>`. This removes the domain
	 * from that URL and stores the upload path.
	 *
	 * The relative upload path excluding `/wp-content/uploads/` is also
	 * given in the `_wp_attached_file` metadata element.
	 *
	 * @param DOMNode $elt
	 *
	 * @return Attachment
	 * @see setAttachedFile()
	 */
	public function setUrl(DOMNode $elt): Attachment
	{
		$url       = $this->cleanUrl($elt->textContent);
		$this->url = str_replace($this->site()->getUrl(), '', $url);
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
				$this->id       = (int)$query['attachment_id'];
				$this->filepath = $parts['path'] === '/' ? '' : $parts['path'];
			}
		} else {
			parent::setFilepath($url);
		}

		return $this;
	}

	/**
	 * Usually the relative upload path of an attachment/uploaded file.
	 * A full qualified URL is also given in `<wp:attachment_url>`.
	 *
	 * @param DOMNode $elt
	 * @return Attachment
	 * @see setUrl()
	 */
	public function setAttachedFile(DOMNode $elt): Attachment
	{
		$this->setFilepath($elt->textContent);

		return $this;
	}

	/**
	 * unserialised array in CDATA $value
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
	 * @param DOMNode $elt
	 * @return Attachment
	 */
	public function setMetadata(DOMNode $elt): Attachment
	{
		// @todo split into sidecar file (Transform?), IPTC etc.
		$this->meta['metadata'] = unserialize($elt->textContent);
		return $this;
	}
	public function setBackupSizes(DOMNode $elt): Attachment
	{
		// @todo split into sidecar file (Transform?), IPTC etc.
		$this->meta['sizes'] = unserialize($elt->textContent);
		return $this;
	}

	public function setImageAlt(DOMNode $elt): Attachment
	{
		$this->addField('AltText', $elt->textContent);
		return $this;
	}

}
