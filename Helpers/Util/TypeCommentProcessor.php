<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class TypeCommentProcessor extends AbstractCommentProcessor
{
    function processRelId()
    {
        /** @var EntityTypeNode|null $entityTypeNode */
        $entityTypeNode = $this->nm->getRepository(EntityTypeNode::class)->find($this->getRefId());

        if (is_null($entityTypeNode)) {
            throw new NotFoundHttpException('Entity type with id "' . $this->getRefId() . ' not found"', 404);
        }

        $this->comment->setEntityType($entityTypeNode);
    }
}