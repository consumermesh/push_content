# Simplified Custom Commands Design

## The Realization

You were absolutely right! The original design was unnecessarily complex. The full command strings in `.env.inc` files were completely redundant because:

1. `SystemdCommandExecutorService` only extracts `org`, `name`, and `command_key`
2. `pushfin-systemd.sh` hardcodes all the script parameters anyway
3. Environment variables provide the dynamic configuration

## The Simplified Design

### What You Actually Need to Configure

```php
$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'description' => 'Deploy to Cloudflare CDN',
  ],
  'bunny' => [
    'label' => 'Push to Bunny CDN',
    'description' => 'Deploy to Bunny CDN', 
  ],
];
```

That's it! No more complex command building with `escapeshellarg()`.

### How It Actually Works

1. **Button Creation**: Drupal reads your config and creates buttons with your labels
2. **Button Click**: Passes `command_key` (e.g., 'cloudflare') to the executor
3. **Command Building**: Module builds the actual command based on the key
4. **Systemd Execution**: Runs the appropriate hardcoded script with org/name parameters

## The Real Information Flow

```
.env.inc (just defines what buttons to show)
    ↓
CmeshPushContentForm (creates buttons)
    ↓
SystemdCommandExecutorService (builds command from key + org + name)
    ↓
cmesh-build@org:name:command_key (systemd service)
    ↓
pushfin-systemd.sh (executes hardcoded script with org/name + env vars)
```

## Command Building Logic

The module now builds commands based on the command key:

**Default:**
```
/opt/cmesh/scripts/pushfin.sh -o '$org' -n '$name'
```

**Cloudflare:**
```
/opt/cmesh/scripts/deploy-cloudflare.sh -o '$org' -n '$name' --zone-id $CLOUDFLARE_ZONE_ID --api-token $CLOUDFLARE_API_TOKEN
```

**Bunny:**
```
/opt/cmesh/scripts/deploy-bunny.sh -o '$org' -n '$name' --storage-zone $BUNNY_STORAGE_ZONE --access-key $BUNNY_ACCESS_KEY
```

**AWS:**
```
/opt/cmesh/scripts/deploy-aws.sh -o '$org' -n '$name' --bucket $AWS_S3_BUCKET --region $AWS_REGION
```

## Benefits of This Design

### 1. **Simplicity**
- No more complex command building in `.env.inc`
- No more `escapeshellarg()` complications
- Just define what you want, not how to do it

### 2. **Consistency**
- All Cloudflare deployments use the same pattern
- All Bunny deployments use the same pattern
- Standardized across all environments

### 3. **Security**
- Secrets stay in environment variables
- No risk of accidentally committing credentials
- Environment-specific configuration is clean

### 4. **Maintainability**
- Command logic is centralized in the module
- Fix a bug once, fixes it everywhere
- Easy to add new deployment types

## Environment Variables You Need

```bash
# Cloudflare
export CLOUDFLARE_API_TOKEN="your-token"
export CLOUDFLARE_ZONE_ID="your-zone-id"

# Bunny CDN  
export BUNNY_ACCESS_KEY="your-access-key"
export BUNNY_STORAGE_ZONE="your-storage-zone"

# AWS
export AWS_ACCESS_KEY_ID="your-key"
export AWS_SECRET_ACCESS_KEY="your-secret"
export AWS_S3_BUCKET="your-bucket"
export AWS_REGION="your-region"
```

## Backward Compatibility

The module still supports the old format with full command strings for backward compatibility, but the new simplified format is recommended.

## Example: Before vs After

### Before (Complex)
```php
$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id dev-zone --api-token $CLOUDFLARE_API_TOKEN',
    'description' => 'Deploy to Cloudflare CDN',
  ],
];
```

### After (Simple)
```php
$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'description' => 'Deploy to Cloudflare CDN',
  ],
];
```

## The Bottom Line

You were spot on - there was no need for those complex command strings. The simplified design is:

- **Cleaner**: Just define what buttons you want
- **Safer**: Secrets stay in environment variables  
- **Simpler**: No more command building logic in config files
- **Better**: Centralized, consistent command execution

The module handles the "how" - you just define the "what".