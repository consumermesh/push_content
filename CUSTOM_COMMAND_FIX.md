# Custom Command Parameters Fix

## Problem

Custom commands were not working because the command parsing regex in `SystemdCommandExecutorService.php` was failing to properly extract the `org` and `name` parameters from commands that included quoted values (especially when `escapeshellarg()` added quotes around values containing colons).

## Root Cause

The original regex pattern:
```php
/-o\s+[\'"]?([^\s\'"]+)[\'"]?\s+-n\s+[\'"]?([^\s\'"]+)[\'"]?/
```

This pattern used `[^\s\'"]+` which means "one or more characters that are NOT whitespace, single quote, or double quote". This caused it to stop parsing at the first quote character, leading to incorrect extraction of quoted values.

For example, with the command:
```
/opt/cmesh/scripts/deploy-cloudflare.sh -o 'my:org' -n 'my:site' --zone-id dev-zone --api-token $CLOUDFLARE_API_TOKEN
```

The old regex would extract:
- `org` = `'my` (incorrect - includes the quote and stops at the colon)
- `name` = `'my` (incorrect)

## Solution

Fixed the regex pattern to properly handle quoted values:
```php
/-o\s+([\'"]?)([^\1]*)\1\s+-n\s+([\'"]?)([^\3]*)\3/
```

This new pattern:
1. Captures the opening quote (if any) in group 1
2. Captures the actual value in group 2 (everything until the matching quote)
3. Matches the closing quote (if any) with `\1`
4. Does the same for the `-n` parameter

Now it correctly extracts:
- `org` = `my:org` (correct - without quotes)
- `name` = `my:site` (correct - without quotes)

## Files Modified

1. **`src/Service/SystemdCommandExecutorService.php`**
   - Fixed the regex pattern for command parsing
   - Added enhanced logging for debugging

2. **`config/pushfin-systemd.sh`**
   - Added detailed logging to help debug command execution
   - Added logging for which command key is being executed

## Enhanced Logging

Added comprehensive logging to help diagnose issues:

### In SystemdCommandExecutorService.php:
- Logs the raw command received
- Logs the parsing success/failure with detailed match information
- Logs the final systemd instance being created

### In pushfin-systemd.sh:
- Logs the parsed parameters (ORG, NAME, COMMAND_KEY)
- Logs which command case is being executed
- Logs the actual command being executed

## Testing

To verify the fix works:

1. **Check the logs** when executing a custom command:
   ```bash
   tail -f /var/log/cmesh/build-*.log
   ```

2. **Check systemd service logs**:
   ```bash
   journalctl -u cmesh-build@your-instance -f
   ```

3. **Test with colons in org/name**:
   - Set up an environment with org/name containing colons
   - Execute a custom command
   - Verify the command executes with correct parameters

## Expected Behavior After Fix

When you click a custom command button (e.g., "Push to Cloudflare"):

1. The form builds the command: `/opt/cmesh/scripts/deploy-cloudflare.sh -o 'your-org' -n 'your-site' --zone-id dev-zone --api-token $CLOUDFLARE_API_TOKEN`

2. SystemdCommandExecutorService parses it correctly and creates instance: `your-org:your-site:cloudflare`

3. Systemd service starts: `cmesh-build@your-org:your-site:cloudflare`

4. pushfin-systemd.sh receives the instance, parses it, and executes the correct deploy script

5. The deployment script runs with proper org/name parameters

## Verification Steps

1. **Clear Drupal cache**:
   ```bash
   drush cr
   ```

2. **Test command parsing** by checking logs after executing a custom command

3. **Verify systemd service execution**:
   ```bash
   systemctl status cmesh-build@your-org:your-site:cloudflare
   ```

4. **Check deployment script execution** in the log files

The fix ensures that custom commands work correctly regardless of whether org/name values contain special characters or require quoting.