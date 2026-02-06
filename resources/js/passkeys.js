/**
 * Passkeys (WebAuthn) Registration Handler
 * 
 * Listens for Livewire events to register passkeys using the WebAuthn API
 */

// Helper to convert base64url to Uint8Array
function base64urlToUint8Array(base64url) {
    // Add padding if needed
    const padding = '='.repeat((4 - (base64url.length % 4)) % 4);
    const base64 = (base64url + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Helper to convert Uint8Array to base64url
function uint8ArrayToBase64url(uint8Array) {
    let binary = '';
    const len = uint8Array.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(uint8Array[i]);
    }
    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

// Register passkey
async function registerPasskey(name) {
    try {
        // Check if WebAuthn is supported
        if (!window.PublicKeyCredential) {
            throw new Error('WebAuthn is not supported in this browser. Please use a modern browser like Chrome, Firefox, Safari, or Edge.');
        }

        // Check if we're in a secure context (HTTPS or localhost)
        if (!window.isSecureContext) {
            throw new Error('WebAuthn requires a secure context (HTTPS). Please access this site over HTTPS.');
        }

        // Check if credentials API is available
        if (!navigator.credentials) {
            throw new Error('Credentials API is not available in this browser.');
        }

        // Step 1: Get registration options from server
        const optionsResponse = await fetch('/auth/passkeys/register/options', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ name: name || 'My Passkey' })
        });

        if (!optionsResponse.ok) {
            const errorData = await optionsResponse.json().catch(() => ({}));
            throw new Error(errorData.message || 'Failed to get registration options');
        }

        const options = await optionsResponse.json();

        // Step 2: Convert options for WebAuthn API
        const publicKeyOptions = {
            challenge: base64urlToUint8Array(options.challenge),
            rp: options.rp,
            user: {
                id: base64urlToUint8Array(options.user.id),
                name: options.user.name,
                displayName: options.user.displayName,
            },
            pubKeyCredParams: options.pubKeyCredParams,
            timeout: options.timeout,
            attestation: options.attestation,
            authenticatorSelection: options.authenticatorSelection,
            excludeCredentials: (options.excludeCredentials || []).map(cred => ({
                type: cred.type,
                id: base64urlToUint8Array(cred.id),
            })),
        };

        // Step 3: Create credential using WebAuthn
        const credential = await navigator.credentials.create({
            publicKey: publicKeyOptions
        });

        if (!credential) {
            throw new Error('Failed to create credential');
        }

        // Step 4: Prepare credential data for server
        const credentialData = {
            id: credential.id,
            rawId: uint8ArrayToBase64url(new Uint8Array(credential.rawId)),
            type: credential.type,
            response: {
                clientDataJSON: uint8ArrayToBase64url(new Uint8Array(credential.response.clientDataJSON)),
                attestationObject: uint8ArrayToBase64url(new Uint8Array(credential.response.attestationObject)),
            },
        };

        // Step 5: Send credential to server
        const registerResponse = await fetch('/auth/passkeys/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                name: name || 'My Passkey',
                credential: credentialData
            })
        });

        if (!registerResponse.ok) {
            const errorData = await registerResponse.json().catch(() => ({}));
            throw new Error(errorData.message || 'Failed to register passkey');
        }

        const result = await registerResponse.json();

        // Success!
        console.log('Passkey registered successfully:', result);

        // Show success notification via Filament
        if (window.$wire) {
            window.$wire.call('$dispatch', 'notify', {
                type: 'success',
                message: 'Passkey registered successfully!'
            });
        }

        // Reload the page to show the new passkey in the list
        setTimeout(() => {
            window.location.reload();
        }, 1000);

    } catch (error) {
        console.error('Passkey registration failed:', error);
        
        let errorMessage = 'Failed to register passkey. Please try again.';
        
        // Provide specific error messages for common issues
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Passkey registration was cancelled or not allowed.';
        } else if (error.name === 'InvalidStateError') {
            errorMessage = 'This passkey is already registered.';
        } else if (error.name === 'NotSupportedError') {
            errorMessage = 'Your browser does not support passkeys. Please use Chrome 67+, Firefox 60+, Safari 13+, or Edge 18+.';
        } else if (error.name === 'SecurityError') {
            errorMessage = 'Security error: Passkeys require HTTPS (or localhost for testing).';
        } else if (error.message) {
            errorMessage = error.message;
        }

        // Show error notification via Filament
        if (window.$wire) {
            window.$wire.call('$dispatch', 'notify', {
                type: 'error',
                message: errorMessage
            });
        } else {
            alert(errorMessage);
        }
    }
}

// Listen for Livewire events
document.addEventListener('DOMContentLoaded', () => {
    // Wait for Livewire to be ready
    const initPasskeys = () => {
        if (window.Livewire) {
            Livewire.on('passkey-register', (data) => {
                // Handle both event formats
                const name = (typeof data === 'object' && data.name) || 
                           (Array.isArray(data) && data[0]?.name) || 
                           'My Passkey';
                registerPasskey(name);
            });
            console.log('Passkeys listener initialized');
        } else {
            setTimeout(initPasskeys, 100);
        }
    };
    
    initPasskeys();
});

// Also expose globally for manual testing
window.registerPasskey = registerPasskey;
