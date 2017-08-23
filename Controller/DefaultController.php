<?php

namespace Nodeart\BuilderBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\FieldValueNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\TypeFieldNodeRepository;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller {

	/**
	 * ajax search for autocomplete fields
	 * @todo: what a mess :/ get rid of that filthy slashes
	 * @Route("/s/{label}/t", name="semantic_search_type")
	 * @Route("/s/{label}/t/", name="semantic_search_type_slash")
	 * @Route("/s/{label}/t/{parentAttrValue}", name="semantic_search_type_val")
	 *
	 * @param string $label
	 * @param string $parentAttrValue
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function semanticSearch( $label = '', $parentAttrValue = '' ) {
		$searchResults = $this->get( 'semantic.input.search' )->search( $label, $parentAttrValue );
		$response      = new JsonResponse();
		$response->setEncodingOptions( JSON_UNESCAPED_UNICODE );
		$response->setData( $searchResults );

		return $response;
	}

	/**
	 * ajax search for autocomplete fields
	 * @todo: what a mess :/ get rid of that filthy slashes
	 * @Route("/s/{label}/v/{parentAttrValue}", name="semantic_search_child")
	 * @Route("/s/{label}/v/{parentAttrValue}/", name="semantic_search_child_slash")
	 * @Route("/s/{label}/v/{parentAttrValue}/{value}", name="semantic_search_child_val")
	 *
	 * @param string $label
	 * @param string $parentAttrValue
	 * @param string $value
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function semanticSearchChilds( $label = '', $parentAttrValue = '', $value = '', Request $request ) {
		$value         = ( ! is_null( $request->request->get( 'value' ) ) ) ? $request->request->get( 'value' ) : $value;
		$searchResults = $this->get( 'semantic.input.search' )->search( $label, $parentAttrValue, $value );
		$response      = new JsonResponse();
		$response->setEncodingOptions( JSON_UNESCAPED_UNICODE );
		$response->setData( $searchResults );

		return $response;
	}


	/**
	 * Ajax file deletion
	 * @todo: make it role based
	 *
	 * @Route("/builder/fieldValue/{id}/delete/ajax", name="builder_delete_ajax_fv_file")
	 * @param $id int
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function deleteFVFileAction( int $id ) {
		/** @var EntityManager $nm */
		$nm = $this->get( 'neo.app.manager' );
		/** @var FieldValueNodeRepository $fvRepository */
		$fvRepository = $nm->getRepository( FieldValueNode::class );
		/** @var FieldValueNode $fieldValueNode */
		$fieldValueNode = $fvRepository->findOneById( $id );
		if ( is_null( $fieldValueNode ) ) {
			return new JsonResponse( [ 'status' => 'error', 'message' => 'Not found' ], 404 );
		}
		$nm->remove( $fieldValueNode, true );
		$nm->flush();

		return new JsonResponse( [ 'status' => 'success', 'message' => 'File node deleted' ] );
	}

	/**
	 * Ajax file unlink
	 * @todo: make it role based
	 *
	 * @Route("/builder/fieldValue/{id}/unlink/object/{objectId}/ajax", name="builder_unlink_ajax_fv_file")
	 * @param $id int
	 * @param int $objectId
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function unlinkFiedlValueAction( int $id, int $objectId ) {
		/** @var EntityManager $nm */
		$nm = $this->get( 'neo.app.manager' );
		/** @var FieldValueNodeRepository $fvRepository */
		$fvRepository = $nm->getRepository( FieldValueNode::class );
		/** @var ObjectNodeRepository $oRepository */
		$oRepository = $nm->getRepository( ObjectNode::class );

		/** @var FieldValueNode $fieldValueNode */
		$fieldValueNode = $fvRepository->findOneById( $id );
		/** @var ObjectNode $object */
		$object = $oRepository->findOneById( $objectId );
		if ( is_null( $fieldValueNode ) || is_null( $object ) ) {
			return new JsonResponse( [ 'status' => 'error', 'message' => 'Not found' ], 404 );
		}

		if ( $fieldValueNode->getObjects()->contains( $object ) ) {
			$fieldValueNode->removeObject( $object );
		}
		if ( $object->getFieldVals()->contains( $fieldValueNode ) ) {
			$object->removeFieldValue( $fieldValueNode );
		}

		$nm->persist( $object );
		$nm->persist( $fieldValueNode );
		$nm->flush();

		return new JsonResponse( [ 'status' => 'success', 'message' => 'File node unlinked' ] );
	}

	/**
	 * Ajax file link & replace
	 * @todo: make it role based
	 *
	 * @Route("/builder/fieldValue/{ids}/link/object/{objectId}/ajax/{typeId}", name="builder_link_ajax_fv_file", defaults={"typeId"=null})
	 * @param $ids string
	 * @param int $objectId
	 * @param int|null $typeId
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function linkReplaceFieldValuesAction( string $ids, int $objectId, int $typeId = null ) {
		/** @var EntityManager $nm */
		$nm = $this->get( 'neo.app.manager' );
		/** @var FieldValueNodeRepository $fvRepository */
		$fvRepository = $nm->getRepository( FieldValueNode::class );
		/** @var ObjectNodeRepository $oRepository */
		$oRepository = $nm->getRepository( ObjectNode::class );
		/** @var TypeFieldNodeRepository $tfRepository */
		$tfRepository = $nm->getRepository( TypeFieldNode::class );

		$ids = explode( '-', $ids );
		$ids = array_map( 'intval', $ids );
		/** @var ArrayCollection $fieldValueNodes */
		$fieldValueNodes = $fvRepository->findByIds( $ids );

		/** @var ObjectNode $object */
		$object = $oRepository->findOneById( $objectId );
		/** @var TypeFieldNode $typeField */
		$typeField = is_null( $typeId ) ? null : $tfRepository->findOneById( $typeId );

		if ( empty( $fieldValueNodes ) || is_null( $object ) || ( ! is_null( $typeId ) && is_null( $typeField ) ) ) {
			return new JsonResponse( [ 'status' => 'error', 'message' => 'Not found' ], 404 );
		}

		/** @var FieldValueNode $fieldValueNode */
		foreach ( $fieldValueNodes as $fieldValueNode ) {
			$fieldValueNode->addObject( $object );
			if ( $fieldValueNode->getTypeField() == null ) {
				$fieldValueNode->setTypeField( $typeField );
			}
			$nm->persist( $fieldValueNode );
		}

		//replace currentFieldValue by new fieldValue
		if ( ! $typeField->isCollection() ) {
			/** @var FieldValueNode $existedFieldValue */
			foreach ( $fvRepository->findObjectValuesByType( $object, $typeField ) as $existedFieldValue ) {
				$existedFieldValue->removeObject( $object );
				$object->removeFieldValue( $existedFieldValue );
			}
		}
		$nm->persist( $object );
		$nm->flush();


		$this->addFlash( 'success', 'Файлы успешно свзязаны с обьектом' );

		return new JsonResponse( [ 'status' => 'success', 'message' => 'FieldValue nodes are linked', ] );
	}

}