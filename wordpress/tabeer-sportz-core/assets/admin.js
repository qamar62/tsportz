/**
 * Crossbar Core - admin behaviour.
 *
 * Three jobs, all of them about stopping a mistake before it reaches a buyer:
 *
 *  1. The price preview updates as the editor types, so a missing named place
 *     or a price left in the wrong currency is visible immediately rather than
 *     after a save.
 *  2. Fields the chosen price mode does not use are dimmed, so nobody fills in
 *     an upper range figure that will never be shown.
 *  3. The gallery, the spec sheet and the documents are picked from the Media
 *     Library instead of being typed in as ID numbers.
 *
 * No jQuery, no build step. The formatting mirrors cb_price_html() in
 * inc/price.php; if that function changes, this has to change with it.
 *
 * @package Crossbar_Core
 */

( function () {
	'use strict';

	var data = window.cbCore || {};
	var i18n = data.i18n || {};

	/**
	 * Format one money value the way cb_money() does.
	 *
	 * Whole numbers lose the trailing ".00", and the separators are fixed rather
	 * than localised: an export price list is read in a dozen countries and a
	 * swapped comma and dot is the difference between 1,200 and 1.200.
	 *
	 * @param {string} amount   Raw amount.
	 * @param {string} currency Currency code.
	 * @return {string} Formatted money.
	 */
	function money( amount, currency ) {
		var value = parseFloat( String( amount ).replace( ',', '.' ) );

		if ( isNaN( value ) ) {
			return '';
		}

		var whole = Math.abs( value - Math.round( value ) ) < 0.005;
		var text  = value.toFixed( whole ? 0 : 2 );
		var parts = text.split( '.' );

		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, ',' );

		return currency + ' ' + parts.join( '.' );
	}

	/**
	 * Read a field's value, falling back to the sitewide default.
	 *
	 * @param {string} id       Element id.
	 * @param {string} fallback Default value.
	 * @return {string} Value.
	 */
	function field( id, fallback ) {
		var el = document.getElementById( id );

		if ( ! el ) {
			return fallback || '';
		}

		var value = String( el.value || '' ).trim();

		return value === '' ? ( fallback || '' ) : value;
	}

	/**
	 * Build the price sentence.
	 *
	 * @return {string} Sentence, or an empty string when there is nothing to show.
	 */
	function sentence() {
		var mode = field( 'cb_price_mode', 'quote' );

		if ( data.quoteOnly ) {
			return '';
		}

		if ( mode === 'quote' ) {
			return '';
		}

		var price = field( 'cb_price', '' );

		if ( price === '' ) {
			return '';
		}

		var currency = field( 'cb_currency', data.currency ).toUpperCase();
		var unit     = field( 'cb_price_unit', data.unit );
		var out      = money( price, currency );

		if ( out === '' ) {
			return '';
		}

		if ( mode === 'range' ) {
			var to = field( 'cb_price_to', '' );

			if ( to !== '' && parseFloat( to ) > parseFloat( price ) ) {
				out += ' – ' + money( to, currency );
			}
		}

		if ( mode === 'from' ) {
			out = ( i18n.from || 'From' ) + ' ' + out;
		}

		var abbr = ( data.units || {} )[ unit ];

		if ( abbr ) {
			out += ' / ' + abbr;
		}

		var term = field( 'cb_incoterm', data.incoterm );

		if ( term && term !== 'none' ) {
			out += ' ' + term;

			var place = field( 'cb_incoterm_place', data.place );

			if ( place ) {
				out += ' ' + place;
			}
		}

		return out;
	}

	/**
	 * Dim the fields the current mode ignores.
	 *
	 * @return {void}
	 */
	function dim() {
		var mode  = field( 'cb_price_mode', 'quote' );
		var quote = ( mode === 'quote' );

		[ 'cb_price', 'cb_currency', 'cb_price_unit', 'cb_incoterm', 'cb_incoterm_place', 'cb_tiers' ].forEach( function ( id ) {
			var el = document.getElementById( id );

			if ( el && el.parentNode ) {
				el.parentNode.classList.toggle( 'is-muted', quote );
			}
		} );

		var to = document.getElementById( 'cb_price_to' );

		if ( to && to.parentNode ) {
			to.parentNode.classList.toggle( 'is-muted', mode !== 'range' );
		}
	}

	/**
	 * Repaint the preview.
	 *
	 * @return {void}
	 */
	function paint() {
		var out = document.querySelector( '.cb-preview__out' );

		if ( ! out ) {
			return;
		}

		var text = sentence();

		if ( text === '' ) {
			out.textContent = data.quoteOnly ? ( i18n.siteQuote || '' ) : ( i18n.request || '' );
			out.classList.add( 'is-empty' );
		} else {
			out.textContent = text;
			out.classList.remove( 'is-empty' );
		}

		dim();
	}

	/**
	 * Wire the price box.
	 *
	 * @return {void}
	 */
	function watchPrice() {
		var ids = [
			'cb_price_mode',
			'cb_price',
			'cb_price_to',
			'cb_currency',
			'cb_price_unit',
			'cb_incoterm',
			'cb_incoterm_place'
		];

		var found = false;

		ids.forEach( function ( id ) {
			var el = document.getElementById( id );

			if ( ! el ) {
				return;
			}

			found = true;
			el.addEventListener( 'input', paint );
			el.addEventListener( 'change', paint );
		} );

		if ( found ) {
			paint();
		}
	}

	/**
	 * Ask before a live import.
	 *
	 * The dry-run box is ticked by default, so the only way to reach this is by
	 * deliberately unticking it — worth one confirmation.
	 *
	 * @return {void}
	 */
	function watchImport() {
		var form = document.querySelector( '.cb-import form' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			var dry = form.querySelector( 'input[name="cb_dry"]' );

			if ( dry && dry.checked ) {
				return;
			}

			if ( ! window.confirm( i18n.confirm || 'Continue?' ) ) {
				event.preventDefault();
			}
		} );
	}

	/* -----------------------------------------------------------------------
	 * Media pickers
	 *
	 * The hidden input is the field. Everything visible is a view of it, and
	 * every operation ends by writing the list of IDs back — which is why
	 * reordering, removing and adding all funnel through sync() rather than
	 * each keeping its own idea of the order.
	 * -------------------------------------------------------------------- */

	/**
	 * Build one tile.
	 *
	 * Mirrors cb_render_media_item() in inc/meta-boxes.php. The two have to
	 * produce the same shape, or a picked image would restyle itself after a
	 * save when the server rendered it instead.
	 *
	 * @param {Object} item Attachment model attributes from wp.media.
	 * @return {HTMLElement} List item.
	 */
	function tile( item ) {
		var li = document.createElement( 'li' );

		li.className = 'cb-media__item';
		li.setAttribute( 'data-id', item.id );
		li.setAttribute( 'draggable', 'true' );
		li.setAttribute( 'tabindex', '0' );

		var name = item.title || item.filename || '';

		if ( item.type === 'image' ) {
			var img   = document.createElement( 'img' );
			var sizes = item.sizes || {};
			var thumb = sizes.thumbnail || sizes.medium || sizes.full;

			img.src = thumb ? thumb.url : item.url;
			img.alt = '';
			li.appendChild( img );
		} else {
			var doc = document.createElement( 'span' );

			doc.className = 'cb-media__doc';
			doc.setAttribute( 'aria-hidden', 'true' );
			doc.textContent = String( item.filename || '' ).split( '.' ).pop().toUpperCase();
			li.appendChild( doc );
		}

		var label = document.createElement( 'span' );

		label.className = 'cb-media__name';
		label.textContent = name;
		li.appendChild( label );

		var remove = document.createElement( 'button' );

		remove.type = 'button';
		remove.className = 'cb-media__remove';
		remove.setAttribute( 'aria-label', String( i18n.remove || 'Remove %s' ).replace( '%s', name ) );
		remove.innerHTML = '<span aria-hidden="true">&times;</span>';
		li.appendChild( remove );

		return li;
	}

	/**
	 * Write the visible order back into the hidden input.
	 *
	 * @param {HTMLElement} box Picker root.
	 * @return {void}
	 */
	function sync( box ) {
		var list  = box.querySelector( '.cb-media__list' );
		var input = box.querySelector( '.cb-media__value' );
		var items = list ? list.querySelectorAll( '.cb-media__item' ) : [];
		var ids   = [];

		Array.prototype.forEach.call( items, function ( li ) {
			ids.push( li.getAttribute( 'data-id' ) );
		} );

		input.value = ids.join( ',' );

		var empty = box.querySelector( '.cb-media__empty' );
		var clear = box.querySelector( '.cb-media__clear' );

		if ( empty ) {
			empty.hidden = ids.length > 0;
		}

		if ( clear ) {
			clear.hidden = ids.length === 0;
		}
	}

	/**
	 * Open the Media Library.
	 *
	 * A fresh frame every time rather than one kept in a variable. A reused
	 * frame remembers its last selection, so the second visit would arrive with
	 * yesterday's images already ticked and quietly add them again.
	 *
	 * @param {HTMLElement} box Picker root.
	 * @return {void}
	 */
	function open( box ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var multiple = box.getAttribute( 'data-multiple' ) === '1';
		var mime     = box.getAttribute( 'data-mime' );

		var frame = window.wp.media( {
			title: box.getAttribute( 'data-frame' ),
			button: { text: multiple ? ( i18n.use || 'Use these files' ) : box.getAttribute( 'data-button' ) },
			library: mime ? { type: mime } : {},
			multiple: multiple ? 'add' : false
		} );

		frame.on( 'select', function () {
			var list  = box.querySelector( '.cb-media__list' );
			var picked = frame.state().get( 'selection' ).toJSON();

			if ( ! multiple ) {
				list.innerHTML = '';
			}

			picked.forEach( function ( item ) {

				// Picking the same photograph twice is nearly always a slip, and
				// a gallery that shows one shot in frames two and five looks
				// like a fault rather than a choice.
				if ( list.querySelector( '.cb-media__item[data-id="' + item.id + '"]' ) ) {
					return;
				}

				list.appendChild( tile( item ) );
			} );

			sync( box );
		} );

		frame.open();
	}

	/**
	 * Move one tile along by a place.
	 *
	 * @param {HTMLElement} li   Tile.
	 * @param {number}      step -1 or 1.
	 * @return {void}
	 */
	function shift( li, step ) {
		var sibling = step < 0 ? li.previousElementSibling : li.nextElementSibling;

		if ( ! sibling ) {
			return;
		}

		if ( step < 0 ) {
			sibling.parentNode.insertBefore( li, sibling );
		} else {
			sibling.parentNode.insertBefore( sibling, li );
		}

		li.focus();
		sync( li.closest( '.cb-media' ) );
	}

	/**
	 * Wire one picker.
	 *
	 * @param {HTMLElement} box Picker root.
	 * @return {void}
	 */
	function watchPicker( box ) {
		var list = box.querySelector( '.cb-media__list' );
		var drag = null;

		box.addEventListener( 'click', function ( event ) {
			var add = event.target.closest( '.cb-media__add' );

			if ( add ) {
				event.preventDefault();
				open( box );
				return;
			}

			var remove = event.target.closest( '.cb-media__remove' );

			if ( remove ) {
				event.preventDefault();
				remove.closest( '.cb-media__item' ).remove();
				sync( box );
				return;
			}

			var clear = event.target.closest( '.cb-media__clear' );

			if ( clear ) {
				event.preventDefault();

				if ( window.confirm( i18n.removeAll || 'Remove everything?' ) ) {
					list.innerHTML = '';
					sync( box );
				}
			}
		} );

		// Reordering with the keyboard, because dragging a thumbnail is the one
		// thing in this box a mouse can do and nothing else can.
		box.addEventListener( 'keydown', function ( event ) {
			var li = event.target.closest( '.cb-media__item' );

			if ( ! li ) {
				return;
			}

			if ( event.key === 'ArrowLeft' || event.key === 'ArrowUp' ) {
				event.preventDefault();
				shift( li, -1 );
			}

			if ( event.key === 'ArrowRight' || event.key === 'ArrowDown' ) {
				event.preventDefault();
				shift( li, 1 );
			}
		} );

		list.addEventListener( 'dragstart', function ( event ) {
			drag = event.target.closest( '.cb-media__item' );

			if ( drag ) {
				drag.classList.add( 'is-dragging' );
				event.dataTransfer.effectAllowed = 'move';

				// Firefox will not start a drag at all unless something is set.
				event.dataTransfer.setData( 'text/plain', drag.getAttribute( 'data-id' ) );
			}
		} );

		list.addEventListener( 'dragover', function ( event ) {
			if ( ! drag ) {
				return;
			}

			event.preventDefault();

			var over = event.target.closest( '.cb-media__item' );

			if ( ! over || over === drag ) {
				return;
			}

			var box2  = over.getBoundingClientRect();
			var after = ( event.clientX - box2.left ) > ( box2.width / 2 );

			over.parentNode.insertBefore( drag, after ? over.nextSibling : over );
		} );

		list.addEventListener( 'drop', function ( event ) {
			event.preventDefault();
		} );

		list.addEventListener( 'dragend', function () {
			if ( drag ) {
				drag.classList.remove( 'is-dragging' );
				drag = null;
				sync( box );
			}
		} );

		sync( box );
	}

	/**
	 * Empty the pickers on the "add category" form once a term has been added.
	 *
	 * That form is submitted over AJAX and stays on screen, and core clears the
	 * text inputs itself but knows nothing about ours. Without this the picture
	 * chosen for one category would still be sitting there when the next one is
	 * typed in, and would quietly be attached to it too.
	 *
	 * @return {void}
	 */
	function watchTermForm() {
		var form = document.getElementById( 'addtag' );

		if ( ! form || ! form.querySelector( '.cb-media' ) || ! window.jQuery ) {
			return;
		}

		window.jQuery( document ).on( 'ajaxSuccess', function ( event, xhr, settings ) {
			if ( String( settings.data || '' ).indexOf( 'action=add-tag' ) === -1 ) {
				return;
			}

			Array.prototype.forEach.call( form.querySelectorAll( '.cb-media' ), function ( box ) {
				box.querySelector( '.cb-media__list' ).innerHTML = '';
				sync( box );
			} );
		} );
	}

	/**
	 * Wire every picker on the screen.
	 *
	 * @return {void}
	 */
	function watchMedia() {
		Array.prototype.forEach.call( document.querySelectorAll( '.cb-media' ), watchPicker );
		watchTermForm();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			watchPrice();
			watchImport();
			watchMedia();
		} );
	} else {
		watchPrice();
		watchImport();
		watchMedia();
	}
}() );
