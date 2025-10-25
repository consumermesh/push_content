# statusUrl Flow - Visual Diagram

## How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. USER CLICKS BUTTON                                           │
│    "Run System Info Check"                                      │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. FORM SUBMITS (AJAX)                                          │
│    CmeshPushContentForm::executeCommand1()                      │
│    ├─ Calls: CmeshPushContentService::executeCommand()          │
│    ├─ Creates: bash script in /tmp                             │
│    ├─ Runs: Script in background                               │
│    └─ Stores: PID, output file path in Drupal State            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. FORM REBUILDS (AJAX)                                         │
│    Form sends to browser:                                       │
│    drupalSettings.cmeshPushContent.statusUrl =                  │
│        "/admin/config/system/cmesh-push-content/status"         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. JAVASCRIPT STARTS POLLING                                    │
│    Every 1 second:                                              │
│    $.ajax({                                                     │
│      url: drupalSettings.cmeshPushContent.statusUrl,            │
│      method: 'GET'                                              │
│    })                                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. CONTROLLER RECEIVES REQUEST                                  │
│    CmeshPushContentController::status()                         │
│    ├─ Calls: CmeshPushContentService::getStatus()               │
│    ├─ Reads: State to get process info                         │
│    ├─ Checks: If process still running (ps command)            │
│    ├─ Reads: Output file (/tmp/cmd_xxx_output.log)             │
│    └─ Returns: JSON response                                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. JSON RESPONSE                                                │
│    {                                                            │
│      "is_running": true,                                        │
│      "output": "System info...\nHostname: web\n...",            │
│      "command": "echo ...",                                     │
│      "pid": 12345                                               │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. JAVASCRIPT UPDATES UI                                        │
│    $('#command-output').val(data.output);                       │
│    ├─ Updates textarea with output                             │
│    ├─ Auto-scrolls to bottom                                   │
│    └─ Continues polling if is_running = true                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ (Repeats every 1 second)
                         │
                         ▼
         ┌───────────────────────────────┐
         │ COMMAND COMPLETES             │
         │ is_running = false            │
         └───────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 8. CLEANUP                                                      │
│    CmeshPushContentService::cleanup()                           │
│    ├─ Deletes: /tmp/cmd_xxx_output.log                         │
│    ├─ Deletes: /tmp/cmd_xxx_pid.txt                            │
│    ├─ Deletes: /tmp/cmd_xxx_script.sh                          │
│    └─ Clears: Drupal State (cmesh_push_content.current)        │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │ JAVASCRIPT STOPS POLLING      │
         │ User can click button again   │
         └───────────────────────────────┘
```

## Key Files Involved

```
statusUrl Creation:
├─ cmesh_push_content.routing.yml .......... Defines route
├─ src/Form/CmeshPushContentForm.php ....... Sets drupalSettings
└─ js/cmesh_push_content.js ................ Reads and uses URL

Status Endpoint:
├─ src/Controller/CmeshPushContentController.php ... Handles request
└─ src/Service/CmeshPushContentService.php ......... Provides data

Data Storage:
├─ Drupal State API ....................... Stores process info
└─ /tmp/cmd_xxx_output.log ............... Stores command output
```

## Data Flow

```
PHP Form
  │
  ├─ Creates: drupalSettings.cmeshPushContent.statusUrl
  │            = "/admin/config/system/cmesh-push-content/status"
  │
  └─ Renders to browser HTML with <script> tag
              │
              ▼
        JavaScript reads drupalSettings
              │
              ├─ statusUrl = drupalSettings.cmeshPushContent.statusUrl
              │
              └─ Makes AJAX GET request to statusUrl every 1 second
                          │
                          ▼
                  Drupal routing system
                          │
                          ├─ Matches: cmesh_push_content.status route
                          │
                          └─ Calls: CmeshPushContentController::status()
                                      │
                                      ├─ Service gets status from State
                                      │
                                      └─ Returns JSON
                                              │
                                              ▼
                                        JavaScript receives JSON
                                              │
                                              └─ Updates <textarea>
```

## Sequence When You Click "Run System Info"

```
Time  Action
────  ──────────────────────────────────────────────────────────
0.0s  User clicks "Run System Info Check"
0.1s  AJAX submits form
0.2s  PHP creates script, executes in background
0.3s  Form rebuilds via AJAX with new state
0.4s  JavaScript detects form updated
0.5s  JavaScript starts polling statusUrl
1.5s  First poll: is_running=true, output="Starting..."
2.5s  Second poll: is_running=true, output="Starting...\nHostname: web"
3.5s  Third poll: is_running=true, output="Starting...\nHostname: web\nOS: Linux"
4.5s  Fourth poll: is_running=false, output="...[Command completed]"
4.6s  JavaScript stops polling
4.7s  Cleanup: temp files deleted, state cleared
```

## URL Examples

**Development:**
- statusUrl: `http://localhost/admin/config/system/cmesh-push-content/status`

**Production:**
- statusUrl: `https://example.com/admin/config/system/cmesh-push-content/status`

**With Subpath:**
- statusUrl: `https://example.com/drupal/admin/config/system/cmesh-push-content/status`

The `Url::fromRoute()->toString()` automatically handles all these cases correctly!
