<?php

namespace Nodeart\BuilderBundle\Controller;

use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\EntityTypeNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Form\ObjectNodeType;
use Nodeart\BuilderBundle\Services\EntityTypeChildsUnlinker;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ObjectController extends Controller {
    /**
     * @Route("/builder/type/{id}/object", name="builder_list_objects")
     * @param int $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @internal param Request $request
     */
    public function objectsListAction(int $id)
    {
        $nm = $this->get('neo.app.manager');
        /** @var ObjectNodeRepository $oRepository */
        $oRepository = $nm->getRepository(ObjectNode::class);
        /** @var EntityTypeNodeRepository $etRepository */
        $etRepository = $nm->getRepository(EntityTypeNode::class);
        /** @var EntityTypeNode $entityType */
        $entityType = $etRepository->findOneById($id);

        if (is_null($entityType)) {
            throw $this->createNotFoundException('There is no such EntityType');
        }

        $mainFieldsByObjects = [];
        $objects = $entityType->getObjects();
        /** @var ObjectNode $object */
        foreach ($objects as $object) {
            $parents = [];
            /** @var ObjectNode $parent */
            foreach ($object->getParentObjects() as $parent) {
                $parents[] = $parent->getName();
            }
            $values = $oRepository->getObjectMainFieldValues($object);
            $parentNames = !empty($parents) ? (' [' . join(', ', $parents) . ']') : '[Нет родителя]';
            $nameValue = (empty($values) ? 'Пусто' : join(', ', $values)) . ' ' . $parentNames;
            $mainFieldsByObjects[$object->getId()] = $nameValue;
        }
        $objects = $objects->toArray();
        usort($objects, function (ObjectNode $a, ObjectNode $b) {
            return lcfirst($a->getName()) <=> lcfirst($b->getName());
        });

        $etParents = [];
        /** @var EntityTypeNode $etParent */
        foreach ($entityType->getParentTypes() as $etParent) {
            $etParents[] = $etParent->getName();
        }

        return $this->render('BuilderBundle:default:list.object.html.twig', [
            'nodeObjects' => $objects,
            'entity' => $entityType,
            'mainFields' => $mainFieldsByObjects,
            'parent_types' => $etParents
        ]);
    }

    /**
     * Add object of specific type
     *
     * @Route("/builder/object/type/{id}/add/{parentObjId}", name="builder_add_type_object", defaults={"parentObjId" = null})
     * @param int $id
     * @param int $parentObjId
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addObjectAction(int $id, int $parentObjId = null, Request $request)
    {
        $nm = $this->get('neo.app.manager');
        /** @var EntityTypeNodeRepository $etRepository */
        $etRepository = $nm->getRepository(EntityTypeNode::class);
        /** @var ObjectNodeRepository $oRepository */
        $oRepository = $nm->getRepository(ObjectNode::class);
        /** @var EntityTypeNode $entityType */
        $entityType = $etRepository->findOneById($id);
        /** @var ObjectNode $parentObjectNode */
        $parentObjectNode = $oRepository->findOneById($parentObjId);

        if (is_null($entityType)) {
            throw $this->createNotFoundException('There is no such EntityType');
        }

        $objectNode = new ObjectNode();
        $objectNodeId = null;
        $mainObjectFields = null;

        $formBuilder = $this->get('form.factory')->createNamedBuilder('obj_fields', ObjectNodeType::class, $objectNode, ['attr' => ['class' => 'ui form']]);

        $formFieldsService = $this->get('object.form.fields');
        $formFieldsService->setObject($objectNode);
        $formFieldsService->setEntityType($entityType);
        $formFieldsService->setParentObj($parentObjectNode);
        $formFieldsService->setParentObjId($parentObjId);

        $formFieldsService->hideFieldsForDataType($formBuilder);
        if (!is_null($parentObjId)) {
            //add just single parent object form field
            $formFieldsService->addSingleParentTypeField($formBuilder);
        } else {
            //add all possible parent object form fields
            $formFieldsService->addParentTypesFields($formBuilder);
        }

        $dynamicFieldsIds = $formFieldsService->addFormFields($formBuilder);

        $formBuilder->setAction($this->generateUrl('builder_add_type_object', [
            'id' => $id,
            'parentObjId' => $parentObjId
        ]));
        /** @var Form $form */
        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectWithNameExists = ($form->has('name') && $oRepository->isObjectWithValueExists($entityType, 'name', $form->get('name')->getData()));
            $objectWithSlugExists = ($form->has('slug') && $oRepository->isObjectWithValueExists($entityType, 'slug', $form->get('slug')->getData()));
            if ($objectWithNameExists) {
                $form->get('name')->addError(new FormError('Object with this name is already exists'));
            }
            if ($objectWithSlugExists) {
                $form->get('slug')->addError(new FormError('Object with this slug is already exists'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $parentIds = $formFieldsService->getPickedParentIds($form);
            $objectNode = $oRepository->createObjectNode($objectNode, $entityType, $parentIds);
            $objectNodeId = $objectNode->getId();

            if (!$request->isXmlHttpRequest()) {
                $this->addFlash('success', 'Объект успешно создан');
            }

            $formFieldsService->handleDynamicFields($form, $dynamicFieldsIds, $objectNode);
            if (!$request->isXmlHttpRequest()) {
                $this->addFlash('success', 'Поля тоже успешно созданы');

                $url = $this->generateUrl(($entityType->isDataType() ? 'builder_edit_object' : 'builder_edit_big_object'), ['id' => $objectNodeId]);

                return $this->redirect($url);
            }

        } else {
            if (!$request->isXmlHttpRequest()) {
                // I don`t even want to go home now
                foreach ($form->getErrors(true, true) as $error) {
                    $this->addFlash('error',
                        $error->getOrigin()->getConfig()->getOption('label') . ': ' . $error->getMessage()
                    );
                }

            }
        }

        $template = $request->isXmlHttpRequest() ?
            // if ajax - show main form segment
            '@Builder/Object/main.object.form.segment.html.twig'
            // else show regular form with all the page
            : '@Builder/Object/add.object.html.twig';
        return $this->render($template, [
            'isEditForm' => false,
            'mainObjectFields' => $mainObjectFields,
            'objectEntity' => $objectNode,
            'relatedObjects' => [],
            'entityType' => $entityType,
            'form' => $form->createView(),
            'fieldsByGroups' => $formFieldsService->getFieldValuesByGroups(),
            'objectNodeId' => $objectNodeId
        ]);
    }


    /**
     * Edit object
     *
     * @Route("/builder/object/{id}/edit", name="builder_edit_object")
     * @Route("/builder/object/{id}/edit-big", name="builder_edit_big_object")
     * @param int $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editObjectAction(int $id, Request $request)
    {
        $objectService = $this->get('object.edit.control.service');
        /** @var ObjectNode $objectNode */
        $objectNode = $objectService->fetchObjectById($id);
        if (is_null($objectNode)) {
            throw $this->createNotFoundException('There is no such Object');
        }

        $formBuilder = $objectService->prepareForm($objectNode, !$request->isXmlHttpRequest());
        $formBuilder->setAction($this->generateUrl($request->get('_route'), ['id' => $id]));
        /** @var Form $form */
        $form = $formBuilder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $objectService->saveForm($form);
        }

        if ($request->isXmlHttpRequest()) {
            $template = 'BuilderBundle:Object:main.object.form.segment.html.twig';
        } else {
            $template = 'BuilderBundle:Object:edit-big.object.html.twig';
        }

        return $this->render($template, [
            'isEditForm' => true,
            'mainObjectFields' => null,
            'objectNodeId' => $objectNode->getId(),
            'objectEntity' => $objectNode,
            'entityType' => $objectNode->getEntityType(),
            'form' => $form->createView(),
            'delete_form' => $this->createDeleteForm($objectNode)->createView(),
            'fieldsByGroups' => $this->get('object.form.fields')->getFieldValuesByGroups(),
            'relatedObjects' => $objectService->getRelatedChildsByTypes()
        ]);
    }

    /**
     * @param ObjectNode $node
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(ObjectNode $node)
    {
        $formClass = 'ui delete form ' . ($node->getEntityType()->isDataType() ? '' : 'main-object');
        /** @var Form $form */
        $form = $this->get('form.factory')->createNamedBuilder('delete_' . $node->getId(), FormType::class, null, ['attr' => ['class' => $formClass, 'name' => 'form_' . $node->getId()]])
            ->setAction($this->generateUrl('builder_delete_object', ['id' => $node->getId()]))
            ->getForm();
        $form
            ->add('id', HiddenType::class, ['data' => $node->getId()]);

        return $form;
    }

    /**
     * @Route("/builder/object/{id}/delete", name="builder_delete_object")
     * @param int $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \GraphAware\Neo4j\Client\Exception\Neo4jExceptionInterface
     */
    public function deleteObjectAction(int $id, Request $request)
    {
        $nm = $this->get('neo.app.manager');
        /** @var ObjectNodeRepository $oRepository */
        $oRepository = $nm->getRepository(ObjectNode::class);
        /** @var ObjectNode $objectNode */
        $objectNode = $oRepository->findOneById($id);

        if (is_null($objectNode)) {
            throw $this->createNotFoundException('There is no such Object');
        }
        $entityTypeId = $objectNode->getEntityType()->getId();

        $jsonResponse = ['status' => 'cant delete!'];

        $form = $this->createDeleteForm($objectNode);
        $form->add('submit', SubmitType::class, ['attr' => ['class' => 'red']]);
        $form->handleRequest($request);

        /** @var EntityTypeChildsUnlinker $unlinker */
        $unlinker = $this->get('neo.app.entity.type.unlinker');

        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * Unlink fieldValues that are related to other EntityTypes
             */
            $unlinker
//				->unlinkObjectRelatedObjects($objectNode)
                ->unlinkFieldValues($objectNode)
                ->deleteObjectWithChilds($objectNode);

            if (!$request->isXmlHttpRequest()) {
                $this->addFlash('success', 'Обьект удалён!');

                return $this->redirect(
                    $this->generateUrl(
                        'builder_list_objects', ['id' => $entityTypeId]
                    )
                );
            } else {
                $jsonResponse = ['status' => 'deleted'];
            }
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($jsonResponse);
        } else {
            return $this->render('BuilderBundle:default:delete.type.fields.html.twig', [
                'entity' => $objectNode,
                'form' => $form->createView(),
            ]);
        }
    }
}