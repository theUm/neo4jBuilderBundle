<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use Doctrine\ORM\EntityNotFoundException;
use Nodeart\BuilderBundle\Entity\ObjectNode;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class ObjectCommentValidator extends AbstractCommentValidator {
	function processRelId() {
		$objectNode = $this->nm->getRepository( ObjectNode::class )->find( $this->getRefId() );

		if ( is_null( $objectNode ) ) {
			throw new EntityNotFoundException( 'Object with id "' . $this->getRefId() . ' not found"', 404 );
		}

		$this->comment->setObject( $objectNode );
	}

}