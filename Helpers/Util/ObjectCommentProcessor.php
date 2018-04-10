<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use Nodeart\BuilderBundle\Entity\ObjectNode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class ObjectCommentProcessor extends AbstractCommentProcessor
{
    function processRelId()
    {
        $objectNode = $this->nm->getRepository(ObjectNode::class)->find($this->getRefId());

        if (is_null($objectNode)) {
            throw new NotFoundHttpException('Object with id "' . $this->getRefId() . '" not found', 404);
        }

        $this->comment->setObject($objectNode);
    }

}