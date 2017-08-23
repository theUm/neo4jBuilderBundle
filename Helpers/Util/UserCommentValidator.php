<?php

namespace BuilderBundle\Helpers\Util;

use BuilderBundle\Entity\UserNode;
use Doctrine\ORM\EntityNotFoundException;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class UserCommentValidator extends AbstractCommentValidator {
	function processRelId() {
		$userNode = $this->nm->getRepository( UserNode::class )->find( $this->getRefId() );

		if ( is_null( $userNode ) ) {
			throw new EntityNotFoundException( 'User with id "' . $this->getRefId() . ' not found"', 404 );
		}

		$this->comment->setUser( $userNode );
	}
}