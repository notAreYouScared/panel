/**
 * Passkeys (WebAuthn) Registration Handler
 * 
 * Listens for Livewire events to register passkeys using the WebAuthn API
 */

// Helper to convert base64url to ArrayBuffer
function base64urlToArrayBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

// Helper to convert ArrayBuffer to base64url
function arrayBufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    const base64 = btoa(binary);
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Register passkey
async function registerPasskey(name) {
    try {
        // Step 1: Get registration options from server
        const optionsResponse = await fetch('/passkeys/register/options', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ name: name || 'My Passkey' })
        });

        if (!optionsResponse.ok) {
            throw new Error('Failed to get registration options');
        }

        const options = await optionsResponse.json();

        // Step 2: Convert options for WebAuthn API
        const publicKeyOptions = {
            ...options,
            challenge: base64urlToArrayBuffer(options.challenge),
            user: {
                ...options.user,
                id: base64urlToArrayBuffer(options.user.id),
            },
            excludeCredentials: options.excludeCredentials?.map(cred => ({
                ...cred,
                id: base64urlToArrayBuffer(cred.id),
            })) || [],
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
            rawId: arrayBufferToBase64url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: arrayBufferToBase64url(credential.response.clientDataJSON),
                attestationObject: arrayBufferToBase64url(credential.response.attestationObject),
            },
        };

        // Step 5: Send credential to server
        const registerResponse = await fetch('/passkeys/register', {
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
            const error = await registerResponse.json();
            throw new Error(error.message || 'Failed to register passkey');
        }

        // Success!
        window.dispatchEvent(new CustomEvent('passkey-registered', {
            detail: { name: name }
        }));

        // Show success notification
        if (window.Livewire) {
            window.Livewire.dispatch('notify', {
                type: 'success',
                message: 'Passkey registered successfully!'
            });
        }

        // Reload the page to show the new passkey
        window.location.reload();

    } catch (error) {
        console.error('Passkey registration failed:', error);
        
        // Show error notification
        if (window.Livewire) {
            window.Livewire.dispatch('notify', {
                type: 'error',
                message: error.message || 'Failed to register passkey. Please try again.'
            });
        } else {
            alert('Failed to register passkey: ' + (error.message || 'Unknown error'));
        }
    }
}

// Listen for Livewire events
if (window.Livewire) {
    // For Livewire v3
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('passkey-register', (event) => {
            const name = event.name || event[0]?.name || 'My Passkey';
            registerPasskey(name);
        });
    });
} else {
    // Fallback: wait for Livewire to load
    document.addEventListener('DOMContentLoaded', () => {
        const checkLivewire = setInterval(() => {
            if (window.Livewire) {
                clearInterval(checkLivewire);
                Livewire.on('passkey-register', (event) => {
                    const name = event.name || event[0]?.name || 'My Passkey';
                    registerPasskey(name);
                });
            }
        }, 100);
    });
}

// Also expose globally for manual testing
window.registerPasskey = registerPasskey;
