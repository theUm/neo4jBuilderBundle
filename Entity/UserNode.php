<?php

namespace Nodeart\BuilderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Model\User;
use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="User", repository="Nodeart\BuilderBundle\Entity\Repositories\UserNodeRepository")
 */
class UserNode extends User {
	const ROLE_USER = 'ROLE_USER';
	const ROLE_ADMIN = 'ROLE_ADMIN';
	const ROLE_CONTENT_MANAGER = 'ROLE_CONTENT_MANAGER';

	use isCommentableTrait;

	/**
	 * @OGM\GraphId()
	 * @var int
	 */
	protected $id;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $name;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $username;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $usernameCanonical;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $email;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $emailCanonical;

	/**
	 * @OGM\Property(type="boolean")
	 * @var bool
	 */
	protected $enabled;

	/**
	 * The salt to use for hashing.
	 *
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $salt;

	/**
	 * Encrypted password. Must be persisted.
	 *
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $password;

	/**
	 * Plain password. Used for model validation. Must not be persisted.
	 *
	 * @var string
	 */
	protected $plainPassword;

	/**
	 * @var \DateTime
	 * @OGM\Property()
	 * @OGM\Convert(type="datetime")
	 */
	protected $lastLogin;

	/**
	 * Random string sent to the user email address in order to verify it.
	 *
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $confirmationToken;

	/**
	 * @var \DateTime
	 * @OGM\Property()
	 * @OGM\Convert(type="datetime")
	 */
	protected $passwordRequestedAt;

	/**
	 * relative url to avatar file
	 *
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $avatar;

	/**
	 * @OGM\Relationship(type="commented", direction="INCOMING", targetEntity="CommentNode", collection=true, mappedBy="author")
	 * @var ArrayCollection|CommentNode[]
	 */
	protected $comments;

	/**
	 * @var array|null
	 * @OGM\Property(type="array", nullable=false)
	 */
	protected $roles = [ 'ROLE_USER' ];
	/**
	 * @var array
	 * @OGM\Property(type="array")
	 */
	protected $groups = [ 'DEFAULT_GROUP' ];

	public function __construct() {
		parent::__construct();
		$this->roles    = [ self::ROLE_USER ];
		$this->comments = new ArrayCollection();
	}

	/**
	 * @return int
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @param int $id
	 *
	 * @return UserNode
	 */
	public function setId( int $id ): UserNode {
		$this->id = $id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $name
	 *
	 * @return UserNode
	 */
	public function setName( string $name ): UserNode {
		$this->name = $name;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUsername(): ?string {
		return $this->username;
	}

	/**
	 * @param string $username
	 *
	 * @return UserNode
	 */
	public function setUsername( $username ): UserNode {
		$this->username = $username;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUsernameCanonical(): string {
		return $this->usernameCanonical;
	}

	/**
	 * @param string $usernameCanonical
	 *
	 * @return UserNode
	 */
	public function setUsernameCanonical( $usernameCanonical ): UserNode {
		$this->usernameCanonical = $usernameCanonical;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEmail(): ?string {
		return $this->email;
	}

	/**
	 * @param string $email
	 *
	 * @return UserNode
	 */
	public function setEmail( $email ): UserNode {
		$this->email = $email;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEmailCanonical(): string {
		return $this->emailCanonical;
	}

	/**
	 * @param string $emailCanonical
	 *
	 * @return UserNode
	 */
	public function setEmailCanonical( $emailCanonical ): UserNode {
		$this->emailCanonical = $emailCanonical;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * @param bool $enabled
	 *
	 * @return UserNode
	 */
	public function setEnabled( $enabled ): UserNode {
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSalt(): ?string {
		return $this->salt;
	}

	/**
	 * @param string $salt
	 *
	 * @return UserNode
	 */
	public function setSalt( $salt ): UserNode {
		$this->salt = $salt;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPassword(): string {
		return $this->password;
	}

	/**
	 * @param string $password
	 *
	 * @return UserNode
	 */
	public function setPassword( $password ): UserNode {
		$this->password = $password;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getPlainPassword() {
		return $this->plainPassword;
	}

	/**
	 * @param mixed $plainPassword
	 *
	 * @return UserNode
	 */
	public function setPlainPassword( $plainPassword ) {
		$this->plainPassword = $plainPassword;

		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getLastLogin(): \DateTime {
		return $this->lastLogin;
	}

	/**
	 * @param \DateTime $lastLogin
	 *
	 * @return UserNode
	 */
	public function setLastLogin( \DateTime $lastLogin = null ): UserNode {
		$this->lastLogin = $lastLogin;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getConfirmationToken(): string {
		return $this->confirmationToken;
	}

	/**
	 * @param string $confirmationToken
	 *
	 * @return UserNode
	 */
	public function setConfirmationToken( $confirmationToken ): UserNode {
		$this->confirmationToken = $confirmationToken;

		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getPasswordRequestedAt(): ?\DateTime {
		return $this->passwordRequestedAt;
	}

	/**
	 * @param \DateTime $passwordRequestedAt
	 *
	 * @return UserNode
	 */
	public function setPasswordRequestedAt( \DateTime $passwordRequestedAt = null ): UserNode {
		$this->passwordRequestedAt = $passwordRequestedAt;

		return $this;
	}

	/**
	 * @return array|null
	 */
	public function getRoles(): ?array {
		return $this->roles;
	}

	/**
	 * @param array $roles
	 *
	 * @return UserNode
	 */
	public function setRoles( array $roles = null ): UserNode {
		$this->roles = $roles;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function serialize() {
		return serialize( array(
			$this->password,
			$this->salt,
			$this->usernameCanonical,
			$this->username,
			$this->enabled,
			$this->id,
			$this->email,
			$this->emailCanonical,
			// notice roles. they also here
			$this->roles
		) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function unserialize( $serialized ) {
		$data = unserialize( $serialized );

		if ( 13 === count( $data ) ) {
			// Unserializing a User object from 1.3.x
			unset( $data[4], $data[5], $data[6], $data[9], $data[10] );
			$data = array_values( $data );
		} elseif ( 11 === count( $data ) ) {
			// Unserializing a User from a dev version somewhere between 2.0-alpha3 and 2.0-beta1
			unset( $data[4], $data[7], $data[8] );
			$data = array_values( $data );
		}

		list(
			$this->password,
			$this->salt,
			$this->usernameCanonical,
			$this->username,
			$this->enabled,
			$this->id,
			$this->email,
			$this->emailCanonical,
			// notice roles. they also here
			$this->roles
			) = $data;
	}

	/**
	 * @return string
	 */
	public function getAvatar(): ?string {
		return $this->avatar;
	}

	/**
	 * @param string $avatar
	 */
	public function setAvatar( string $avatar ) {
		$this->avatar = $avatar;
	}

	/**
	 * @return ArrayCollection|CommentNode[]
	 */
	public function getComments(): ArrayCollection {
		return $this->comments;
	}

	/**
	 * @param ArrayCollection|CommentNode[] $comments
	 */
	public function setComments( $comments ) {
		$this->comments = $comments;
	}

}