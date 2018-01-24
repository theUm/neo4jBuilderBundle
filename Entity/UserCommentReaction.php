<?php

namespace Nodeart\BuilderBundle\Entity;

use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\RelationshipEntity(type="Reaction")
 */
class UserCommentReaction
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
    protected $liked;

    /**
     * @OGM\Property(type="boolean")
     * @var bool
     */
    protected $disliked;

    /**
     * @OGM\Property(type="boolean")
     * @var bool
     */
    protected $whined;

    /**
     * @var CommentNode
     *
     * @OGM\StartNode(targetEntity="CommentNode")
     */
    protected $comment;

    /**
     * @var UserNode
     *
     * @OGM\EndNode(targetEntity="UserNode")
     */
    protected $user;

    public function __construct(UserNode $user, CommentNode $comment)
    {
        $this->user = $user;
        $this->comment = $comment;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return CommentNode
     */
    public function getComment(): CommentNode
    {
        return $this->comment;
    }

    /**
     * @param CommentNode $comment
     *
     * @return UserCommentReaction
     */
    public function setComment(CommentNode $comment): UserCommentReaction
    {
        $this->comment = $comment;

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
     *
     * @return UserCommentReaction
     */
    public function setUser(UserNode $user): UserCommentReaction
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return bool
     */
    public function isLiked(): ?bool
    {
        return $this->liked;
    }

    /**
     * @param bool $liked
     *
     * @return UserCommentReaction
     */
    public function setLiked(bool $liked): UserCommentReaction
    {
        $this->liked = $liked;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDisliked(): ?bool
    {
        return $this->disliked;
    }

    /**
     * @param bool $disliked
     *
     * @return UserCommentReaction
     */
    public function setDisliked(bool $disliked): UserCommentReaction
    {
        $this->disliked = $disliked;

        return $this;
    }

    /**
     * @return bool
     */
    public function isWhined(): ?bool
    {
        return $this->whined;
    }

    /**
     * @param bool $whined
     *
     * @return UserCommentReaction
     */
    public function setWhined(bool $whined): UserCommentReaction
    {
        $this->whined = $whined;

        return $this;
    }


}