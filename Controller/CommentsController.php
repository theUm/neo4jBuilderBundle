<?php

namespace BuilderBundle\Controller;

use BuilderBundle\Entity\CommentNode;
use BuilderBundle\Entity\Repositories\CommentNodeRepository;
use BuilderBundle\Form\CommentNodeType;
use BuilderBundle\Helpers\CommentSaver;
use FrontBundle\Controller\Base\BaseController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CommentsController extends BaseController {

	/**
	 * @Route("/comments/add", name="commentsAdd")
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
					return new JsonResponse( [
						'status'  => 'success',
						'message' => 'Comment successfully added',
						'comment' => [ 'id' => $comment->getId() ]
					] );
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
	 * @Route("/comments/list/{type}/{id}/{perPage}", name="commentsList", defaults={"type"=CommentNode::CAT_COMMENT, "id"=0, "perPage"=6})
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function listCommentsAction( $type, $id, $perPage, Request $request ) {

		$nm = $this->get( 'neo.app.manager' );
		/** @var CommentNodeRepository $commentsRepo */
		$commentsRepo = $nm->getRepository( CommentNode::class );
		$comments     = $commentsRepo->findCommentsByRefIdAndType( $id, $type, $perPage );

		$response = $this->render( 'BuilderBundle:Comments:list.comments.html.twig', [
			'comments' => $comments,
			'refId'    => $id
		] );

		$response->setSharedMaxAge( 120 );

		return $response;
	}


}