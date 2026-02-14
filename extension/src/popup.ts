// Type declaration for TomSelect (loaded via CDN)
declare const TomSelect: any;

import { getAPIHost, getJWTToken, uploadFileObject, createBookmark, fetchUserTags, ensureTagsExist } from './api';
import { showStatus as showStatusLib, showApiHostRequiredError as showApiHostRequiredErrorLib, showLoginRequiredError as showLoginRequiredErrorLib, startClockAnimation } from './lib/ui';
import { PageData, BookmarkPayload, Tag, ArchivePageResponse } from './types';
import { getBrowserRuntime, getBrowserTabs } from './lib/browser';
import { formatTagName } from '@shared';

document.addEventListener('DOMContentLoaded', () => {
    // Store user tags for tag input completion
    let userTags: Tag[] = [];
    let tagsSelect: any = null;
    let currentUrl: string = '';
    const bookmarkForm = document.getElementById('bookmarkForm') as HTMLFormElement | null;
    const optionsLink = document.getElementById('optionsLink');
    const titleInput = document.getElementById('title') as HTMLInputElement | null;
    const tagsSelectElement = document.getElementById('tags') as HTMLSelectElement | null;
    const imageUrlInput = document.getElementById('imageUrl') as HTMLInputElement | null;
    const isPublicCheckbox = document.getElementById('isPublic') as HTMLInputElement | null;
    const imageUrlLabel = document.getElementById('imageUrlLabel') as HTMLLabelElement | null;
    const imagePreviewContainer = document.getElementById('imagePreviewContainer') as HTMLElement | null;
    const imagePreview = document.getElementById('imagePreview') as HTMLImageElement | null;
    const manualImageButton = document.getElementById('manualImageButton') as HTMLButtonElement | null;
    const submitButton = document.getElementById('submitButton') as HTMLButtonElement | null;
    const statusMessage = document.getElementById('statusMessage') as HTMLElement | null;

    function makeAbsoluteUrl(url: string, baseUrl: string): string {
        if (!url) return url;

        if (/^https?:\/\//i.test(url)) {
            return url;
        }

        try {
            const base = new URL(baseUrl);
            const resolved = new URL(url, base);
            return resolved.href;
        } catch (error) {
            console.error('Error resolving URL:', error);
            return url;
        }
    }

    function updateImagePreview(imageUrl: string, currentPageUrl: string): void {
        if (!imagePreviewContainer || !imagePreview || !imageUrlInput || !imageUrlLabel) return;

        const absoluteImageUrl = makeAbsoluteUrl(imageUrl, currentPageUrl);

        if (absoluteImageUrl) {
            imagePreview.src = absoluteImageUrl;
            imagePreviewContainer.style.display = 'block';
            imageUrlInput.style.display = 'none';
            imageUrlInput.value = absoluteImageUrl;
            imageUrlLabel.style.display = 'none';
        } else {
            imagePreviewContainer.style.display = 'none';
            imageUrlInput.style.display = 'block';
            imageUrlLabel.style.display = 'block';
        }
    }

    function updateUrlDisplay(url: string): void {
        currentUrl = url;
    }

    function showApiHostRequiredError(): void {
        if (!statusMessage) return;
        const runtime = getBrowserRuntime();
        showApiHostRequiredErrorLib(statusMessage, runtime);
    }

    // Auto-fill form when popup opens
    async function autoFillForm(): Promise<void> {
        const runtime = getBrowserRuntime();
        const tabs = getBrowserTabs();

        if (!runtime || !tabs) {
            return;
        }

        try {
            const [activeTab] = await new Promise<chrome.tabs.Tab[]>((resolve) => {
                tabs.query({ active: true, currentWindow: true }, resolve);
            });

            if (!activeTab?.id) {
                return;
            }

            // Send message to content script and wait for response
            tabs.sendMessage(activeTab.id, { action: 'executeCode' }, (response) => {
                if (runtime.lastError) {
                    console.error('Error sending message to content script:', runtime.lastError.message);
                    return;
                }

                if (response && response.success && response.data) {
                    const pageData: PageData = response.data;
                    // Fill the form
                    if (titleInput) titleInput.value = pageData.title || '';
                    updateUrlDisplay(pageData.url || '');
                    if (pageData.image) {
                        updateImagePreview(pageData.image, pageData.url || '');
                    } else {
                        // Show input and label if no image detected
                        if (imagePreviewContainer) imagePreviewContainer.style.display = 'none';
                        if (imageUrlInput) imageUrlInput.style.display = 'block';
                        if (imageUrlLabel) imageUrlLabel.style.display = 'block';
                    }
                } else {
                    console.error('Failed to extract page data:', response);
                }
            });
        } catch (error) {
            // Silently fail - form will just be empty
            console.error('Error auto-filling form:', error);
        }
    }

    async function loadUserTags(): Promise<void> {
        try {
            userTags = await fetchUserTags();
            console.log(`Loaded ${userTags.length} user tags`);
            if (tagsSelect) {
                updateTomSelectOptions();
            }
        } catch (error) {
            console.error('Error fetching user tags:', error);
            const errorMessage = error instanceof Error ? error.message : 'Unknown error';
            if (errorMessage.includes('API host not configured')) {
                showApiHostRequiredError();
            } else if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                showLoginRequiredError();
            }
        }
    }

    // Archive the current page and get the archived file object Id
    async function archivePage(): Promise<string | null> {
        const runtime = getBrowserRuntime();
        const tabs = getBrowserTabs();

        if (!runtime || !tabs) {
            console.error('Browser APIs not available');
            return null;
        }

        try {
            const [activeTab] = await new Promise<chrome.tabs.Tab[]>((resolve) => {
                tabs.query({ active: true, currentWindow: true }, resolve);
            });

            if (!activeTab?.id) {
                console.error('No active tab found');
                return null;
            }

            // Content script is already loaded via manifest.json, no need to inject
            // Just wait a bit to ensure it's ready
            await new Promise(resolve => setTimeout(resolve, 100));

            return new Promise<string | null>((resolve) => {
                tabs.sendMessage(activeTab.id!, { action: 'archivePage' }, (response: ArchivePageResponse) => {
                    if (runtime.lastError) {
                        console.error('Error archiving page:', runtime.lastError.message);
                        resolve(null);
                        return;
                    }

                    if (response && response.success && response.fileObjectId) {
                        console.log('Page archived successfully, file object ID:', response.fileObjectId);
                        resolve(response.fileObjectId);
                    } else {
                        console.error('Failed to archive page:', response);
                        resolve(null);
                    }
                });
            });
        } catch (error) {
            console.error('Error freezing page:', error);
            return null;
        }
    }

    function updateTomSelectOptions(): void {
        if (!tagsSelect) return;

        const options = userTags.map(tag => ({
            value: tag.name,
            text: formatTagName(tag),
            icon: tag.icon
        }));

        tagsSelect.clearOptions();
        tagsSelect.addOptions(options);
    }

    function initializeTomSelect(): void {
        if (!tagsSelectElement) {
            console.error('Tags select element not found');
            return;
        }

        if (typeof TomSelect === 'undefined') {
            setTimeout(() => {
                initializeTomSelect();
            }, 100);
            return;
        }

        if (tagsSelect) {
            tagsSelect.destroy();
            tagsSelect = null;
        }

        const options = userTags.map(tag => ({
            value: tag.name,
            text: formatTagName(tag),
            icon: tag.icon
        }));

        tagsSelect = new TomSelect(tagsSelectElement, {
            plugins: ['remove_button', 'restore_on_backspace', 'clear_button'],
            options: options,
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            create: true,
            placeholder: 'Select or create tags',
            maxItems: null,
            maxOptions: null,
            closeAfterSelect: true,
            render: {
                option: function(data: any, escape: (str: string) => string) {
                    return `<div class="ts-option-text">${escape(data.text)}</div>`;
                },
                item: function(data: any, escape: (str: string) => string) {
                    return `<div class="ts-item-text">${escape(data.text)}</div>`;
                }
            }
        });
    }

    function waitForTomSelectAndInitialize(): void {
        if (typeof TomSelect !== 'undefined') {
            loadUserTags().then(() => {
                initializeTomSelect();
            }).catch(() => {
                initializeTomSelect();
            });
        } else {
            setTimeout(() => {
                waitForTomSelectAndInitialize();
            }, 100);
        }
    }

    if (manualImageButton) {
        manualImageButton.addEventListener('click', () => {
            if (imagePreviewContainer && imageUrlInput && imageUrlLabel) {
                imagePreviewContainer.style.display = 'none';
                imageUrlInput.style.display = 'block';
                imageUrlInput.type = 'text';
                imageUrlLabel.style.display = 'block';
            }
        });
    }

    autoFillForm();
    waitForTomSelectAndInitialize();

    // Handle form submission
    if (bookmarkForm) {
        bookmarkForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!titleInput || !imageUrlInput || !submitButton) {
                const errorMsg = 'Form fields not found';
                console.error(errorMsg);
                showStatus(errorMsg, 'error');
                return;
            }

            const title = titleInput.value.trim();
            const url = currentUrl.trim();
            let imageUrl = imageUrlInput.value.trim();
            const isPublic = isPublicCheckbox ? isPublicCheckbox.checked : false;

            if (imageUrl) {
                imageUrl = makeAbsoluteUrl(imageUrl, url);
            }

            if (!title || !url) {
                const errorMsg = 'Please fill in title and URL';
                console.error(errorMsg);
                showStatus(errorMsg, 'error');
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
            const stopInitialSavingAnimation = startClockAnimation(submitButton, 'Saving...');

            try {
                const apiHost = await getAPIHost();
                if (!apiHost) {
                    stopInitialSavingAnimation();
                    showApiHostRequiredError();
                    return;
                }

                const token = await getJWTToken();
                if (!token) {
                    stopInitialSavingAnimation();
                    showLoginRequiredError();
                    return;
                }

                const selectedTagNames: string[] = tagsSelect ? tagsSelect.getValue() : [];
                let tagIRIs: string[] = [];

                if (selectedTagNames.length > 0) {
                    stopInitialSavingAnimation();
                    submitButton.textContent = 'Creating tags...';
                    try {
                        tagIRIs = await ensureTagsExist(selectedTagNames, userTags);
                        updateTomSelectOptions();
                    } catch (error) {
                        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                        if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                            showLoginRequiredError();
                            return;
                        }
                        throw error; // Re-throw if it's not an auth error
                    }
                } else {
                    stopInitialSavingAnimation();
                }

                let mainImageId: string = '';
                if (imageUrl) {
                    try {
                        submitButton.textContent = 'Uploading image...';

                        const imageResponse = await fetch(imageUrl);
                        if (!imageResponse.ok) {
                            throw new Error(`Failed to fetch image: ${imageResponse.status}`);
                        }

                        const imageBlob = await imageResponse.blob();

                        const fileObject = await uploadFileObject(imageBlob);
                        mainImageId = fileObject['@iri'];

                        console.log('Image uploaded successfully:', mainImageId);
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                        if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                            stopInitialSavingAnimation();
                            showLoginRequiredError();
                            return;
                        }
                        showStatus(`Warning: Failed to upload image: ${errorMessage}`, 'error');
                        // Don't throw - allow bookmark creation without image
                    }
                }

                submitButton.textContent = 'Archiving page...';
                const stopArchivingAnimation = startClockAnimation(submitButton, 'Archiving page...');

                // Archive the page and get the archived file object Id
                let archiveId: string | null = null;
                try {
                    archiveId = await archivePage();
                    if (archiveId) {
                        console.log('Page archived successfully:', archiveId);
                    } else {
                        console.warn('Failed to archive page, continuing without archive');
                    }
                } catch (error) {
                    console.error('Error archiving page:', error);
                    // Continue without archive if archiving fails
                }

                stopArchivingAnimation();
                submitButton.textContent = 'Saving bookmark...';

                let stopSavingAnimation: (() => void) | null = null;

                const payload: BookmarkPayload = {
                    title: title,
                    url: url,
                    ...(mainImageId && { mainImage: mainImageId }),
                    isPublic: isPublic,
                    tags: tagIRIs,
                    archive: archiveId || undefined
                };

                try {
                    stopSavingAnimation = startClockAnimation(submitButton, 'Saving bookmark...');
                    await createBookmark(payload);

                    if (stopSavingAnimation) {
                        stopSavingAnimation();
                    }
                    submitButton.textContent = 'Bookmark saved!';

                    showStatus('Bookmark saved successfully!', 'success');

                    if (bookmarkForm) {
                        bookmarkForm.style.display = 'none';
                    }

                    setTimeout(() => { window.close(); }, 1000);
                } catch (error) {
                    if (stopSavingAnimation) {
                        stopSavingAnimation();
                    }

                    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                    // Handle 401 Unauthorized - show login message with link
                    if (errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                        showLoginRequiredError();
                        return;
                    }
                    throw error;
                }
            } catch (error) {
                stopInitialSavingAnimation();

                const errorMessage = error instanceof Error ? error.message : 'Unknown error occurred';
                if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                    showLoginRequiredError();
                    return;
                }

                console.error('Error saving bookmark:', error);
                showStatus(`Error: ${errorMessage}`, 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Save Bookmark';
            }
        });
    }

    if (optionsLink) {
        optionsLink.addEventListener('click', (e) => {
            e.preventDefault();
            // Open options page
            const runtime = getBrowserRuntime();
            if (runtime && typeof runtime.openOptionsPage === 'function') {
                runtime.openOptionsPage();
            }
        });
    }

    function showLoginRequiredError(): void {
        if (!statusMessage) return;
        const runtime = getBrowserRuntime();
        showLoginRequiredErrorLib(statusMessage, runtime);
    }

    function showStatus(message: string, type: 'success' | 'error', keepOpen: boolean = false): void {
        if (!statusMessage) return;
        showStatusLib(statusMessage, message, type, keepOpen);
    }
});

