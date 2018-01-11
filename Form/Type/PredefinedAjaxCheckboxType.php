<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PredefinedAjaxCheckboxType extends AbstractType {

	/**
	 * {@inheritDoc}
	 */
	public function buildView( FormView $view, FormInterface $form, array $options ) {
		$view->vars['is_multiple']       = $options['is_multiple'];
		$view->vars['local_search_data'] = $options['local_search_data'];
		$view->vars['delimiter']         = $options['delimiter'];
        if ($options['is_multiple']) {
            $options['maxSelections'] = false;
        }

		//symfony config does not merge option values, just replaces them if they are present
		$view->vars['dropdown_options'] = [
			'maxSelections'  => $options['maxSelections'],
			'allowAdditions' => false,
			'minChars'       => $options['minChars'],
			'forceSelection' => false,
			'saveRemoteData' => false,
			'localSearch'    => true,
			'delimiter'      => $options['delimiter'],
			'keys'           => [ 'delimiter' => false ],
			'fullTextSearch' => true
		];

		$view->vars['dropdown_options'] = json_encode( $view->vars['dropdown_options'] );
	}

	public function getParent() {
		return AjaxCheckboxType::class;
	}


	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( [
			'allowAdditions' => false,
			'localSearch'    => true,
			'label_attr'     => [ 'tooltip' => '' ],
		] );
	}
}
