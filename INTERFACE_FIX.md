# Fixed: Form Now Uses Interface

## The Problem

The form constructor was hardcoded to use `CmeshPushContentService`:

```php
public function __construct(CmeshPushContentService $command_executor) {
  $this->commandExecutor = $command_executor;
}
```

This meant even if you changed `services.yml` to use `SystemdCommandExecutorService`, the form would fail because of the type hint.

## The Solution

Created `CommandExecutorInterface` and updated the form to use it:

```php
public function __construct(CommandExecutorInterface $command_executor) {
  $this->commandExecutor = $command_executor;
}
```

Now the form works with **either** service implementation!

## What Changed

### 1. Created Interface

**File:** `src/Service/CommandExecutorInterface.php`

```php
interface CommandExecutorInterface {
  public function executeCommand($command);
  public function getStatus();
  public function stopCommand();
  public function clearState();
}
```

### 2. Updated Both Services

**CmeshPushContentService:**
```php
class CmeshPushContentService implements CommandExecutorInterface {
  // ... existing code ...
}
```

**SystemdCommandExecutorService:**
```php
class SystemdCommandExecutorService implements CommandExecutorInterface {
  // ... existing code ...
}
```

### 3. Updated Form

**CmeshPushContentForm:**
```php
use Drupal\cmesh_push_content\Service\CommandExecutorInterface;

class CmeshPushContentForm extends FormBase {
  protected $commandExecutor;

  public function __construct(CommandExecutorInterface $command_executor) {
    $this->commandExecutor = $command_executor;
  }
}
```

## How It Works Now

### Service Configuration (services.yml)

```yaml
services:
  # Default: SystemdCommandExecutorService
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

### Dependency Injection

```
services.yml defines: SystemdCommandExecutorService
         ↓
Container registers it as: cmesh_push_content.service
         ↓
Form requests: CommandExecutorInterface
         ↓
Container provides: SystemdCommandExecutorService (implements interface)
         ↓
Form works with: Whatever implementation is configured ✅
```

## Benefits

1. ✅ **Flexible:** Switch implementations by editing services.yml only
2. ✅ **Type-safe:** Interface ensures both implementations have same methods
3. ✅ **Proper DI:** Follows Drupal best practices
4. ✅ **Testable:** Can mock the interface in tests

## Switching Between Implementations

### Use Systemd (Recommended for PHP-FPM)

**Edit:** `cmesh_push_content.services.yml`

```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

### Use Direct Exec (Only for Apache mod_php)

**Edit:** `cmesh_push_content.services.yml`

```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\CmeshPushContentService
    arguments: ['@file_system', '@state']
```

Then always run:
```bash
drush cr
```

## Verify Which Service Is Active

```bash
drush php:eval "
\$service = \Drupal::service('cmesh_push_content.service');
echo 'Active service: ' . get_class(\$service) . \"\n\";
"
```

Output should be:
- `Drupal\cmesh_push_content\Service\SystemdCommandExecutorService` (for PHP-FPM)
- `Drupal\cmesh_push_content\Service\CmeshPushContentService` (for Apache mod_php)

## Testing

### 1. Clear Cache

```bash
drush cr
```

### 2. Check Service Type

```bash
drush php:eval "echo get_class(\Drupal::service('cmesh_push_content.service'));"
```

### 3. Test Form

Navigate to `/admin/config/system/cmesh-push-content` and click a button.

### 4. Check Logs

```bash
# Drupal logs
drush watchdog:show --type=cmesh_push_content

# Systemd logs (if using systemd service)
sudo journalctl -u cmesh-build@... -f
```

## Default Configuration

The module now ships with **SystemdCommandExecutorService as default** because:
- ✅ Works with PHP-FPM (most common)
- ✅ Works with Apache mod_php too
- ✅ More reliable process isolation
- ✅ Better logging and monitoring

If you want direct exec, just change services.yml and clear cache.

## Migration Path

If you have the old module installed:

```bash
# 1. Update module files
# 2. Clear cache
drush cr

# 3. Verify it works
drush php:eval "
\$form = \Drupal::formBuilder()->getForm('Drupal\cmesh_push_content\Form\CmeshPushContentForm');
echo 'Form loaded successfully';
"

# If you get errors about type hints, your cache wasn't cleared
# Try: drush cr --yes
```

## Summary

**Before:** Form hardcoded to `CmeshPushContentService` ❌

**After:** Form uses `CommandExecutorInterface` ✅

**Result:** Can switch between implementations via services.yml!

The form now properly uses dependency injection with an interface, making it work with both service implementations.
