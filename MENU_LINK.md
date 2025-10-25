# Menu Link Added

## What Changed

Added `cmesh_push_content.links.menu.yml` to make the module appear in the admin menu.

## Where It Appears

The module will now appear in:

**Configuration → System → Cmesh Push Content**

Path: `/admin/config/system`

## Menu Configuration

```yaml
cmesh_push_content.admin:
  title: 'Cmesh Push Content'
  description: 'Execute predefined commands and view real-time output'
  route_name: cmesh_push_content.page
  parent: system.admin_config_system
  weight: 50
```

## After Installing/Updating

**IMPORTANT:** You must clear cache for menu links to appear:

```bash
drush cr

# Or rebuild router specifically
drush router:rebuild
```

## Navigation

After cache clear, you can access the module via:

1. **Admin Menu:**
   - Click "Configuration" in admin toolbar
   - Click "System"
   - Find "Cmesh Push Content"

2. **Direct URL:**
   - `/admin/config/system/cmesh-push-content`

3. **Admin Menu Search:**
   - Type "cmesh" in admin toolbar search
   - Click "Cmesh Push Content"

## Customizing Menu Location

To change where it appears in the menu, edit `cmesh_push_content.links.menu.yml`:

### Different Parent Locations

**Under Development:**
```yaml
parent: system.admin_config_development
```

**Under Content:**
```yaml
parent: system.admin_content
```

**Under Reports:**
```yaml
parent: system.admin_reports
```

**Top Level Configuration:**
```yaml
parent: system.admin_config
```

### Change Order

Adjust the `weight` value:
- Lower numbers appear higher in menu
- Higher numbers appear lower in menu
- Default: 50

```yaml
weight: 10  # Appears near top
weight: 99  # Appears near bottom
```

## If Menu Link Doesn't Appear

### 1. Clear Cache
```bash
drush cr
drush router:rebuild
```

### 2. Check File Exists
```bash
ls -la modules/custom/cmesh_push_content/cmesh_push_content.links.menu.yml
```

### 3. Check File Contents
```bash
cat modules/custom/cmesh_push_content/cmesh_push_content.links.menu.yml
```

Should show the menu configuration.

### 4. Verify Module is Enabled
```bash
drush pm:list | grep cmesh
```

Should show: `Enabled`

### 5. Rebuild Menu
```bash
drush cr
drush router:rebuild
drush cache:rebuild
```

### 6. Check in UI

Go to: `/admin/structure/menu/manage/admin`

Search for "Cmesh" - should appear in the list.

## Manual Menu Link (Alternative)

If automatic menu link doesn't work, you can add it manually:

1. Go to `/admin/structure/menu/manage/admin`
2. Click "Add link"
3. Fill in:
   - **Menu link title:** Cmesh Push Content
   - **Link:** `/admin/config/system/cmesh-push-content`
   - **Description:** Execute predefined commands and view real-time output
   - **Parent link:** <System>
   - **Weight:** 50
4. Click "Save"

## Verification

After clearing cache, check:

1. Go to `/admin/config/system`
2. You should see "Cmesh Push Content" in the list
3. Click it to access the module
4. URL should be: `/admin/config/system/cmesh-push-content`

## Files Updated

Only one new file added:
- `cmesh_push_content.links.menu.yml`

All other files remain unchanged.
