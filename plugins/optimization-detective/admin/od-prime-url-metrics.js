/* global odPrimeData */
( function () {
	document.addEventListener( 'DOMContentLoaded', () => {
		const container = document.getElementById( 'od-prime-app' );
		if ( ! container || ! odPrimeData ) {
			return;
		}

		const data = odPrimeData?.data ? odPrimeData.data : [];
		let urls = Array.isArray( data?.urls ) ? data.urls : [];
		const breakpoints = Array.isArray( odPrimeData.breakpoints )
			? odPrimeData.breakpoints
			: [];

		// Indices
		let currentUrlIndex = 0;
		let currentBreakpointIndex = 0;
		let currentBatch = 1;

		// Create Buttons
		const btnStart = document.createElement( 'button' );
		btnStart.textContent = 'Start';

		const btnPlay = document.createElement( 'button' );
		btnPlay.textContent = 'Auto Play';

		const btnLoad = document.createElement( 'button' );
		btnLoad.textContent = 'Load This Breakpoint';
		btnLoad.disabled = true;

		const btnNextBreakpoint = document.createElement( 'button' );
		btnNextBreakpoint.textContent = 'Next Breakpoint';
		btnNextBreakpoint.disabled = true;

		const btnNextUrl = document.createElement( 'button' );
		btnNextUrl.textContent = 'Next URL';
		btnNextUrl.disabled = true;

		const btnNextBatch = document.createElement( 'button' );
		btnNextBatch.textContent = 'Fetch Next Batch';

		const info = document.createElement( 'div' );
		info.style.marginTop = '10px';
		info.innerText = 'Click "Start" to begin.';

		const iframe = document.createElement( 'iframe' );
		iframe.style.display = 'block';
		iframe.style.marginTop = '20px';
		iframe.width = '900';
		iframe.height = '600';
		iframe.style.border = '1px solid #ccc';

		// Layout rows
		const row1 = document.createElement( 'div' );
		row1.style.marginBottom = '10px';
		row1.appendChild( btnStart );
		row1.appendChild( btnPlay );
		row1.appendChild( btnNextBatch );

		const row2 = document.createElement( 'div' );
		row2.style.marginBottom = '10px';
		row2.appendChild( btnLoad );
		row2.appendChild( btnNextBreakpoint );

		const row3 = document.createElement( 'div' );
		row3.style.marginBottom = '10px';
		row3.appendChild( btnNextUrl );

		container.appendChild( row1 );
		container.appendChild( row2 );
		container.appendChild( row3 );
		container.appendChild( info );
		container.appendChild( iframe );

		// Helpers
		function getCurrentUrl() {
			return urls[ currentUrlIndex ];
		}
		function getCurrentBreakpoint() {
			return breakpoints[ currentBreakpointIndex ];
		}

		function updateInfoDisplay( msg ) {
			const totalUrls = urls.length;
			const totalBps = breakpoints.length;

			const urlStr = `URL ${ currentUrlIndex + 1 } / ${ totalUrls }`;
			const bpStr = `Breakpoint ${
				currentBreakpointIndex + 1
			} / ${ totalBps }`;

			let detailStr = '';
			if (
				currentUrlIndex < totalUrls &&
				currentBreakpointIndex < totalBps
			) {
				const url = getCurrentUrl();
				const bp = getCurrentBreakpoint();
				detailStr = `Current: URL=${ url } | ${ bp.width }x${ bp.height }`;
			}

			info.innerHTML = `
		[${ urlStr }]<br/>
		[${ bpStr }]<br/>
		[${ detailStr }]<br/>
		${ msg || '' }
	  `;
		}

		function loadCurrentIframe() {
			if (
				currentUrlIndex >= urls.length ||
				currentBreakpointIndex >= breakpoints.length
			) {
				updateInfoDisplay( 'All done or out of range' );
				return;
			}
			const url = getCurrentUrl();
			const bp = getCurrentBreakpoint();

			const paramChar = url.includes( '?' ) ? '&' : '?';
			const loadUrl = `${ url }${ paramChar }od_prime=1`;

			iframe.width = String( bp.width );
			iframe.height = String( bp.height );

			updateInfoDisplay(
				`Loading URL at breakpoint width=${ bp.width }, height=${ bp.height }, aspect=${ bp.ar }}`
			);
			iframe.src = loadUrl;
		}

		btnStart.addEventListener( 'click', () => {
			currentUrlIndex = 0;
			currentBreakpointIndex = 0;
			btnLoad.disabled = false;
			btnNextBreakpoint.disabled = false;
			btnNextUrl.disabled = false;

			updateInfoDisplay(
				'Ready. Click "Load This Breakpoint" to load the first URL/breakpoint.'
			);
		} );

		btnLoad.addEventListener( 'click', () => {
			loadCurrentIframe();
		} );

		btnNextBreakpoint.addEventListener( 'click', () => {
			currentBreakpointIndex++;
			if ( currentBreakpointIndex >= breakpoints.length ) {
				currentBreakpointIndex = breakpoints.length - 1;
				updateInfoDisplay(
					'No more breakpoints. Maybe go to Next URL.'
				);
			} else {
				updateInfoDisplay(
					'Now at next breakpoint. Click "Load This Breakpoint".'
				);
			}
		} );

		btnNextUrl.addEventListener( 'click', () => {
			currentUrlIndex++;
			currentBreakpointIndex = 0;
			if ( currentUrlIndex >= urls.length ) {
				currentUrlIndex = urls.length - 1;
				updateInfoDisplay( 'No more URLs left.' );
			} else {
				updateInfoDisplay(
					'Now at next URL, first breakpoint. Click "Load This Breakpoint".'
				);
			}
		} );

		// Auto Play
		let autoPlayOn = false;
		btnPlay.addEventListener( 'click', () => {
			autoPlayOn = ! autoPlayOn;
			if ( autoPlayOn ) {
				btnPlay.textContent = 'Stop Auto Play';
				loadCurrentIframe();
			} else {
				btnPlay.textContent = 'Auto Play';
			}
		} );

		window.addEventListener( 'message', ( event ) => {
			if ( 'done_prime' === event.data ) {
				info.innerHTML += ' … iframe loaded successfully.';
				if ( autoPlayOn ) {
					setTimeout( async () => {
						currentBreakpointIndex++;
						if ( currentBreakpointIndex >= breakpoints.length ) {
							currentBreakpointIndex = 0;
							currentUrlIndex++;
							if ( currentUrlIndex >= urls.length ) {
								await fetchNextBatch();
								if ( currentUrlIndex >= urls.length ) {
									btnPlay.textContent = 'Auto Play';
									updateInfoDisplay( 'Auto play finished.' );
									return;
								}
							}
						}
						loadCurrentIframe();
					}, 1000 );
				}
			} else if ( 'failed_prime' === event.data ) {
				info.innerHTML += ' … iframe failed to load.';
			}
		} );

		btnNextBatch.addEventListener( 'click', fetchNextBatch );

		async function fetchNextBatch() {
			updateInfoDisplay( `Fetching batch ${ currentBatch }...` );
			const formData = new FormData();
			formData.append( 'action', 'od_generate_urls_list' );
			formData.append( 'provider_index', data.next_provider_index );
			formData.append( 'subtype_index', data.next_subtype_index );
			formData.append( 'page_number', data.next_page_number );
			formData.append(
				'offset_within_page',
				data.next_offset_within_page
			);
			formData.append( 'batch_size', data.batch_size );
			formData.append( 'nonce', data.od_next_nonce );

			try {
				const response = await fetch( '/wp-admin/admin-ajax.php', {
					method: 'POST',
					body: formData,
				} );
				const result = await response.json();

				if ( result.success ) {
					info.innerText += `\nBatch ${ currentBatch } completed.`;

					if ( result.data.has_more ) {
						// Update data for the next batch
						data.next_provider_index =
							result.data.next_provider_index;
						data.next_subtype_index =
							result.data.next_subtype_index;
						data.next_page_number = result.data.next_page_number;
						data.next_offset_within_page =
							result.data.next_offset_within_page;
						currentBatch++;

						urls = result.data.urls;

						// Reset the indices
						currentUrlIndex = 0;
						currentBreakpointIndex = 0;
					} else {
						updateInfoDisplay( 'No more batches available.' );
					}
				}
			} catch ( error ) {
				updateInfoDisplay( `Error: ${ error.message }` );
			}
		}
	} );
} )();
