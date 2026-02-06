# Passkeys Setup Guide

## Quick Start

### 1. Build Frontend Assets

The passkeys JavaScript needs to be compiled:

```bash
# Install dependencies
npm install

# Build assets (production)
npm run build

# OR run in development mode
npm run dev
```

### 2. Setup Environment

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your APP_URL in .env
# IMPORTANT: Must match your actual domain for passkeys to work
APP_URL=http://localhost:8000
```

### 3. Run Database Migrations

```bash
php artisan migrate
```

### 4. Start the Application

```bash
php artisan serve
```

## Testing Passkeys

1. Open your browser and navigate to the application
2. Log in to your account
3. Go to **Profile** (click your avatar → Profile)
4. Click the **"Passkeys"** tab
5. Enter a name for your passkey (e.g., "My Laptop")
6. Click **"Register New Passkey"**
7. Your browser will show a biometric/PIN prompt
8. Complete the authentication
9. The passkey should appear in the list!

## Troubleshooting

### "Nothing happens when I click Register"

**Problem**: JavaScript not loaded
**Solution**: Run `npm run build` or `npm run dev`

### "Failed to get registration options"

**Problem**: Routes not registered or server not running
**Solution**: 
- Check `php artisan route:list | grep passkey` shows the routes
- Make sure `php artisan serve` is running

### "Registration cancelled or not allowed"

**Problem**: Browser WebAuthn not available or HTTPS required
**Solutions**:
- Use Chrome, Firefox, Safari, or Edge (latest versions)
- For local dev, use `localhost` (HTTPS not required)
- For production, HTTPS is mandatory

### "Invalid state error"

**Problem**: Passkey already registered
**Solution**: This specific credential is already registered, try a different one

## Browser Compatibility

Passkeys work on:
- ✅ Chrome/Edge 67+
- ✅ Firefox 60+
- ✅ Safari 13+
- ✅ Mobile browsers (iOS 14+, Android)

## How It Works

1. **Frontend** (`resources/js/passkeys.js`):
   - Listens for Livewire events
   - Calls WebAuthn browser API
   - Handles credential creation

2. **Backend** (`app/Http/Controllers/Auth/PasskeyController.php`):
   - Generates challenges
   - Validates credentials
   - Stores passkeys in database

3. **Routes** (`routes/auth.php`):
   - `/passkeys/register/options` - Get challenge
   - `/passkeys/register` - Store passkey

## Security Notes

- This is a basic WebAuthn implementation
- In production, consider adding full attestation verification
- Challenge validation should be strengthened
- Consider implementing passkey authentication (login) flow

## Files Modified

- `resources/js/passkeys.js` - WebAuthn client JavaScript
- `app/Http/Controllers/Auth/PasskeyController.php` - Backend controller
- `app/Filament/Pages/Auth/EditProfile.php` - Profile page with passkeys tab
- `routes/auth.php` - Passkey endpoints
- `app/Models/Passkey.php` - Passkey model
- `database/migrations/2026_02_01_030000_create_passkeys_table.php` - Database schema
- `lang/en/profile.php` - Translations

## Next Steps

After passkeys work:
- [ ] Test on different browsers
- [ ] Test on mobile devices
- [ ] Implement passkey authentication (login without password)
- [ ] Add security hardening for production
- [ ] Consider adding passkey backup/recovery flow
