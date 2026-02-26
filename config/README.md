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

3. Each file can define:
   - `$org` - Organization name
   - `$name` - Environment/site name
   - `$custom_commands` - Array of custom commands (optional)

## File Format

Each `.env.inc` file must:
- Start with `<?php`
- Set variables (not echo them)
- Have no closing `?>` tag
- Have no whitespace before `<?php`

### Basic Example:

```php
<?php

/**
 * @file
 * Development environment configuration.
 */

$org = 'your-org';
$name = 'your-site-name';
```

### Advanced Example with Custom Commands:

```php
<?php

/**
 * @file
 * Development environment configuration.
 */

$org = 'your-org';
$name = 'your-site-name';

// Define custom commands for different CDN providers
$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id your-zone --api-token $CLOUDFLARE_API_TOKEN',
    'description' => 'Deploy to Cloudflare CDN',
  ],
  'bunny' => [
    'label' => 'Push to Bunny CDN',
    'command' => '/opt/cmesh/scripts/deploy-bunny.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --storage-zone your-storage --access-key $BUNNY_ACCESS_KEY',
    'description' => 'Deploy to Bunny CDN',
  ],
  'aws' => [
    'label' => 'Push to AWS S3',
    'command' => '/opt/cmesh/scripts/deploy-aws.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --bucket your-bucket --region us-east-1',
    'description' => 'Deploy to AWS S3',
  ],
  'default' => [
    'label' => 'Push to ' . $name,
    'command' => '/opt/cmesh/scripts/pushfin.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name),
    'description' => 'Default push command',
  ],
];
```

## Custom Commands Structure

The `$custom_commands` array should contain command configurations with the following structure:

- **label**: Display name for the button
- **command**: The full command to execute (use `escapeshellarg()` for variables)
- **description**: Description shown in status messages

### Environment Variables

You can use environment variables in your commands:
- `$CLOUDFLARE_API_TOKEN` - Cloudflare API token
- `$BUNNY_ACCESS_KEY` - Bunny CDN access key
- `$AWS_ACCESS_KEY_ID` - AWS access key
- `$AWS_SECRET_ACCESS_KEY` - AWS secret key

Make sure these environment variables are set in your system or web server configuration.

## Security

**Important:** Never commit real `.env.inc` files to version control!

The `.gitignore` should contain:
```
config/*.env.inc
```

Only commit the `.example` files.

## Buttons

The module will automatically create buttons for each command defined in your `.env.inc` files:

### Basic Configuration:
- `dev.env.inc` → "Push to dev" button
- `staging.env.inc` → "Push to staging" button  
- `prod.env.inc` → "Push to prod" button

### Custom Commands:
- Each key in `$custom_commands` creates a separate button
- Button labels come from the `label` field
- Multiple environments can have different sets of commands

## Command Execution

When clicking a button, the corresponding command is executed in the background. Commands can:
- Use different scripts for different CDN providers
- Include custom parameters and flags
- Use environment variables for sensitive data
- Be completely customized per environment

## Examples for Different CDN Providers

### Cloudflare
```php
'cloudflare' => [
  'label' => 'Deploy to Cloudflare',
  'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id your-zone --api-token $CLOUDFLARE_API_TOKEN',
  'description' => 'Deploy to Cloudflare CDN',
],
```

### Bunny CDN
```php
'bunny' => [
  'label' => 'Deploy to Bunny CDN',
  'command' => '/opt/cmesh/scripts/deploy-bunny.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --storage-zone your-storage --access-key $BUNNY_ACCESS_KEY --cdn-host your-cdn.b-cdn.net',
  'description' => 'Deploy to Bunny CDN',
],
```

### AWS S3 + CloudFront
```php
'aws' => [
  'label' => 'Deploy to AWS',
  'command' => '/opt/cmesh/scripts/deploy-aws.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --bucket your-bucket --cloudfront-distribution-id $CLOUDFRONT_DISTRIBUTION_ID',
  'description' => 'Deploy to AWS S3 and invalidate CloudFront',
],
```

### Multiple Regions
```php
'us-east' => [
  'label' => 'Deploy to US East',
  'command' => '/opt/cmesh/scripts/deploy.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --region us-east-1 --bucket us-east-bucket',
  'description' => 'Deploy to US East region',
],
'eu-west' => [
  'label' => 'Deploy to EU West',
  'command' => '/opt/cmesh/scripts/deploy.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --region eu-west-1 --bucket eu-west-bucket',
  'description' => 'Deploy to EU West region',
],
```