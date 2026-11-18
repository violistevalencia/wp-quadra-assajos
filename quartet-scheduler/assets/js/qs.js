/* Programant Haydn — Quadra d'assajos */
(function () {
	'use strict';

	if ( typeof qsData === 'undefined' ) {
		return;
	}

	var root = document.getElementById( 'qs-app' );
	if ( ! root ) {
		return;
	}

	var state = {
		month: root.getAttribute( 'data-month' ) || '',
		week: parseInt( root.getAttribute( 'data-week' ), 10 ) || 0,
		mid: root.getAttribute( 'data-mid' ) || '',
		day: root.getAttribute( 'data-day' ) || ''
	};

	/* Esborranys no desats, clau: id de músic ('' = músic nou).
	   Format: drafts[mid][date][slot] = '0' | '1' | '2' | '' */
	var drafts = {};

	function draftFor( mid ) {
		if ( ! drafts[ mid ] ) {
			drafts[ mid ] = {};
		}
		return drafts[ mid ];
	}

	function postAction( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', qsData.nonce );
		Object.keys( data ).forEach( function ( k ) {
			if ( null !== data[ k ] && undefined !== data[ k ] ) {
				body.append( k, data[ k ] );
			}
		} );
		return fetch( qsData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( res ) { return res.json(); } );
	}

	function render() {
		var old = root;
		var busy = document.createElement( 'div' );
		busy.className = 'qs-app';
		busy.id = 'qs-app';
		busy.setAttribute( 'data-month', state.month );
		busy.setAttribute( 'data-week', state.week );
		busy.setAttribute( 'data-mid', state.mid );
		busy.setAttribute( 'data-day', state.day );
		busy.textContent = qsData.i18n.loading;
		old.parentNode.replaceChild( busy, root );
		root = busy;

		return postAction( 'qs_render', {
			month: state.month,
			week: state.week,
			mid: state.mid,
			day: state.day
		} ).then( function ( res ) {
			if ( res && res.success && res.data && res.data.html ) {
				root.innerHTML = res.data.html;
				applyDrafts();
			} else {
				root.textContent = qsData.i18n.saveError;
			}
		} ).catch( function () {
			root.textContent = qsData.i18n.saveError;
		} );
	}

	function applyDrafts() {
		var mid = currentDraftKey();
		var draft = draftFor( mid );
		root.querySelectorAll( '.qs-cell' ).forEach( function ( btn ) {
			var date = btn.getAttribute( 'data-date' );
			var slot = btn.getAttribute( 'data-slot' );
			if (
				draft[ date ] &&
				Object.prototype.hasOwnProperty.call( draft[ date ], slot )
			) {
				setCell( btn, draft[ date ][ slot ] );
			}
		} );
	}

	function currentDraftKey() {
		return state.mid;
	}

	function setCell( btn, st ) {
		btn.setAttribute( 'data-st', st );
		btn.classList.remove( 'qs-f0', 'qs-f1', 'qs-f2' );
		if ( '' !== st && null !== st ) {
			btn.classList.add( 'qs-f' + st );
		}
	}

	function onChangeMonth( month ) {
		if ( state.month === month ) {
			return;
		}
		state.month = month;
		state.week = 0;
		state.day = '';
		render();
	}

	function onChangeWeek( dir ) {
		state.week = Math.max( 0, state.week + dir );
		render();
	}

	function onSelectDay( date ) {
		state.day = ( state.day === date ) ? '' : date;
		render();
	}

	function onCycleCell( btn ) {
		if ( ! state.mid ) {
			var select = root.querySelector( '#qs-mid' );
			if ( select ) {
				select.focus();
			}
			setNotice( qsData.i18n.needPick );
			return;
		}
		var date = btn.getAttribute( 'data-date' );
		var slot = btn.getAttribute( 'data-slot' );
		var cur = btn.getAttribute( 'data-st' );
		var next;
		if ( '' === cur ) {
			next = '2';
		} else if ( '2' === cur ) {
			next = '1';
		} else if ( '1' === cur ) {
			next = '0';
		} else {
			next = '';
		}
		setCell( btn, next );

		var draft = draftFor( state.mid );
		if ( ! draft[ date ] ) {
			draft[ date ] = {};
		}
		draft[ date ][ slot ] = next;
	}

	function setNotice( msg ) {
		var el = root.querySelector( '#qs-status' );
		if ( el ) {
			el.textContent = msg;
			el.className = 'qs-status qs-err';
		}
	}

	function getChangesFor( mid ) {
		var draft = draftFor( mid );
		var changed = {};
		Object.keys( draft ).forEach( function ( date ) {
			var slots = draft[ date ];
			Object.keys( slots ).forEach( function ( slot ) {
				if ( ! changed[ date ] ) {
					changed[ date ] = {};
				}
				changed[ date ][ slot ] = '' === slots[ slot ] ? null : parseInt( slots[ slot ], 10 );
			} );
		} );
		return changed;
	}

	function onSave() {
		var btn = root.querySelector( '#qs-save' );
		var status = root.querySelector( '#qs-status' );
		var nameEl = root.querySelector( '#qs-name' );
		var instEl = root.querySelector( '#qs-instrument' );

		var isNew = ! state.mid;
		var name = nameEl ? nameEl.value.trim() : '';
		var instrument = instEl ? instEl.value.trim() : '';

		if ( isNew && '' === name ) {
			status.textContent = qsData.i18n.needName;
			status.className = 'qs-status qs-err';
			if ( nameEl ) {
				nameEl.focus();
			}
			return;
		}

		if ( btn ) {
			btn.disabled = true;
		}
		if ( status ) {
			status.textContent = qsData.i18n.loading;
			status.className = 'qs-status';
		}

		postAction( 'qs_save', {
			mid: state.mid,
			name: isNew ? name : '',
			instrument: isNew ? instrument : '',
			changes: JSON.stringify( getChangesFor( state.mid ) ),
			month: state.month
		} ).then( function ( res ) {
			if ( res && res.success && res.data && res.data.mid ) {
				var mid = res.data.mid;
				delete drafts[ state.mid ];
				state.mid = mid;
				return render().then( function () {
					var el = root.querySelector( '#qs-status' );
					if ( el ) {
						el.textContent = res.data.message || '';
						el.className = 'qs-status qs-ok';
					}
				} );
			}
			throw new Error( res && res.data ? String( res.data ) : 'error' );
		} ).catch( function () {
			if ( btn ) {
				btn.disabled = false;
			}
			if ( status ) {
				status.textContent = qsData.i18n.saveError;
				status.className = 'qs-status qs-err';
			}
		} );
	}

	document.addEventListener( 'click', function ( ev ) {
		if ( ! root || ! root.contains( ev.target ) ) {
			return;
		}
		var t = ev.target;

		var tab = t.closest ? t.closest( '.qs-tab' ) : null;
		if ( tab ) {
			onChangeMonth( tab.getAttribute( 'data-month' ) );
			return;
		}

		var nav = t.closest ? t.closest( '.qs-nav' ) : null;
		if ( nav ) {
			onChangeWeek( parseInt( nav.getAttribute( 'data-dir' ), 10 ) || 0 );
			return;
		}

		var dayCell = t.closest ? t.closest( '.qs-day[data-date]' ) : null;
		if ( dayCell ) {
			onSelectDay( dayCell.getAttribute( 'data-date' ) );
			return;
		}

		var cell = t.closest ? t.closest( '.qs-cell' ) : null;
		if ( cell ) {
			onCycleCell( cell );
			return;
		}

		if ( t.id === 'qs-save' ) {
			onSave();
			return;
		}
	} );

	document.addEventListener( 'keydown', function ( ev ) {
		if ( ! root || ! root.contains( ev.target ) ) {
			return;
		}
		var dayCell = ev.target.closest ? ev.target.closest( '.qs-day[data-date]' ) : null;
		if ( dayCell && ( 13 === ev.keyCode || 32 === ev.keyCode ) ) {
			ev.preventDefault();
			onSelectDay( dayCell.getAttribute( 'data-date' ) );
			return;
		}
		var cell = ev.target.closest ? ev.target.closest( '.qs-cell' ) : null;
		if ( cell && ( 13 === ev.keyCode || 32 === ev.keyCode ) ) {
			ev.preventDefault();
			onCycleCell( cell );
		}
	} );

	document.addEventListener( 'change', function ( ev ) {
		if ( ! root || ! root.contains( ev.target ) ) {
			return;
		}
		if ( ev.target.id === 'qs-mid' ) {
			state.mid = ev.target.value;
			state.day = '';
			render();
		}
	} );
})();
