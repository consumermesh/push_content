#!/usr/bin/env php
<?php

/**
 * Test script for custom commands functionality
 */

// Test the getEnvironmentCommands method logic
function testGetEnvironmentCommands($env_content, $env_key) {
  echo "=== Testing $env_key Environment ===\n";
  
  // Default values
  $org = 'mars';
  $name = 'mpvg';
  $script = '/opt/cmesh/scripts/pushfin.sh';
  $bucket = '';
  $custom_commands = [];

  // Evaluate the environment content (simulate include)
  eval('?>' . $env_content);

  // If custom commands are defined, use them
  if (!empty($custom_commands) && is_array($custom_commands)) {
    echo "Custom commands found:\n";
    foreach ($custom_commands as $key => $config) {
      echo "  - $key: {$config['label']}\n";
      echo "    Command: {$config['command']}\n";
      echo "    Description: {$config['description']}\n\n";
    }
    return $custom_commands;
  }

  // Otherwise, build the default command
  $default_command = sprintf(
    '%s -o %s -n %s -b %s',
    escapeshellarg($script),
    escapeshellarg($org),
    escapeshellarg($name),
    escapeshellarg($bucket)
  );

  echo "Using default command:\n";
  echo "  Command: $default_command\n\n";

  return [
    'default' => [
      'label' => 'Push to ' . $env_key,
      'command' => $default_command,
      'description' => "Push to $env_key",
    ],
  ];
}

// Test different environment configurations
echo "Testing Custom Commands Functionality\n";
echo "=====================================\n\n";

// Test 1: Basic configuration (no custom commands)
$basic_config = '<?php

/**
 * @file
 * Basic environment configuration.
 */

$org = "test-org";
$name = "test-site";
';

testGetEnvironmentCommands($basic_config, 'basic');

// Test 2: Configuration with custom commands
$custom_config = '<?php

/**
 * @file
 * Custom environment configuration.
 */

$org = "my-org";
$name = "my-site";

// Define custom commands for different CDN providers
$custom_commands = [
  "cloudflare" => [
    "label" => "Push to Cloudflare",
    "command" => "/opt/cmesh/scripts/deploy-cloudflare.sh -o " . escapeshellarg($org) . " -n " . escapeshellarg($name) . " --zone-id dev-zone --api-token $CLOUDFLARE_API_TOKEN",
    "description" => "Deploy to Cloudflare CDN",
  ],
  "bunny" => [
    "label" => "Push to Bunny CDN",
    "command" => "/opt/cmesh/scripts/deploy-bunny.sh -o " . escapeshellarg($org) . " -n " . escapeshellarg($name) . " --storage-zone dev-storage --access-key $BUNNY_ACCESS_KEY",
    "description" => "Deploy to Bunny CDN",
  ],
  "aws" => [
    "label" => "Push to AWS S3",
    "command" => "/opt/cmesh/scripts/deploy-aws.sh -o " . escapeshellarg($org) . " -n " . escapeshellarg($name) . " --bucket dev-bucket --region us-east-1",
    "description" => "Deploy to AWS S3",
  ],
  "default" => [
    "label" => "Push to Default",
    "command" => "/opt/cmesh/scripts/pushfin.sh -o " . escapeshellarg($org) . " -n " . escapeshellarg($name),
    "description" => "Default push command",
  ],
];
';

testGetEnvironmentCommands($custom_config, 'custom');

// Test 3: Configuration with partial custom commands (no default)
$partial_config = '<?php

/**
 * @file
 * Partial custom configuration.
 */

$org = "partial-org";
$name = "partial-site";

$custom_commands = [
  "cloudflare" => [
    "label" => "Deploy to Cloudflare",
    "command" => "/opt/cmesh/scripts/deploy-cloudflare.sh -o " . escapeshellarg($org) . " -n " . escapeshellarg($name),
    "description" => "Cloudflare deployment",
  ],
  "bunny" => [
    "label" => "Deploy to Bunny CDN",
    "command" => "/opt/cmesh/scripts/deploy-bunny.sh -o " . escapeshellarg($org) . " -n " . escapeshellarg($name),
    "description" => "Bunny CDN deployment",
  ],
];
';

testGetEnvironmentCommands($partial_config, 'partial');

echo "=== Testing Systemd Instance Parsing ===\n";

// Test systemd instance parsing
function testSystemdParsing($command) {
  echo "Testing command: $command\n";
  
  // Parse command to extract org, name, and command key
  $command_key = 'default';
  
  // Try to extract command key from script name
  if (preg_match('/deploy-([^\.]+)\.sh/', $command, $cmd_match)) {
    $command_key = $cmd_match[1];
  } elseif (preg_match('/pushfin.*\.sh/', $command)) {
    $command_key = 'default';
  }
  
  // Try to match -o and -n flags with their values
  if (preg_match('/-o\s+[\'"]?([^\s\'"]+)[\'"]?\s+-n\s+[\'"]?([^\s\'"]+)[\'"]?/', $command, $matches)) {
    $org = $matches[1];
    $name = $matches[2];
    
    // Build instance with org:name:command format
    $instance = "{$org}:{$name}:{$command_key}";
    echo "  Parsed instance: $instance\n";
    echo "  Command key: $command_key\n";
  } else {
    echo "  Failed to parse org and name\n";
  }
  echo "\n";
}

// Test different command formats
testSystemdParsing("/opt/cmesh/scripts/pushfin.sh -o 'mars' -n 'mpvg'");
testSystemdParsing("/opt/cmesh/scripts/deploy-cloudflare.sh -o 'mars' -n 'mpvg' --zone-id test");
testSystemdParsing("/opt/cmesh/scripts/deploy-bunny.sh -o 'ramsalt' -n 'playground' --storage-zone test");
testSystemdParsing("/opt/cmesh/scripts/deploy-aws.sh -o 'acme' -n 'production' --bucket prod-bucket");

echo "=== All Tests Completed ===\n";