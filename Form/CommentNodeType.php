<?php

namespace Nodeart\BuilderBundle\Form;

use Nodeart\BuilderBundle\Entity\CommentNode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CommentNodeType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('comment', TextareaType::class, [
                'label' => 'Комментарий',
                'required' => true,
                'empty_data' => null,
                'constraints' => [new NotBlank()],
            ])
            ->add('refType', ChoiceType::class, [
                'label' => 'Категория',
                'choices' => [
                    CommentNode::CAT_COMMENT => CommentNode::CAT_COMMENT,
                    CommentNode::CAT_MISTAKE => CommentNode::CAT_MISTAKE,
                    CommentNode::CAT_SUGGEST => CommentNode::CAT_SUGGEST
                ],
                'required' => true,
                'empty_data' => CommentNode::CAT_COMMENT,
            ])
            ->add('relationType', HiddenType::class, [
                'required' => true,
                'empty_data' => CommentNode::RELATION_TYPE_OBJECT,
            ])
            ->add('refId', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('refComment', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('subscribe', CheckboxType::class, [
                'mapped' => false,
                'label' => 'comment_subscribe',
                'required' => false
            ]);
//			->add( 'legal', CheckboxType::class, [
//				'mapped' => false,
//				'label'  => 'comment_legal_info'
//			] );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => CommentNode::class,
        ));
    }
}
