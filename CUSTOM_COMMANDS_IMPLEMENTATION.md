# Custom Commands Implementation Summary

## Overview

This implementation adds the ability to execute custom scripts with different parameters for different CDN providers (Cloudflare, Bunny CDN, AWS S3, etc.) to the cmesh_push_content Drupal module. All parameters are defined in the `.env.inc` files, and both the build service and custom commands support the additional parameters.

## Key Changes Made

### 1. Fixed Existing Bug
- **Issue**: In `CmeshPushContentForm.php`, the `$script` variable was commented out but still used in the command building logic
- **Fix**: Restored the `$script` variable and implemented proper custom command handling

### 2. Enhanced Configuration Format
- **Old Format**: Only supported basic `$org`, `$name`, `$script`, `$bucket` variables
- **New Format**: Added support for `$custom_commands` array with multiple commands per environment
- **Colon Support**: Organization and site names can now contain colons (e.g., `company:division`)

### 3. Updated Form Interface
- **Old Interface**: Single button per environment ("Push to dev", "Push to staging", etc.)
- **New Interface**: Multiple buttons per environment based on custom commands defined
- **Colon Handling**: Proper handling of colons in organization/site names using URL encoding

### 4. Enhanced Systemd Integration
- **Old Systemd**: Only supported basic `org:name` format
- **New Systemd**: Supports `org:name:command` format for custom command routing
- **Colon Safety**: URL encoding for colons in org/name values to prevent parsing issues

## Implementation Details

### Configuration Files (`.env.inc`)

#### Basic Configuration (Backward Compatible)
```php
<?php
$org = 'your-org';
$name = 'your-site-name';
```

#### Advanced Configuration with Custom Commands
```php
<?php
$org = 'your-org';
$name = 'your-site-name';

$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id $CLOUDFLARE_ZONE_ID --api-token $CLOUDFLARE_API_TOKEN',
    'description' => 'Deploy to Cloudflare CDN',
  ],
  'bunny' => [
    'label' => 'Push to Bunny CDN',
    'command' => '/opt/cmesh/scripts/deploy-bunny.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --storage-zone $BUNNY_STORAGE_ZONE --access-key $BUNNY_ACCESS_KEY',
    'description' => 'Deploy to Bunny CDN',
  ],
];
```

### Core Files Modified

#### 1. `src/Form/CmeshPushContentForm.php`
- **Fixed**: Restored `$script` variable usage
- **Added**: `getEnvironmentCommands()` method to parse custom commands
- **Modified**: `executeEnvCommand()` to handle custom command keys
- **Enhanced**: Form building logic to create multiple buttons per environment

#### 2. `src/Service/SystemdCommandExecutorService.php`
- **Enhanced**: Command parsing to extract command key from script names
- **Modified**: Instance naming to support `org:name:command` format
- **Updated**: Logging to include command key information

#### 3. `config/pushfin-systemd.sh`
- **Enhanced**: Complete rewrite to handle custom command routing
- **Added**: Support for Cloudflare, Bunny CDN, AWS deployment scripts
- **Added**: Environment variable validation
- **Added**: Proper error handling and logging

### New Files Created

#### Example Configuration Files
- `config/dev.env.inc.example` - Updated with custom commands examples
- `config/staging.env.inc.example` - Updated with custom commands examples
- `config/prod.env.inc.example` - Updated with custom commands examples

#### Example Deployment Scripts
- `config/deploy-cloudflare.sh.example` - Cloudflare deployment script
- `config/deploy-bunny.sh.example` - Bunny CDN deployment script
- `config/deploy-aws.sh.example` - AWS S3 + CloudFront deployment script

#### Documentation
- `CUSTOM_COMMANDS_USAGE.md` - Comprehensive usage guide
- `CUSTOM_COMMANDS_IMPLEMENTATION.md` - This implementation summary

## Command Flow

### 1. Form Submission Flow
```
User clicks button → Form submits → executeEnvCommand() → getEnvironmentCommands() → executeCommand() → Service executes
```

### 2. Command Execution Flow (Regular Service)
```
CmeshPushContentService::executeCommand() → Build command script → Execute in background → Monitor status
```

### 3. Command Execution Flow (Systemd Service)
```
SystemdCommandExecutorService::executeCommand() → Parse command → Create systemd instance → Start service → Monitor log file
```

### 4. Systemd Service Flow
```
systemd service → pushfin-systemd.sh → Parse instance (org:name:command) → Execute appropriate script
```

## Supported CDN Providers

### Cloudflare
- **Script**: `deploy-cloudflare.sh`
- **Parameters**: `--zone-id`, `--api-token`
- **Environment Variables**: `$CLOUDFLARE_ZONE_ID`, `$CLOUDFLARE_API_TOKEN`
- **Features**: Pages deployment, cache purge, CDN upload

### Bunny CDN
- **Script**: `deploy-bunny.sh`
- **Parameters**: `--storage-zone`, `--access-key`, `--cdn-host`
- **Environment Variables**: `$BUNNY_STORAGE_ZONE`, `$BUNNY_ACCESS_KEY`
- **Features**: Storage zone upload, cache purge, CDN URL generation

### AWS S3 + CloudFront
- **Script**: `deploy-aws.sh`
- **Parameters**: `--bucket`, `--region`, `--cloudfront-distribution-id`
- **Environment Variables**: `$AWS_ACCESS_KEY_ID`, `$AWS_SECRET_ACCESS_KEY`, `$AWS_S3_BUCKET`, `$AWS_REGION`
- **Features**: S3 sync, content-type setting, CloudFront invalidation

## Backward Compatibility

### Maintained Compatibility
- Existing `.env.inc` files without custom commands continue to work
- Default behavior (single button per environment) is preserved
- Legacy `$script` and `$bucket` variables are still supported
- All existing APIs and endpoints remain unchanged

### Migration Path
- Users can gradually migrate by adding `$custom_commands` arrays
- No breaking changes to existing functionality
- Mixed configurations (some environments with custom commands, some without) are supported

## Security Considerations

### Environment Variables
- Sensitive data (API tokens, access keys) should be stored in environment variables
- Never commit actual credentials to configuration files
- Use `.example` files as templates with placeholder variables

### Script Permissions
- Custom deployment scripts should be executable by the web server user
- Scripts should be located in secure directories (e.g., `/opt/cmesh/scripts/`)
- Follow principle of least privilege for script permissions

### Input Validation
- All user inputs are properly escaped using `escapeshellarg()`
- Command parsing includes validation and error handling
- Systemd service runs with appropriate user permissions

## Testing Strategy

### Manual Testing
1. Create test `.env.inc` files with custom commands
2. Verify multiple buttons appear in the form
3. Test each custom command execution
4. Verify systemd service integration
5. Test error handling and logging

### Automated Testing
- Created `test_custom_commands.php` for logic validation
- Test command parsing and instance generation
- Validate environment variable handling

## Deployment Instructions

### 1. Update Module Files
Replace the following files with the new versions:
- `src/Form/CmeshPushContentForm.php`
- `src/Service/SystemdCommandExecutorService.php`
- `config/pushfin-systemd.sh`

### 2. Create Custom Scripts
Copy and customize the example deployment scripts:
```bash
cp config/deploy-cloudflare.sh.example /opt/cmesh/scripts/deploy-cloudflare.sh
chmod +x /opt/cmesh/scripts/deploy-cloudflare.sh
```

### 3. Update Configuration Files
Update your `.env.inc` files to include custom commands:
```bash
cp config/dev.env.inc.example sites/default/files/cmesh-config/dev.env.inc
# Edit the file to add your custom commands
```

### 4. Set Environment Variables
Configure required environment variables:
```bash
export CLOUDFLARE_API_TOKEN="your-token"
export BUNNY_ACCESS_KEY="your-key"
export AWS_ACCESS_KEY_ID="your-key"
export AWS_SECRET_ACCESS_KEY="your-secret"
```

### 5. Clear Drupal Cache
```bash
drush cr
```

## Troubleshooting Guide

### Common Issues

#### Buttons Not Appearing
- Check `.env.inc` file syntax
- Verify file location: `sites/default/files/cmesh-config/`
- Check Drupal logs for PHP errors

#### Scripts Not Executing
- Verify script permissions and location
- Check environment variable configuration
- Review systemd service logs

#### Systemd Service Failures
- Check service status: `systemctl status cmesh-build@instance`
- Review logs: `journalctl -u cmesh-build@instance`
- Verify systemd wrapper script permissions

### Debug Mode
Enable debug logging to troubleshoot issues:
- Check Drupal watchdog logs
- Review systemd service output
- Verify command parsing in logs

### Colon Handling Implementation

A critical issue was identified and resolved regarding colon characters in organization and site names:

**Problem**: The systemd service uses colon as a delimiter in instance names (format: `org:name:command`). If the org or name values contain colons, this breaks the parsing logic.

**Example of the problem**:
- Input: org="my:org", name="my:site", command_key="cloudflare"
- Instance: "my:org:my:site:cloudflare"
- Parsed incorrectly as: ORG="my", NAME="org", COMMAND_KEY="my:site:cloudflare"

**Solution**: URL encoding of colons in the systemd instance name:

1. **Encoding in PHP** (`SystemdCommandExecutorService.php`):
```php
// URL encode colons in org and name to prevent parsing issues
$encoded_org = str_replace(':', '%3A', $org);
$encoded_name = str_replace(':', '%3A', $name);
$instance = "{$encoded_org}:{$encoded_name}:{$command_key}";
```

2. **Decoding in Bash** (`pushfin-systemd.sh`):
```bash
# Parse the encoded instance
if [[ "$INSTANCE" =~ ^([^:]+):([^:]+):(.+)$ ]]; then
    ORG_ENCODED="${BASH_REMATCH[1]}"
    NAME_ENCODED="${BASH_REMATCH[2]}"
    COMMAND_KEY="${BASH_REMATCH[3]}"
    
    # Decode URL-encoded colons
    ORG="${ORG_ENCODED//%3A/:}"
    NAME="${NAME_ENCODED//%3A/:}"
```

**Result**: 
- Input: org="my:org", name="my:site", command_key="cloudflare"
- Instance: "my%3Aorg:my%3Asite:cloudflare"
- Parsed correctly as: ORG="my:org", NAME="my:site", COMMAND_KEY="cloudflare"

This ensures that organization and site names containing colons work correctly with the systemd service.

## Future Enhancements

### Potential Improvements
1. **Web UI Configuration**: Add admin interface for managing custom commands
2. **Command Validation**: Add pre-flight command validation
3. **Deployment History**: Track deployment history and success rates
4. **Rollback Support**: Add rollback functionality for deployments
5. **Multi-Region Support**: Enhanced multi-region deployment strategies
6. **Webhook Integration**: Support for deployment webhooks and notifications

### API Extensions
1. **REST API**: Add REST endpoints for command management
2. **Command Templates**: Pre-built templates for popular CDN providers
3. **Parameter Validation**: Enhanced parameter validation and sanitization
4. **Async Operations**: Better support for long-running deployments

## Conclusion

This implementation successfully adds custom command support while maintaining full backward compatibility. The modular design allows for easy extension with new CDN providers and deployment strategies. The comprehensive documentation and example scripts provide a solid foundation for users to implement their own custom deployment workflows.

The solution addresses the original requirements:
- ✅ Execute custom scripts with different parameters
- ✅ Support different CDN providers (Cloudflare, Bunny CDN, AWS S3)
- ✅ Parameters defined in `.env.inc` files
- ✅ Build service supports additional parameters
- ✅ Custom commands for different environments

The implementation is production-ready and includes proper error handling, security considerations, and comprehensive documentation.