(function () {
	'use strict';

	function fieldSelector(key) {
		return '[name="fields[' + CSS.escape( key ) + ']"], [name="fields[' + CSS.escape( key ) + '][]"]';
	}

	function getValue(form, key) {
		var elements = form.querySelectorAll( fieldSelector( key ) );
		if ( ! elements.length) {
			return '';
		}
		if (elements[0].type === 'radio') {
			var checkedRadio = Array.from( elements ).find(
				function (element) {
					return element.checked; }
			);
			return checkedRadio ? checkedRadio.value : '';
		}
		if (elements[0].type === 'checkbox') {
			return Array.from( elements ).filter(
				function (element) {
					return element.checked; }
			).map(
				function (element) {
						return element.value; }
			).join( ',' );
		}
		return elements[0].value || '';
	}

	function serializeForm(form) {
		var data     = {};
		var formData = new FormData( form );
		formData.forEach(
			function (value, key) {
				if (value instanceof File || key.indexOf( 'nonce' ) !== -1 || key === 'action') {
					return;
				}
				if (Object.prototype.hasOwnProperty.call( data, key )) {
					if ( ! Array.isArray( data[key] )) {
						data[key] = [data[key]];
					}
					data[key].push( value );
				} else {
					data[key] = value;
				}
			}
		);
		return data;
	}

	function restoreForm(form, data) {
		Object.keys( data || {} ).forEach(
			function (name) {
				var value    = data[name];
				var elements = form.querySelectorAll( '[name="' + CSS.escape( name ) + '"]' );
				if ( ! elements.length) {
					return;
				}
				var values = Array.isArray( value ) ? value.map( String ) : [String( value )];
				elements.forEach(
					function (element) {
						if (element.type === 'checkbox' || element.type === 'radio') {
							element.checked = values.indexOf( element.value ) !== -1;
						} else if ( ! element.value || element.classList.contains( 'clever-signature-data' )) {
							element.value = values[0] || '';
						}
					}
				);
			}
		);
	}

	function initializeForm(wrap) {
		var form = wrap.querySelector( 'form' );
		if ( ! form) {
			return;
		}

		var formId         = wrap.dataset.formId || '0';
		var storageKey     = 'clever-form-' + formId;
		var pageIndex      = 0;
		var pages          = Array.from( form.querySelectorAll( '.clever-page' ) );
		var previousButton = form.querySelector( '.clever-prev' );
		var nextButton     = form.querySelector( '.clever-next' );
		var submitRow      = form.querySelector( '.clever-submit-row' );
		var progress       = form.querySelector( '.clever-progress span' );
		var useTransitions = wrap.dataset.transitions === '1';
		var signaturePads  = [];

		function showPage() {
			pages.forEach(
				function (page, index) {
					page.classList.toggle( 'is-active', index === pageIndex );
					page.classList.toggle( 'clever-page-transition', useTransitions );
				}
			);
			if (previousButton) {
				previousButton.hidden = pageIndex === 0;
			}
			if (nextButton) {
				nextButton.hidden = pageIndex >= pages.length - 1;
			}
			if (submitRow) {
				submitRow.hidden = pages.length > 1 && pageIndex < pages.length - 1;
			}
			if (progress && pages.length) {
				progress.style.width = (((pageIndex + 1) / pages.length) * 100) + '%';
				progress.parentElement.setAttribute( 'aria-valuenow', String( pageIndex + 1 ) );
				progress.parentElement.setAttribute( 'aria-valuemax', String( pages.length ) );
			}
			window.requestAnimationFrame(
				function () {
					signaturePads.forEach(
						function (item) {
							item.pad.resize(); }
					);
				}
			);
		}

		function visiblePageValid() {
			var page = pages[pageIndex];
			if ( ! page) {
				return true;
			}
			var controls = page.querySelectorAll( 'input, select, textarea' );
			for (var i = 0; i < controls.length; i += 1) {
				var control = controls[i];
				if (control.closest( '.is-hidden' ) || control.type === 'hidden') {
					continue;
				}
				if ( ! control.checkValidity()) {
					control.reportValidity();
					return false;
				}
			}
			return true;
		}

		if (nextButton) {
			nextButton.addEventListener(
				'click',
				function () {
					if (visiblePageValid() && pageIndex < pages.length - 1) {
						pageIndex += 1;
						showPage();
					}
				}
			);
		}
		if (previousButton) {
			previousButton.addEventListener(
				'click',
				function () {
					if (pageIndex > 0) {
						pageIndex -= 1;
						showPage();
					}
				}
			);
		}

		function applyConditions() {
			form.querySelectorAll( '[data-condition-field]' ).forEach(
				function (box) {
					var key      = box.dataset.conditionField;
					var operator = box.dataset.conditionOperator;
					var wanted   = box.dataset.conditionValue || '';
					var actual   = getValue( form, key );
					var show     = true;
					if (operator === 'equals') {
						show = actual === wanted; } else if (operator === 'not_equals') {
										show = actual !== wanted; } else if (operator === 'contains') {
							show = actual.indexOf( wanted ) !== -1; } else if (operator === 'not_empty') {
											show = actual !== ''; }
							box.classList.toggle( 'is-hidden', ! show );
							box.querySelectorAll( '[required]' ).forEach(
								function (element) {
									if ( ! show && element.required) {
										element.dataset.cleverWasRequired = '1';
										element.required                  = false;
									} else if (show && element.dataset.cleverWasRequired === '1') {
										element.required = true;
										delete element.dataset.cleverWasRequired;
									}
								}
							);
				}
			);
		}
		form.addEventListener( 'input', applyConditions );
		form.addEventListener( 'change', applyConditions );

		wrap.querySelectorAll( '.clever-signature' ).forEach(
			function (box) {
				var canvas      = box.querySelector( '.clever-signature-canvas' );
				var hidden      = box.querySelector( '.clever-signature-data' );
				var clearButton = box.querySelector( '.clever-signature-clear' );
				var status      = box.querySelector( '.clever-signature-status' );
				if ( ! canvas || ! hidden || ! window.CleverFormsSignaturePad) {
					return;
				}
				var pad  = new window.CleverFormsSignaturePad( canvas );
				var item = { pad: pad, hidden: hidden, canvas: canvas, status: status };
				signaturePads.push( item );

				function syncSignature() {
					hidden.value = pad.toDataURL();
					if (status) {
						status.textContent = hidden.value ? 'Signature captured' : '';
					}
				}

				canvas.addEventListener( 'cleverforms:signaturechange', syncSignature );
				if (clearButton) {
					clearButton.addEventListener(
						'click',
						function () {
							pad.clear();
							hidden.value = '';
							if (status) {
								status.textContent = 'Signature cleared'; }
						}
					);
				}
			}
		);

		var persistTimer = null;
		function persistDraft() {
			if (wrap.dataset.save !== '1') {
				return;
			}
			signaturePads.forEach(
				function (item) {
					if ( ! item.pad.isEmpty()) {
						item.hidden.value = item.pad.toDataURL();
					}
				}
			);
			try {
				localStorage.setItem( storageKey, JSON.stringify( { page: pageIndex, fields: serializeForm( form ), savedAt: Date.now() } ) );
			} catch (error) {
				// Browsers may disable storage. The form remains fully usable.
			}
		}

		if (wrap.dataset.save === '1') {
			var saveButton       = wrap.querySelector( '.clever-save' );
			var clearDraftButton = wrap.querySelector( '.clever-clear' );
			if (saveButton) {
				saveButton.addEventListener(
					'click',
					function () {
						persistDraft();
						window.alert( 'Progress saved in this browser.' );
					}
				);
			}
			if (clearDraftButton) {
				clearDraftButton.addEventListener(
					'click',
					function () {
						localStorage.removeItem( storageKey );
						window.alert( 'Saved progress cleared.' );
					}
				);
			}
			try {
				var stored = JSON.parse( localStorage.getItem( storageKey ) || '{}' );
				if (stored.fields) {
					restoreForm( form, stored.fields );
					pageIndex = Math.min( Math.max( parseInt( stored.page || 0, 10 ), 0 ), Math.max( pages.length - 1, 0 ) );
				}
			} catch (error) {
				// Ignore malformed browser storage.
			}
			if (wrap.dataset.autoSave === '1') {
				['input', 'change'].forEach(
					function (eventName) {
						form.addEventListener(
							eventName,
							function () {
								window.clearTimeout( persistTimer );
								persistTimer = window.setTimeout( persistDraft, eventName === 'input' ? 650 : 250 );
							}
						);
					}
				);
			}
		}

		form.querySelectorAll( '.clever-signature-data' ).forEach(
			function (hidden) {
				if ( ! hidden.value) {
					return; }
				var box  = hidden.closest( '.clever-signature' );
				var item = signaturePads.find(
					function (candidate) {
						return candidate.hidden === hidden; }
				);
				if (box && item) {
					item.pad.fromDataURL( hidden.value );
				}
			}
		);

		function copyValues() {
			form.querySelectorAll( '[data-copy-from]' ).forEach(
				function (box) {
					var source = box.dataset.copyFrom;
					var target = box.querySelector( 'input:not([type=hidden]), textarea, select' );
					if ( ! target) {
						return; }
					var value = getValue( form, source );
					if (document.activeElement !== target && value !== '' && target.value === '') {
						target.value = value;
					}
				}
			);
		}
		form.addEventListener( 'input', copyValues );
		form.addEventListener( 'change', copyValues );

		form.querySelectorAll( 'input[type=checkbox][data-max-selections]' ).forEach(
			function (checkbox) {
				checkbox.addEventListener(
					'change',
					function () {
						var max = parseInt( checkbox.dataset.maxSelections || '0', 10 );
						if ( ! max) {
							return; }
						var group = Array.from( form.querySelectorAll( 'input[type=checkbox][name="' + CSS.escape( checkbox.name ) + '"]' ) );
						if (group.filter(
							function (item) {
								return item.checked; }
						).length > max) {
							checkbox.checked = false;
							window.alert( 'You may select up to ' + max + ' options.' );
						}
					}
				);
			}
		);

		form.querySelectorAll( 'textarea[data-min-words], textarea[data-max-words]' ).forEach(
			function (textarea) {
				var note = textarea.closest( '.clever-field' ) ? textarea.closest( '.clever-field' ).querySelector( '.clever-word-count' ) : null;
				function updateWordCount() {
					var words = textarea.value.trim().match( /\S+/g ) || [];
					var min   = parseInt( textarea.dataset.minWords || '0', 10 );
					var max   = parseInt( textarea.dataset.maxWords || '0', 10 );
					if (note) {
						note.textContent = words.length + ' word' + (words.length === 1 ? '' : 's'); }
					var message = '';
					if (min && words.length < min) {
						message = 'Enter at least ' + min + ' words.'; }
					if (max && words.length > max) {
						message = 'Use no more than ' + max + ' words.'; }
					textarea.setCustomValidity( message );
				}
				textarea.addEventListener( 'input', updateWordCount );
				updateWordCount();
			}
		);

		form.querySelectorAll( '.clever-slider' ).forEach(
			function (slider) {
				var output = slider.parentElement.querySelector( 'output' );
				function updateSlider() {
					if (output) {
								output.textContent = slider.value; } }
				slider.addEventListener( 'input', updateSlider );
				updateSlider();
			}
		);

		form.querySelectorAll( '.clever-list' ).forEach(
			function (list) {
				var addButton = list.querySelector( '.clever-list-add' );
				var firstRow  = list.querySelector( '.clever-list-row' );
				if (addButton && firstRow) {
					addButton.addEventListener(
						'click',
						function () {
							var clone = firstRow.cloneNode( true );
							clone.querySelectorAll( 'input' ).forEach(
								function (input) {
									input.value = ''; }
							);
							list.insertBefore( clone, addButton );
						}
					);
				}
				list.addEventListener(
					'click',
					function (event) {
						if ( ! (event.target instanceof Element) || ! event.target.classList.contains( 'clever-list-remove' )) {
							return; }
						var rows = list.querySelectorAll( '.clever-list-row' );
						if (rows.length > 1) {
							event.target.closest( '.clever-list-row' ).remove();
						} else {
							var input = rows[0].querySelector( 'input' );
							if (input) {
								input.value = ''; }
						}
					}
				);
			}
		);

		function updatePrice() {
			var products = Array.from( form.querySelectorAll( '[data-product-price]:checked' ) );
			var subtotal = products.reduce(
				function (total, product) {
					return total + (parseFloat( product.dataset.productPrice || '0' ) || 0); },
				0
			);
			form.querySelectorAll( '.clever-calc-subtotal' ).forEach(
				function (field) {
					field.value = subtotal.toFixed( 2 ); }
			);
			form.querySelectorAll( '[data-money-type=subtotal] strong' ).forEach(
				function (output) {
					output.textContent = subtotal.toFixed( 2 ); }
			);
		}
		form.addEventListener( 'change', updatePrice );

		if (wrap.dataset.preview === '1') {
			form.addEventListener(
				'submit',
				function (event) {
					if (form.dataset.previewed === '1') {
						return; }
					event.preventDefault();
					var body = wrap.querySelector( '.clever-preview-body' );
					var box  = wrap.querySelector( '.clever-preview' );
					if ( ! body || ! box) {
						return; }
					body.replaceChildren();
					var list = document.createElement( 'dl' );
					form.querySelectorAll( '.clever-field[data-field-key]' ).forEach(
						function (field) {
							if (field.classList.contains( 'is-hidden' )) {
								return; }
							var label         = field.querySelector( 'label strong' );
							var controls      = field.querySelectorAll( 'input, select, textarea' );
							var values        = Array.from( controls ).filter(
								function (control) {
									return control.type !== 'hidden' && control.type !== 'file' && ((control.type !== 'checkbox' && control.type !== 'radio') || control.checked);
								}
							).map(
								function (control) {
									return control.value; }
							).filter( Boolean );
							var signatureData = field.querySelector( '.clever-signature-data' );
							if (signatureData && signatureData.value) {
								values.push( 'Signature captured' );
							}
							if ( ! values.length) {
								return; }
							var term               = document.createElement( 'dt' );
							var definition         = document.createElement( 'dd' );
							term.textContent       = label ? label.innerText : field.dataset.fieldKey;
							definition.textContent = values.join( ', ' );
							list.append( term, definition );
						}
					);
					body.append( list );
					pages.forEach(
						function (page) {
							page.classList.remove( 'is-active' ); }
					);
					if (previousButton) {
						previousButton.hidden = true; }
					if (nextButton) {
						nextButton.hidden = true; }
					if (submitRow) {
						submitRow.hidden = false; }
					box.hidden             = false;
					form.dataset.previewed = '1';
				}
			);
			var editButton = wrap.querySelector( '.clever-edit' );
			if (editButton) {
				editButton.addEventListener(
					'click',
					function () {
						var box = wrap.querySelector( '.clever-preview' );
						if (box) {
							box.hidden = true; }
						form.dataset.previewed = '0';
						showPage();
					}
				);
			}
		}

		form.addEventListener(
			'submit',
			function (event) {
				for (var i = 0; i < signaturePads.length; i += 1) {
					var item = signaturePads[i];
					if ( ! item.pad.isEmpty()) {
						item.hidden.value = item.pad.toDataURL();
					}
					if (item.hidden.dataset.required === '1' && ! item.hidden.value) {
						event.preventDefault();
						var field = item.hidden.closest( '.clever-field' );
						if (field) {
							var page        = field.closest( '.clever-page' );
							var targetIndex = pages.indexOf( page );
							if (targetIndex >= 0) {
								pageIndex = targetIndex; showPage(); }
						}
						if (item.status) {
							item.status.textContent = 'Signature is required.'; }
						item.canvas.focus();
						return;
					}
				}
				if ( ! event.defaultPrevented) {
					localStorage.removeItem( storageKey );
				}
			}
		);

		window.addEventListener(
			'resize',
			function () {
				window.requestAnimationFrame(
					function () {
						signaturePads.forEach(
							function (item) {
								item.pad.resize(); }
						);
					}
				);
			}
		);

		applyConditions();
		copyValues();
		updatePrice();
		showPage();
	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			document.querySelectorAll( '.clever-form-wrap' ).forEach( initializeForm );
		}
	);
}());
