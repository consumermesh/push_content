# cmesh_push_content - Installation & Usage

## Module Information

- **Name:** Cmesh Push Content
- **Machine Name:** cmesh_push_content
- **Path:** /admin/config/system/cmesh-push-content
- **Drupal Version:** 10
- **Type:** Custom module

## Quick Install

```bash
# Extract module
cd /path/to/drupal/web/modules/custom
tar -xzf cmesh_push_content.tar.gz

# Enable module
drush en cmesh_push_content -y

# Clear cache
drush cr

# Access the module
# Navigate to: /admin/config/system/cmesh-push-content
```

## Features

This module provides a web interface to execute predefined command-line operations with real-time output display.

### Predefined Commands

1. **Run System Info Check**
   - Displays hostname, OS, kernel, CPU, uptime
   - Shows disk and memory usage
   - Provides system architecture details

2. **List Temp Directory**
   - Shows /tmp directory contents
   - Displays top 10 largest files
   - Shows overall disk usage

### Key Features

✅ Real-time output updates (every 1 second)
✅ Background command execution
✅ Process state persistence (survives page refreshes)
✅ Auto-scrolling output
✅ Stop running commands
✅ Status indicators
✅ AJAX form submission (no page reloads)
✅ Automatic cleanup when commands complete

## Access Control

Required permission: **Administer site configuration**

Only users with this permission can access and use the module.

## Usage

1. Navigate to `/admin/config/system/cmesh-push-content`
2. Click one of the two predefined command buttons
3. Watch output appear in real-time
4. Output updates automatically every second
5. Click "Stop Command" to terminate running commands
6. Navigate away and back - status persists

## Technical Details

### Routes

- **Main page:** cmesh_push_content.page
  - Path: /admin/config/system/cmesh-push-content
  
- **Status endpoint:** cmesh_push_content.status
  - Path: /admin/config/system/cmesh-push-content/status
  - Method: GET
  
- **Execute endpoint:** cmesh_push_content.execute
  - Path: /admin/config/system/cmesh-push-content/execute
  - Method: POST

### Service

- **Service ID:** cmesh_push_content.service
- **Class:** Drupal\cmesh_push_content\Service\CmeshPushContentService
- **Dependencies:** @file_system, @state

### State Storage

Commands use Drupal's State API:
- **Key:** cmesh_push_content.current
- **Contains:** PID, command, output file path, timestamps

### Files

```
cmesh_push_content/
├── cmesh_push_content.info.yml          # Module definition
├── cmesh_push_content.routing.yml       # Routes
├── cmesh_push_content.services.yml      # Service container
├── cmesh_push_content.libraries.yml     # JS/CSS assets
├── src/
│   ├── Controller/
│   │   └── CmeshPushContentController.php   # AJAX endpoints
│   ├── Form/
│   │   └── CmeshPushContentForm.php         # Main UI form
│   └── Service/
│       └── CmeshPushContentService.php      # Command execution
├── js/
│   └── cmesh_push_content.js            # Real-time polling
├── css/
│   └── cmesh_push_content.css           # Styling
└── Documentation files (*.md)
```

## Customization

To add more predefined commands, edit `src/Form/CmeshPushContentForm.php`:

```php
// Add button
$form['actions']['command3'] = [
  '#type' => 'submit',
  '#value' => $this->t('Your Command'),
  '#submit' => ['::executeCommand3'],
  '#ajax' => [
    'callback' => '::ajaxRebuildForm',
    'wrapper' => 'cmesh-push-content-form',
    'effect' => 'fade',
  ],
  '#attributes' => ['class' => ['button', 'button--primary']],
];

// Add handler
public function executeCommand3(array &$form, FormStateInterface $form_state) {
  $command = 'your-command-here';
  $this->executeCommand($command, 'Your Description');
  $form_state->setRebuild(TRUE);
}
```

## Requirements

- Drupal 10
- PHP `exec()` function enabled
- Unix-like OS (uses `ps`, `bash`, etc.)
- Write access to temp directory
- Optional: `stdbuf` for better real-time output

## Troubleshooting

### Common Issues

**No output appearing:**
- Check PHP `exec()` is enabled: `php -i | grep disable_functions`
- Verify temp directory permissions
- Check browser console for JavaScript errors

**JavaScript errors:**
- Clear Drupal cache: `drush cr`
- Clear browser cache: Ctrl+Shift+R
- Check console for specific errors

**Commands not executing:**
- Verify web server user has permissions
- Check that commands exist and are in PATH
- Look at Drupal logs: `/admin/reports/dblog`

**Output not updating:**
- Open browser console (F12)
- Should see "Status check: Running" every second
- If not, check JavaScript is loaded

See `TROUBLESHOOTING.md` for detailed debug steps.

## Documentation Files

- **README.md** - General overview
- **INSTALL.md** - Installation guide
- **TROUBLESHOOTING.md** - Debug help
- **CACHE_CLEAR.md** - Cache clearing guide
- **RENAME_GUIDE.md** - Migration from old name
- **FIX_SUMMARY.md** - Bug fixes applied
- **REALTIME_FIX.md** - Polling fix details
- **ONCE_FIX.md** - JavaScript fixes

## Support

Check the included documentation files for:
- Installation help
- Troubleshooting steps
- Configuration options
- Customization examples

## Security Notes

⚠️ This module executes shell commands with web server permissions.

- Only predefined commands can be executed
- Requires administrator permissions
- Commands run as web server user (www-data, apache, etc.)
- Output/logs are automatically cleaned up
- Not suitable for untrusted users

## Uninstallation

```bash
# Uninstall module
drush pm:uninstall cmesh_push_content

# Remove directory
rm -rf modules/custom/cmesh_push_content
```

## Version

Current version: 2.0
- Fixed JavaScript polling
- Fixed once() error
- AJAX form submission
- Real-time updates working
