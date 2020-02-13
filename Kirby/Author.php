<?php
/**
 * Map and assign Kirby authors/users to previous Wordpress user data.
 * Wordpress user names and ids are kept for logging and can be mapped to
 * a Kirby username.
 * <b>Your Kirby accounts won't be touched nor will this create new accounts!</b>
 * Names are used during the transform process and are then simply stored
 * in the site folder as `authors.md`.
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

class Author extends Content
{
	/** @var string this could go into a "user.txt" for a valid Kirby account
	  * @see setLogin() */
	protected $filename = '{username}.md';

	/** @var string target path where the generated user data is temporarily saved */
	protected $contentPath = 'site/accounts/';

	/** @var int Author ID from Wordpress */
	protected $id;
	/** @var int hash key from existing Kirby Account */
	protected $hash;
	/** @var string login name from Wordpress (not used in Kirby) */
	protected $username;
	/** @var string Author email from Wordpress */
	protected $email;

	/** @var array  Account data (user.txt) */
	protected $user = ['firstName'=>null, 'lastName'=>null, 'fullName'=>null];

	/** @var array  */
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
	 *
	 * @return Author
	 */
	public function setFirstName(string $firstName): Author
	{
		$this->user['firstName'] = $firstName;
		$this->setFullName();
		return $this;
	}

	/**
	 * @param string $lastName
	 *
	 * @return Author
	 */
	public function setLastName(string $lastName): Author
	{
		$this->user['lastName'] = $lastName;
		$this->setFullName();
		return $this;
	}

	/**
	 * @param string $full_name
	 */
	public function setFullName(): void
	{
		$this->user['fullName'] = $this->user['firstName'] . ' ' . $this->user['lastName'];
	}

}

