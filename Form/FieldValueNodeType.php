<?php

namespace Nodeart\BuilderBundle\Form;

use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldValueNodeType extends AbstractType {
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm( FormBuilderInterface $builder, array $options ) {
		$builder
			->add( 'originalFileName', TextType::class, [
				'label'      => 'Имя файла',
				'attr'       => [
					'maxlength' => 255,
				],
				'required'   => false,
				'empty_data' => ''
			] );
	}

	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( array(
			'data_class' => FieldValueNode::class,
		) );
	}
}
