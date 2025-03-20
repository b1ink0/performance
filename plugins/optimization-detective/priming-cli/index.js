#!/usr/bin/env node

import puppeteer from 'puppeteer';
import { program } from 'commander';
import ora from 'ora';
import { execSync } from 'child_process';
import chalk from 'chalk';

// Declare the 'success' property on Window interface
/**
 * @typedef {Object} Window
 * @property {boolean} success - Flag to track if measurement was successful
 */

program
	.name( 'od-prime' )
	.description( 'CLI tool to prime URL metrics for Optimization Detective' )
	.parse( process.argv );

const spinner = ora( 'Starting...' ).start();
let browser;
let page;
const abortController = new AbortController();
const { signal } = abortController;

process.on( 'SIGINT', async () => {
	spinner.start( 'Aborting...' );
	abortController.abort();
} );

/**
 * Fetches the next batch of URLs.
 * @param {Object} lastCursor - The cursor to fetch the next batch.
 * @return {Object} - The batch of URLs.
 */
function getBatch( lastCursor ) {
	const batch = JSON.parse(
		execSync(
			`wp od get_url_batch --format=json --cursor=${ JSON.stringify(
				lastCursor
			) }`
		).toString()
	);
	return batch[ 0 ];
}

/**
 * Flattens the batch into individual tasks.
 * @param {Object} batch - The batch to flatten.
 * @return {Array<{ url: string, width: number, height: number }>} The list of tasks.
 */
function flattenBatchToTasks( batch ) {
	const tasks = [];
	for ( const urlObj of batch.batch ) {
		for ( const breakpoint of urlObj.breakpoints ) {
			tasks.push( {
				url: urlObj.url,
				width: breakpoint.width,
				height: breakpoint.height,
			} );
		}
	}
	return tasks;
}

/**
 * Processes a single task using Puppeteer.
 *
 * @param {import('puppeteer').Page}                       currentPage       - The Puppeteer page to use.
 * @param {{ url: string, width: number, height: number }} task              - The task parameters.
 * @param {string}                                         verificationToken - The verification token.
 * @param {AbortSignal}                                    currentSignal     - The signal to abort the task.
 * @return {Promise<void>}
 */
function processTask( currentPage, task, verificationToken, currentSignal ) {
	return new Promise( async ( resolve, reject ) => {
		function onAbort() {
			reject( new Error( 'Task aborted.' ) );
		}
		currentSignal.addEventListener( 'abort', onAbort );

		try {
			// Before each navigation, reset the success flag.
			await currentPage.evaluate( () => {
				// @ts-ignore
				window.success = false;
			} );

			const urlToLoad = new URL( task.url );
			urlToLoad.searchParams.append(
				'od-verification-token',
				verificationToken
			);

			// Set viewport dimensions.
			await currentPage.setViewport( {
				width: task.width,
				height: task.height,
			} );

			// Navigate to the URL.
			await currentPage.goto( urlToLoad.toString(), {
				waitUntil: 'load',
			} );

			// Wait for the success flag to become true (with a 30-second timeout).
			await currentPage.waitForFunction( 'window.success === true', {
				timeout: 30000,
			} );
		} catch ( error ) {
			reject( error );
		} finally {
			currentSignal.removeEventListener( 'abort', onAbort );
			resolve();
		}
	} );
}

async function main() {
	browser = await puppeteer.launch( { headless: true } );
	page = await browser.newPage();

	// const slow3G = {
	// 	offline: false,
	// 	download: ( 500 * 1024 ) / 8, // converts 500 Kbps to bytes per second
	// 	upload: ( 500 * 1024 ) / 8,
	// 	latency: 400, // milliseconds
	// };
	// await page.emulateNetworkConditions( slow3G );

	await page.evaluateOnNewDocument( () => {
		// @ts-ignore
		window.success = false;
		window.addEventListener( 'message', ( event ) => {
			if ( event.data === 'OD_PRIME_URL_METRICS_REQUEST_SUCCESS' ) {
				// @ts-ignore
				window.success = true;
			}
		} );
	} );

	let isNextBatchAvailable = true;
	let cursor = {};
	let currentBatchNumber = 0;
	let verificationToken;

	// Process batches until no more are available.
	while ( isNextBatchAvailable ) {
		if ( signal.aborted ) {
			break;
		}
		spinner.start( 'Fetching next batch...' );
		const currentBatch = await getBatch( cursor );
		// If no URLs remain in the batch, finish processing.
		if ( ! currentBatch.batch || currentBatch.batch.length === 0 ) {
			isNextBatchAvailable = false;
			break;
		}
		verificationToken = currentBatch.verificationToken;
		currentBatchNumber++;

		spinner.succeed(
			`Batch ${ currentBatchNumber } fetched successfully.`
		);

		// Extract token and debug flag from the batch.
		const currentTasks = flattenBatchToTasks( currentBatch );

		// Process each task sequentially.
		for ( let i = 0; i < currentTasks.length; i++ ) {
			if ( signal.aborted ) {
				break;
			}
			const task = currentTasks[ i ];
			// Record the start time for this task.
			const taskStartTime = Date.now();

			spinner.start(
				`Processing task ${ chalk.green(
					i + 1 + '/' + currentTasks.length
				) } for ${ chalk.blue( task.url ) } at ${ chalk.blue(
					task.width + 'x' + task.height
				) }..`
			);
			try {
				await processTask( page, task, verificationToken, signal );
				const taskEndTime = Date.now();
				const timeTakenSeconds = (
					( taskEndTime - taskStartTime ) /
					1000
				).toFixed( 2 );
				spinner.succeed(
					`Task ${ chalk.green(
						i + 1 + '/' + currentTasks.length
					) } completed successfully in ${ chalk.blue(
						timeTakenSeconds
					) } seconds for ${ chalk.blue(
						task.url
					) } at ${ chalk.blue( task.width + 'x' + task.height ) }.`
				);
			} catch ( error ) {
				const taskEndTime = Date.now();
				const timeTakenSeconds = (
					( taskEndTime - taskStartTime ) /
					1000
				).toFixed( 2 );
				spinner.fail(
					`Task ${ chalk.green(
						i + 1 + '/' + currentTasks.length
					) } failed after ${ chalk.blue(
						timeTakenSeconds
					) } seconds for ${ chalk.blue(
						task.url
					) } at ${ chalk.blue( task.width + 'x' + task.height ) }.
					Error: ${ chalk.red( error.message ) }`
				);
			}
		}
		// Update the cursor to fetch the next batch.
		cursor = currentBatch.cursor;
	}

	if ( signal.aborted ) {
		spinner.start( 'Aborted.' );
	} else {
		spinner.succeed( 'All batches processed.' );
	}
	await browser.close();
	process.exit( 0 );
}
main();
