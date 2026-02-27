# Cmesh Push Content Module for Drupal 11

A Drupal 11 module that allows administrators to push content to different environments (dev, staging, production) through a web interface with real-time output display.

## 🚨 Important Configuration Update

**Configuration files now persist through module updates!**

Previous versions stored environment configuration files in the module directory, which caused them to be deleted during module updates. This has been fixed by moving configuration to a persistent location.

**New configuration location:** `sites/default/files/cmesh-config/`

See [PERSISTENT_CONFIG.md](PERSISTENT_CONFIG.md) for detailed setup instructions.

## Features

- Push content to multiple environments (dev, staging, production)
- **Custom commands support for different CDN providers (Cloudflare, Bunny CDN, AWS S3, etc.)**
- **Support for organization and site names with colons (e.g., `company:division`)**
- Environment-specific configuration files
- Real-time output display with auto-scrolling
- Background command execution
- Process state persistence (survives page refreshes)
- Automatic cleanup when commands complete
- Stop running commands
- Shows command status (running/completed)
- Persistent configuration (survives module updates)

## Environment Configuration

The module uses environment-specific configuration files to define push targets:

1. **Development** (`dev.env.inc`) - Push to development environment
2. **Staging** (`staging.env.inc`) - Push to staging environment  
3. **Production** (`prod.env.inc`) - Push to production environment

Each configuration file defines:
- `$org` - Organization name
- `$name` - Environment/site name
- Optional: `$custom_commands` - Array of custom commands for different CDN providers
- Optional: `$script` - Custom script path (legacy)
- Optional: `$bucket` - Storage bucket name (legacy)

Configuration files are stored in `sites/default/files/cmesh-config/` for persistence across module updates.

### Custom Commands Support

You can now define multiple custom commands per environment for different CDN providers:

```php
$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id your-zone --api-token $CLOUDFLARE_API_TOKEN',
    'description' => 'Deploy to Cloudflare CDN',
  ],
  'bunny' => [
    'label' => 'Push to Bunny CDN',
    'command' => '/opt/cmesh/scripts/deploy-bunny.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --storage-zone your-storage --access-key $BUNNY_ACCESS_KEY',
    'description' => 'Deploy to Bunny CDN',
  ],
];
```

See [CUSTOM_COMMANDS_USAGE.md](CUSTOM_COMMANDS_USAGE.md) for detailed configuration instructions.

## Installation

1. Copy the module to your Drupal installation's `modules/custom/` directory
2. Enable the module: `drush en cmesh_push_content -y` or through the admin UI
3. Clear cache: `drush cr`
4. Set up configuration files (see Configuration section below)

## Configuration

### Initial Setup

1. Create the persistent configuration directory:
   ```bash
   mkdir -p sites/default/files/cmesh-config
   chmod 755 sites/default/files/cmesh-config
   ```

2. Copy example configuration files:
   ```bash
   cp modules/custom/cmesh_push_content/config/*.env.inc.example sites/default/files/cmesh-config/
   ```

3. Edit each configuration file with your environment settings:
   - `sites/default/files/cmesh-config/dev.env.inc`
   - `sites/default/files/cmesh-config/staging.env.inc`
   - `sites/default/files/cmesh-config/prod.env.inc`

### Configuration File Format

Each `.env.inc` file should follow this format:
```php
<?php

/**
 * Environment configuration.
 */

$org = 'your-org-name';
$name = 'your-site-name';
```

See [PERSISTENT_CONFIG.md](PERSISTENT_CONFIG.md) for detailed configuration instructions and security considerations.

## Usage

1. Navigate to the cmesh push content interface in your Drupal admin
2. You will see buttons for each configured environment and custom command:
   - Basic configuration: "Push to dev", "Push to staging", "Push to prod"
   - Custom commands: Multiple buttons per environment (e.g., "Push to Cloudflare", "Push to Bunny CDN", etc.)
3. Click the button for your target environment and CDN provider
4. The command will execute in the background
5. Real-time output will appear in the textarea
6. You can stop running commands or clear completed output

## Customizing Commands

### Default Behavior
The module executes the following command for each environment:
```bash
/opt/cmesh/scripts/pushfin.sh -o '<org>' -n '<name>' -b '<bucket>'
```

Where `<org>` and `<name>` are read from the corresponding `.env.inc` file.

### Custom Commands
You can define multiple custom commands per environment for different CDN providers. Each command can use different scripts and parameters. See the [Configuration](#configuration) section above and [CUSTOM_COMMANDS_USAGE.md](CUSTOM_COMMANDS_USAGE.md) for detailed instructions.

To add custom environments or modify command behavior, edit the configuration files or create custom deployment scripts.

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
cmesh_push_content/
├── cmesh_push_content.info.yml
├── cmesh_push_content.routing.yml
├── cmesh_push_content.services.yml
├── cmesh_push_content.libraries.yml
├── cmesh_push_content.permissions.yml
├── src/
│   ├── Controller/
│   │   └── CmeshPushContentController.php
│   ├── Form/
│   │   └── CmeshPushContentForm.php
│   └── Service/
│       ├── CmeshPushContentService.php
│       ├── SystemdCommandExecutorService.php
│       └── CommandExecutorInterface.php
├── config/
│   ├── *.env.inc.example    # Example configuration files
│   └── README.md            # Configuration documentation
├── js/
│   └── cmesh_push_content.js
└── css/
    └── cmesh_push_content.css
```

### Configuration Files Location
```
sites/default/files/
└── cmesh-config/
    ├── dev.env.inc
    ├── staging.env.inc
    └── prod.env.inc
```

## Security Considerations

**WARNING**: This module executes shell commands. Use with caution!

- Only users with "administer cmesh push content" permission can access the interface
- Commands are executed with the web server's user permissions
- Configuration files contain sensitive environment data - protect them appropriately
- Configuration files are stored in `sites/default/files/cmesh-config/` and should be protected from web access
- Consider additional access restrictions in production environments
- Never commit real configuration files to version control (use `.example` files instead)

## API Endpoints

- `GET /admin/config/content/cmesh-push-content/status` - Get current command status
- `POST /admin/config/content/cmesh-push-content/execute` - Execute environment push command

## Requirements

- Drupal 11
- PHP exec() function enabled
- Unix-like operating system (uses `ps` command for process checking)
- Access to `/opt/cmesh/scripts/pushfin.sh` script
- Write permissions to `sites/default/files/cmesh-config/` directory

## License

GPL-2.0+
