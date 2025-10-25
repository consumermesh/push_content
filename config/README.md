# Environment Configuration

This directory contains environment-specific configuration files.

## Setup

1. Copy the example files and remove `.example`:
   ```bash
   cp dev.env.inc.example dev.env.inc
   cp staging.env.inc.example staging.env.inc
   cp prod.env.inc.example prod.env.inc
   ```

2. Edit each file to match your environment settings

3. Each file should define:
   - `$org` - Organization name
   - `$name` - Environment/site name

## File Format

Each `.env.inc` file must:
- Start with `<?php`
- Set variables (not echo them)
- Have no closing `?>` tag
- Have no whitespace before `<?php`

### Example:

```php
<?php

/**
 * @file
 * Development environment configuration.
 */

$org = 'your-org';
$name = 'your-site-name';
```

## Security

**Important:** Never commit real `.env.inc` files to version control!

The `.gitignore` should contain:
```
config/*.env.inc
```

Only commit the `.example` files.

## Buttons

The module will automatically create a "Push to X" button for each `X.env.inc` file found in this directory.

For example:
- `dev.env.inc` → "Push to dev" button
- `staging.env.inc` → "Push to staging" button  
- `prod.env.inc` → "Push to prod" button

## Command Executed

When clicking a button, the module runs:
```bash
/opt/cmesh/scripts/pushfin.sh -o '<org>' -n '<name>'
```

Where `<org>` and `<name>` are read from the corresponding `.env.inc` file.
