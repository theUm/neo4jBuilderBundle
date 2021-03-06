<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use Nodeart\BuilderBundle\Entity\UserNode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class UserCommentProcessor extends AbstractCommentProcessor
{
    function processRelId()
    {
        $userNode = $this->nm->getRepository(UserNode::class)->find($this->getRefId());

        if (is_null($userNode)) {
            throw new NotFoundHttpException('User with id "' . $this->getRefId() . ' not found"', 404);
        }

        $this->comment->setUser($userNode);
    }
}