# Command Executor - Installation Guide

## Quick Start

1. **Extract the module:**
   ```bash
   cd /path/to/drupal/web/modules/custom
   tar -xzf command_executor.tar.gz
   ```

2. **Enable the module:**
   ```bash
   drush en command_executor -y
   drush cr
   ```
   
   Or via UI: Go to `/admin/modules`, find "Command Executor", check the box, and click "Install"

3. **Access the interface:**
   Navigate to `/admin/config/system/command-executor`

## Usage

1. Click "Run System Info Check" to view system information
2. Click "List Temp Directory" to see /tmp contents
3. Watch the output appear in real-time
4. Navigate away and come back - the status will persist
5. Once complete, logs are automatically cleaned up

## Predefined Commands

### Command 1: System Info Check
Displays comprehensive system information including hostname, OS, kernel version, uptime, disk usage, and memory usage.

### Command 2: List Temp Directory
Shows detailed listing of /tmp directory and disk usage of the largest files.

## Adding Custom Commands

To add your own predefined commands, edit `src/Form/CommandExecutorForm.php`:

```php
// In the buildForm method, add a new button:
$form['actions']['command3'] = [
  '#type' => 'submit',
  '#value' => $this->t('Your Command Name'),
  '#submit' => ['::executeCommand3'],
  '#attributes' => ['class' => ['button', 'button--primary']],
];

// Add the corresponding submit handler:
public function executeCommand3(array &$form, FormStateInterface $form_state) {
  $command = 'echo "Your command here" && your-actual-command';
  $this->executeCommand($command, 'Your Command Description');
  $form_state->setRebuild(TRUE);
}
```

## Troubleshooting

### "Call to undefined method" errors
- Run `drush cr` to clear cache
- Ensure all files are properly placed in the module directory

### Commands not executing
- Check PHP `exec()` function is enabled (not in `disable_functions`)
- Verify web server user has necessary permissions
- Check PHP error logs for details

### Output not updating
- Check browser console for JavaScript errors
- Verify AJAX endpoints are accessible
- Clear Drupal cache: `drush cr`

### Process still shows running after completion
- The process monitoring uses the `ps` command
- Ensure your system has `ps` available
- Check that PIDs are being written correctly to temp files

## Security Notes

⚠️ **IMPORTANT:** This module allows execution of arbitrary shell commands!

- Only grant access to trusted administrators
- Consider additional access restrictions in production
- Monitor usage and logs regularly
- Be cautious about what commands you allow

## File Locations

- **Module files:** `modules/custom/command_executor/`
- **Temp files:** System temp directory (e.g., `/tmp/`)
- **State storage:** Drupal State API (database)

## Uninstallation

```bash
drush pm:uninstall command_executor
```

This will:
- Remove all module files when you delete the directory
- Clean up the state storage
- Remove any remaining temporary files

## Requirements

- Drupal 10
- PHP `exec()` function enabled
- Unix-like OS (uses `ps` command)
- Web server with command execution permissions

## Support

For issues or feature requests, check the README.md file in the module directory.
