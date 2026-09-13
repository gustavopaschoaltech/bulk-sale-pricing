( function( $ ) {
	'use strict';

	$( function() {
		var $ruleForm = $( '.bsp-rule-form' );
		var initialFormState = $ruleForm.serialize();
		var isDirty = false;
		var isSubmitting = false;

		$ruleForm.on( 'change input', ':input:not([type="hidden"])', function() {
			isDirty = initialFormState !== $ruleForm.serialize();
		} );

		$ruleForm.on( 'submit', function() {
			isSubmitting = true;
		} );

		$( window ).on( 'beforeunload', function( event ) {
			if ( ! isDirty || isSubmitting ) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
			return '';
		} );

		$( '#bsp_categories, #bsp_tags' ).each( function() {
			var $select = $( this );

			if ( 'function' !== typeof $.fn.selectWoo ) {
				return;
			}

			if ( $select.hasClass( 'enhanced' ) ) {
				$select.selectWoo( 'destroy' ).removeClass( 'enhanced' );
			}

			$select.selectWoo( {
				closeOnSelect: false,
				minimumResultsForSearch: 0,
				placeholder: $select.data( 'placeholder' ),
				width: '100%'
			} ).addClass( 'enhanced' );
		} );
	} );
} )( jQuery );
