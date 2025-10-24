# Command Executor - Changelog

## Version 2.0 - Predefined Commands Update

### Changed
- ✅ **Removed free-form text input** for security
- ✅ **Added 2 predefined command buttons:**
  - "Run System Info Check" - Displays system information
  - "List Temp Directory" - Shows /tmp contents and disk usage
- ✅ **Improved UI styling** with better button layout
- ✅ **Enhanced security** by limiting to predefined commands only

### Features Retained
- ✅ Real-time output display with auto-scrolling
- ✅ Background command execution
- ✅ Process state persistence (survives page refreshes)
- ✅ Automatic cleanup when commands complete
- ✅ Stop running commands functionality
- ✅ Status indicator (running/completed)

### Technical Changes
- Updated `CommandExecutorForm.php`:
  - Removed `command` textfield
  - Added `executeCommand1()` and `executeCommand2()` submit handlers
  - Added helper method `executeCommand()` to reduce code duplication
  - Made `submitForm()` a stub (required by interface)
  
- Updated CSS:
  - Added flexbox layout for action buttons
  - Styled `.button--primary` class
  - Added spacing between buttons

- Updated Documentation:
  - README.md now describes predefined commands
  - INSTALL.md includes instructions for adding custom commands
  - Removed examples of free-form command usage

### Migration from Version 1.0
If you were using the free-form version:
1. Identify the commands you were using
2. Add them as predefined buttons following the pattern in the code
3. Or modify the existing predefined commands to suit your needs

### Security Improvements
- Eliminates risk of command injection
- Users can only execute pre-approved commands
- Easier to audit and control what commands are available
- Better for production environments

### File Structure
```
command_executor/
├── command_executor.info.yml          # Module metadata
├── command_executor.routing.yml       # Routes (unchanged)
├── command_executor.services.yml      # Services (unchanged)
├── command_executor.libraries.yml     # Assets (unchanged)
├── src/
│   ├── Controller/
│   │   └── CommandExecutorController.php  # AJAX endpoints (unchanged)
│   ├── Form/
│   │   └── CommandExecutorForm.php        # ✨ Updated with predefined commands
│   └── Service/
│       └── CommandExecutorService.php     # Command execution (unchanged)
├── js/
│   └── command_executor.js            # Polling logic (unchanged)
├── css/
│   └── command_executor.css           # ✨ Updated button styles
├── README.md                           # ✨ Updated documentation
├── INSTALL.md                          # ✨ Updated with customization guide
└── CHANGELOG.md                        # This file
```
