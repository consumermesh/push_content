# Environment Configuration Files

## The Error You Saw

```
$org = 'mars';
$name = 'mpvg';
```

This output appeared in the AJAX response because the `.env.inc` file was outputting PHP code as text instead of executing it.

## Root Cause

The `.env.inc` file likely had a syntax error or was missing the PHP opening tag.

## Correct Format for .env.inc Files

### Option 1: Pure PHP (RECOMMENDED)

**File:** `config/dev.env.inc`

```php
<?php

/**
 * Environment configuration for dev.
 */

$org = 'mars';
$name = 'mpvg';
```

**Important:**
- ✅ Must start with `<?php`
- ✅ No closing `?>` tag needed
- ✅ No whitespace before `<?php`
- ✅ Variables are set, not echoed
- ✅ No output statements

### Option 2: Using return statement (BETTER)

**File:** `config/dev.env.inc`

```php
<?php

/**
 * Environment configuration for dev.
 */

return [
  'org' => 'mars',
  'name' => 'mpvg',
];
```

Then update the form to use this:

```php
public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
  $trigger = $form_state->getTriggeringElement();
  $envKey = $trigger['#env_key'];

  $inc = dirname(__DIR__, 2) . "/config/{$envKey}.env.inc";
  
  // Default values
  $config = [
    'org' => 'mars',
    'name' => 'mpvg',
  ];
  
  // Include the environment file if it exists
  if (is_file($inc)) {
    $envConfig = include $inc;
    if (is_array($envConfig)) {
      $config = array_merge($config, $envConfig);
    }
  }

  $command = sprintf(
    '/opt/cmesh/scripts/pushfin.sh -o %s -n %s',
    escapeshellarg($config['org']),
    escapeshellarg($config['name'])
  );

  $this->executeCommand($command, "Push to $envKey");
  $form_state->setRebuild(TRUE);
}
```

### Option 3: YAML Configuration (BEST for Drupal)

Instead of `.env.inc` files, use YAML:

**File:** `config/dev.env.yml`

```yaml
org: mars
name: mpvg
```

**File:** `config/staging.env.yml`

```yaml
org: acme
name: prod-site
```

**File:** `config/prod.env.yml`

```yaml
org: company
name: production
```

Then update the form:

```php
use Symfony\Component\Yaml\Yaml;

private function listEnvironments(): array {
  $dir = dirname(__DIR__, 2) . '/config';
  $list = glob("$dir/*.env.yml");
  return array_map(
    fn($f) => basename($f, '.env.yml'),
    $list
  );
}

public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
  $trigger = $form_state->getTriggeringElement();
  $envKey = $trigger['#env_key'];

  $yml = dirname(__DIR__, 2) . "/config/{$envKey}.env.yml";
  
  // Default values
  $config = [
    'org' => 'mars',
    'name' => 'mpvg',
  ];
  
  // Load YAML config
  if (is_file($yml)) {
    try {
      $envConfig = Yaml::parseFile($yml);
      if (is_array($envConfig)) {
        $config = array_merge($config, $envConfig);
      }
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error loading config: @msg', ['@msg' => $e->getMessage()]));
      return;
    }
  }

  $command = sprintf(
    '/opt/cmesh/scripts/pushfin.sh -o %s -n %s',
    escapeshellarg($config['org']),
    escapeshellarg($config['name'])
  );

  $this->executeCommand($command, "Push to $envKey");
  $form_state->setRebuild(TRUE);
}
```

## Current Solution (Output Buffering)

The current code uses `ob_start()` and `ob_end_clean()` to capture and discard any output:

```php
if (is_file($inc)) {
  ob_start();
  include $inc;
  ob_end_clean();
}
```

This prevents PHP output from leaking into the AJAX response, but it's better to fix the source files.

## File Structure Examples

### Multiple Environments

```
cmesh_push_content/
├── config/
│   ├── dev.env.inc       # Development
│   ├── staging.env.inc   # Staging
│   └── prod.env.inc      # Production
```

### Each file should look like:

**dev.env.inc:**
```php
<?php
$org = 'mars';
$name = 'dev-site';
```

**staging.env.inc:**
```php
<?php
$org = 'mars';
$name = 'staging-site';
```

**prod.env.inc:**
```php
<?php
$org = 'acme';
$name = 'production-site';
```

## Common Mistakes

### ❌ Wrong: Missing PHP tag
```php
$org = 'mars';
$name = 'mpvg';
```
**Result:** File is output as plain text

### ❌ Wrong: Using echo
```php
<?php
echo "$org = 'mars';\n";
echo "$name = 'mpvg';\n";
```
**Result:** Output appears in AJAX response

### ❌ Wrong: HTML in file
```html
<html>
<?php $org = 'mars'; ?>
```
**Result:** HTML output in AJAX response

### ✅ Correct: Pure PHP
```php
<?php
$org = 'mars';
$name = 'mpvg';
```

### ✅ Correct: Return array
```php
<?php
return [
  'org' => 'mars',
  'name' => 'mpvg',
];
```

## Debugging

### Check if file has proper PHP tag:

```bash
head -1 config/dev.env.inc
# Should output: <?php
```

### Validate PHP syntax:

```bash
php -l config/dev.env.inc
# Should output: No syntax errors detected
```

### Test file manually:

```bash
php -r "include 'config/dev.env.inc'; echo \$org . ' ' . \$name;"
# Should output: mars mpvg
```

## Best Practices

1. **Use YAML for configuration** (most Drupal-like)
2. **Use return arrays** (cleaner, testable)
3. **Add comments** to explain each setting
4. **Validate configs** on module install
5. **Provide defaults** in code
6. **Don't commit sensitive data** to git

## Security Note

If these files contain sensitive data:

1. Add to `.gitignore`:
   ```
   /modules/custom/cmesh_push_content/config/*.env.inc
   /modules/custom/cmesh_push_content/config/*.env.yml
   ```

2. Provide example files:
   ```
   config/dev.env.inc.example
   config/staging.env.inc.example
   config/prod.env.inc.example
   ```

3. Document in README how to create the real files
