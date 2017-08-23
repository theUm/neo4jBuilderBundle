<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use Doctrine\ORM\EntityNotFoundException;
use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\Repositories\CommentNodeRepository;
use Symfony\Component\Form\FormInterface;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
abstract class AbstractCommentValidator {
	const FORM_REL_ID_FIELD_NAME = 'refId';
	const FORM_REF_COMMENT_ID_FIELD_NAME = 'refComment';

	protected $form;
	/**
	 * @var EntityManager
	 */
	protected $nm;
	/**
	 * @var CommentNode
	 */
	protected $comment;
	protected $refId;
	protected $parentCommentId = null;

	/**
	 * this method implemented in childs of this class knows which repo use to find entity and knows where to link it
	 *
	 * @return mixed
	 */
	abstract function processRelId();

	function bindForm( FormInterface $form ) {
		$this->form  = $form;
		$this->refId = intval( $form->get( self::FORM_REL_ID_FIELD_NAME )->getData() );

		$formParentCommentId = $form->get( self::FORM_REF_COMMENT_ID_FIELD_NAME )->getData();
		if ( ! is_null( $formParentCommentId ) ) {
			$this->parentCommentId = $formParentCommentId;
		}
	}

	function bindNM( EntityManager $nm ) {
		$this->nm = $nm;
	}

	function bindComment( CommentNode $comment ) {
		$this->comment = $comment;
	}

	function processParentComment() {
		if ( ! is_null( $this->getParentCommentId() ) ) {
			/** @var CommentNode $parentComment */
			$parentComment = $this->getCommentsRepo()->find( $this->getParentCommentId() );

			if ( is_null( $parentComment ) ) {
				throw new EntityNotFoundException( 'Comment with id "' . $this->getParentCommentId() . '"', 404 );
			}
			$this->comment->setParentComment( $parentComment );
			// increase folding level of current comment
			$this->comment->setLevel( intval( $parentComment->getLevel() ) + 1 );
		}
	}

	protected function getParentCommentId() {
		return $this->parentCommentId;
	}

	/**
	 * @return CommentNodeRepository
	 */
	protected function getCommentsRepo(): CommentNodeRepository {
		/** @var CommentNodeRepository $repo */
		$repo = $this->nm->getRepository( CommentNode::class );

		return $repo;
	}

	/**
	 * Increments order string of comment.
	 * If current comment has sibling it takes his order and incerements last numeric part,
	 * otherwise it takes parent`s order and adds ".1" to it.
	 *
	 */
	function processOrder() {
		// if comments has parent - we need to find last child of
		if ( ! is_null( $this->comment->getParentComment() ) ) {
			$lastInsertedChild = $this->getCommentsRepo()->findLastCommentChild(
				$this->comment->getParentComment()->getId(),
				$this->comment->getParentComment()->getRefType(),
				$this->comment->getLevel()
			);
			$orderStr          = $this->comment->getParentComment()->getOrder();
			if ( ! is_null( $lastInsertedChild ) ) {
				$numericOrder = explode( '.', $lastInsertedChild->getOrder() );

				$lastOrderDigit = intval( end( $numericOrder ) );
				$orderStr       .= '.' . ( $lastOrderDigit + 1 );
			} else {
				$orderStr .= '.1';
			}

			$this->comment->setOrder( $orderStr );
		} else { // if comment is top level comment - we need to get last sibling`s order to increase
			$lastInsertedSibling = $this->getCommentsRepo()->findLastSibling(
				$this->refId,
				$this->comment->getRefType()
			);
			$this->comment->setOrder( strval( intval( $lastInsertedSibling->getOrder() ) + 1 ) );
		}
	}

	protected function getRefId() {
		return $this->refId;
	}
}