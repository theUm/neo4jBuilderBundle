<?php

namespace BuilderBundle\Services;

use BuilderBundle\Entity\ObjectNode;
use BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use BuilderBundle\Form\ObjectNodeType;
use GraphAware\Neo4j\OGM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;

/**
 *
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 * @property null|object object
 */
class ObjectEditControlService {
	private $container;
	/** @var EntityManager $nm */
	private $nm;
	private $formFieldsService;
	/** @var ObjectNodeRepository $oRepository */
	private $oRepository;

	private $dynamicFieldsIds = [];

	public function __construct( Container $container ) {
		$this->container         = $container;
		$this->nm                = $container->get( 'neo.app.manager' );
		$this->oRepository       = $this->nm->getRepository( ObjectNode::class );
		$this->formFieldsService = $this->container->get( 'object.form.fields' );
	}

	public function fetchObjectById( $id ) {
		return $this->oRepository->findOneById( $id );
	}

	/**
	 * @param ObjectNode $objectNode
	 *
	 * @return \Symfony\Component\Form\FormBuilderInterface
	 */
	public function prepareForm( ObjectNode $objectNode ) {
		$formBuilder = $this->container->get( 'form.factory' )
		                               ->createNamedBuilder( 'obj_fields', ObjectNodeType::class, $objectNode, [
			                               'attr' => [
				                               'id'    => 'obj_fields',
				                               'class' => 'ui form'
			                               ]
		                               ] );

		$this->formFieldsService->setObject( $objectNode );
		$this->formFieldsService->addParentTypesFields( $formBuilder );
		$this->formFieldsService->hideFieldsForDataType( $formBuilder );

		$this->dynamicFieldsIds = $this->formFieldsService->addFormFields( $formBuilder );

		return $formBuilder;
	}

	public function saveForm( Form $form, $silent = true ) {
		//update name, slug, desc
		$this->oRepository->updateObjectNodeData( $this->formFieldsService->getObject()->getId(), $this->getObject()->toArray() );

		//this is normal way to save relations, and it not works for now
		//$this->formFieldsService->setParentsToObject($form);
		//$this->nm->getNM()->persist($this->getObject());
		//$this->nm->getNM()->flush();

		//update links to parent objects
		$pickedParentsIds = $this->formFieldsService->getPickedParentIds( $form );
		$this->oRepository->updateParenthesisRelationsByIds(
			$this->getObject()->getId(),
			$pickedParentsIds,
			$this->formFieldsService->getCurrentParentObjectsIds(),
			ObjectNodeRepository::LINK_TO_PARENTS );

		if ( ! $silent ) {
			$this->container->get( 'session' )->getFlashBag()->add( 'success', 'Объект успешно изменён' );
		}
		// update FieldsValues in separate queries
		$this->formFieldsService->handleDynamicFields( $form, $this->dynamicFieldsIds );
		if ( ! $silent ) {
			$this->container->get( 'session' )->getFlashBag()->add( 'success', 'Поля тоже успешно изменёны' );
		}
	}

	private function getObject() {
		return $this->formFieldsService->getObject();

	}

	public function getFieldValuesByGroups() {
		return $this->formFieldsService->getFieldValuesByGroups();
	}

	public function getRelatedChildsByTypes() {
		$childByTypes = [];
		foreach ( $this->getObject()->getChildObjects() as $childObject ) {
			$childByTypes[ $childObject->getEntityType()->getId() ][] = $childObject;
		}

		return $childByTypes;
	}
}