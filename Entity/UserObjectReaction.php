<?php

namespace Nodeart\BuilderBundle\Entity;

use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\RelationshipEntity(type="Reaction")
 */
class UserObjectReaction
{
    /**
     * @OGM\GraphId()
     * @var int
     */
    protected $id;

    /**
     * @OGM\Property(type="boolean")
     * @var bool
     */
    protected $liked = false;

    /**
     * @OGM\Property(type="boolean")
     * @var bool
     */
    protected $disliked = false;

    /**
     * @var \DateTime
     * @OGM\Property()
     * @OGM\Convert(type="datetime", options={})
     */
    protected $createdAt;

    /**
     * @var CommentNode
     *
     * @OGM\StartNode(targetEntity="ObjectNode")
     */
    protected $object;

    /**
     * @var UserNode
     *
     * @OGM\EndNode(targetEntity="UserNode")
     */
    protected $user;

    public function __construct(UserNode $user = null, ObjectNode $object = null, $liked = true)
    {
        $this->user = $user;
        $this->object = $object;
        if ($liked) {
            $this->setLiked(true);
        }
        $this->createdAt = new \DateTime();
    }
    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isLiked(): bool
    {
        return $this->liked;
    }

    /**
     * @param bool $liked
     * @return UserObjectReaction
     */
    public function setLiked(bool $liked): UserObjectReaction
    {
        $this->liked = $liked;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisliked(): bool
    {
        return $this->disliked;
    }

    /**
     * @param bool $disliked
     * @return UserObjectReaction
     */
    public function setDisliked(bool $disliked): UserObjectReaction
    {
        $this->disliked = $disliked;
        return $this;
    }

    /**
     * @return CommentNode
     */
    public function getObject(): CommentNode
    {
        return $this->object;
    }

    /**
     * @param CommentNode $object
     * @return UserObjectReaction
     */
    public function setObject(CommentNode $object): UserObjectReaction
    {
        $this->object = $object;
        return $this;
    }

    /**
     * @return UserNode
     */
    public function getUser(): UserNode
    {
        return $this->user;
    }

    /**
     * @param UserNode $user
     * @return UserObjectReaction
     */
    public function setUser(UserNode $user): UserObjectReaction
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}