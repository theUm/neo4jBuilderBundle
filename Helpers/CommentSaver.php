<?php

namespace BuilderBundle\Helpers;

use BuilderBundle\Entity\CommentNode;
use BuilderBundle\Helpers\Util\AbstractCommentValidator;
use BuilderBundle\Helpers\Util\ObjectCommentValidator;
use BuilderBundle\Helpers\Util\TypeCommentValidator;
use BuilderBundle\Helpers\Util\UserCommentValidator;
use GraphAware\Neo4j\OGM\EntityManager;
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
	 * @var AbstractCommentValidator
	 */
	private $validator;

	public function __construct( EntityManager $nm ) {
		$this->nm = $nm;
	}

	public function bindForm( FormInterface $form ) {
		switch ( $form->get( 'refType' )->getData() ) {
			case CommentNode::REF_TYPE_OBJECT: {
				$this->validator = new ObjectCommentValidator();
				break;
			}
			case CommentNode::REF_TYPE_USER: {
				$this->validator = new UserCommentValidator();
				break;
			}
			case CommentNode::REF_TYPE_TYPE: {
				$this->validator = new TypeCommentValidator();
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
		$this->validator->processOrder();

		return $this;
	}

}