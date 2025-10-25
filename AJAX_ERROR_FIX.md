# AJAX Error Fix - PHP Output in Response

## The Error

```
$org = 'mars';
$name = 'mpvg';
```

This appeared in the AJAX response instead of the expected JSON/HTML.

## Root Cause

Your `.env.inc` files were missing the `<?php` opening tag, causing PHP to output them as plain text instead of executing them as code.

## The Fix

Added output buffering to prevent any accidental output from included files:

```php
if (is_file($inc)) {
  // Use output buffering to prevent any output from the included file
  ob_start();
  include $inc;
  ob_end_clean();
}
```

**What this does:**
- `ob_start()` - Start capturing all output
- `include $inc` - Include the file (any output goes to buffer)
- `ob_end_clean()` - Discard the buffer contents

## Proper .env.inc File Format

Your environment files **MUST** start with `<?php`:

### ❌ Wrong (causes the error):
```
$org = 'mars';
$name = 'mpvg';
```

### ✅ Correct:
```php
<?php

/**
 * @file
 * Development environment configuration.
 */

$org = 'mars';
$name = 'mpvg';
```

## Example Files Included

I've included example configuration files in the `config/` directory:

- `config/dev.env.inc.example`
- `config/staging.env.inc.example`
- `config/prod.env.inc.example`
- `config/.gitignore` (protects real files)
- `config/README.md` (instructions)

## Setup Your Environment Files

1. **Copy the examples:**
   ```bash
   cd modules/custom/cmesh_push_content/config
   cp dev.env.inc.example dev.env.inc
   cp staging.env.inc.example staging.env.inc
   cp prod.env.inc.example prod.env.inc
   ```

2. **Edit each file:**
   ```bash
   nano dev.env.inc
   ```

3. **Ensure proper format:**
   - First line must be: `<?php`
   - No whitespace before `<?php`
   - Set variables, don't echo them
   - No closing `?>` needed

4. **Validate syntax:**
   ```bash
   php -l dev.env.inc
   # Should say: No syntax errors detected
   ```

## How It Works Now

1. User clicks "Push to dev" button
2. Form looks for `config/dev.env.inc`
3. File is included with output buffering
4. Variables `$org` and `$name` are set
5. Command is built: `/opt/cmesh/scripts/pushfin.sh -o 'mars' -n 'mpvg'`
6. Command executes in background
7. Output appears in real-time

## Dynamic Buttons

The module automatically creates buttons for each `.env.inc` file:

- `dev.env.inc` → "Push to dev" button
- `staging.env.inc` → "Push to staging" button
- `prod.env.inc` → "Push to prod" button
- `my-env.env.inc` → "Push to my-env" button

## Security Notes

1. **Don't commit real `.env.inc` files!**
   - They're in `.gitignore`
   - Only commit `.example` files

2. **Restrict permissions:**
   ```bash
   chmod 600 config/*.env.inc
   ```

3. **Keep sensitive data secure:**
   - These files might contain credentials
   - Only readable by web server user

## Troubleshooting

### Button doesn't appear
- Check file exists: `ls -la config/*.env.inc`
- Check file has `.env.inc` extension
- Clear cache: `drush cr`

### Still getting PHP output in response
- Check file starts with `<?php`
- No whitespace before `<?php`
- Validate syntax: `php -l config/dev.env.inc`

### Variables not being set
- Check variable names match: `$org` and `$name`
- Check file is being included (add debug message)
- Verify file path is correct

### Command not found
- Check: `/opt/cmesh/scripts/pushfin.sh` exists
- Check: Script is executable
- Check: Web server user has permission

## Testing

Test your config files:

```bash
# Test if file loads without errors
php -r "include 'config/dev.env.inc'; echo 'OK';"

# Test if variables are set
php -r "include 'config/dev.env.inc'; echo \$org . '/' . \$name;"
# Should output: mars/mpvg
```

## Files Changed

1. **src/Form/CmeshPushContentForm.php**
   - Added `listEnvironments()` method
   - Added `executeEnvCommand()` method  
   - Added output buffering protection
   - Dynamic button generation
   - Removed hardcoded command buttons

2. **config/** (new directory)
   - Example environment files
   - README with instructions
   - .gitignore for security

## After Updating

```bash
# Extract module
tar -xzf cmesh_push_content.tar.gz

# Set up your environment files
cd cmesh_push_content/config
cp dev.env.inc.example dev.env.inc
nano dev.env.inc  # Edit with your values

# Clear cache
drush cr

# Test!
```

Your error should now be fixed, and buttons will appear for each environment you configure!
