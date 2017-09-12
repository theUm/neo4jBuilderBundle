<?php

namespace Nodeart\BuilderBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\EntityTypeNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\FieldValueNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\TypeFieldNodeRepository;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Form\FieldValueNodeType;
use Nodeart\BuilderBundle\Form\Type\AjaxCheckboxType;
use Nodeart\BuilderBundle\Form\Type\NodeCheckboxType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;

class MediaController extends Controller {
	const ROUTE_TYPE_ID_PARAM = '-1';
	const ROUTE_OBJECT_ID_PARAM = '-2';

	/**
	 * @Route("/builder/media/dashboard/{filters}", name="media_dashboard_pageless")
	 * @Route("/builder/media/dashboard/{filters}/page/{page}/{perPage}", name="media_dashboard", requirements={"page": "\d+", "perPage": "\d+"}, defaults={"page":1, "perPage":5, "filters":"all"})
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function mediaDashboardAction( Request $request ) {
		$pager = $this->get( 'media.neo.pager' );
		$pager->setQueryString( 'MATCH (fv:FieldValue)' );
		$pager->setBaseFilters( ' WHERE fv.webPath is not null' );
		$pager->setOrderBy( ' ORDER BY fv.createdAt DESC' );
		$pager->setReturnMapping( 'fv', FieldValueNode::class );

		/** @var Form $filtersForm */
		$filtersForm = $this->createFormBuilder()
		                    ->add( 'fileName', TextType::class, [
			                    'required' => false,
			                    'label'    => false,
			                    'attr'     => [ 'placeholder' => 'fileName' ]
		                    ] )
		                    ->add( 'createdAt', DateTimeType::class, [
			                    'required'    => false,
			                    'widget'      => 'single_text',
			                    'placeholder' => 'test',
			                    'label'       => false,
			                    'attr'        => [ 'placeholder' => 'createdAt' ]
		                    ] )
		                    ->add( 'isLinked', CheckboxType::class, [ 'required' => false ] )
		                    ->add( 'Filter', SubmitType::class, [ 'label' => 'Фильтровать' ] )
		                    ->getForm();

		$filtersForm->handleRequest( $request );
		//@todo: Сделать фильтры для галереи
		if ( $filtersForm->isSubmitted() && $filtersForm->isValid() ) {
			$this->addFlash( 'error', ' Media gallery filters are not implemented yet :)' );
		}

		if ( $request->isXmlHttpRequest() ) {
			return $this->render( 'BuilderBundle:Media:ajax.media.pics.grid.html.twig', [
				'pics'  => $pager->paginate(),
				'pager' => $pager->getPaginationData(),
			] );
		}

		return $this->render( 'BuilderBundle:Media:dashboard.html.twig', [
			'pics'        => $pager->paginate(),
			'pager'       => $pager->getPaginationData(),
			'filtersForm' => $filtersForm->createView()
		] );
	}

	/**
	 * @todo Very long + single use case logic controller
	 * @Route("/builder/media/edit/{id}", name="media_ajax_edit", requirements={"id": "\d+"})
	 *
	 * @param int $id
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function ajaxEditCreateSingleMediaAction( int $id, Request $request ) {
		/** @var EntityManager $nam */
		$nam = $this->get( 'neo.app.manager' );
		/** @var FieldValueNodeRepository $fvRepo */
		$fvRepo = $nam->getRepository( FieldValueNode::class );
		/** @var EntityTypeNodeRepository $etRepo */
		$etRepo = $nam->getRepository( EntityTypeNode::class );
		/** @var TypeFieldNodeRepository $etfRepo */
		$etfRepo = $nam->getRepository( TypeFieldNode::class );

		/** @var ObjectNodeRepository $oRepo */
		$oRepo = $nam->getRepository( ObjectNode::class );

		/** @var FieldValueNode $fieldValue */
		$fieldValue = $fvRepo->findOneById( $id );
		if ( null == $fieldValue ) {
			$fieldValue = new FieldValueNode();
		}
		$fieldTypeId = ( $fieldValue->getTypeField() ) ? $fieldValue->getTypeField()->getId() : null;

		/** @var FormBuilder $form */
		$formBuilder = $this->get( 'form.factory' )->createBuilder( FieldValueNodeType::class, $fieldValue, [ 'attr' => [ 'data-id' => $id ] ] )
		                    ->setAction( $this->generateUrl( $request->get( '_route' ), [ 'id' => $id ] ) )
		                    ->add( 'file', FileType::class, [
			                    'mapped'   => false,
			                    'required' => false,
			                    'label'    => 'Загрузить файл'
		                    ] )
		                    ->add( 'upload', SubmitType::class, [ 'label' => 'Загрузить' ] );

		/** @var Form $form */
		$uploadForm = $formBuilder->getForm();

		//if fieldValue is already linked to TypeFieldNode - prevent linking to another TypeFieldNode
		$possibleEntityTypes = is_null( $fieldValue->getTypeField() ) ? $etRepo->findAllWithFieldTypeFile() : new ArrayCollection( [ $fieldValue->getTypeField()->getEntityType() ] );

		/** @var Form $linkForm */
		$linkForm = $this->get( 'form.factory' )->createNamedBuilder( 'link-my-form' )
		                 ->setAction( $this->generateUrl( 'media_ajax_edit', [ 'id' => $id ] ) )
		                 ->add( 'entityType', NodeCheckboxType::class, [
			                 'label'             => 'Тип объекта',
			                 'attr'              => [ 'id' => 'type_pick_input' ],
			                 'is_multiple'       => false,
			                 'placeholder'       => 'pick_entity_type',
			                 'local_search_data' => $possibleEntityTypes,
			                 'updateChilds'      => [ self::ROUTE_TYPE_ID_PARAM => [ 'object', 'field' ] ],
			                 'constraints'       => [ new NotBlank() ],
		                 ] )
		                 ->add( 'object', AjaxCheckboxType::class, [
			                 'mapped'         => false,
			                 'label'          => 'Объект',
			                 'is_multiple'    => false,
			                 'placeholder'    => 'pick_object',
			                 'url'            => $this->generateUrl( 'media_search_dropdown', [
				                 'searchType'  => 'obj',
				                 'typeId'      => self::ROUTE_TYPE_ID_PARAM,
				                 'fieldTypeId' => $fieldTypeId
			                 ] ),
			                 'updateChilds'   => [ self::ROUTE_OBJECT_ID_PARAM => [ 'field' ] ],
			                 'attr'           => [ 'class' => 'disabled' ],
			                 'constraints'    => [ new NotBlank() ],
			                 'error_bubbling' => false
		                 ] )
		                 ->add( 'field', AjaxCheckboxType::class, [
			                 'mapped'         => false,
			                 'label'          => 'Поле',
			                 'is_multiple'    => false,
			                 'placeholder'    => 'pick_field',
			                 'url'            => $this->generateUrl( 'media_search_dropdown', [
				                 'typeId'      => self::ROUTE_TYPE_ID_PARAM,
				                 'searchType'  => 'field',
				                 'objectId'    => self::ROUTE_OBJECT_ID_PARAM,
				                 'fieldTypeId' => $fieldTypeId
			                 ] ),
			                 'constraints'    => [ new NotBlank() ],
			                 'attr'           => [ 'class' => 'disabled' ],
			                 'error_bubbling' => false
		                 ] )
		                 ->add( 'link', SubmitType::class, [ 'label' => 'Свзязать' ] )
		                 ->getForm();

		$uploadForm->handleRequest( $request );
		$linkForm->handleRequest( $request );

		if ( $uploadForm->isSubmitted() && $uploadForm->isValid() ) {
			if ( $uploadForm->get( 'file' )->getData() instanceof UploadedFile ) {
				$this->get( 'field.value.file.saver' )->moveTransformFileToNode( $uploadForm->get( 'file' )->getData(), $fieldValue );
			}

			$nam->persist( $fieldValue );
			$nam->flush();

			$cacheManager = $this->get( 'liip_imagine.cache.manager' );
			$cacheManager->remove( $fieldValue->getWebPath() );

			return $this->redirect( $this->generateUrl( 'media_ajax_edit', [ 'id' => $fieldValue->getId() ] ) );
		}

		if ( $linkForm->isSubmitted() && $linkForm->isValid() ) {
			$entityTypeId = $linkForm->get( 'entityType' )->getData()->getId();
			$objectId     = intval( $linkForm->get( 'object' )->getData() );
			$fieldId      = intval( $linkForm->get( 'field' )->getData() );

			// manual re-creating path that leads to this fieldID.
			$choices      = $fvRepo->getMediaDropdownChoices( $entityTypeId, $objectId );
			$fieldTypeIds = [];
			foreach ( $choices as $choice ) {
				$fieldTypeIds[] = $choice['value'];
			}
			if ( in_array( $fieldId, $fieldTypeIds ) ) {

				/** @var TypeFieldNode $typeField */
				$typeField = $etfRepo->findOneById( $fieldId );

				/** @var ObjectNode $object */
				$object = $oRepo->findOneById( $objectId );

				if ( ! $typeField->isCollection() ) {
					/** find and unlink previous fieldValue node
					 * @var FieldValueNode $oldFieldValue
					 */
					$oldFieldValue = $oRepo->getValByTypeFieldId( $object, $typeField->getId() );

					if ( $oldFieldValue ) {
						$object->removeFieldValue( $oldFieldValue );
						$oldFieldValue->removeObject( $object );
					}
				}

				$object->addFieldValue( $fieldValue );
				$typeField->addFieldValue( $fieldValue );
				$fieldValue->addObject( $object );
				$fieldValue->setTypeField( $typeField );
				$nam->persist( $object );
				$nam->persist( $typeField );
				$nam->persist( $fieldValue );
				$nam->flush();

				$this->redirect( $this->generateUrl( 'media_ajax_edit', [ 'id' => $id ] ) );
			}
		}

		if ( ! $request->isXmlHttpRequest() ) {
			return $this->render( 'BuilderBundle:Media:simple.single.media.form.html.twig', [
				'fieldValue' => $fieldValue,
				'uploadForm' => $uploadForm->createView(),
				'linkForm'   => $linkForm->createView(),
			] );
		}

		return $this->render( 'BuilderBundle:Media:simple.ajax.single.media.form.html.twig', [
			'fieldValue' => $fieldValue,
			'uploadForm' => $uploadForm->createView(),
			'linkForm'   => $linkForm->createView(),
		] );
	}

	/**
	 * json for AjaxCheckboxType`s ajax calls. notice updateChilds option
	 *
	 * @Route("/builder/media/search/{typeId}/{searchType}/{objectId}/{fieldTypeId}", name="media_search_dropdown", defaults={"typeId"=-10, "objectId"=-10, "fieldTypeId"=null})
	 * @param int|null $typeId
	 * @param string $searchType
	 * @param int $objectId
	 * @param int $fieldTypeId
	 *
	 * @return Response
	 */
	public function ajaxObjectSearchAction( int $typeId, string $searchType, int $objectId, int $fieldTypeId = null ) {
		if ( ! in_array( $searchType, [ 'obj', 'field' ] ) ) {
			return new JsonResponse(
				[ "success" => false, "results" => [] ],
				404
			);
		}

		$nam = $this->get( 'neo.app.manager' );
		if ( $searchType === 'obj' ) {
			/** @var ObjectNodeRepository $repo */
			$repo    = $nam->getRepository( ObjectNode::class );
			$choices = $repo->getMediaDropdownChoices( $typeId, $fieldTypeId );
		} else {
			/** @var FieldValueNodeRepository $repo */
			$repo    = $nam->getRepository( FieldValueNode::class );
			$choices = $repo->getMediaDropdownChoices( $typeId, $objectId, $fieldTypeId );
		}

		return new JsonResponse( [
			'success' => true,
			'results' => $choices
		] );
	}
}