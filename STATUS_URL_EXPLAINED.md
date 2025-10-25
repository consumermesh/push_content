# How statusUrl Works

## Overview

The `statusUrl` is the AJAX endpoint that JavaScript polls to get real-time command output and status updates.

## Flow Diagram

```
PHP Form (CmeshPushContentForm.php)
    ↓
Sets drupalSettings['cmeshPushContent']['statusUrl']
    ↓
Renders to browser with JavaScript
    ↓
JavaScript (cmesh_push_content.js) reads statusUrl
    ↓
Polls endpoint every 1 second
    ↓
PHP Controller (CmeshPushContentController.php)
    ↓
Returns JSON with status and output
    ↓
JavaScript updates textarea
```

## Code Walkthrough

### 1. PHP Form Sets the URL (Line 57-58)

**File:** `src/Form/CmeshPushContentForm.php`

```php
public function buildForm(array $form, FormStateInterface $form_state) {
  // ...
  
  // Set the status URL for JavaScript to use
  $form['#attached']['drupalSettings']['cmeshPushContent']['statusUrl'] = 
    Url::fromRoute('cmesh_push_content.status')->toString();
    
  $form['#attached']['drupalSettings']['cmeshPushContent']['executeUrl'] = 
    Url::fromRoute('cmesh_push_content.execute')->toString();
  
  // ...
}
```

**What it does:**
- `Url::fromRoute('cmesh_push_content.status')` - Gets the URL for the status route
- `->toString()` - Converts to string like `/admin/config/system/cmesh-push-content/status`
- `drupalSettings` - Drupal's way of passing data from PHP to JavaScript
- This becomes available as `drupalSettings.cmeshPushContent.statusUrl` in JavaScript

### 2. Route Definition

**File:** `cmesh_push_content.routing.yml`

```yaml
cmesh_push_content.status:
  path: '/admin/config/system/cmesh-push-content/status'
  defaults:
    _controller: '\Drupal\cmesh_push_content\Controller\CmeshPushContentController::status'
  requirements:
    _permission: 'administer site configuration'
  methods: [GET]
```

**What it defines:**
- **Route name:** `cmesh_push_content.status`
- **URL path:** `/admin/config/system/cmesh-push-content/status`
- **Controller method:** `CmeshPushContentController::status()`
- **Permission required:** Admin
- **HTTP method:** GET

### 3. JavaScript Reads and Uses URL

**File:** `js/cmesh_push_content.js` (Line 26)

```javascript
Drupal.behaviors.cmeshPushContent = {
  attach: function (context, settings) {
    // ...
    
    // Read the URL that was set by PHP
    var statusUrl = drupalSettings.cmeshPushContent.statusUrl;
    
    // Use it in AJAX request
    function checkStatus() {
      $.ajax({
        url: statusUrl,  // Uses the URL from PHP
        method: 'GET',
        dataType: 'json',
        cache: false,
        success: function (data) {
          // Update output with response
          $('#command-output').val(data.output || '');
          // ...
        }
      });
    }
    
    // Poll every 1 second
    setInterval(checkStatus, 1000);
  }
};
```

### 4. PHP Controller Handles Request

**File:** `src/Controller/CmeshPushContentController.php`

```php
public function status() {
  $status = $this->commandExecutor->getStatus();

  if (!$status) {
    return new JsonResponse([
      'is_running' => FALSE,
      'output' => '',
    ]);
  }

  return new JsonResponse($status);
}
```

**Returns JSON like:**
```json
{
  "is_running": true,
  "output": "System information...\nHostname: example.com\n...",
  "command": "echo 'test'",
  "started": 1234567890,
  "pid": 12345
}
```

### 5. Service Provides Status

**File:** `src/Service/CmeshPushContentService.php`

```php
public function getStatus() {
  // Get stored process info from state
  $process_info = $this->state->get('cmesh_push_content.current');
  
  if (!$process_info) {
    return NULL;
  }

  // Check if still running
  $is_running = $this->isProcessRunning($process_info['pid']);

  // Read output file
  $output = '';
  if (file_exists($process_info['output_file'])) {
    $output = file_get_contents($process_info['output_file']);
  }

  return [
    'is_running' => $is_running,
    'output' => $output,
    'command' => $process_info['command'],
    'started' => $process_info['started'],
    'pid' => $process_info['pid'],
  ];
}
```

## Complete Request/Response Cycle

### When Command is Running:

**Request:**
```http
GET /admin/config/system/cmesh-push-content/status HTTP/1.1
```

**Response:**
```json
{
  "is_running": true,
  "output": "=== System Information ===\nHostname: web-server\nOS: Linux\n...",
  "command": "echo 'System info' && hostname",
  "started": 1698765432,
  "pid": 54321
}
```

### When No Command Running:

**Request:**
```http
GET /admin/config/system/cmesh-push-content/status HTTP/1.1
```

**Response:**
```json
{
  "is_running": false,
  "output": ""
}
```

## Debugging statusUrl

### Check URL is Set Correctly

In browser console:
```javascript
console.log(drupalSettings.cmeshPushContent.statusUrl);
// Should output: "/admin/config/system/cmesh-push-content/status"
```

### Test Endpoint Directly

```bash
# Using curl (when logged in)
curl -X GET 'http://your-site.com/admin/config/system/cmesh-push-content/status' \
  -H 'Cookie: YOUR_SESSION_COOKIE'

# Should return JSON
```

### Check in Browser Network Tab

1. Open DevTools (F12)
2. Go to Network tab
3. Filter by "XHR" or "Fetch"
4. Watch for requests to `/status`
5. Should see one every 1 second when command running

### Common Issues

**statusUrl is undefined:**
- Check drupalSettings is loaded: `console.log(drupalSettings)`
- Verify form is attaching the settings
- Clear cache: `drush cr`

**404 on status endpoint:**
- Route not registered
- Clear cache: `drush cr`
- Rebuild router: `drush router:rebuild`

**Permission denied:**
- User doesn't have "administer site configuration" permission
- Check user permissions in Drupal

**No polling happening:**
- JavaScript not loading
- Check browser console for errors
- Verify library is attached to form

## Configuration

All configuration happens in these files:

1. **Routing** (`cmesh_push_content.routing.yml`) - Defines the endpoint URL
2. **Form** (`CmeshPushContentForm.php`) - Passes URL to JavaScript
3. **JavaScript** (`cmesh_push_content.js`) - Polls the endpoint
4. **Controller** (`CmeshPushContentController.php`) - Handles the request
5. **Service** (`CmeshPushContentService.php`) - Gets the actual status

## Summary

The `statusUrl` is:
- **Set by:** PHP form using Drupal's Url::fromRoute()
- **Stored in:** drupalSettings JavaScript object
- **Used by:** JavaScript for AJAX polling
- **Points to:** `/admin/config/system/cmesh-push-content/status`
- **Returns:** JSON with command status and output
- **Polled:** Every 1 second while command is running
