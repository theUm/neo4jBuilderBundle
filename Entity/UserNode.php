<?php

namespace Nodeart\BuilderBundle\Entity;

use FOS\UserBundle\Model\User;
use GraphAware\Neo4j\OGM\Annotations as OGM;
use GraphAware\Neo4j\OGM\Common\Collection;

/**
 * @OGM\Node(label="User", repository="Nodeart\BuilderBundle\Entity\Repositories\UserNodeRepository")
 */
class UserNode extends User implements \Serializable {
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
	 * @var array|null
	 * @OGM\Property(type="array", nullable=false)
	 */
	protected $roles = [ 'ROLE_USER' ];
	/**
	 * @var array
	 * @OGM\Property(type="array")
	 */
	protected $groups = [ 'DEFAULT_GROUP' ];

	/**
	 * @OGM\Property(key="google_id", type="string")
	 * @var string
	 */
	protected $googleId;

	/**
	 * @OGM\Property(key="vk_id", type="string")
	 * @var string
	 */
	protected $vkId;

	/**
	 * @OGM\Property(key="fb_id", type="string")
	 * @var string
	 */
	protected $fbId;

	/**
	 * @var string
	 */
	protected $googleAccessToken;

	/**
	 * @var string
	 */
	protected $vkAccessToken;

	/**
	 * @var string
	 */
	protected $fbAccessToken;

	/**
	 * @OGM\Relationship(type="commented", direction="INCOMING", targetEntity="CommentNode", collection=true, mappedBy="author")
	 * @var Collection|CommentNode[]
	 */
	protected $comments;

	/**
	 * @var Collection|UserCommentReaction[]
	 *
	 * @OGM\Relationship(relationshipEntity="UserCommentReaction", type="Reaction", direction="OUTGOING", collection=true, mappedBy="user")
	 */
	protected $reactions;

	public function __construct() {
		parent::__construct();
		$this->roles     = [ self::ROLE_USER ];
		$this->comments  = new Collection();
		$this->reactions = new Collection();
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
	public function getName(): ?string {
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
	public function getUsernameCanonical(): ?string {
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
	public function getEmailCanonical(): ?string {
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
	public function getPassword(): ?string {
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
			$this->roles,
			//and social ids
			$this->googleId,
			$this->fbId,
			$this->vkId
		) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function unserialize( $serialized ) {
		$data = unserialize( $serialized );
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
			$this->roles,
			//and social ids
			$this->googleId,
			$this->fbId,
			$this->vkId
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
	 * @return Collection|CommentNode[]
	 */
	public function getComments(): Collection {
		return $this->comments;
	}

	/**
	 * @param Collection|CommentNode[] $comments
	 */
	public function setComments( $comments ) {
		$this->comments = $comments;
	}

	/**
	 * @param UserCommentReaction $reaction
	 *
	 * @return UserNode
	 */
	public function addReaction( UserCommentReaction $reaction ): UserNode {
		$this->reactions->add( $reaction );

		return $this;
	}

	/**
	 * @param Collection|UserCommentReaction[] $reactions
	 *
	 * @return UserNode
	 */
	public function setReactions( Collection $reactions ): UserNode {
		$this->reactions = $reactions;

		return $this;
	}

	/**
	 * @return Collection|UserCommentReaction[]
	 */
	public function getReactions() {
		return $this->reactions;
	}

	/**
	 * @return string
	 */
	public function getGoogleId(): ?string {
		return $this->googleId;
	}

	/**
	 * @param string $googleId
	 */
	public function setGoogleId( $googleId ) {
		$this->googleId = $googleId;
	}

	/**
	 * @param string $googleAccessToken
	 *
	 * @return User
	 */
	public function setGoogleAccessToken( $googleAccessToken ) {
		$this->googleAccessToken = $googleAccessToken;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getGoogleAccessToken() {
		return $this->googleAccessToken;
	}

	/**
	 * @return string
	 */
	public function getVkAccessToken(): ?string {
		return $this->vkAccessToken;
	}

	/**
	 * @param string $vkAccessToken
	 */
	public function setVkAccessToken( $vkAccessToken ) {
		$this->vkAccessToken = $vkAccessToken;
	}

	/**
	 * @return string
	 */
	public function getVkId(): ?string {
		return $this->vkId;
	}

	/**
	 * @param string $vkId
	 */
	public function setVkId( $vkId ) {
		$this->vkId = $vkId;
	}


	/**
	 * @return string
	 */
	public function getFbId(): ?string {
		return $this->fbId;
	}

	/**
	 * @param string $fbId
	 *
	 * @return UserNode
	 */
	public function setFbId( $fbId ): UserNode {
		$this->fbId = $fbId;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getFbAccessToken(): ?string {
		return $this->fbAccessToken;
	}

	/**
	 * @param string $fbAccessToken
	 *
	 * @return UserNode
	 */
	public function setFbAccessToken( $fbAccessToken ): UserNode {
		$this->fbAccessToken = $fbAccessToken;

		return $this;
	}

}