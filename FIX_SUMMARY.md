# Output Display Fix - Summary

## Problem
Commands were executing but output was not showing or appearing properly.

## Root Causes Identified

1. **`escapeshellcmd()` was breaking commands** - It escaped special characters like `&&`, `$()`, pipes, etc.
2. **Output buffering** - Commands weren't flushing output in real-time
3. **No progress indicators** - Commands completed too quickly to see

## Solutions Implemented

### 1. Script File Approach
Instead of trying to escape the command, we now:
- Write the command to a temporary bash script file
- Execute the script file directly
- This preserves all special characters and syntax

### 2. Unbuffered Output
- Added `stdbuf -oL -eL` for systems that have it (line-buffered output)
- Fallback for systems without `stdbuf`
- Explicit error redirection and output flushing in scripts

### 3. Better Script Structure
```bash
#!/bin/bash
set -o pipefail       # Fail on pipe errors
exec 2>&1             # Redirect stderr to stdout
[your command here]
exit_code=$?
echo ""
echo "[Command completed with exit code: $exit_code]"
exit $exit_code
```

### 4. Enhanced Commands
**System Info Check:**
- Added progress messages ("Starting...", "Complete!")
- More informative output (CPU info, architecture, etc.)
- Better formatting with separators

**List Temp Directory:**
- Shows file count
- Displays top 10 largest items (not just last 10)
- Shows overall /tmp usage
- Progress indicators

### 5. Improved Timing
- Increased wait time after execution from 100ms to 300ms
- Gives files time to be written and initial output to appear

## Files Changed

1. **src/Service/CommandExecutorService.php**
   - Complete rewrite of `executeCommand()` method
   - Added script file creation
   - Added `stdbuf` detection and fallback
   - Updated cleanup to remove script files

2. **src/Form/CommandExecutorForm.php**
   - Enhanced both predefined commands
   - Added progress indicators
   - More detailed output

## Testing the Fix

After updating, test with these steps:

1. **Clear cache:**
   ```bash
   drush cr
   ```

2. **Test System Info Check:**
   - Click "Run System Info Check"
   - Should see: "Starting system information check..."
   - Should see each section appear
   - Should end with: "System information check complete!"

3. **Test Temp Directory:**
   - Click "List Temp Directory"
   - Should see: "Starting temp directory analysis..."
   - Should see file listing and disk usage
   - Should end with: "Temp directory analysis complete!"

## If Output Still Not Showing

Check the TROUBLESHOOTING.md file for:
- PHP configuration issues
- Permission problems
- Path issues
- JavaScript/AJAX problems

## Quick Debug Commands

```bash
# Check if exec() is enabled
php -r "exec('echo test', \$out); print_r(\$out);"

# Check temp directory permissions
ls -la /tmp/cmd_*

# Check if stdbuf is available
which stdbuf

# Test as web server user
sudo -u www-data bash /tmp/cmd_XXXXX_script.sh

# Check Drupal logs
drush watchdog:show --severity=Error

# Clear and test
drush cr && drush cache:rebuild
```

## Expected Behavior Now

✅ Commands should execute immediately
✅ Output should appear in real-time (or near real-time)
✅ Each section should be visible as it processes
✅ Completion message should appear at the end
✅ Exit code should be displayed
✅ No escaping issues with special characters
✅ Works on systems with or without stdbuf

## Performance Notes

- Commands typically complete in 1-3 seconds
- Polling happens every 2 seconds
- Total latency: command execution + polling interval
- Maximum 2-second delay before you see output start
- Real-time updates as command progresses
