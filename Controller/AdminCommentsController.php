<?php

namespace Nodeart\BuilderBundle\Controller;

use FrontBundle\Controller\Base\BaseController;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\CommentNodeRepository;
use Nodeart\BuilderBundle\Entity\UserNode;
use Nodeart\BuilderBundle\Services\Pager\Pager;
use Nodeart\BuilderBundle\Services\Pager\Queries\ManageCommentsQueries;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminCommentsController extends BaseController {


	/**
	 * Updates status of comments which ids are sent in get param
	 *
	 * @Route("/builder/comments/update/{status}", name="comments_mass_action_update", requirements={"status":"\d+"})
	 * @param int $status
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function processMassActionAction( int $status, Request $request ) {
		$response = new JsonResponse();

		if ( ! in_array( $status, array_keys( CommentNode::STATUSES ) ) ) {
			$response->setData( [ 'status' => 'error', 'message' => 'Unknown status' ] );
			$response->setStatusCode( Response::HTTP_BAD_REQUEST );

			return $response;
		}

		if ( ! $request->query->has( 'ids' ) ) {
			$response->setData( [ 'status' => 'error', 'message' => 'No Id parameter provided to update' ] );
			$response->setStatusCode( Response::HTTP_BAD_REQUEST );

			return $response;
		}

		$ids = $request->query->get( 'ids' );
		if ( ! is_string( $ids ) ) {
			$response->setData( [ 'status'  => 'error',
			                      'message' => 'Id`s must be in string, values separated by comma'
			] );
			$response->setStatusCode( Response::HTTP_BAD_REQUEST );

			return $response;
		}
		if ( empty( $ids ) ) {
			$response->setData( [ 'status' => 'error', 'message' => 'No Id`s provided to update' ] );
			$response->setStatusCode( Response::HTTP_BAD_REQUEST );

			return $response;
		}

		$ids = array_map( 'intval', explode( ',', $ids ) );

		$nm = $this->get( 'neo.app.manager' );
		/** @var CommentNodeRepository $commentsRepo */
		$commentsRepo = $nm->getRepository( CommentNode::class );
		$res          = $commentsRepo->updateCommentStatuses( $status, $ids );
		$response->setData( [
			'status'  => 'success',
			'message' => count( $res['ids'] ) . ' node(s) updated',
			'payload' => [
				'status'     => $this->get( 'translator' )->trans( CommentNode::STATUSES[ $status ] ),
				'updatedIds' => $res['ids']
			]
		] );

		return $response;
	}


	/**
     * Detects Node type and redirects to its `show` page, based on "type" param
	 *
	 * @Route("/builder/comments/r/{id}/{type}", name="comments_redirect_ref_id")
	 * @param int $id
	 * @param string $type
	 *
	 * @return Response
	 */
	public function redirectToReference( int $id, string $type ) {
		$nm          = $this->get( 'neo.app.manager' );
		$redirectUrl = $this->generateUrl( 'homepage' );
		switch ( $type ) {
			case CommentNode::RELATION_TYPE_OBJECT : {
				/** @var ObjectNode $object */
				$object = $nm->getRepository( ObjectNode::class )->find( $id );
				if ( ! is_null( $object ) ) {
					$redirectUrl = $this->generateUrl( 'vSingleObject', [ 'object'     => $object->getSlug(),
					                                                      'entityType' => $object->getEntityType()->getSlug()
					] );
				}
				break;
			}
			case CommentNode::RELATION_TYPE_TYPE : {
				/** @var EntityTypeNode $entityType */
				$entityType = $nm->getRepository( EntityTypeNode::class )->find( $id );
				if ( ! is_null( $entityType ) ) {
					$redirectUrl = $this->generateUrl( 'vListObject', [ 'entityType' => $entityType->getSlug() ] );
				}
				break;
			}
			case CommentNode::RELATION_TYPE_USER : {
				/** @var UserNode $user */
				$user = $nm->getRepository( UserNode::class )->find( $id );
				if ( ! is_null( $user ) ) {
					$redirectUrl = $this->generateUrl( 'user_page_show', [ 'username' => $user->getUsername() ] );
				}
				break;
			}
		}

		return $this->redirect( $redirectUrl );
	}

	/**
	 * @Route("/builder/comments/{page}/{perPage}/{statusFilter}", name="comments_list_manage", defaults={"page":1, "perPage":20, "statusFilter":-1}, requirements={"statusFilter":"\d+"} )
	 * @param int $page
	 * @param int $perPage
	 * @param int $statusFilter
	 *
	 * @return Response
	 */
	public function manageListCommentAction( int $page, int $perPage, int $statusFilter, Request $request ) {
		$nm = $this->get( 'neo.app.manager' );


		$pagerQueries = new ManageCommentsQueries( $nm );
		$pagerQueries->processOrder( $request->query->all() );
		$pagerQueries->processFilter( $statusFilter );

		/** @var Pager $pager */
		$pager = $this->get( 'neo.pager' );
		$pager->setLimit( $request->get( 'perPage' ) );
		$pager->createQueries( $pagerQueries );

		$masterRequest = $this->get( 'request_stack' )->getMasterRequest();

		return $this->render( 'BuilderBundle:Comments:admin/paged.list.comments.html.twig', [
			'comments'     => $pager->paginate(),
			'pager'        => $pager->getPaginationData(),
			'masterRoute'  => $masterRequest->attributes->get( '_route' ),
			'masterParams' => $masterRequest->attributes->get( '_route_params' )
		] );
	}

}