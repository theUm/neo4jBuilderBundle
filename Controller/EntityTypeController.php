<?php

namespace Nodeart\BuilderBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\Repositories\EntityTypeNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\TypeFieldNodeRepository;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Form\EntityTypeNodeType;
use Nodeart\BuilderBundle\Form\FieldType;
use Nodeart\BuilderBundle\Form\Type\AjaxCheckboxType;
use Nodeart\BuilderBundle\Services\EntityTypeChildsUnlinker;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class EntityTypeController extends Controller
{
    /**
     * @Route("/builder/type", name="builder_add_types")
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function typesListAction(Request $request)
    {
        $nm = $this->get('neo.app.manager');
        /** @var EntityTypeNodeRepository $etRepository */
        $etRepository = $nm->getRepository(EntityTypeNode::class);

        $allRootTypes = $etRepository->findBy(['isDataType' => false], ['name' => 'ASC']);
        $allRootTypesArray = [];
        /** @var EntityTypeNode $node */
        foreach ($allRootTypes as $node) {
            if (!$node->isDataType()) {
                $allRootTypesArray[$node->getName()] = $node->getId();
            }
        }

        $entityType = new EntityTypeNode();
        $form = $this->createForm(EntityTypeNodeType::class, $entityType)
            ->add('hasParents', AjaxCheckboxType::class, [
                'is_multiple' => true,
                'label' => 'Принадлежит типам:',
                'localSearch' => true,
                'local_search_data' => array_flip($allRootTypesArray),
                'db_label' => 'EntityType',
                'mapped' => false,
                'allowAdditions' => false
            ])
            ->add('requiredParents', AjaxCheckboxType::class, [
                'is_multiple' => true,
                'label' => 'Обязательные типы',
                'localSearch' => true,
                'local_search_data' => [],
                'mapped' => true,
                'allowAdditions' => false,
                'empty_data' => null,
            ])
            ->add('Создать тип', SubmitType::class, ['attr' => ['class' => 'red']]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($etRepository->countBy(['name' => $form->get('name')->getData()]) > 0) {
                $form->get('name')->addError(new FormError('This entity type name is already exists'));
            }

            if ($etRepository->countBy(['slug' => $form->get('slug')->getData()]) > 0) {
                $form->get('slug')->addError(new FormError('This entity type slug is already exists'));
            }

            if ($form->get('isDataType')->getData() && empty($form->get('hasParents')->getData())) {
                $form->get('hasParents')->addError(new FormError('DATA type MUST have PARENT type'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var EntityTypeNode $typeNode */
            foreach ($allRootTypes as $typeNode) {
                if (in_array($typeNode->getId(), $form->get('hasParents')->getData())) {
                    $entityType->addParentType($typeNode);
                }
            }

            // works only for creating new ones
            $nm->persist($entityType);
            $nm->flush();

            $this->addFlash('success', 'Тип создан');

            return $this->redirect(
                $this->generateUrl(
                    'builder_add_types'
                )
            );
        }

        return $this->render('BuilderBundle:default:add.type.html.twig', [
            'form' => $form->createView(),
            'rootNodeTypes' => $allRootTypes,
            'entity' => $entityType
        ]);
    }

    /**
     * @Route("/builder/type/{id}/edit", name="builder_edit_type")
     * @param int $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function typeEditAction(int $id, Request $request)
    {
        $nm = $this->get('neo.app.manager');
        /** @var EntityTypeNodeRepository $etRepository */
        $etRepository = $nm->getRepository(EntityTypeNode::class);
        /** @var EntityTypeNode $entityType */
        $entityType = $etRepository->findOneById($id);
        if (is_null($entityType)) {
            throw new NotFoundHttpException('Тип не найден');
        }

        /** @var ArrayCollection $allTypes */
        $allTypes = new ArrayCollection($etRepository->findBy(['isDataType' => false]));
        //remove self-circular link
//        $allTypes->removeElement($entityType);
        $allNodeTypes = [];
        /** @var EntityTypeNode $node */
        foreach ($allTypes as $node) {
            $allNodeTypes[$node->getName()] = $node->getId();
        }

        $previousNodes = [];
        /** @var EntityTypeNode $type */
        foreach ($entityType->getParentTypes() as $type) {
            $previousNodes[$type->getId()] = $type->getName();
        }
        $previousNodeIds = array_keys($previousNodes);

        $fileFields = $entityType->getEntityTypeFields();
        $fileFieldNames = [];

        // list of file fields
        /** @var TypeFieldNode $typeField */
        foreach ($fileFields as $typeField) {
            if ($typeField->getFieldType() == 'file') {
                $fileFieldNames[$typeField->getName()] = $typeField->getSlug();
            }
        }

        $form = $this->createForm(EntityTypeNodeType::class, $entityType)
            ->add('mainPictureField', ChoiceType::class, [
                'label' => 'Главная картинка',
                'required' => false,
                'choices' => $fileFieldNames,
                'empty_data' => ''
            ])
            ->add('hasParents', AjaxCheckboxType::class, [
                'is_multiple' => true,
                'label' => 'Принадлежит типам:',
                'data' => $previousNodeIds,
                'localSearch' => true,
                'local_search_data' => array_flip($allNodeTypes),
                'db_label' => 'EntityType',
                'mapped' => false,
                'allowAdditions' => false
            ])
            ->add('requiredParents', AjaxCheckboxType::class, [
                'is_multiple' => true,
                'label' => 'Обязательные типы',
                'localSearch' => true,
                'local_search_data' => [],
                'mapped' => true,
                'allowAdditions' => false,
                'empty_data' => null
            ])
            ->add('Сохранить', SubmitType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if (empty($form->get('requiredParents')->getData())) {
                $entityType->setRequiredParents(null);
            }

            $submittedParentsData = $form->get('hasParents')->getData();
            /** @var EntityTypeNode $typeNode */
            foreach ($allTypes as $typeNode) {
                if (is_array($submittedParentsData) && in_array($typeNode->getId(), $submittedParentsData)) {
                    $entityType->addParentType($typeNode);
                } else {
                    $entityType->removeParentType($typeNode);
                }
            }

            $nm->persist($entityType);
            $nm->flush();

            $this->addFlash('success', 'Тип сохранён');

            return $this->redirect(
                $this->generateUrl(
                    'builder_edit_type',
                    ['id' => $id]
                )
            );
        }

        return $this->render('BuilderBundle:default:edit.type.html.twig', [
            'entityType' => $entityType,
            'form' => $form->createView(),
            'delete_form' => $this->createDeleteForm($entityType)->createView()
        ]);
    }

    /**
     * @param $node
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(EntityTypeNode $node)
    {
        $form = $this->createFormBuilder(null, ['attr' => ['class' => 'ui form']])
            ->setAction($this->generateUrl('builder_delete_type', ['id' => $node->getId()]))
            ->setMethod('DELETE')
            ->getForm();
        $form
            ->add('id', HiddenType::class, ['data' => $node->getId()])
            ->add('Удалить', SubmitType::class, ['attr' => ['class' => 'left floated red']]);

        return $form;
    }

    /**
     * @todo: refactor_with_new_orm - all db logic are must be rewritten
     * @Route("/builder/type/{id}/fields/edit", name="builder_edit_type_fields")
     *
     * @param int $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editTypeFieldsAction(int $id, Request $request)
    {
        $nm = $this->get('neo.app.manager');
        /** @var EntityTypeNodeRepository $etRepository */
        $etRepository = $nm->getRepository(EntityTypeNode::class);
        /** @var TypeFieldNodeRepository $tfRepository */
        $tfRepository = $nm->getRepository(TypeFieldNode::class);

        /** @var EntityTypeNode $entityType */
        $entityType = $etRepository->findOneById($id);
        // no such EntityType
        if (is_null($entityType)) {
            throw $this->createNotFoundException('There is no such EntityType');
        }

        $existedFields = $tfRepository->findEntityTypeFieldsByType($entityType->getSlug());
        $currentFields = $entityTypeFieldGroups = [];
        /** @var TypeFieldNode $field */
        foreach ($existedFields as $field) {
            $currentFields[] = $field->toArray();
            if (!isset($entityTypeFieldGroups[$field->getTabGroup()])) {
                $entityTypeFieldGroups[$field->getTabGroup()] = empty($field->getTabGroup()) ? 'default' : $field->getTabGroup();
            }
        }

        $form = $this->get('form.factory')->createNamedBuilder('type_fields')
            ->add('dyn_fields', CollectionType::class, [
                'label' => false,
                'data' => $currentFields,
                'entry_type' => FieldType::class,
                'entry_options' => [
                    'label' => false,
                    'entityTypeFieldGroups' => $entityTypeFieldGroups,
                    'isDataType' => $entityType->isDataType()
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'delete_empty' => true,
                'attr' => ['class' => 'field-type']
            ])
            ->add('submit', SubmitType::class, ['label' => 'Сохранить']);
        $form = $form->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fnb = $this->get('form.node.bridge');
            $formFields = [];
            foreach ($form->get('dyn_fields') as $key => $formFieldColl) {
                $formFields[$key] = $formFieldColl->getData();
                //@todo: try to make data transformer to avoid call getData for child fields manually
                $formFields[$key]['tabGroup'] = $formFieldColl->get('tabGroup')->getData();
            }

            $fieldsToDelete = $fnb->getDeletedFieldIds($entityType, $formFields);
            $etRepository->deleteEntityTypeFieldValuesWithValues($entityType, $fieldsToDelete);

            $fieldsToEdit = $fnb->getChangedTypeNodesData($entityType, $formFields);
            $etRepository->deleteEntityTypeFieldValuesByValue($entityType, $fnb->getDeletedPredefinedValues());
            $etRepository->editEntityTypeFields($fieldsToEdit);

            $etRepository->createFields($entityType, $formFields);
            $this->addFlash('success', 'Поля обновлены!');

            return $this->redirect(
                $this->generateUrl(
                    'builder_edit_type_fields', ['id' => $entityType->getId()]
                )
            );
        }

        return $this->render('BuilderBundle:default:edit.type.fields.html.twig', [
            'entity' => $entityType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/builder/type/{id}/delete", name="builder_delete_type")
     * @param int $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteEntityTypeAction(int $id, Request $request)
    {
        $nm = $this->get('neo.app.manager');
        /** @var EntityTypeNodeRepository $etRepository */
        $etRepository = $nm->getRepository(EntityTypeNode::class);
        /** @var EntityTypeNode $entityType */
        $entityType = $etRepository->findOneById($id);

        if (is_null($entityType)) {
            throw $this->createNotFoundException('There is no such EntityType');
        }

        $form = $this->createDeleteForm($entityType);
        $form->handleRequest($request);

        /** @var EntityTypeChildsUnlinker $unlinker */
        $unlinker = $this->get('neo.app.entity.type.unlinker');

        if ($form->isSubmitted() && $form->isValid()) {

            /**
             * Unlink fieldValues that are related to other EntityTypes
             */
            $unlinker
                ->unlinkFieldValues($entityType)
                ->unlinkFieldTypes($entityType)
                ->unlinkObjects($entityType)
                ->deleteEntityTypeWithChilds($entityType);

            $this->addFlash('info', 'Тип удалён!');

            return $this->redirect(
                $this->generateUrl(
                    'builder_add_types'
                )
            );
        }

        return $this->render('BuilderBundle:default:edit.type.fields.html.twig', [
            'entity' => $entityType,
            'form' => $form->createView(),
        ]);
    }

}