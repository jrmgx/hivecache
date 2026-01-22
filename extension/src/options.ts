// Options page script for the extension

import { createBrowserStorageAdapter } from '@shared';
import { createApiClient } from '@shared';

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm') as HTMLFormElement | null;
    const loggedInState = document.getElementById('loggedInState') as HTMLElement | null;
    const instanceUrlInput = document.getElementById('instanceUrl') as HTMLInputElement | null;
    const usernameInput = document.getElementById('username') as HTMLInputElement | null;
    const passwordInput = document.getElementById('password') as HTMLInputElement | null;
    const loginButton = document.getElementById('loginButton') as HTMLButtonElement | null;
    const logoutButton = document.getElementById('logoutButton') as HTMLButtonElement | null;
    const statusMessage = document.getElementById('statusMessage') as HTMLElement | null;
    const loggedInMessage = document.getElementById('loggedInMessage') as HTMLElement | null;

    const storageAdapter = createBrowserStorageAdapter();

    // Check authentication status and load saved instance URL on page load
    checkAuthStatus();

    // Handle form submission
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await authenticate();
        });
    }

    // Handle logout button
    if (logoutButton) {
        logoutButton.addEventListener('click', async () => {
            await logout();
        });
    }

    // Check authentication status and show appropriate UI
    async function checkAuthStatus(): Promise<void> {
        try {
            const token = await storageAdapter.getToken();
            const instanceUrl = await storageAdapter.getBaseUrl();

            if (token) {
                // User is logged in - show logged in state
                showLoggedInState(instanceUrl || undefined);
            } else {
                // User is not logged in - show login form
                showLoginForm();
                // Load saved instance URL if available
                if (instanceUrl && instanceUrlInput) {
                    instanceUrlInput.value = instanceUrl;
                }
            }
        } catch (error) {
            console.error('Error checking auth status:', error);
            showLoginForm();
        }
    }

    // Show logged in state
    function showLoggedInState(instanceUrl?: string): void {
        if (loggedInState) {
            loggedInState.classList.add('show');
        }
        if (loginForm) {
            loginForm.classList.add('hide');
        }
        // Update message with instance URL if available
        if (loggedInMessage) {
            if (instanceUrl) {
                loggedInMessage.textContent = `You are currently logged in to your HiveCache instance: ${instanceUrl}`;
            } else {
                loggedInMessage.textContent = 'You are currently logged in to your HiveCache instance.';
            }
        }
    }

    // Show login form
    function showLoginForm(): void {
        if (loggedInState) {
            loggedInState.classList.remove('show');
        }
        if (loginForm) {
            loginForm.classList.remove('hide');
        }
    }

    // Authenticate and get JWT token
    async function authenticate(): Promise<void> {
        if (!instanceUrlInput || !usernameInput || !passwordInput) {
            showStatus('Form fields not found', 'error');
            return;
        }

        const instanceUrl = instanceUrlInput.value.trim();
        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        // Validate inputs
        if (!instanceUrl) {
            showStatus('Please enter an instance URL', 'error');
            return;
        }

        if (!username || !password) {
            showStatus('Please enter both username and password', 'error');
            return;
        }

        // Validate URL format and add https:// if no protocol
        let normalizedUrl: string;
        try {
            normalizedUrl = instanceUrl;
            // Add https:// if no protocol is provided
            if (!/^https?:\/\//i.test(normalizedUrl)) {
                normalizedUrl = `https://${normalizedUrl}`;
            }
            normalizedUrl = normalizedUrl.replace(/\/$/, '');
            new URL(normalizedUrl);
        } catch (error) {
            showStatus('Please enter a valid URL (e.g., https://hivecache.test)', 'error');
            return;
        }

        // Disable form inputs during request
        if (loginButton) {
            loginButton.disabled = true;
            loginButton.textContent = 'Logging in...';
        }
        instanceUrlInput.disabled = true;
        usernameInput.disabled = true;
        passwordInput.disabled = true;

        try {
            // Save instance URL first (like the client does)
            await storageAdapter.setBaseUrl(normalizedUrl);

            // Create API client with the new base URL
            const client = createApiClient({
                baseUrl: normalizedUrl,
                storage: storageAdapter,
                enableCache: true,
            });

            // Authenticate and get token (automatically saved by the storage adapter via the API client)
            await client.login(username, password);
            showStatus('Authentication successful! Token saved securely.', 'success');

            // Clear password field for security
            passwordInput.value = '';

            // Switch to logged in state
            showLoggedInState(normalizedUrl);
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Unknown error occurred';
            showStatus(`Authentication failed: ${errorMessage}`, 'error');
        } finally {
            // Re-enable form inputs
            if (loginButton) {
                loginButton.disabled = false;
                loginButton.textContent = 'Login';
            }
            instanceUrlInput.disabled = false;
            usernameInput.disabled = false;
            passwordInput.disabled = false;
        }
    }

    // Logout and clear all stored data
    async function logout(): Promise<void> {
        if (!logoutButton) return;

        // Disable logout button during process
        logoutButton.disabled = true;
        logoutButton.textContent = 'Logging out...';

        try {
            // Clear token
            await storageAdapter.clearToken();

            // Clear instance URL as well (to fully reset)
            await storageAdapter.setBaseUrl('');

            // Switch back to login form
            showLoginForm();

            // Clear form fields
            if (instanceUrlInput) instanceUrlInput.value = '';
            if (usernameInput) usernameInput.value = '';
            if (passwordInput) passwordInput.value = '';

            showStatus('Logged out successfully. All stored data has been cleared.', 'success');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Unknown error occurred';
            showStatus(`Logout failed: ${errorMessage}`, 'error');
        } finally {
            // Re-enable logout button
            if (logoutButton) {
                logoutButton.disabled = false;
                logoutButton.textContent = 'Logout';
            }
        }
    }

    // Show status message
    function showStatus(message: string, type: 'success' | 'error'): void {
        if (!statusMessage) return;

        statusMessage.textContent = message;
        statusMessage.className = `status-message ${type} show`;

        setTimeout(() => {
            statusMessage.classList.remove('show');
        }, 3000);
    }
});

