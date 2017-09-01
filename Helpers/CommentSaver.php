<?php

namespace Nodeart\BuilderBundle\Helpers;

use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Helpers\Util\AbstractCommentProcessor;
use Nodeart\BuilderBundle\Helpers\Util\ObjectCommentProcessor;
use Nodeart\BuilderBundle\Helpers\Util\TypeCommentProcessor;
use Nodeart\BuilderBundle\Helpers\Util\UserCommentProcessor;
use Symfony\Component\Form\FormInterface;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class CommentSaver {

	private $nm;

	/**
	 * @var AbstractCommentProcessor
	 */
	private $validator;

	public function __construct( EntityManager $nm ) {
		$this->nm = $nm;
	}

	public function bindForm( FormInterface $form ) {
		switch ( $form->get( 'refType' )->getData() ) {
			case CommentNode::REF_TYPE_OBJECT: {
				$this->validator = new ObjectCommentProcessor();
				break;
			}
			case CommentNode::REF_TYPE_USER: {
				$this->validator = new UserCommentProcessor();
				break;
			}
			case CommentNode::REF_TYPE_TYPE: {
				$this->validator = new TypeCommentProcessor();
				break;
			}
		}
		$this->validator->bindForm( $form );
		$this->validator->bindNM( $this->nm );

		return $this;
	}

	public function bindComment( CommentNode $comment ) {
		$this->validator->bindComment( $comment );

		return $this;
	}

	public function processForm() {
		$this->validator->processRelId();
		$this->validator->processParentComment();

		return $this;
	}

}