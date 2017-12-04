<?php

namespace Nodeart\BuilderBundle\Form;

use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Form\Type\SluggableText;
use Nodeart\BuilderBundle\Form\Type\WysiwygType;
use Nodeart\BuilderBundle\Helpers\TemplateTwigFileResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ObjectNodeType extends AbstractType {
	private $templateTwigFileResolver;

	public function __construct( TemplateTwigFileResolver $templateTwigFileResolver ) {
		$this->templateTwigFileResolver = $templateTwigFileResolver;
	}

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @throws \Exception
     */
	public function buildForm( FormBuilderInterface $builder, array $options ) {
		$builder
			->add( 'name', TextType::class, [
				'label'      => 'Имя конкретного объекта',
				'attr'       => [
					'maxlength' => 255,
					'class'     => 'slug-base'
				],
				'required'   => true,
                'constraints' => [
                    new NotBlank()
                ],
			] )
			->add( 'slug', SluggableText::class, [
				'label'               => 'Slug (Имя в ссылках)',
                'error_bubbling' => false,
				'attr'                => [
					'maxlength' => 32,
				],
				'constraints'         => [
					new Length( [ 'min' => 3 ] ),
					new Regex( [
						'pattern' => '/[0-9a-zA-Z]+/',
						'message' => 'Numbers and latin characters only!'
                    ]),
                    new NotBlank(),
				],
				'required'            => true,
				'base_field_selector' => '.slug-base',
			] )
			->add( 'description', WysiwygType::class, [
				'label'      => 'Описание конкретного объекта',
				'required'   => false,
				'empty_data' => ''
			] )
			->add( 'isCommentable', CheckboxType::class, [
				'label'    => 'Разрешить комментарии?',
				'required' => false
			] );

		/** @var ObjectNode $object */
		$object = $builder->getData();
		if ( ! is_null( $object ) && ! is_null( $object->getEntityType() ) && ! ( $object->getEntityType()->isDataType() ) ) {
			$this->templateTwigFileResolver->addTemplateFields( $builder, 'Object' );
		}
	}

	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( array(
			'data_class' => ObjectNode::class,
		) );
	}
}
