# Module Renamed: cmesh_push_content

## What Changed

The module has been renamed from `command_executor` to `cmesh_push_content`.

### All Changes:
- **Module name:** command_executor → cmesh_push_content
- **Path:** /admin/config/system/command-executor → /admin/config/system/cmesh-push-content
- **Namespace:** Drupal\command_executor → Drupal\cmesh_push_content
- **Service:** command_executor.service → cmesh_push_content.service
- **State key:** command_executor.current → cmesh_push_content.current
- **Classes:**
  - CommandExecutorService → CmeshPushContentService
  - CommandExecutorForm → CmeshPushContentForm
  - CommandExecutorController → CmeshPushContentController

## Fresh Installation

If installing for the first time:

```bash
# Extract to modules/custom
cd /path/to/drupal/web/modules/custom
tar -xzf cmesh_push_content.tar.gz

# Enable module
drush en cmesh_push_content -y
drush cr

# Access at:
# /admin/config/system/cmesh-push-content
```

## Migrating from command_executor

If you already have command_executor installed:

### Step 1: Uninstall Old Module
```bash
# Uninstall old module
drush pm:uninstall command_executor

# Delete old directory
rm -rf modules/custom/command_executor
```

### Step 2: Install New Module
```bash
# Extract new module
cd modules/custom
tar -xzf cmesh_push_content.tar.gz

# Enable new module
drush en cmesh_push_content -y
drush cr
```

### Step 3: Update Bookmarks
Old URL: `/admin/config/system/command-executor`
New URL: `/admin/config/system/cmesh-push-content`

## File Structure

```
cmesh_push_content/
├── cmesh_push_content.info.yml
├── cmesh_push_content.routing.yml
├── cmesh_push_content.services.yml
├── cmesh_push_content.libraries.yml
├── src/
│   ├── Controller/
│   │   └── CmeshPushContentController.php
│   ├── Form/
│   │   └── CmeshPushContentForm.php
│   └── Service/
│       └── CmeshPushContentService.php
├── js/
│   └── cmesh_push_content.js
├── css/
│   └── cmesh_push_content.css
└── *.md (documentation)
```

## Important Notes

1. **State data will NOT transfer** - If you had a command running with the old module, it will be lost after uninstalling
2. **Clear cache after migration** - Always run `drush cr` after installing
3. **Update any custom code** - If you have custom code referencing the old module, update it
4. **Browser cache** - Clear browser cache to load new JavaScript/CSS

## Routes

- **Main page:** /admin/config/system/cmesh-push-content
- **Status endpoint:** /admin/config/system/cmesh-push-content/status
- **Execute endpoint:** /admin/config/system/cmesh-push-content/execute

## Permissions

Same as before: `administer site configuration`

## Functionality

All functionality remains the same:
- ✅ Two predefined command buttons
- ✅ Real-time output display
- ✅ Background execution
- ✅ Auto-polling updates
- ✅ Process state persistence

## Troubleshooting After Rename

### "Module not found"
- Check directory name is `cmesh_push_content`
- Check files are in `modules/custom/cmesh_push_content/`
- Run `drush cr`

### "Class not found" errors
- Verify file names match class names
- Check namespaces are correct
- Run `drush cr` and `composer dump-autoload`

### JavaScript not working
- Clear browser cache (Ctrl+Shift+R)
- Check browser console for errors
- Verify JS file is `js/cmesh_push_content.js`
- Run `drush cr`

### CSS not loading
- Clear cache: `drush cr`
- Hard refresh browser
- Check file is `css/cmesh_push_content.css`

## Verification

After installation, verify:

```bash
# Module is enabled
drush pm:list | grep cmesh

# Should show:
# Cmesh Push Content (cmesh_push_content)  Enabled

# Check routes exist
drush route | grep cmesh

# Should show three routes
```

## Support Files Included

- README.md - General documentation
- INSTALL.md - Installation guide
- TROUBLESHOOTING.md - Debug help
- CACHE_CLEAR.md - Cache clearing guide
- FIX_SUMMARY.md - Bug fix history
- REALTIME_FIX.md - Polling fix details
- ONCE_FIX.md - once() error fix
- This file - Migration guide
