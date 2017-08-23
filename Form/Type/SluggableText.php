<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SluggableText extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function getBlockPrefix() {
		return 'sluggable_text';
	}

	public function getParent() {
		return TextType::class;
	}


	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( [
			'speakingurl_options' => [],
			'slug_field_selector' => '',
			'base_field_selector' => '.slug-base',
			'additional_options'  => []
		] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function buildView( FormView $view, FormInterface $form, array $options ) {
		$view->vars['speakingurl_options'] = json_encode( [
			'slug_field' => $options['slug_field_selector'],
			'base_field' => $options['base_field_selector'],
			'options'    => $options['additional_options'],
		] );
	}
}
