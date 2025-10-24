# Command Executor Module for Drupal 10

A Drupal 10 module that allows administrators to execute predefined command-line commands through a web interface with real-time output display.

## Features

- Execute predefined shell commands through a web interface
- Real-time output display with auto-scrolling
- Background command execution
- Process state persistence (survives page refreshes)
- Automatic cleanup when commands complete
- Stop running commands
- Shows command status (running/completed)

## Predefined Commands

The module includes two predefined commands:

1. **System Info Check** - Displays system information including:
   - Hostname and OS
   - Kernel version
   - System uptime
   - Current date/time
   - Disk usage
   - Memory usage

2. **List Temp Directory** - Shows:
   - Detailed listing of /tmp directory
   - Disk usage of largest files in /tmp

## Installation

1. Copy the module to your Drupal installation's `modules/custom/` directory
2. Enable the module: `drush en command_executor -y` or through the admin UI
3. Clear cache: `drush cr`

## Usage

1. Navigate to `/admin/config/system/command-executor`
2. Click one of the predefined command buttons
3. The output will appear in real-time in the textarea below
4. If you navigate away and come back, you'll see if the command is still running
5. Once the command completes, the logs are automatically cleaned up

## Customizing Commands

To add or modify commands, edit the `CommandExecutorForm.php` file:

```php
// Add a new command button
$form['actions']['command3'] = [
  '#type' => 'submit',
  '#value' => $this->t('Your Command Name'),
  '#submit' => ['::executeCommand3'],
  '#attributes' => ['class' => ['button', 'button--primary']],
];

// Add the submit handler
public function executeCommand3(array &$form, FormStateInterface $form_state) {
  $command = 'your-command-here';
  $this->executeCommand($command, 'Your Command Description');
  $form_state->setRebuild(TRUE);
}
```

## How It Works

### Backend (PHP)
- **CommandExecutorService**: Executes commands in background using `exec()` with output redirection
- **State API**: Stores current command information (PID, output file path, etc.)
- **Process Monitoring**: Checks if process is running using `ps` command
- **Automatic Cleanup**: Removes temporary files and state when command completes

### Frontend (JavaScript)
- **Polling**: Checks command status every 2 seconds via AJAX
- **Auto-scroll**: Automatically scrolls output textarea to show latest output
- **Page Refresh Support**: Resumes polling if command is still running after page reload

### File Structure
```
command_executor/
├── command_executor.info.yml
├── command_executor.routing.yml
├── command_executor.services.yml
├── command_executor.libraries.yml
├── src/
│   ├── Controller/
│   │   └── CommandExecutorController.php
│   ├── Form/
│   │   └── CommandExecutorForm.php
│   └── Service/
│       └── CommandExecutorService.php
├── js/
│   └── command_executor.js
└── css/
    └── command_executor.css
```

## Security Considerations

**WARNING**: This module executes shell commands. Use with caution!

- Only users with "administer site configuration" permission can access the interface
- Commands are executed with the web server's user permissions
- Predefined commands reduce security risk compared to free-form input
- Consider additional access restrictions in production environments

## API Endpoints

- `GET /admin/config/system/command-executor/status` - Get current command status
- `POST /admin/config/system/command-executor/execute` - Execute a new command

## Requirements

- Drupal 10
- PHP exec() function enabled
- Unix-like operating system (uses `ps` command for process checking)

## License

GPL-2.0+
