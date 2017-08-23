<?php

namespace Nodeart\BuilderBundle\Form;

use FrontBundle\Helpers\TemplateTwigFileResolver;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Form\Type\SluggableText;
use Nodeart\BuilderBundle\Form\Type\WysiwygType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class EntityTypeNodeType extends AbstractType {
	private $templateTwigFileResolver;

	public function __construct( TemplateTwigFileResolver $templateTwigFileResolver ) {
		$this->templateTwigFileResolver = $templateTwigFileResolver;
	}

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm( FormBuilderInterface $builder, array $options ) {
		$builder
			->add( 'name', TextType::class, [
				'label' => 'Имя типа объекта',
				'attr'  => [
					'maxlength' => 255,
					'class'     => 'slug-base'
				],
			] )
			->add( 'slug', SluggableText::class, [
				'label'               => 'Slug (Имя в ссылках)',
				'attr'                => [
					'maxlength' => 32
				],
				'constraints'         => [
					new Length( [ 'min' => 3 ] ),
					new Regex( [
						'pattern' => '/[0-9a-zA-Z]+/',
						'message' => 'Numbers and latin characters only!'
					] )
				],
				'base_field_selector' => '.slug-base',
			] )
			->add( 'description', WysiwygType::class, [
				'label'      => 'Описание типа объекта',
				'required'   => false,
				'empty_data' => ''
			] )
			->add( 'isDataType', CheckboxType::class, [
				'label'    => 'Тип для данных?',
				'required' => false
			] )
			->add( 'isCommentable', CheckboxType::class, [
				'label'    => 'Разрешить комментарии?',
				'required' => false
			] );
		$this->templateTwigFileResolver->addTemplateFields( $builder, 'EntityType' );
	}

	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( array(
			'data_class' => EntityTypeNode::class,
		) );
	}
}
