# Bucket Parameter Configuration

## Overview

The bucket parameter allows you to specify a custom S3 bucket for AWS deployments directly in your environment configuration files. This provides more flexibility than relying solely on environment variables.

## Configuration

### Basic Setup

Add the bucket parameter to your `.env.inc` file:

```php
<?php

$org = 'your-org';
$name = 'your-site';

// Optional: Define bucket for AWS S3 deployments
$bucket = 'my-custom-bucket';

// Define custom commands
$custom_commands = [
  'aws' => [
    'label' => 'Push to AWS S3',
    'description' => 'Deploy to AWS S3 with custom bucket',
  ],
];
```

### Environment-Specific Buckets

You can define different buckets for different environments:

**Development (`dev.env.inc`):**
```php
$bucket = 'dev-bucket';
```

**Production (`prod.env.inc`):**
```php
$bucket = 'prod-bucket';
```

### Fallback Behavior

If you don't define a bucket parameter, the system will fall back to using the `AWS_S3_BUCKET` environment variable:

```php
// Without bucket parameter
$org = 'mars';
$name = 'mpvg';
// No $bucket defined - will use $AWS_S3_BUCKET environment variable
```

## How It Works

### 1. Configuration Loading

When your environment file is loaded, the bucket variable becomes available:

```php
// In dev.env.inc
$bucket = 'my-dev-bucket';
```

### 2. Command Building

The system builds the AWS command using your configured bucket:

```bash
# With bucket parameter
/opt/cmesh/scripts/deploy-aws.sh -o 'mars' -n 'mpvg' --bucket 'my-dev-bucket' --region $AWS_REGION

# Without bucket parameter (fallback)
/opt/cmesh/scripts/deploy-aws.sh -o 'mars' -n 'mpvg' --bucket $AWS_S3_BUCKET --region $AWS_REGION
```

### 3. Systemd Instance Naming

The bucket is included in the systemd instance name for uniqueness:

```
# With bucket
mars:mpvg:aws:my-dev-bucket

# Without bucket
mars:mpvg:aws
```

### 4. Script Execution

The `pushfin-systemd.sh` script parses the bucket and passes it to the deployment script:

```bash
# The script receives the bucket parameter
if [[ -n "$BUCKET" ]]; then
    execute_command "/opt/cmesh/scripts/deploy-aws.sh -o '$ORG' -n '$NAME' --bucket '$BUCKET' --region \$AWS_REGION" "AWS deployment"
fi
```

## Examples

### Example 1: Development with Custom Bucket
```php
<?php
// dev.env.inc
$org = 'mycompany';
$name = 'dev-site';
$bucket = 'mycompany-dev-bucket';

$custom_commands = [
  'aws' => [
    'label' => 'Deploy to Dev S3',
    'description' => 'Deploy to development S3 bucket',
  ],
];
```

### Example 2: Production with Environment Variable Fallback
```php
<?php
// prod.env.inc
$org = 'mycompany';
$name = 'prod-site';
// No bucket defined - will use AWS_S3_BUCKET environment variable

$custom_commands = [
  'aws' => [
    'label' => 'Deploy to Prod S3',
    'description' => 'Deploy to production S3 bucket',
  ],
];
```

### Example 3: Multiple Environments
```php
<?php
// staging.env.inc
$org = 'mycompany';
$name = 'staging-site';
$bucket = 'mycompany-staging-bucket-us-east-1';

$custom_commands = [
  'aws' => [
    'label' => 'Deploy to Staging S3',
    'description' => 'Deploy to staging S3 bucket',
  ],
];
```

## Advanced Usage

### Dynamic Bucket Names
You can build bucket names dynamically:

```php
$org = 'mycompany';
$name = 'site';
$environment = 'dev'; // or 'prod', 'staging'
$region = 'us-west-2';

$bucket = $org . '-' . $name . '-' . $environment . '-' . $region;
// Results in: mycompany-site-dev-us-west-2
```

### Bucket with Organization Names Containing Colons
The system handles URL encoding for bucket names with special characters:

```php
$org = 'company:division';
$name = 'project:environment';
$bucket = 'my:custom:bucket';
// Will be encoded as: company%3Adivision:project%3Aenvironment:aws:my%3Acustom%3Abucket
```

## Environment Variables Still Required

Even with bucket configuration, you still need these environment variables:

```bash
export AWS_ACCESS_KEY_ID="your-access-key"
export AWS_SECRET_ACCESS_KEY="your-secret-key"
export AWS_REGION="your-region"
# AWS_S3_BUCKET is optional if you define $bucket in config
```

## Troubleshooting

### Bucket Not Being Used
1. Check that you've defined `$bucket` in your `.env.inc` file
2. Verify the variable name is exactly `$bucket` (case-sensitive)
3. Check logs to see if bucket is being detected: `drush watchdog:show --type=cmesh_push_content`

### Instance Parsing Errors
If your bucket name contains special characters, ensure they're properly handled:
- Colons in bucket names are automatically URL-encoded
- Spaces and other special characters should work with proper escaping

### Deployment Failures
1. Verify the bucket exists and you have permissions
2. Check that the bucket name is correctly passed in the logs
3. Ensure AWS credentials are properly configured

## Migration from Environment Variables

If you're currently using `AWS_S3_BUCKET` environment variable and want to switch to configuration:

1. **Add bucket to your `.env.inc`:**
   ```php
   $bucket = 'your-bucket-name';
   ```

2. **Remove or update AWS_S3_BUCKET environment variable** (optional)

3. **Test the deployment** to ensure it works with the new configuration

The system will automatically prefer the configured bucket over the environment variable."

## Summary

The bucket parameter provides:
- ✅ **Flexibility**: Configure buckets per environment
- ✅ **Clarity**: See bucket configuration in your config files
- ✅ **Consistency**: Same pattern as org/name configuration
- ✅ **Backward Compatibility**: Falls back to environment variables when not specified
- ✅ **Special Character Support**: Handles colons and other characters via URL encoding

This makes AWS S3 deployments more configurable and environment-specific while maintaining the simplified architecture we achieved earlier."}