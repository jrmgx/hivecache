import { inlineAllCSS } from './lib/css';
import { embedAllImages } from './lib/images';
import { removeAllScripts, removeNoscriptAndIframes, disableAllLinks, cleanupHead } from './lib/dom';
import { extractPageMetadata } from './lib/metadata';
import { getBrowserAPI, getBrowserRuntime } from './lib/browser';

const browserAPI = getBrowserAPI();
const runtime = getBrowserRuntime();

let messageListenerRegistered = false;

async function compressHTML(): Promise<string | null> {
    console.log("Archive: Getting final HTML...");

    // Get the complete HTML of the page
    const html = '<!DOCTYPE html>\n' + document.documentElement.outerHTML;
    console.log(`Archive: HTML size: ${html.length} characters`);

    // Send HTML to background script for compression
    try {
        const response = await browserAPI.runtime.sendMessage({
            action: 'compressHTML',
            html: html
        });
        console.log("Archive: HTML sent to background script for compression");
        return response?.fileObjectId || null;
    } catch (error) {
        console.error("Archive: Error sending HTML to background script:", error);
        return null;
    }
}

// Main function that archives the page
async function archivePage(): Promise<string | null> {
    console.log("Archive: Starting page archive...");

    removeAllScripts();
    removeNoscriptAndIframes();
    await inlineAllCSS();
    await embedAllImages();
    disableAllLinks();
    cleanupHead();
    const fileObjectId = await compressHTML();

    console.log("Archive: Page archive complete!");

    // Refresh the page after capture is complete
    window.location.reload();
    return fileObjectId;
}

// Listen for messages from popup (for metadata extraction and page archiving)
if (runtime && typeof runtime.onMessage === 'object' && !messageListenerRegistered) {
    messageListenerRegistered = true;
    runtime.onMessage.addListener((message: { action?: string }, _sender, sendResponse) => {
        if (message.action === 'executeCode') {
            extractPageMetadata().then((pageData) => {
                console.log('Page metadata:', pageData);
                sendResponse({ success: true, data: pageData });
            }).catch((error) => {
                console.error('Error extracting page metadata:', error);
                sendResponse({ success: false, error: error.message });
            });
            return true; // Keep the message channel open for async response
        } else if (message.action === 'archivePage') {
            archivePage().then((fileObjectId) => {
                sendResponse({ success: true, fileObjectId: fileObjectId || undefined });
            }).catch((error: Error) => {
                console.error('Archive: Error archiving page:', error);
                sendResponse({ success: false, error: error.message });
            });
            return true; // Keep the message channel open for async response
        }
        return false; // Don't keep channel open for unknown actions
    });
}
