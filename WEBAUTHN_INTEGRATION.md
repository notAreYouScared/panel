# WebAuthn Integration Notes

## How Passkey Registration Works

The passkey registration flow has two parts:

### 1. Backend (Already Implemented)
- Routes in `routes/auth.php` provide WebAuthn endpoints
- `Passkey` model stores credential data
- Activity logging tracks operations
- Profile UI triggers the flow

### 2. Frontend (Provided by spatie/laravel-passkeys)

The `spatie/laravel-passkeys` package includes JavaScript that:

1. Listens for Livewire `passkey-register` event
2. Calls `/passkeys/register/options` to get challenge from server
3. Invokes browser's `navigator.credentials.create()` WebAuthn API
4. Sends signed response to `/passkeys/register` endpoint
5. Server validates and stores the passkey

This JavaScript is automatically included when you install the package via its service provider.

## Key Points

- **No manual JavaScript needed** - The package handles it
- **Browser prompts user** - Native biometric/security key UI
- **Server validates everything** - FIDO2/WebAuthn protocol
- **Works automatically** - Once package is installed

## For Developers

If you need to customize the JavaScript behavior, you can:

1. Publish the package assets:
   ```bash
   php artisan vendor:publish --tag="passkeys-assets"
   ```

2. Modify the JavaScript in your `resources/js` directory

3. Listen for custom events:
   ```javascript
   // Custom registration handling
   Livewire.on('passkey-register', (data) => {
       // Your custom logic here
   });
   ```

But for most use cases, the default behavior works perfectly!

## Security Flow

```
User Action → Livewire Dispatch → JavaScript Handler → Browser WebAuthn API
                                                              ↓
Server Validates ← HTTP Response ← Signed Credential ←  User Confirms
```

The private key NEVER leaves the user's device. Only public key data is sent to the server.

## References

- [Spatie Laravel Passkeys Docs](https://spatie.be/docs/laravel-passkeys)
- [WebAuthn Specification](https://www.w3.org/TR/webauthn/)
- [FIDO Alliance](https://fidoalliance.org/)
