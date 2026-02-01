# Passkeys Implementation

This implementation adds passkey authentication to Pelican Panel without using the `marcelweidum/filament-passkeys` plugin. Instead, it uses the underlying `spatie/laravel-passkeys` package directly and provides custom Filament integration.

## What Are Passkeys?

Passkeys let users sign in securely without passwords using biometrics (fingerprint, Face ID), PINs, or security keys. They use WebAuthn/FIDO2 technology and are more secure than passwords because they:
- Cannot be phished
- Are unique to each website
- Cannot be reused across sites
- Cannot be stolen in data breaches

## Installation

### 1. Install the Package

Due to CI environment limitations, the package needs to be installed manually:

```bash
composer require spatie/laravel-passkeys
```

### 2. Run Migrations

The passkeys table migration is already included in this PR:

```bash
php artisan migrate
```

### 3. Configuration

The routes are already configured in `routes/auth.php` and will automatically activate once the package is installed.

## Features Implemented

### ✅ Database Schema
- `passkeys` table with all necessary fields:
  - `credential_id`: Unique identifier for the passkey
  - `public_key_data`: The public key data
  - `name`: User-friendly name for the passkey
  - `counter`: Signature counter for replay protection
  - `last_used_at`: Timestamp of last use
  - `transports`: Supported authenticator transports

### ✅ Models & Relationships
- `Passkey` model with full relationships
- `User->passkeys()` relationship for accessing user's passkeys
- Proper model casts and accessors

### ✅ Filament Integration
- New "Passkeys" tab in user profile page
- Register new passkeys with custom name
- View all registered passkeys with creation date and last used timestamp
- Delete passkeys with confirmation
- Activity logging for passkey operations
- Responsive grid layout

### ✅ Security Features
- Passkey registration protected by authentication
- Activity logs for passkey creation and deletion
- Confirmation required for passkey deletion
- Only shows tab when package is available

### ✅ UI/UX
- Intuitive two-column layout:
  - Left: Registration form
  - Right: Passkey management list
- Fingerprint icon for passkeys tab
- Helpful descriptions and placeholders
- Consistent with existing Pelican Panel design

### ✅ Translations
- Complete English translations
- Ready for internationalization (can be copied to other language files)
- Translation keys:
  - `profile.tabs.passkeys`
  - `profile.register_passkey*`
  - `profile.passkey_*`
  - `profile.existing_passkeys`
  - And more...

### ✅ Routes
- Conditional route registration (only when package is installed)
- POST `/passkeys/register/options` - Get registration options
- POST `/passkeys/register` - Complete registration
- POST `/passkeys/authenticate/options` - Get authentication options
- POST `/passkeys/authenticate` - Complete authentication

## How It Works

### Registration Flow
1. User navigates to Profile → Passkeys tab
2. User enters a name for their passkey (e.g., "My Laptop")
3. User clicks "Register New Passkey"
4. Browser prompts for biometric/security key
5. Passkey is registered and saved to database
6. Activity log records the event

### Authentication Flow
1. User goes to login page
2. If passkeys exist, browser offers passkey login
3. User selects their passkey or uses biometric
4. Authenticated without password

### Management
- View all registered passkeys in the profile
- See when each was created and last used
- Delete passkeys with a single click (with confirmation)

## Files Modified/Created

### New Files
- `app/Models/Passkey.php` - Passkey model
- `app/Filament/Plugins/PasskeysPlugin.php` - Filament plugin class
- `database/migrations/2026_02_01_030000_create_passkeys_table.php` - Database schema

### Modified Files
- `app/Models/User.php` - Added passkeys relationship
- `app/Filament/Pages/Auth/EditProfile.php` - Added passkeys tab
- `lang/en/profile.php` - Added passkey translations
- `routes/auth.php` - Added passkey routes
- `composer.json` - Added spatie/laravel-passkeys dependency

## Testing

After installing the package and running migrations:

1. **Test Registration**
   ```
   - Go to Profile → Passkeys tab
   - Enter a name like "Test Passkey"
   - Click "Register New Passkey"
   - Complete browser's biometric prompt
   - Verify passkey appears in the list
   ```

2. **Test Authentication**
   ```
   - Log out
   - Go to login page
   - Browser should offer passkey login
   - Use your passkey to login
   - Verify successful authentication
   ```

3. **Test Deletion**
   ```
   - Go to Profile → Passkeys tab
   - Click delete icon on a passkey
   - Confirm deletion
   - Verify passkey is removed
   - Check activity log for deletion event
   ```

## Browser Compatibility

Passkeys work on:
- ✅ Chrome/Edge 67+
- ✅ Firefox 60+
- ✅ Safari 13+
- ✅ Android with Chrome
- ✅ iOS/iPadOS 14+ with Safari

## Security Considerations

- Passkeys use public-key cryptography - private keys never leave the device
- Each passkey is unique to your domain (configured via `APP_URL`)
- Counter values prevent replay attacks
- HTTPS is required for passkeys to work (except localhost)

## Troubleshooting

### Passkeys tab doesn't appear
- Make sure `spatie/laravel-passkeys` is installed
- Clear your application cache: `php artisan cache:clear`

### Registration fails
- Check that `APP_URL` in `.env` matches your actual domain
- Ensure you're using HTTPS (or localhost for development)
- Verify browser supports WebAuthn

### Can't delete passkey
- Check user permissions
- Verify activity logging is configured correctly

## Future Enhancements

Possible improvements:
- [ ] Add passkey login button to login page UI
- [ ] Support passwordless-only accounts
- [ ] Add passkey usage statistics
- [ ] Allow renaming passkeys
- [ ] Export/backup passkeys
- [ ] Passkey recovery flow

## Credits

- Based on [spatie/laravel-passkeys](https://github.com/spatie/laravel-passkeys)
- Inspired by [marcelweidum/filament-passkeys](https://github.com/marcelweidum/filament-passkeys)
- WebAuthn specification: [W3C WebAuthn](https://www.w3.org/TR/webauthn/)

## License

Same license as Pelican Panel (AGPL-3.0-only)
