<?php

namespace BuilderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class WysiwygType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function getBlockPrefix() {
		return 'wys_textarea';
	}

	public function getParent() {
		return TextareaType::class;
	}

}
