<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoubleLabeledTextType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'double_labeled_text';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder->add('value', TextType::class, ['required' => false, 'attr' => ['maxlength' => 128]]);
        $builder->add('text', TextType::class, ['required' => false, 'attr' => ['maxlength' => 128]]);
        $builder->add('text2', TextType::class, ['required' => false, 'attr' => ['maxlength' => 128]]);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => true,
            'error_bubbling' => true,
            'multiple' => false,
            'is_multiple' => false
        ]);
    }

}
