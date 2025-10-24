# Quick Fix - once() Error

## Error Message
```
Uncaught TypeError: $(...).once is not a function
```

## Cause
The JavaScript was using jQuery's `.once()` method which requires the Drupal `once` library as a dependency.

## Fix Applied

### 1. Removed `.once()` Usage
Replaced the `.once()` approach with a simple flag-based initialization:

```javascript
var initialized = false;

Drupal.behaviors.commandExecutor = {
  attach: function (context, settings) {
    if (initialized) {
      return;
    }
    initialized = true;
    // ... rest of code
  }
}
```

### 2. Updated Libraries (Just in Case)
Added `core/once` to dependencies in `command_executor.libraries.yml`

### 3. Made pollInterval Global
Moved `pollInterval` to module scope so it persists across Drupal.behaviors calls

## Files Changed
- `js/command_executor.js` - Rewrote to avoid once()
- `command_executor.libraries.yml` - Added once dependency

## After Updating

**CRITICAL:** You MUST clear cache and hard refresh:

```bash
# Clear Drupal cache
drush cr

# Clear CSS/JS cache specifically
drush cc css-js
```

**In Browser:**
- Hard refresh: `Ctrl + Shift + R` (Windows/Linux)
- Hard refresh: `Cmd + Shift + R` (Mac)
- Or open DevTools (F12) → Right-click refresh button → "Empty Cache and Hard Reload"

## Testing

1. **Open browser console (F12)**
2. **Refresh the page**
3. **You should see:** No errors!
4. **Click a command button**
5. **You should see:** 
   ```
   Status check: Running
   Starting polling...
   Status check: Running
   Status check: Running
   ...
   Status check: Not running
   Stopping polling...
   ```

## Expected Behavior

✅ No JavaScript errors
✅ Polling starts automatically
✅ Output updates every 1 second
✅ Console shows status checks
✅ Form updates via AJAX (no page reload)

## If Still Not Working

1. **Check browser console for other errors**
   - Open DevTools (F12)
   - Look at Console tab
   - Any red error messages?

2. **Verify cache is actually cleared**
   ```bash
   # Nuclear option - clear everything
   drush cr
   drush cc css-js
   drush cc render
   
   # Rebuild cache
   drush cache:rebuild
   ```

3. **Check if JavaScript file loaded**
   - DevTools → Network tab
   - Filter by JS
   - Look for `command_executor.js`
   - Should show 200 status
   - Click it and verify the code is the new version (has `initialized` variable)

4. **Check drupalSettings**
   In browser console, run:
   ```javascript
   console.log(drupalSettings.commandExecutor);
   ```
   Should output:
   ```javascript
   {
     statusUrl: "/admin/config/system/command-executor/status",
     executeUrl: "/admin/config/system/command-executor/execute"
   }
   ```

5. **Manual test - Run this in console:**
   ```javascript
   $.get('/admin/config/system/command-executor/status', function(data) {
     console.log('Manual status check:', data);
   });
   ```

## Common Issues After Update

**"Still seeing old code"**
- Browser is caching old JavaScript
- Solution: Hard refresh or clear browser cache completely

**"drupalSettings is undefined"**
- Library not loading properly
- Solution: Check library is attached in form, clear cache

**"AJAX not working"**
- May need to check if AJAX is properly configured
- Check Network tab for AJAX requests
- Should see POST requests when clicking buttons

**"Polling starts but stops immediately"**
- Check the status endpoint is returning correct data
- Verify command is actually running (check state with drush)
- Run: `drush state:get command_executor.current`
