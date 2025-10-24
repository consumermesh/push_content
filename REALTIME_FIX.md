# Real-Time Polling Fix

## Problem
Output was only showing when the page was manually refreshed, not updating automatically in real-time.

## Root Cause
The JavaScript polling was only starting when the page loaded with a command already running. When you clicked a button to start a new command, the form would submit and reload, but polling wouldn't start automatically.

## Solution Implemented

### 1. AJAX Form Submission
Changed all command buttons to use AJAX instead of full page reload:

```php
'#ajax' => [
  'callback' => '::ajaxRebuildForm',
  'wrapper' => 'command-executor-form',
  'effect' => 'fade',
],
```

**Benefits:**
- No page reload when clicking buttons
- Form updates instantly via AJAX
- JavaScript context is maintained
- Polling can continue uninterrupted

### 2. Improved JavaScript Polling Logic

**Key Changes:**
- Detects AJAX form completions and starts polling
- Polls every 1 second (changed from 2 seconds) for more responsive updates
- Better console logging for debugging
- Simplified state management
- Added cache: false to AJAX requests

**Flow:**
1. Page loads → Check status immediately
2. If command running → Start polling
3. Click button → AJAX submits form
4. Form updates → JavaScript detects completion
5. Check status → Start polling if command running
6. Poll every 1 second until command completes
7. Command completes → Stop polling

### 3. Added Form Wrapper
```php
$form['#prefix'] = '<div id="command-executor-form">';
$form['#suffix'] = '</div>';
```
This allows AJAX to replace the entire form content.

## Files Changed

1. **src/Form/CommandExecutorForm.php**
   - Added `#ajax` to all buttons
   - Added `ajaxRebuildForm()` callback method
   - Added form wrapper with prefix/suffix

2. **js/command_executor.js**
   - Simplified polling logic
   - Added AJAX completion detection
   - Reduced polling interval to 1 second
   - Better status detection

## Testing the Fix

After updating and clearing cache:

1. **Click "Run System Info Check"**
   - Form should update via AJAX (no page reload)
   - Output should start appearing immediately
   - Should see updates every ~1 second
   - Console should show: "Starting polling..."

2. **Open Browser Console (F12)**
   - Should see status checks: "Status check: Running"
   - Every 1 second while command runs
   - "Status check: Not running" when done

3. **Don't Refresh!**
   - Output should update automatically
   - No need to manually refresh
   - Progress appears in real-time

## Expected Behavior

✅ Click button → Form updates instantly (AJAX)
✅ Polling starts within 0.5 seconds
✅ Output updates every 1 second
✅ See progress in real-time
✅ Auto-scrolls to bottom
✅ Stops polling when done
✅ Form shows new buttons when complete

## Debug Console Commands

Open browser console and try:

```javascript
// Check if polling is active
console.log('Checking status manually...');
$.get('/admin/config/system/command-executor/status', function(data) {
  console.log('Is running:', data.is_running);
  console.log('Output length:', data.output ? data.output.length : 0);
});

// Force check status
Drupal.behaviors.commandExecutor.attach(document, drupalSettings);
```

## Still Not Working?

1. **Clear ALL caches:**
   ```bash
   drush cr
   drush cc css-js
   # Hard refresh browser: Ctrl+Shift+F5
   ```

2. **Check browser console for errors:**
   - Open DevTools (F12)
   - Look for JavaScript errors in Console tab
   - Check Network tab for failed AJAX requests

3. **Verify AJAX is working:**
   - Network tab should show AJAX requests
   - Should NOT see full page reloads when clicking buttons
   - Should see requests to `/status` endpoint every 1 second

4. **Check form wrapper:**
   - Right-click page → View Source
   - Search for: `id="command-executor-form"`
   - Should be wrapped around the form

## Performance Notes

- **Polling interval:** 1 second (faster than before)
- **Network overhead:** ~1 request per second while running
- **Typical command duration:** 2-5 seconds
- **Total requests:** Usually 3-6 status checks per command
- **Very responsive:** See output within 1 second of generation

## Comparison

### Before (Broken):
❌ Click button → Page reloads
❌ Output only on manual refresh
❌ No automatic updates
❌ Have to keep refreshing

### After (Fixed):
✅ Click button → AJAX update
✅ Output updates automatically
✅ Real-time progress display
✅ No manual intervention needed
