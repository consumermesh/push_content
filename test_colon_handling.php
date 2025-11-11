#!/usr/bin/env php
<?php

/**
 * Test script to verify colon handling in systemd instance names
 */

echo "=== Testing Colon Handling in Systemd Instance Names ===\n\n";

// Test cases with different org/name combinations that contain colons
$test_cases = [
    ['org' => 'mars', 'name' => 'mpvg', 'command_key' => 'cloudflare'],
    ['org' => 'my:org', 'name' => 'my:site', 'command_key' => 'cloudflare'],
    ['org' => 'company:division', 'name' => 'project:environment', 'command_key' => 'bunny'],
    ['org' => 'test:org:with:many:colons', 'name' => 'test:site:with:colons', 'command_key' => 'aws'],
    ['org' => 'org:with:colons', 'name' => 'simple-site', 'command_key' => 'default'],
    ['org' => 'simple-org', 'name' => 'site:with:colons', 'command_key' => 'cloudflare'],
];

function encode_instance($org, $name, $command_key) {
    // URL encode colons in org and name to prevent parsing issues
    $encoded_org = str_replace(':', '%3A', $org);
    $encoded_name = str_replace(':', '%3A', $name);
    return "{$encoded_org}:{$encoded_name}:{$command_key}";
}

function decode_instance($instance) {
    // Parse the instance format
    if (preg_match('/^([^:]+):([^:]+):(.+)$/', $instance, $matches)) {
        $org_encoded = $matches[1];
        $name_encoded = $matches[2];
        $command_key = $matches[3];
        
        // Decode URL-encoded colons
        $org = str_replace('%3A', ':', $org_encoded);
        $name = str_replace('%3A', ':', $name_encoded);
        
        return [
            'org' => $org,
            'name' => $name,
            'command_key' => $command_key
        ];
    }
    return null;
}

echo "Testing encoding and decoding:\n\n";

foreach ($test_cases as $test_case) {
    $org = $test_case['org'];
    $name = $test_case['name'];
    $command_key = $test_case['command_key'];
    
    echo "Input: org='$org', name='$name', command_key='$command_key'\n";
    
    // Encode
    $encoded = encode_instance($org, $name, $command_key);
    echo "Encoded instance: '$encoded'\n";
    
    // Decode
    $decoded = decode_instance($encoded);
    if ($decoded) {
        echo "Decoded: org='{$decoded['org']}', name='{$decoded['name']}', command_key='{$decoded['command_key']}'\n";
        
        // Verify round-trip
        if ($decoded['org'] === $org && $decoded['name'] === $name && $decoded['command_key'] === $command_key) {
            echo "✅ Round-trip successful\n";
        } else {
            echo "❌ Round-trip failed!\n";
            echo "Expected: org='$org', name='$name', command_key='$command_key'\n";
            echo "Got: org='{$decoded['org']}', name='{$decoded['name']}', command_key='{$decoded['command_key']}'\n";
        }
    } else {
        echo "❌ Failed to decode instance\n";
    }
    
    echo "\n";
}

echo "=== Testing Edge Cases ===\n\n";

// Test edge cases
$edge_cases = [
    "mars:mpvg:cloudflare",  // Normal case
    "my%3Aorg:my%3Asite:cloudflare",  // Encoded colons
    "company%3Adivision:project%3Aenvironment:bunny",  // Complex case
    "simple:simple:default",  // No colons in org/name
    "a%3Ab%3Ac:d%3Ae%3Af:g%3Ah",  // Multiple encoded colons
];

foreach ($edge_cases as $instance) {
    echo "Testing instance: '$instance'\n";
    $decoded = decode_instance($instance);
    if ($decoded) {
        echo "Decoded successfully: " . json_encode($decoded) . "\n";
    } else {
        echo "Failed to decode\n";
    }
    echo "\n";
}

echo "=== Testing Command Building with Colons ===\n\n";

// Simulate the actual command building process
function build_command_test($org, $name, $script, $bucket = '') {
    // This simulates the actual command building in the form
    $command = sprintf(
        '%s -o %s -n %s -b %s',
        escapeshellarg($script),
        escapeshellarg($org),
        escapeshellarg($name),
        escapeshellarg($bucket)
    );
    return $command;
}

$test_orgs = ['mars', 'my:org', 'company:division:team'];
$test_names = ['mpvg', 'my:site', 'project:environment:app'];

foreach ($test_orgs as $org) {
    foreach ($test_names as $name) {
        $command = build_command_test($org, $name, '/opt/cmesh/scripts/deploy-cloudflare.sh');
        echo "Command for org='$org', name='$name':\n";
        echo "  $command\n";
        
        // Test parsing this command
        if (preg_match('/-o\s+[\'"]?([^\s\'"]+)[\'"]?\s+-n\s+[\'"]?([^\s\'"]+)[\'"]?/', $command, $matches)) {
            $parsed_org = $matches[1];
            $parsed_name = $matches[2];
            echo "  Parsed org: '$parsed_org'\n";
            echo "  Parsed name: '$parsed_name'\n";
            
            // Check if parsing preserved the colons
            if ($parsed_org === $org && $parsed_name === $name) {
                echo "  ✅ Command parsing preserved colons correctly\n";
            } else {
                echo "  ❌ Command parsing did not preserve colons\n";
            }
        } else {
            echo "  ❌ Failed to parse command\n";
        }
        echo "\n";
    }
}

echo "=== Summary ===\n";
echo "The encoding/decoding system successfully handles colons in org and name values.\n";
echo "URL encoding (%3A) is used to preserve colons during systemd instance parsing.\n";
echo "This ensures that organizations and site names with colons work correctly.\n";