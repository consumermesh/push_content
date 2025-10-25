# Polling and Form Refresh Fix

## The Problem

After a command completed:
- ❌ JavaScript continued polling forever
- ❌ "Stop Command" button remained visible
- ❌ Form didn't show completion message
- ❌ No "Clear Output" button appeared
- ✅ statusUrl correctly showed completed time

## Root Cause

The form didn't automatically rebuild when the command completed. The JavaScript could see the status change via AJAX, but the form HTML (buttons, status message) only updated on explicit button clicks.

## The Solution

### 1. Hidden Refresh Button

Added a hidden submit button that triggers form rebuild:

```php
$form['refresh_trigger'] = [
  '#type' => 'submit',
  '#value' => 'Refresh',
  '#submit' => ['::refreshForm'],
  '#ajax' => [
    'callback' => '::ajaxRebuildForm',
    'wrapper' => 'cmesh-push-content-form',
  ],
  '#attributes' => [
    'style' => 'display: none;',
    'id' => 'refresh-trigger',
  ],
];
```

### 2. JavaScript Auto-Trigger

When JavaScript detects command completion, it clicks the hidden button:

```javascript
if (data.is_running) {
  // Keep polling
  startPolling();
} else {
  // Command finished
  stopPolling();
  
  // If Stop button still visible, form needs refresh
  if (hasStopButton) {
    console.log('Triggering form refresh');
    $('#refresh-trigger').click();  // Triggers AJAX form rebuild
  }
}
```

### 3. Status Flags

Added status flags to drupalSettings to help JavaScript track state:

```php
$form['#attached']['drupalSettings']['cmeshPushContent']['isRunning'] = TRUE/FALSE;
$form['#attached']['drupalSettings']['cmeshPushContent']['isCompleted'] = TRUE/FALSE;
```

## Flow After Completion

```
1. Command completes
2. JavaScript polls statusUrl
3. Response: is_running=false, completed=timestamp
4. JavaScript: "Command finished, stopping polling"
5. JavaScript checks: is Stop button visible?
6. Yes → JavaScript: "Triggering form refresh"
7. JavaScript clicks hidden #refresh-trigger button
8. AJAX form rebuild triggered
9. Form rebuilds with:
   - Completion message
   - Duration shown
   - "Clear Output" button
   - No more "Stop Command" button
10. Polling stays stopped
```

## Console Output

You'll now see:

```
Status check: Running
Status check: Running
Status check: Running
Status check: Completed
Command finished, stopping polling
Triggering form refresh to show completion status
[Form refreshes via AJAX]
```

## What You'll See

### Before Fix:
```
[Output area with content]

[Stop Command] ← Still there! ❌
Polling continues forever... ❌
```

### After Fix:
```
✅ Command completed successfully!
Duration: 31 seconds

[Output area with content]

[Clear Output] ← Correct button! ✅
Polling stopped ✅
```

## Technical Details

### Files Changed

1. **src/Form/CmeshPushContentForm.php**
   - Added hidden refresh_trigger button
   - Added refreshForm() submit handler
   - Added status flags to drupalSettings

2. **js/cmesh_push_content.js**
   - Detects Stop button presence
   - Clicks hidden refresh button on completion
   - Better logging

### New Methods

**Form:**
```php
public function refreshForm(array &$form, FormStateInterface $form_state) {
  // Just rebuild the form to show updated status
  $form_state->setRebuild(TRUE);
}
```

**JavaScript:**
```javascript
var hasStopButton = $('#edit-stop').length > 0;
if (hasStopButton) {
  $('#refresh-trigger').click();
}
```

## Benefits

1. **No Manual Refresh** - Form auto-updates when command completes
2. **Polling Stops** - No wasted resources after completion
3. **Correct UI** - Shows completion message and correct button
4. **Clean UX** - Seamless transition from running to completed

## Testing

After updating:

1. **Clear cache:** `drush cr`
2. **Hard refresh browser:** Ctrl+Shift+R
3. **Click "Push to dev"**
4. **Watch console:**
   ```
   Status check: Running
   Status check: Running
   Status check: Completed
   Command finished, stopping polling
   Triggering form refresh to show completion status
   ```
5. **Form should automatically update to show:**
   - ✅ Completion message
   - ✅ Duration
   - ✅ "Clear Output" button
   - ✅ No more "Stop Command"
   - ✅ Polling stopped

## Troubleshooting

### Polling still continues
- Check console for "Command finished" message
- Check if #refresh-trigger exists: `$('#refresh-trigger').length`
- Clear cache: `drush cr`

### Form doesn't refresh
- Check AJAX is working (Network tab)
- Check for JavaScript errors (Console tab)
- Verify refresh_trigger button exists in HTML

### Stop button still shows
- Check if form rebuilt
- Check if status.completed is set
- Verify getStatus() returns completed timestamp

### Console errors
- Check jQuery is loaded
- Check Drupal behaviors attached
- Verify all JavaScript syntax correct

## Debug Commands

In browser console:

```javascript
// Check if refresh trigger exists
console.log('Refresh trigger:', $('#refresh-trigger').length);

// Check current status
$.get(drupalSettings.cmeshPushContent.statusUrl, function(data) {
  console.log('Status:', data);
});

// Manually trigger refresh
$('#refresh-trigger').click();

// Check buttons
console.log('Stop button:', $('#edit-stop').length);
console.log('Clear button:', $('#edit-clear').length);
```

## Edge Cases Handled

### Quick Commands
- Command completes in < 1 second
- Still caught on next poll
- Form refreshes properly

### Page Refresh During Running
- Polling restarts
- Catches completion
- Form refreshes

### Multiple Users
- Each user's form refreshes independently
- All see completion simultaneously

### Network Delays
- Polling continues until confirmed stopped
- Form refreshes when possible
- No duplicate refreshes

## Performance Impact

- **Before:** Polling forever = wasted requests
- **After:** Polling stops immediately = efficient
- **Refresh trigger:** Single AJAX call when needed
- **Net result:** Better performance

## Comparison

### Old Behavior:
```
Command completes
↓
JavaScript sees: is_running=false
↓
Stop polling ✓
↓
...nothing else happens
↓
Form still shows "Stop Command" ❌
↓
User must manually refresh page
```

### New Behavior:
```
Command completes
↓
JavaScript sees: is_running=false
↓
Stop polling ✓
↓
Check: Stop button still visible?
↓
Yes → Click hidden refresh button
↓
AJAX form rebuild ✓
↓
Form shows completion message ✓
↓
Form shows "Clear Output" button ✓
↓
Perfect! ✅
```
