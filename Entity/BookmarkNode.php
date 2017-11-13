<?php

namespace Nodeart\BuilderBundle\Entity;

use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="Bookmark", repository="Nodeart\BuilderBundle\Entity\Repositories\BookmarkNodeRepository")
 */
class BookmarkNode {

    /**
     * @OGM\GraphId()
     * @var int
     */
    protected $id;


    /**
     * @OGM\Property(type="int")
     * @var int
     */
    protected $refType;

    /**
     * @OGM\Property(type="int")
     * @var int
     */
    protected $refId;

    /**
     * @var \DateTime
     * @OGM\Property(nullable=true)
     * @OGM\Convert(type="datetime", options={})
     */
    protected $createdAt;

    /**
     * @OGM\Relationship(type="bookmarked", direction="OUTGOING", targetEntity="UserNode", collection=false, mappedBy="bookmarks")
     * @var UserNode
     */
    protected $user;

    public function __construct( int $refId, int $type, UserNode $user ) {
        $this->setRefId( $refId );
        $this->setRefType( $type );
        $this->setUser( $user );
        $this->createdAt = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getRefId(): int {
        return $this->refId;
    }

    /**
     * @param int $refId
     *
     * @return BookmarkNode
     */
    public function setRefId( ?int $refId ): BookmarkNode {
        $this->refId = $refId;

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
     */
    public function setCreatedAt( ?\DateTime $createdAt ) {
        $this->createdAt = $createdAt;
    }

    /**
     * @return UserNode
     */
    public function getUser(): UserNode {
        return $this->user;
    }

    /**
     * @param UserNode $user
     */
    public function setUser( UserNode $user ) {
        $this->user = $user;
    }

    /**
     * @return int
     */
    public function getRefType(): int {
        return $this->refType;
    }

    /**
     * @param int $refType
     */
    public function setRefType( int $refType ) {
        $this->refType = $refType;
    }

}