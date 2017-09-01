<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\Repositories\CommentNodeRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
abstract class AbstractCommentProcessor {
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

	/**
	 * second level comments must share same parent. When comment replies to second level comment that comment must be
	 * refComment, but not parentComment
	 *
	 * @throws NotFoundHttpException
	 */
	function processParentComment() {
		if ( ! is_null( $this->getParentCommentId() ) ) {
			/** @var CommentNode $parentComment */
			$parentComment = $this->getCommentsRepo()->find( $this->getParentCommentId() );

			if ( is_null( $parentComment ) ) {
				throw new NotFoundHttpException( 'Comment with id "' . $this->getParentCommentId() . '"', null, 404 );
			}

			if ($parentComment->getParentComment() !== null){
				$this->comment->setParentComment( $parentComment->getParentComment() );
				$this->comment->setRefComment( $parentComment );
			} else {
				$this->comment->setParentComment( $parentComment );
			}

			// increase folding level of current comment
			$this->comment->setLevel( 1 );
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

	protected function getRefId() {
		return $this->refId;
	}
}