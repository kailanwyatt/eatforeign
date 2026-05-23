( function () {
	'use strict';

	var cfg = window.efPendingItem || {};
	var nonce = cfg.nonce || '';
	var i18n = cfg.i18n || {};

	function showAdminNotice( message, type ) {
		var wrap = document.querySelector( '.wrap' );
		if ( ! wrap ) {
			return;
		}
		var existing = wrap.querySelector( '.ef-pending-admin-notice' );
		if ( existing ) {
			existing.remove();
		}
		var notice = document.createElement( 'div' );
		notice.className =
			'notice ef-pending-admin-notice notice-' +
			( type === 'success' ? 'success' : type === 'error' ? 'error' : 'info' ) +
			' is-dismissible';
		notice.innerHTML = '<p>' + message + '</p>';
		var h1 = wrap.querySelector( 'h1' );
		if ( h1 && h1.nextSibling ) {
			wrap.insertBefore( notice, h1.nextSibling );
		} else {
			wrap.insertBefore( notice, wrap.firstChild );
		}
	}

	function setStatus( el, message, type ) {
		if ( ! el ) {
			showAdminNotice( message, type );
			return;
		}
		el.textContent = message;
		el.className = 'ef-pending-generate-status';
		if ( type ) {
			el.classList.add( 'ef-pending-generate-status--' + type );
		}
	}

	function setBusy( btn, busy ) {
		if ( ! btn ) {
			return;
		}
		btn.disabled = !! busy;
		if ( busy ) {
			btn.setAttribute( 'aria-busy', 'true' );
		} else {
			btn.removeAttribute( 'aria-busy' );
		}
	}

	function runGenerate( pendingId, btn, statusEl ) {
		if ( ! pendingId || ! nonce ) {
			return;
		}

		setBusy( btn, true );
		setStatus( statusEl, i18n.generating || 'Generating…', 'info' );

		var body = new URLSearchParams();
		body.append( 'action', 'eatforeign_generate_pending_item' );
		body.append( 'nonce', nonce );
		body.append( 'pending_id', String( pendingId ) );

		fetch( ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
			credentials: 'same-origin',
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( data.success && data.data ) {
					var msg = data.data.message || ( i18n.success || 'Done.' );
					if ( data.data.dish_edit_url ) {
						msg +=
							' <a href="' +
							data.data.dish_edit_url +
							'">' +
							( i18n.editDish || 'Edit dish' ) +
							'</a>';
					}
					if ( statusEl ) {
						statusEl.innerHTML = msg;
						statusEl.className = 'ef-pending-generate-status ef-pending-generate-status--success';
					} else {
						setStatus( null, msg, 'success' );
					}
					if ( cfg.redirectUrl ) {
						window.setTimeout( function () {
							window.location.href = cfg.redirectUrl;
						}, 1500 );
					} else if ( cfg.reloadOnSuccess ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 1500 );
					}
					return;
				}

				var err = data.data || {};
				var message = err.message || ( i18n.failed || 'Generation failed.' );
				if ( err.code === 'rate_limited' && err.retry_after ) {
					message += ' Retry in about ' + err.retry_after + 's.';
				}
				if ( err.dish_edit_url ) {
					message += ' <a href="' + err.dish_edit_url + '">' + ( i18n.editDish || 'Edit dish' ) + '</a>';
				}
				if ( statusEl ) {
					statusEl.innerHTML = message;
					statusEl.className = 'ef-pending-generate-status ef-pending-generate-status--error';
				} else {
					setStatus( null, message, 'error' );
				}
			} )
			.catch( function () {
				setStatus( statusEl, i18n.networkError || 'Network error.', 'error' );
			} )
			.finally( function () {
				setBusy( btn, false );
			} );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.ef-generate-pending-item' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var pendingId = btn.getAttribute( 'data-pending-id' );
		var statusEl = document.getElementById( 'ef-pending-generate-status-' + pendingId )
			|| document.getElementById( 'ef-pending-generate-status' );
		runGenerate( pendingId, btn, statusEl );
	} );
} )();
