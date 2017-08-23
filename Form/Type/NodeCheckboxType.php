<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class NodeCheckboxType accepts ArrayCollection of nodes as local_search_data config argument, and similar collection
 * or single node entity. Does not maps
 * @package Nodeart\BuilderBundle\Form\Type
 */
class NodeCheckboxType extends AbstractType {

	public function buildForm( FormBuilderInterface $builder, array $options ) {
		$delimiter       = $options['delimiter'];
		$localSearchData = $options['local_search_data'];
		$isMultiple      = $options['is_multiple'];
		$idPath          = $idPath = 'get' . ucfirst( $options['id_path'] );

		$builder->addViewTransformer( new CallbackTransformer(
			function ( $value ) use ( $localSearchData, $isMultiple, $idPath ) {
				if ( empty( $value ) ) {
					return null;
				}
				if ( $isMultiple ) {
					$transformedValue = [];
					foreach ( $value as $val ) {
						$transformedValue[] = $val->{$idPath}();
					}
				} else {
					$transformedValue = $value->{$idPath}();
				}

				return $transformedValue;
			},
			function ( $value ) use ( $isMultiple, $localSearchData, $delimiter, $idPath ) {
				if ( empty( $value ) ) {
					return null;
				}
				if ( $isMultiple ) {
					$transformedValue = $this->mapMultipleIdsToCollection( $value, $localSearchData, $delimiter, $idPath );
				} else {
					$transformedValue = $this->mapSingleIdToNode( $value, $localSearchData, $idPath );
				}

				return $transformedValue;
			}
		) );


	}

	private function mapMultipleIdsToCollection( $value, $localSearchData, $delimiter, $idPath ) {
		$ids              = explode( $delimiter, $value );
		$transformedValue = new ArrayCollection();
		foreach ( $localSearchData as $node ) {
			if ( in_array( $node->{$idPath}(), $ids ) ) {
				$transformedValue->add( $node );
			}
		}

		return $transformedValue;
	}

	private function mapSingleIdToNode( $value, ArrayCollection $localSearchData, $idPath ) {
		foreach ( $localSearchData as $node ) {
			if ( $node->{$idPath}() == $value ) {
				return $node;
			}
		}

		return null;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getBlockPrefix() {
		return 'node_checkbox';
	}

	public function getParent() {
		return HiddenType::class;
	}


	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( [
			'local_search_data' => [],
			'id_path'           => 'id',
			'property_path'     => 'name',
			'data_class'        => null,
			'mapped'            => false,
			'is_multiple'       => false,
			'label_attr'        => [ 'tooltip' => '' ],
			'placeholder'       => '',
			'updateChilds'      => false,
			//semantic ui dropdown options
			'maxSelections'     => 1,
			'allowAdditions'    => false,
			'minChars'          => false,
			'saveRemoteData'    => false,
			'localSearch'       => true,
			'delimiter'         => '|^',
		] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function buildView( FormView $view, FormInterface $form, array $options ) {
		$view->vars['property_path']     = $options['property_path'];
		$view->vars['is_multiple']       = $options['is_multiple'];
		$view->vars['local_search_data'] = $options['local_search_data'];
		$view->vars['delimiter']         = $options['delimiter'];
		$view->vars['placeholder']       = $options['placeholder'];
		if ( ! $options['is_multiple'] ) {
			$options['maxSelections'] = false;
		}
		$view->vars['dropdown_options'] = [
			'maxSelections'  => $options['maxSelections'],
			'allowAdditions' => $options['allowAdditions'],
			'minChars'       => $options['minChars'],
			'forceSelection' => false,
			'saveRemoteData' => $options['saveRemoteData'],
			'delimiter'      => $options['delimiter'],
			'keys'           => [ 'delimiter' => false ],
			'updateChilds'   => $options['updateChilds']
		];

		$view->vars['dropdown_options'] = json_encode( $view->vars['dropdown_options'], JSON_FORCE_OBJECT );
	}
}
