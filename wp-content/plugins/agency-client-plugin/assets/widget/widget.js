( function () {
	const { createElement, render, useEffect, useState } = wp.element;

	function CaseStudiesWidget( { endpoint } ) {
		const [ state, setState ] = useState( {
			status: 'loading',
			items: [],
			error: '',
		} );

		useEffect( function () {
			let cancelled = false;

			fetch( endpoint )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Request failed' );
					}

					return response.json();
				} )
				.then( function ( data ) {
					if ( cancelled ) {
						return;
					}

					setState( {
						status: 'ready',
						items: Array.isArray( data.items ) ? data.items : [],
						error: '',
					} );
				} )
				.catch( function () {
					if ( cancelled ) {
						return;
					}

					setState( {
						status: 'error',
						items: [],
						error: 'Unable to load case studies right now.',
					} );
				} );

			return function () {
				cancelled = true;
			};
		}, [ endpoint ] );

		if ( 'loading' === state.status ) {
			return createElement( 'p', { className: 'acp-widget__status' }, 'Loading case studies...' );
		}

		if ( 'error' === state.status ) {
			return createElement( 'p', { className: 'acp-widget__status acp-widget__status--error' }, state.error );
		}

		if ( ! state.items.length ) {
			return createElement( 'p', { className: 'acp-widget__status' }, 'No case studies available yet.' );
		}

		return createElement(
			'div',
			{ className: 'acp-widget' },
			state.items.map( function ( item ) {
				return createElement(
					'article',
					{ className: 'acp-widget__item', key: item.id },
					createElement(
						'h3',
						{ className: 'acp-widget__title' },
						createElement( 'a', { href: item.link }, item.title )
					),
					item.metric
						? createElement( 'p', { className: 'acp-widget__metric' }, item.metric )
						: null,
					item.excerpt
						? createElement( 'p', { className: 'acp-widget__excerpt' }, item.excerpt )
						: null,
					createElement(
						'p',
						{ className: 'acp-widget__link' },
						createElement( 'a', { href: item.link }, 'Read case study' )
					)
				);
			} )
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.acp-case-studies-widget' ).forEach( function ( node ) {
			const endpoint = node.getAttribute( 'data-endpoint' );

			render(
				createElement( CaseStudiesWidget, { endpoint: endpoint } ),
				node
			);
		} );
	} );
} )();
