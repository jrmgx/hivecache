// Popup script for the extension action button

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
    let tagsSelect: any = null; // TomSelect instance
    let currentUrl: string = ''; // Store the current page URL
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

    // Helper function to convert relative URL to absolute URL
    function makeAbsoluteUrl(url: string, baseUrl: string): string {
        if (!url) return url;

        // If URL is already absolute (starts with http:// or https://), return as is
        if (/^https?:\/\//i.test(url)) {
            return url;
        }

        try {
            // Use URL constructor to resolve relative URLs
            const base = new URL(baseUrl);
            const resolved = new URL(url, base);
            return resolved.href;
        } catch (error) {
            // If URL parsing fails, return original URL
            console.error('Error resolving URL:', error);
            return url;
        }
    }

    // Helper function to update image preview
    function updateImagePreview(imageUrl: string, currentPageUrl: string): void {
        if (!imagePreviewContainer || !imagePreview || !imageUrlInput || !imageUrlLabel) return;

        const absoluteImageUrl = makeAbsoluteUrl(imageUrl, currentPageUrl);

        if (absoluteImageUrl) {
            // Show preview, hide input and label
            imagePreview.src = absoluteImageUrl;
            imagePreviewContainer.style.display = 'block';
            imageUrlInput.style.display = 'none';
            imageUrlInput.value = absoluteImageUrl;
            imageUrlLabel.style.display = 'none';
        } else {
            // Hide preview, keep input hidden (only shown via manual button)
            imagePreviewContainer.style.display = 'none';
            imageUrlInput.style.display = 'none';
            imageUrlLabel.style.display = 'none';
        }
    }

    // Helper function to store URL
    function updateUrlDisplay(url: string): void {
        currentUrl = url;
    }

    // Helper function to show API host required error
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
            // Get the active tab
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
                    // Auto-fill the form
                    if (titleInput) titleInput.value = pageData.title || '';
                    // Update URL display and store URL
                    updateUrlDisplay(pageData.url || '');
                    // Prefill with image (which may contain og:image or favicon as fallback)
                    if (pageData.image) {
                        updateImagePreview(pageData.image, pageData.url || '');
                    } else {
                        // Hide preview, input, and label if no image
                        if (imagePreviewContainer) imagePreviewContainer.style.display = 'none';
                        if (imageUrlInput) imageUrlInput.style.display = 'none';
                        if (imageUrlLabel) imageUrlLabel.style.display = 'none';
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

    // Fetch user tags when popup opens
    async function loadUserTags(): Promise<void> {
        try {
            userTags = await fetchUserTags();
            console.log(`Loaded ${userTags.length} user tags`);
            // Update tom-select options if it's already initialized
            if (tagsSelect) {
                updateTomSelectOptions();
            }
        } catch (error) {
            console.error('Error fetching user tags:', error);
            // Check if it's an API host configuration error
            const errorMessage = error instanceof Error ? error.message : 'Unknown error';
            if (errorMessage.includes('API host not configured')) {
                showApiHostRequiredError();
            } else if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                showLoginRequiredError();
            }
            // Don't initialize here - let waitForTomSelectAndInitialize handle it
        }
    }

    // Archive the current page and get the archived file object ID
    async function archivePage(): Promise<string | null> {
        const runtime = getBrowserRuntime();
        const tabs = getBrowserTabs();

        if (!runtime || !tabs) {
            console.error('Browser APIs not available');
            return null;
        }

        try {
            // Get the active tab
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

            // Send archive page message and wait for response
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

    // Update tom-select options with current userTags
    function updateTomSelectOptions(): void {
        if (!tagsSelect) return;

        // Prepare options from userTags (using formatted name with icon and public indicator)
        const options = userTags.map(tag => ({
            value: tag.name,
            text: formatTagName(tag),
            icon: tag.icon
        }));

        // Clear existing options and add new ones
        tagsSelect.clearOptions();
        tagsSelect.addOptions(options);
    }

    // Initialize tom-select with plugins and options
    function initializeTomSelect(): void {
        if (!tagsSelectElement) {
            console.error('Tags select element not found');
            return;
        }

        if (typeof TomSelect === 'undefined') {
            // Wait a bit for TomSelect to load if it's not ready yet
            setTimeout(() => {
                initializeTomSelect();
            }, 100);
            return;
        }

        // Destroy existing instance if any
        if (tagsSelect) {
            tagsSelect.destroy();
            tagsSelect = null;
        }

        // Prepare options from userTags (using formatted name with icon and public indicator)
        const options = userTags.map(tag => ({
            value: tag.name,
            text: formatTagName(tag),
            icon: tag.icon
        }));

        // Initialize tom-select with plugins and options
        tagsSelect = new TomSelect(tagsSelectElement, {
            plugins: ['remove_button', 'restore_on_backspace', 'clear_button'],
            options: options,
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            create: true, // allow create
            placeholder: 'Select or create tags',
            maxItems: null,
            maxOptions: null, // Show all available options
            closeAfterSelect: true, // Close dropdown after selecting a tag
            render: {
                option: function(data: any, escape: (str: string) => string) {
                    // Text already includes icon and public indicator from formatTagName
                    return `<div class="ts-option-text">${escape(data.text)}</div>`;
                },
                item: function(data: any, escape: (str: string) => string) {
                    // Text already includes icon and public indicator from formatTagName
                    return `<div class="ts-item-text">${escape(data.text)}</div>`;
                }
            },
            onItemAdd: function() {
                // Clear the input field after adding an item
                this.setTextboxValue('');
                // Log selected tags to console
                const selectedValues = this.getValue();
                console.log('Selected tags:', selectedValues);
            },
            onItemRemove: function() {
                // Log selected tags to console
                const selectedValues = this.getValue();
                console.log('Selected tags:', selectedValues);
            }
        });
    }

    // Wait for TomSelect library to load, then initialize
    function waitForTomSelectAndInitialize(): void {
        if (typeof TomSelect !== 'undefined') {
            // TomSelect is loaded, wait for tags to load then initialize
            loadUserTags().then(() => {
                initializeTomSelect();
            }).catch(() => {
                // Initialize even if tags fail to load
                initializeTomSelect();
            });
        } else {
            // Wait a bit more for TomSelect to load
            setTimeout(() => {
                waitForTomSelectAndInitialize();
            }, 100);
        }
    }

    // Handle close image button click
    if (manualImageButton) {
        manualImageButton.addEventListener('click', () => {
            if (imagePreviewContainer && imageUrlInput && imageUrlLabel) {
                // Hide preview, show input and label, keep current URL value
                imagePreviewContainer.style.display = 'none';
                imageUrlInput.style.display = 'block';
                imageUrlInput.type = 'text';
                imageUrlLabel.style.display = 'block';
                // Keep the current URL value (don't clear it)
                // The value is already set from updateImagePreview
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

            // Ensure image URL is absolute
            if (imageUrl) {
                imageUrl = makeAbsoluteUrl(imageUrl, url);
            }

            if (!title || !url) {
                const errorMsg = 'Please fill in title and URL';
                console.error(errorMsg);
                showStatus(errorMsg, 'error');
                return;
            }

            // Disable submit button during request
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
            const stopInitialSavingAnimation = startClockAnimation(submitButton, 'Saving...');

            try {
                // Get API host
                const apiHost = await getAPIHost();
                if (!apiHost) {
                    stopInitialSavingAnimation();
                    showApiHostRequiredError();
                    return;
                }

                // Get JWT token for authentication
                const token = await getJWTToken();
                if (!token) {
                    stopInitialSavingAnimation();
                    showLoginRequiredError();
                    return;
                }

                // Get selected tags from tom-select
                const selectedTagNames: string[] = tagsSelect ? tagsSelect.getValue() : [];
                let tagIRIs: string[] = [];

                // Ensure all selected tags exist and get their IRIs
                if (selectedTagNames.length > 0) {
                    stopInitialSavingAnimation();
                    submitButton.textContent = 'Creating tags...';
                    try {
                        tagIRIs = await ensureTagsExist(selectedTagNames, userTags);
                        // Update tom-select options to include newly created tags
                        updateTomSelectOptions();
                    } catch (error) {
                        // Check if it's an authentication error
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

                // Upload image if provided
                let mainImageId: string = '';
                if (imageUrl) {
                    try {
                        submitButton.textContent = 'Uploading image...';

                        // Fetch the image as a blob
                        const imageResponse = await fetch(imageUrl);
                        if (!imageResponse.ok) {
                            throw new Error(`Failed to fetch image: ${imageResponse.status}`);
                        }

                        const imageBlob = await imageResponse.blob();

                        // Upload the image file
                        const fileObject = await uploadFileObject(imageBlob);
                        mainImageId = fileObject['@iri'];

                        console.log('Image uploaded successfully:', mainImageId);
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        // Check if it's an authentication error
                        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                        if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                            stopInitialSavingAnimation();
                            showLoginRequiredError();
                            return;
                        }
                        // Continue without image if upload fails (for other errors)
                        showStatus(`Warning: Failed to upload image: ${errorMessage}`, 'error');
                        // Don't throw - allow bookmark creation without image
                    }
                }

                submitButton.textContent = 'Archiving page...';
                const stopArchivingAnimation = startClockAnimation(submitButton, 'Archiving page...');

                // Archive the page and get the archived file object ID
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

                    // Stop the "Saving bookmark..." animation before showing success
                    if (stopSavingAnimation) {
                        stopSavingAnimation();
                    }
                    submitButton.textContent = 'Bookmark saved!';

                    showStatus('Bookmark saved successfully!', 'success');

                    // Hide the form
                    if (bookmarkForm) {
                        bookmarkForm.style.display = 'none';
                    }

                    // Close popup after 1 second
                    setTimeout(() => {
                        window.close();
                    }, 1000);
                } catch (error) {
                    // Stop animation if it's running
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
                // Stop initial animation if still running
                stopInitialSavingAnimation();

                // Check if it's a login/authentication error
                const errorMessage = error instanceof Error ? error.message : 'Unknown error occurred';
                if (errorMessage.includes('No authentication token found') || errorMessage.includes('401') || errorMessage.includes('Unauthorized')) {
                    showLoginRequiredError();
                    return;
                }

                console.error('Error saving bookmark:', error);
                showStatus(`Error: ${errorMessage}`, 'error');
            } finally {
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.textContent = 'Save Bookmark';
            }
        });
    }

    // Handle options link click
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

    // Helper function to show login required error
    function showLoginRequiredError(): void {
        if (!statusMessage) return;
        const runtime = getBrowserRuntime();
        showLoginRequiredErrorLib(statusMessage, runtime);
    }

    // Helper function to show status message
    function showStatus(message: string, type: 'success' | 'error', keepOpen: boolean = false): void {
        if (!statusMessage) return;
        showStatusLib(statusMessage, message, type, keepOpen);
    }
});

