# Quick Setup: Passkeys

## Step 1: Install the Package

```bash
composer require spatie/laravel-passkeys:^1.5
```

## Step 2: Run Migrations

```bash
php artisan migrate
```

## Step 3: Clear Cache (Optional but Recommended)

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

## Step 4: Test It!

1. Log in to your account
2. Go to **Profile** (click your avatar → Profile)
3. Click the **"Passkeys"** tab
4. Click **"Register New Passkey"**
5. Follow your browser's prompts to create a passkey
6. Done! Your passkey is now registered

## Using Passkeys to Login

- Next time you login, your browser will offer to use your passkey
- Select your passkey and authenticate (fingerprint, face, PIN, etc.)
- You'll be logged in without typing a password!

## Requirements

- HTTPS enabled (or localhost for development)
- Modern browser (Chrome 67+, Firefox 60+, Safari 13+, Edge 18+)
- `APP_URL` in `.env` must match your actual domain

## Troubleshooting

**Passkeys tab doesn't appear:**
- Make sure the package is installed: `composer show spatie/laravel-passkeys`
- Clear cache: `php artisan cache:clear`

**Registration fails:**
- Check `APP_URL` in `.env` matches your domain exactly
- Make sure you're using HTTPS
- Try a different browser

**More Help:**
See `PASSKEYS.md` for detailed documentation.
