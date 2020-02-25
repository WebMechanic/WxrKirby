<?php
/**
 * Map and assign Kirby authors/users to previous Wordpress user data.
 * Wordpress user names and ids are kept for logging and can be mapped to
 * a Kirby username.
 * <b>Your Kirby accounts won't be touched nor will this create new accounts!</b>
 * Names are used during the transform process and are then simply stored
 * in the site folder as `{username}.txt`.
 * You can use this to translate all Wordpress "admin" users to you (new)
 * actual Kirby username/author of the target site in order to display their
 * names with the content or to manage editing rights.
 *
 * author > author_id            : id            - [M] map to Kirby user account
 * author > author_login        : username    - [M] map to Kirby user account
 * author > author_email        : email
 * author > author_data['firstName']    : data['firstName']
 * author > author_last_name    : data['lastName']
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Kirby;

use WebMechanic\Converter\Converter;
use WebMechanic\Converter\Wordpress\Item;

class Author extends Content
{
	/** @var string Kirby content folder; updated at runtime */
	protected $contentPath = '/site/accounts/';

	/**
	 * @see setLogin()
	 * @var string this could go into a "user.txt" for a valid Kirby account
	 */
	protected $filename = '{username}.txt';

	/** @var int Author ID from Wordpress */
	protected $id;
	/** @var int hash key from existing Kirby Account */
	protected $hash;
	/** @var string login name from Wordpress (not used in Kirby) */
	protected $username;
	/** @var string Author email from Wordpress */
	protected $email;

	/** @var array  Account data (user.txt) */
	protected $user = ['firstName' => null, 'lastName' => null, 'fullName' => null];

	/** @var array normalize all wp:author_xyz element names */
	protected $prefixFilter = '/^author_?/';

	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 *
	 * @return Author
	 */
	public function setId(int $id): Author
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getUsername(): string
	{
		return $this->username;
	}

	/**
	 * Change the username and the data filename of the Kirby account.
	 *
	 * @param string $login
	 *
	 * @return Author
	 * @see $filename
	 */
	public function setLogin(string $login): Author
	{
		$this->username = $login;
		$this->filename = str_replace('{username}', $login, $this->filename);
		return $this;
	}

	/**
	 * @param string $email
	 *
	 * @return Author
	 */
	public function setEmail(string $email): Author
	{
		$this->email = $email;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getEmail(): string
	{
		return $this->email;
	}

	/**
	 * @param string $firstName
	 * @return Author
	 * @uses setFullName()
	 */
	public function setFirstName(string $firstName): Author
	{
		$this->user['firstName'] = $firstName;
		$this->setFullName();
		return $this;
	}

	public function getFirstName(): string { return $this->user['firstName']; }
	/**
	 * @param string $lastName
	 * @return Author
	 * @uses setFullName()
	 */
	public function setLastName(string $lastName): Author
	{
		$this->user['lastName'] = $lastName;
		$this->setFullName();
		return $this;
	}

	public function getLastName(): string { return $this->user['lastName']; }

	/**
	 * Concatenate $user['fullName'] from 'firstName' + 'lastName'.
	 *
	 * @see setFirstName(), setLastName()
	 */
	public function setFullName(): void
	{
		$this->user['fullName'] = $this->user['firstName'] . ' ' . $this->user['lastName'];
	}
	public function getFullName(): string { return $this->user['fullName']; }

	/**
	 * stub to satisfy Content interface.
	 *
	 * @param Item $author
	 * @return Author
	 */
	public function assign($author): Author
	{
		$this->ext = Converter::getOption('extension', '.txt');
		return $this;
	}

	/** @todo Implement writeOutput() method. */
	public function writeOutput()
	{
		echo <<<ACCOUNT
Username: $this->username
----
Firstname: {$this->user['firstName']}
----
Lastname: {$this->user['lastName']}
----
Fullname: {$this->user['fullName']}
----
Email: $this->email
----

ACCOUNT;
	}

	/**
	 * Creates a JSON representation of this Author for use with Kirby's
	 * users()->create() and users()->update() as illustrated in the
	 * "Migrate Users" Cookbook.
	 *
	 * @link https://getkirby.com/docs/cookbook/setup/migrate-users
	 * @todo implement JSON output
	 */
	public function toJson(): Author
	{
		return $this;
	}

	public function __toString()
	{
		return $this->username;
	}
}

