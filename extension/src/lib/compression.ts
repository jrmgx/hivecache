/**
 * HTML compression and upload functions
 */

import { uploadFileObject } from '../api';
import type { FileObject } from '@shared';

/**
 * Compresses HTML using gzip compression
 * @param html The HTML string to compress
 * @returns The compressed HTML as a Uint8Array
 */
async function compressHTML(html: string): Promise<Uint8Array> {
    console.log("Archive Background: Starting compression...");
    const encoder = new TextEncoder();
    const htmlBytes = encoder.encode(html);
    const readableStream = new ReadableStream({
        start(controller) {
            controller.enqueue(htmlBytes);
            controller.close();
        }
    });

    const compressionStream = new CompressionStream('gzip');
    const compressedStream = readableStream.pipeThrough(compressionStream) as ReadableStream<Uint8Array>;
    const reader = compressedStream.getReader();
    const chunks: Uint8Array[] = [];

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        if (value) {
            chunks.push(value);
        }
    }

    const totalLength = chunks.reduce((acc, chunk) => acc + chunk.length, 0);
    const compressed = new Uint8Array(totalLength);
    let offset = 0;
    for (const chunk of chunks) {
        compressed.set(chunk, offset);
        offset += chunk.length;
    }

    console.log("Archive Background: Compression complete!");
    console.log(`Archive Background: Original size: ${htmlBytes.length} bytes`);
    console.log(`Archive Background: Compressed size: ${compressed.length} bytes`);

    return compressed;
}

/**
 * Compresses HTML and uploads it to the API
 * @param html The HTML string to compress and upload
 * @returns The file object response containing @iri
 * @TODO move to logic controller
 */
export async function compressAndUploadHTML(html: string): Promise<FileObject> {
    console.log(`Archive Background: HTML size: ${html.length} characters`);

    try {
        const compressed = await compressHTML(html);

        // Send compressed data to API endpoint
        const blob = new Blob([compressed as BlobPart], { type: 'application/gzip' });
        const result = await uploadFileObject(blob);
        console.log("Archive Background: Successfully uploaded to API:", result);
        return result;
    } catch (error) {
        console.error("Archive Background: Compression error:", error);
        throw error;
    }
}

