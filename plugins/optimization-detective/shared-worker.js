const clients = new Map(); // Map to store client ports and info by URL.
const metricsStore = new Map(); // Store metrics by client URL.
const HEARTBEAT_INTERVAL = 5000; // Interval for sending heartbeats.
const MAX_MISSED_ACKS = 3; // Max missed ACKs before removing a client and sending metrics.

/**
 * Logs a message
 * @param {string} message - The message(s) to log.
 */
function log( message ) {
	// eslint-disable-next-line no-console
	console.log( `[Optimization Detective Shared Worker] ${ message }` );
}

/**
 * Compresses a JSON string using CompressionStream API.
 * @param {string} jsonString - JSON string to compress.
 * @return {Promise<Blob>} Compressed data.
 */
async function compress( jsonString ) {
	const encodedData = new TextEncoder().encode( jsonString );
	const compressedDataStream = new Blob( [ encodedData ] )
		.stream()
		.pipeThrough( new CompressionStream( 'gzip' ) );
	const compressedDataBuffer = await new Response(
		compressedDataStream
	).arrayBuffer();
	return new Blob( [ compressedDataBuffer ], { type: 'application/gzip' } );
}

/**
 * Sends metrics for a specific URL to the server.
 * @param {string} pageUrl - The URL for which to send metrics.
 * @return {Promise<void>} Resolves when the metrics are sent.
 */
async function sendMetricsForUrl( pageUrl ) {
	const metrics = metricsStore.get( pageUrl );
	if ( ! metrics ) {
		return;
	}

	const { urlMetric, urlMetricUrlConfig } = metrics;
	if ( ! urlMetric || ! urlMetricUrlConfig ) {
		return;
	}

	const url = new URL( urlMetricUrlConfig.restApiEndpoint );
	if ( typeof urlMetricUrlConfig.restApiNonce === 'string' ) {
		url.searchParams.set( '_wpnonce', urlMetricUrlConfig.restApiNonce );
	}
	url.searchParams.set( 'slug', urlMetricUrlConfig.urlMetricSlug );
	url.searchParams.set( 'current_etag', urlMetricUrlConfig.currentETag );
	if ( typeof urlMetricUrlConfig.cachePurgePostId === 'number' ) {
		url.searchParams.set(
			'cache_purge_post_id',
			urlMetricUrlConfig.cachePurgePostId.toString()
		);
	}
	url.searchParams.set( 'hmac', urlMetricUrlConfig.urlMetricHMAC );
	const headers = {
		'Content-Type': 'application/json',
	};

	let payloadBlob = null;
	if ( urlMetricUrlConfig.gzdecodeAvailable ) {
		payloadBlob = await compress( JSON.stringify( urlMetric ) );
		headers[ 'Content-Encoding' ] = 'gzip';
	} else {
		payloadBlob = new Blob( [ JSON.stringify( urlMetric ) ], {
			type: 'application/json',
		} );
	}

	const request = new Request( url, {
		method: 'POST',
		body: payloadBlob,
		headers,
	} );

	log( `Sending metrics for URL: ${ pageUrl }` );
	await fetch( request );
}

/**
 * Sends heartbeat to all connected clients and checks for responses.
 */
function sendHeartbeats() {
	for ( const [ url, clientInfo ] of clients.entries() ) {
		// Send heartbeat request to client.
		try {
			clientInfo.port.postMessage( { type: 'heartbeat' } );
			log( `Heartbeat sent to client: ${ url }` );
		} catch ( error ) {
			log( `Failed to send heartbeat to ${ url }: ${ error.message }` );
			// Remove client if port is dead.
			clients.delete( url );
			continue;
		}

		// Check if client missed too many ACKs.
		if ( clientInfo.missedAcks >= MAX_MISSED_ACKS ) {
			log(
				`Client ${ url } missed ${ clientInfo.missedAcks } heartbeat ACKs. Removing client and sending metrics...`
			);
			sendMetricsForUrl( url );
			clients.delete( url );
		} else {
			// Increment missed ACKs (will be reset when ACK is received).
			clientInfo.missedAcks++;
		}
	}
}

// Send heartbeats to all clients periodically.
setInterval( sendHeartbeats, HEARTBEAT_INTERVAL );

/**
 * Handles incoming connections from clients.
 * @param {MessageEvent} event - The connection event.
 * @return {void}
 */
function handleOnConnect( event ) {
	const port = event.ports[ 0 ];

	port.onmessage = function ( e ) {
		const data = e.data;

		if ( data.type === 'register' ) {
			// Register a new client.
			clients.set( data.url, {
				port,
				lastHeartbeat: Date.now(),
				missedAcks: 0, // Track missed ACKs per client
			} );
			if ( ! metricsStore.has( data.url ) ) {
				metricsStore.set( data.url, {} );
			}
			port.postMessage( { type: 'registered', url: data.url } );
			log( `Client registered: ${ data.url }` );
		} else if ( data.type === 'heartbeat_ack' ) {
			// Handle heartbeat ACK from client.
			const client = clients.get( data.url );
			if ( client ) {
				client.lastHeartbeat = Date.now();
				client.missedAcks = 0;
				log( `Heartbeat ACK received from: ${ data.url }` );
			}
		} else if ( data.type === 'metrics' ) {
			// Store metrics for the client.
			metricsStore.set( data.url, data.metrics );
			port.postMessage( { type: 'metrics_ack' } );
		} else if ( data.type === 'unregister' ) {
			// Handle client unregister.
			sendMetricsForUrl( data.url );
			clients.delete( data.url );
			metricsStore.delete( data.url );
			log( `Client unregistered: ${ data.url }` );
		}
	};

	port.start();
}

// eslint-disable-next-line
// @ts-ignore
self.onconnect = handleOnConnect;
