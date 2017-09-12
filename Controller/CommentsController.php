<?php

namespace Nodeart\BuilderBundle\Controller;

use FrontBundle\Controller\Base\BaseController;
use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\Repositories\CommentNodeRepository;
use Nodeart\BuilderBundle\Form\CommentNodeType;
use Nodeart\BuilderBundle\Helpers\CommentSaver;
use Nodeart\BuilderBundle\Services\Pager\Pager;
use Nodeart\BuilderBundle\Services\Pager\Queries\CommentsQueries;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CommentsController extends BaseController {

	/**
	 * @Route("/comments/add", name="comments_add")
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function addCommentAction( Request $request ) {

		$nm   = $this->get( 'neo.app.manager' );
		$user = $this->getUser();

		$comment     = new CommentNode();
		$formBuilder = $this->get( 'form.factory' )->createNamedBuilder( 'comment_form', CommentNodeType::class, $comment );
		$form        = $formBuilder->add( 'submit_button', SubmitType::class, [ 'label' => 'Создать объект' ] )->getForm();

		$form->handleRequest( $request );

		if ( $form->isSubmitted() ) {

			// standart form validation
			if ( $form->isValid() ) {
				$this->get( CommentSaver::class )
				     ->bindForm( $form )
				     ->bindComment( $comment )
				     ->processForm();

				$comment->setAuthor( $user );
				$nm->persist( $comment );
				$nm->flush();
			}

			// in case if ajax: if valid - return single comment template, else return json with error
			if ( $request->isXmlHttpRequest() ) {
				if ( $form->isValid() ) {
					return $this->render( 'BuilderBundle:Comments:single.comment.html.twig', [ 'pair'=>['comment' => $comment, 'user' => $user] ] );
				} else {
					return new JsonResponse( [
						'status'  => 'failure',
						'message' => 'Comment form has errors',
						'errors'  => $form->getErrors()
					], 400 /*bad request*/ );
				}
			}
		}

		return $this->render( 'default/empty.form.html.twig', [ 'form' => $form->createView() ] );
	}

	/**
	 * @Route("/comments/paged/{oId}/{type}/page/{page}/{perPage}", name="comments_paged_list", requirements={"oId": "\d+", "page": "\d+", "perPage": "\d+"}, defaults={"page":1})
	 * @param int $oId
	 * @param $type string
	 * @param int $page
	 * @param int $perPage
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function listPagedCommentsAction( int $oId, string $type, int $page, int $perPage = 10, Request $request ) {

		/** @var EntityManager $nm */
		$nm = $this->get( 'neo.app.manager' );

		/** @var Pager $pager */
		$pager = $this->get( 'neo.pager' );
		$pager->setLimit( $perPage );

		$pagerQueries = new CommentsQueries( $nm );
		$pagerQueries->setParams( [ $oId, $type ] );

		$pager->createQueries( $pagerQueries );


		$masterRequest = $this->get( 'request_stack' )->getMasterRequest();

		$response = $this->render( 'BuilderBundle:Comments:paged.list.comments.html.twig', [
			'oId'          => $oId,
			'type'         => $type,
			'comments'     => $pager->paginate(),
			'pager'        => $pager->getPaginationData(),
			'masterRoute'  => $masterRequest->attributes->get( '_route' ),
			'masterParams' => $masterRequest->attributes->get( '_route_params' )
		] );
		$response->setSharedMaxAge( 120 );

		return $response;
	}

	/**
	 * @Route("/comments/more/{fromId}/{level}", name="comments_load_more", requirements={"oId": "\d+", "fromId": "\d+"}, defaults={"level":CommentNode::COMM_LEVEL_REPLY})
	 * @param int $fromId
	 * @param int $level
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function loadMoreCommentsAction( int $fromId, int $level = CommentNode::COMM_LEVEL_REPLY, Request $request ) {

		/** @var EntityManager $nm */
		$nm = $this->get( 'neo.app.manager' );

		/** @var CommentNodeRepository $repo */
		$repo = $nm->getRepository( CommentNode::class );

		if ( $level == CommentNode::COMM_LEVEL_MAIN ) {
			$comments = $repo->findMoreParentComments( $fromId );
			$template = '@Builder/Comments/flat.parent.list.comments.html.twig';
		} else {
			$comments = $repo->findMoreChildComments( $fromId );
			$template = '@Builder/Comments/flat.childs.list.comments.html.twig';
		}
		if ( count( $comments ) == 0 ) {
			return new JsonResponse( [
				'status'  => 'not_found',
				'message' => 'No more comments for you!',
			], 404 /*Not found*/ );
		}

		$response = $this->render( $template, [
			'comments' => $comments,
		] );
		$response->setSharedMaxAge( 120 );

		return $response;
	}


	/**
	 * @Route("/comments/report/{commentId}", name="comments_report", requirements={"commentId": "\d+"})
	 * @param int $commentId
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function reportCommentAction( int $commentId ) {

		/** @var EntityManager $nm */
		$nm = $this->get( 'neo.app.manager' );

		/** @var CommentNodeRepository $repo */
		$repo = $nm->getRepository( CommentNode::class );
		/** @var CommentNode $comment */
		$comment = $repo->findOneById( $commentId );

		if ( ! $comment ) {
			return new JsonResponse( [
				'status'  => 'not_found',
				'message' => 'Comment not found',
			], 404 /*Not found*/ );
		}

		$user = $this->getUser();
		if ( $comment->isReportedBy( $user ) ) {
			$comment->getReportedBy()->removeElement( $user );
			$user->getReported()->removeElement( $comment );
			$comment->decSpamCount();
		} else {
			$comment->addReportedBy( $user );
			$comment->incSpamCount();
		}
		$nm->persist( $comment );
		$nm->persist( $user );
		$nm->flush();

		return new JsonResponse( [
			'status'  => 'updated',
			'message' => 'Thank you for your submission',
		] );

	}

}