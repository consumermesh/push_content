# Output Retention Feature

## What Changed

The output is now **retained after the command completes** instead of being cleared.

## New Behavior

### Before (Old):
1. Command runs
2. Output displays in real-time
3. Command completes
4. **Output disappears** ❌
5. Form resets to show buttons

### After (New):
1. Command runs
2. Output displays in real-time
3. Command completes
4. **Output is retained** ✅
5. Completion message shows
6. "Clear Output" button appears

## Features Added

### 1. Completion Status Message

When a command finishes, you'll see:

```
Command completed successfully!
Command: /opt/cmesh/scripts/pushfin.sh -o 'mars' -n 'mpvg'
Started: 2025-10-24 17:38:14
Completed: 2025-10-24 17:38:45
Duration: 31 seconds
```

### 2. Clear Output Button

After completion, a "Clear Output" button appears that:
- Clears the output textarea
- Removes the completion message
- Shows the environment buttons again
- Ready for next command

### 3. Retained Output

The complete command output stays visible until you:
- Click "Clear Output" button
- Run another command
- Navigate away and back (output is stored in state)

## How It Works Internally

### State Management

**While Running:**
```php
[
  'is_running' => true,
  'output' => 'Real-time output...',
  'command' => 'command here',
  'started' => 1234567890,
  'pid' => 12345,
]
```

**After Completion:**
```php
[
  'is_running' => false,
  'output' => 'Complete output...',
  'command' => 'command here',
  'started' => 1234567890,
  'completed' => 1234567921,  // NEW
  'final_output' => 'Complete output...',  // NEW (stored copy)
  'pid' => 12345,
]
```

### File Cleanup

**Before:** Everything deleted when command finishes
- ❌ Output file deleted
- ❌ State cleared
- ❌ Output lost

**After:** Files deleted but state preserved
- ✅ Output file deleted (temp cleanup)
- ✅ Output stored in state
- ✅ State kept until manually cleared
- ✅ Output retained and visible

## User Workflow

### Running a Command

1. **Click "Push to dev"**
   ```
   Status: Command is currently running...
   PID: 12345
   Started: 2025-10-24 17:38:14
   
   [Output shows here in real-time]
   
   [Stop Command] button
   ```

2. **Command Completes**
   ```
   Status: Command completed successfully!
   Command: /opt/cmesh/scripts/pushfin.sh...
   Started: 2025-10-24 17:38:14
   Completed: 2025-10-24 17:38:45
   Duration: 31 seconds
   
   [Full output still visible here]
   
   [Clear Output] button
   ```

3. **Review Output**
   - Scroll through complete output
   - Copy output if needed
   - Check for errors
   - Verify completion

4. **Clear When Ready**
   - Click "Clear Output"
   - Form resets
   - Buttons appear again
   - Ready for next command

## JavaScript Changes

Polling now recognizes completed state:

```javascript
console.log('Status check:', 
  data.is_running ? 'Running' : 
  (data.completed ? 'Completed' : 'Not running')
);
```

**Console output examples:**
- While running: `Status check: Running`
- After completion: `Status check: Completed`
- After clearing: `Status check: Not running`

## Benefits

### 1. Debugging
- Review complete output after completion
- Copy error messages
- Check exit codes
- Verify success

### 2. User Experience
- No lost information
- Clear completion indicator
- Duration tracking
- Manual control over when to clear

### 3. Workflow
- Review results before next action
- Keep output for reference
- Multiple people can see same result
- Output survives page refresh

## API Changes

### Service: getStatus()

Returns new fields:
```php
[
  'is_running' => false,
  'completed' => 1234567921,  // NEW: timestamp when completed
  'output' => 'full output',
  // ... existing fields
]
```

### Service: clearState()

New method to manually clear state:
```php
$this->commandExecutor->clearState();
```

### Form: clearOutput()

New submit handler:
```php
public function clearOutput(array &$form, FormStateInterface $form_state) {
  $this->commandExecutor->clearState();
  $this->messenger()->addStatus($this->t('Output cleared.'));
  $form_state->setRebuild(TRUE);
}
```

## Edge Cases Handled

### Page Refresh
- State persists in Drupal State API
- Output remains visible
- Completion message shows
- "Clear Output" button available

### Multiple Users
- Each sees the same completed output
- Any user can clear output
- Next command overwrites state

### Long Output
- Complete output stored in state (not temp file)
- No file size limits
- Retained until manually cleared

### Failed Commands
- Exit code shown in output
- Completion message still displays
- Output retained for debugging
- Can review errors

## Comparison

### Old Behavior:
```
[Click button] → Running → Completed → ❌ Output gone → Buttons
```

### New Behavior:
```
[Click button] → Running → Completed → ✅ Output retained → [Clear] → Buttons
```

## Testing

After updating:

1. **Run a command:**
   ```
   Click "Push to dev"
   ```

2. **Wait for completion**
   - Should see completion message
   - Should see duration
   - Output should remain visible

3. **Verify retention:**
   - Refresh page
   - Output should still be there
   - Completion message should still show

4. **Clear output:**
   - Click "Clear Output"
   - Output should clear
   - Buttons should reappear

## Files Modified

1. **src/Service/CmeshPushContentService.php**
   - Modified `getStatus()` to store completed state
   - Added `cleanupFiles()` method
   - Modified `cleanup()` to use cleanupFiles
   - Added `clearState()` method

2. **src/Form/CmeshPushContentForm.php**
   - Added completion status message
   - Added "Clear Output" button
   - Added `clearOutput()` submit handler
   - Show duration calculation

3. **js/cmesh_push_content.js**
   - Updated console logging for completed state

## Troubleshooting

### Output still disappearing
- Clear cache: `drush cr`
- Check service code has new changes
- Verify state is being set

### "Clear Output" button not appearing
- Check if status has 'completed' key
- Clear cache: `drush cr`
- Check form code updated

### Duration shows negative/wrong
- Server time zone issue
- Check timestamps are correct
- Verify time() is working

### State not persisting
- Check State API is working
- Run: `drush state:get cmesh_push_content.current`
- Should show stored data
