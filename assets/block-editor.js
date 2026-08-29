( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var Placeholder = components.Placeholder;
	var SelectControl = components.SelectControl;
	var __ = i18n.__;
	var configuredSliders = window.mfsBlockEditor && window.mfsBlockEditor.sliders ? window.mfsBlockEditor.sliders : [];
	var options = [ { label: __( 'Select a slider', 'mlyn-flexible-slider' ), value: '' } ].concat( configuredSliders );

	blocks.registerBlockType( 'mlyn/slider', {
		edit: function ( props ) {
			var control = el( SelectControl, {
				label: __( 'Slider', 'mlyn-flexible-slider' ),
				value: props.attributes.slider,
				options: options,
				onChange: function ( value ) {
					props.setAttributes( { slider: value } );
				}
			} );

			return el(
				element.Fragment,
				null,
				el( InspectorControls, null, el( PanelBody, { title: __( 'Slider settings', 'mlyn-flexible-slider' ) }, control ) ),
				el(
					'div',
					useBlockProps(),
					props.attributes.slider
						? [
							el( 'span', { className: 'mfs-editor-preview-label', key: 'label' }, __( 'Náhled slideru', 'mlyn-flexible-slider' ) ),
							el( ServerSideRender, { block: 'mlyn/slider', attributes: props.attributes, key: 'preview' } )
						]
						: el( Placeholder, { icon: 'images-alt2', label: __( 'Mlýn slider', 'mlyn-flexible-slider' ) }, control )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
