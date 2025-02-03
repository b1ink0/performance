// od-prime-post.js
( function ( wp, odPrimeData ) {
	const { select, subscribe } = wp.data;

	let isPriming = false;
	let postURL = '';
	let currentBreakpointIndex = 0;
	const breakpoints = odPrimeData.breakpoints;
	const iframe = document.createElement( 'iframe' );
	iframe.style.display = 'block';
	iframe.style.marginTop = '20px';
	iframe.width = '900';
	iframe.height = '600';
	iframe.style.border = '1px solid #ccc';
	document.body.appendChild( iframe );

	// Function to prime the metrics for a single URL
	function primeURL( url ) {
		return new Promise( ( resolve, reject ) => {
			loadCurrentIframe( url );

			window.addEventListener( 'message', function ( event ) {
				if ( 'done_prime' === event.data ) {
					resolve();
					currentBreakpointIndex++;
					loadCurrentIframe( url );
					console.log( 'Primed metrics for URL:', url );
				}
			} );
		} );
	}

	function loadCurrentIframe( url ) {
		if ( currentBreakpointIndex >= breakpoints.length ) {
			console.log( 'All done or out of range' );
			return;
		}
		const bp = breakpoints[ currentBreakpointIndex ];

		const paramChar = url.includes( '?' ) ? '&' : '?';
		const loadUrl = `${ url }${ paramChar }od_prime=1`;

		iframe.width = String( bp.width );
		iframe.height = String( bp.height );

		console.log(
			`Loading URL at breakpoint width=${ bp.width }, height=${ bp.height }, aspect=${ bp.ar }}`
		);
		iframe.src = loadUrl;
	}

	// Function to handle post save/publish
	async function handlePostSave() {
		if ( isPriming ) {
			return;
		}

		isPriming = true;
		postURL = select( 'core/editor' ).getPermalink();
		console.log( 'postURL', postURL );

		await primeURL( postURL );

		// // Show confirmation dialog if user tries to leave
		// window.addEventListener( 'beforeunload', beforeUnloadHandler );

		// try {
		// 	await primeURL( postURL );
		// 	// Optionally, notify the user of success
		// 	console.log( `Primed metrics for URL: ${ postURL }` );
		// } catch ( error ) {
		// 	console.error( error );
		// 	// Optionally, notify the user of failure
		// } finally {
		// 	isPriming = false;
		// 	window.removeEventListener( 'beforeunload', beforeUnloadHandler );
		// }
	}

	// beforeunload event handler
	function beforeUnloadHandler( e ) {
		if ( isPriming ) {
			e.preventDefault();
			e.returnValue = '';
		}
	}

	// Listen for post save/publish events
	const unsubscribe = subscribe( () => {
		const isSaving = select( 'core/editor' ).isSavingPost();
		const isAutosaving = select( 'core/editor' ).isAutosavingPost();
		const isPublished =
			select( 'core/editor' ).getCurrentPost().status === 'publish';
		const isJustSaved = isSaving && ! isAutosaving && isPublished;
		// console.log('isSaving', isSaving);
		// console.log('isAutosaving', isAutosaving);
		// console.log('isPublished', isPublished);
		if ( isJustSaved ) {
			handlePostSave();
		}
	} );
} )( window.wp, window.odPrimeData );
