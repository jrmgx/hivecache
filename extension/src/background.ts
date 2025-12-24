import { compressAndUploadHTML } from './lib/compression';
import { MessageRequest, MessageResponse } from './types';
import { getBrowserRuntime } from './lib/browser';

getBrowserRuntime().onMessage.addListener((
    request: MessageRequest,
    _sender: chrome.runtime.MessageSender,
    sendResponse: (response: MessageResponse & { fileObjectId?: string }) => void
): boolean => {
    if (request.action === 'compressHTML') {
        handleCompressHTML(request.html)
            .then((fileObjectId) => {
                sendResponse({ success: true, fileObjectId });
            })
            .catch((error: Error) => {
                console.error("Archive Background: Compression error:", error);
                sendResponse({ success: false, error: error.message });
            });
        return true; // Keep the message channel open for async response
    }
    return false;
});

async function handleCompressHTML(html: string): Promise<string> {
    const fileObject = await compressAndUploadHTML(html);
    return fileObject['@iri'];
}

