<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabeledNumberType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'labeled_number';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder->add('value', IntegerType::class, ['required' => false, 'attr' => ['maxlength' => 16]]);
        $builder->add('text', TextType::class, ['required' => false, 'attr' => ['maxlength' => 16]]);

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
