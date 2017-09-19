<?php

namespace Nodeart\BuilderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations as OGM;
use GraphAware\Neo4j\OGM\Common\Collection;

/**
 * @OGM\Node(label="Comment", repository="Nodeart\BuilderBundle\Entity\Repositories\CommentNodeRepository")
 */
class CommentNode {
	const COMM_LEVEL_MAIN = 0;
	const COMM_LEVEL_REPLY = 1;

	const CAT_COMMENT = 'cat_comment';
	const CAT_MISTAKE = 'cat_mistake';
	const CAT_SUGGEST = 'cat_suggest';

	const RELATION_TYPE_USER = 'ref_user';
	const RELATION_TYPE_OBJECT = 'ref_object';
	const RELATION_TYPE_TYPE = 'ref_type';

	/**
	 * @OGM\GraphId()
	 * @var int
	 */
	protected $id;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $comment = '';

	/**
	 * @var \DateTime
	 * @OGM\Property()
	 * @OGM\Convert(type="datetime")
	 */
	protected $createdAt;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $refType = self::CAT_COMMENT;

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $relationType = self::RELATION_TYPE_OBJECT;

	/**
	 * @OGM\Property(type="int")
	 * @var int
	 */
	protected $likes = 0;

	/**
	 * @OGM\Property(type="int")
	 * @var int
	 */
	protected $dislikes = 0;

	/**
	 * @OGM\Property(type="int")
	 * @var int
	 */
	protected $reports = 0;

	/**
	 * @OGM\Property(type="int")
	 * @var int
	 */
	protected $level = 0;

	/**
	 * @OGM\Relationship(type="commented", direction="OUTGOING", targetEntity="UserNode", collection=false, mappedBy="comments")
	 * @var UserNode
	 */
	protected $author;

	/**
	 * @OGM\Relationship(type="is_child_of", direction="OUTGOING", targetEntity="CommentNode", collection=false, mappedBy="childComments")
	 * @var CommentNode
	 */
	protected $parentComment;

	/**
	 * @OGM\Relationship(type="is_ref_to", direction="OUTGOING", targetEntity="CommentNode", collection=false)
	 * @var CommentNode
	 */
	protected $refComment;

	/**
	 * @OGM\Relationship(type="is_child_of", direction="INCOMING", targetEntity="CommentNode", collection=true)
	 * @var ArrayCollection
	 */
	protected $childComments;

	/**
	 * @OGM\Relationship(type="is_comment_of", direction="BOTH", targetEntity="ObjectNode", collection=false, mappedBy="comments")
	 * @var ObjectNode
	 */
	protected $object;

	/**
	 * @OGM\Relationship(type="is_comment_of", direction="BOTH", targetEntity="EntityTypeNode", collection=false, mappedBy="commentsAbout")
	 * @var EntityTypeNode
	 */
	protected $entityType;

	/**
	 * @OGM\Relationship(type="is_comment_of", direction="BOTH", targetEntity="UserNode", collection=false, mappedBy="commentsAbout")
	 * @var UserNode
	 */
	protected $user;

	/**
	 * @var UserCommentReaction[]
	 *
	 * @OGM\Relationship(relationshipEntity="UserCommentReaction", type="Reaction", direction="OUTGOING", collection=true, mappedBy="comment")
	 */
	protected $reactions;

	public function __construct() {
		$this->childComments = new Collection();
		$this->createdAt     = new \DateTime();
		$this->reactions     = new Collection();
	}

	/**
	 * @return int
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getComment(): string {
		return $this->comment;
	}

	/**
	 * @param string $comment
	 *
	 * @return CommentNode
	 */
	public function setComment( string $comment ): CommentNode {
		$this->comment = $comment;

		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getCreatedAt(): ?\DateTime {
		return $this->createdAt;
	}

	/**
	 * @param \DateTime $createdAt
	 *
	 * @return CommentNode
	 */
	public function setCreatedAt( \DateTime $createdAt ): CommentNode {
		$this->createdAt = $createdAt;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLikes(): ?int {
		return $this->likes;
	}

	/**
	 * @param int $likes
	 *
	 * @return CommentNode
	 */
	public function setLikes( int $likes ): CommentNode {
		$this->likes = $likes;

		return $this;
	}


	public function changeLikes( int $value ) {
		$this->likes = $this->likes + $value;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getDislikes(): ?int {
		return $this->dislikes;
	}

	/**
	 * @param int $dislikes
	 *
	 * @return CommentNode
	 */
	public function setDislikes( int $dislikes ): CommentNode {
		$this->dislikes = $dislikes;
		return $this;
	}

	public function changeDislikes( int $value ) {
		$this->dislikes = $this->dislikes + $value;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getReports(): ?int {
		return $this->reports;
	}

	/**
	 * @param int $reports
	 *
	 * @return CommentNode
	 */
	public function setReports( int $reports ): CommentNode {
		$this->reports = $reports;

		return $this;
	}

	public function changeReports( int $value ) {
		$this->reports = $this->reports + $value;

		return $this;
	}

	/**
	 * @return UserNode
	 */
	public function getAuthor(): UserNode {
		return $this->author;
	}

	/**
	 * @param UserNode $author
	 *
	 * @return CommentNode
	 */
	public function setAuthor( UserNode $author ): CommentNode {
		$this->author = $author;

		return $this;
	}

	/**
	 * @return CommentNode|null
	 */
	public function getParentComment() {
		return $this->parentComment;
	}

	/**
	 * @param CommentNode $parentComment
	 *
	 * @return CommentNode
	 */
	public function setParentComment( CommentNode $parentComment ): CommentNode {
		$this->parentComment = $parentComment;

		return $this;
	}

	/**
	 * @return ObjectNode
	 */
	public function getObject() {
		return $this->object;
	}

	/**
	 * @param ObjectNode $object
	 *
	 * @return CommentNode
	 */
	public function setObject( ObjectNode $object ): CommentNode {
		$this->object = $object;

		return $this;
	}

	/**
	 * @return EntityTypeNode
	 */
	public function getEntityType() {
		return $this->entityType;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return CommentNode
	 */
	public function setEntityType( EntityTypeNode $entityType ): CommentNode {
		$this->entityType = $entityType;

		return $this;
	}

	/**
	 * @return UserNode
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @param UserNode $user
	 *
	 * @return CommentNode
	 */
	public function setUser( UserNode $user ): CommentNode {
		$this->user = $user;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRefType(): string {
		return $this->refType;
	}

	/**
	 * @param string $refType
	 */
	public function setRefType( string $refType ) {
		$this->refType = $refType;
	}

	/**
	 * @return int
	 */
	public function getLevel(): ?int {
		return $this->level;
	}

	/**
	 * @param int $level
	 *
	 * @return CommentNode
	 */
	public function setLevel( int $level ): CommentNode {
		$this->level = $level;

		return $this;
	}

	/**
	 * @return ArrayCollection
	 */
	public function getChildComments() {
		return $this->childComments;
	}

	/**
	 * @param ArrayCollection $childComments
	 */
	public function setChildComments( $childComments ) {
		$this->childComments = $childComments;
	}

	/**
	 * @param CommentNode $refComment
	 *
	 * @return CommentNode
	 */
	public function setRefComment( CommentNode $refComment ): CommentNode {
		$this->refComment = $refComment;

		return $this;
}

	/**
	 * @return CommentNode
	 */
	public function getRefComment() {
		return $this->refComment;
	}

	/**
	 * @param UserCommentReaction $reaction
	 *
	 * @return CommentNode
	 */
	public function addReaction( UserCommentReaction $reaction ): CommentNode {
		$this->reactions->add( $reaction );

		return $this;
	}

	/**
	 * @param Collection|UserCommentReaction[] $reactions
	 *
	 * @return CommentNode
	 */
	public function setReactions( Collection $reactions ): CommentNode {
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
	 * @param UserNode $user
	 *
	 * @return UserCommentReaction|null
	 */
	public function getReactionByUser( UserNode $user ) {
		$userReaction = null;
		foreach ( $this->getReactions() as $reaction ) {
			if ( $user === $reaction->getUser() ) {
				return $reaction;
			}
		}

		return null;
	}

	/**
	 * @return string
	 */
	public function getRelationType(): ?string {
		return $this->relationType;
	}

	/**
	 * @param string $relationType
	 */
	public function setRelationType( string $relationType ) {
		$this->relationType = $relationType;
	}

}