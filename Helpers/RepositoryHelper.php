<?php

namespace Nodeart\BuilderBundle\Helpers;

use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Entity\UserNode;

class RepositoryHelper
{

    const BOOKMARK_OBJECT = 0;
    const BOOKMARK_USER = 1;
    const BOOKMARK_ENTITY_TYPE = 2;
    const BOOKMARK_COMMENT = 3;
    const BOOKMARK_FIELD_VALUE = 4;
    const BOOKMARK_FIELD_TYPE = 5;

    const BOOKMARKS_TYPES = [
        self::BOOKMARK_OBJECT => ObjectNode::class,
        self::BOOKMARK_USER => UserNode::class,
        self::BOOKMARK_ENTITY_TYPE => EntityTypeNode::class,
        self::BOOKMARK_COMMENT => CommentNode::class,
        self::BOOKMARK_FIELD_VALUE => FieldValueNode::class,
        self::BOOKMARK_FIELD_TYPE => TypeFieldNode::class
    ];

    private $nm;

    public function __construct(EntityManager $nm)
    {
        $this->nm = $nm;
    }

    public function findOneByIdAndType(int $id, int $type)
    {
        if (!in_array($type, array_keys(self::BOOKMARKS_TYPES))) {
            throw new \Exception(sprintf('Possible types are [%s]', join(array_keys(self::BOOKMARKS_TYPES), ',')));
        }

        return $this->nm->getRepository(self::BOOKMARKS_TYPES[$type])->findOneById($id);
    }
}