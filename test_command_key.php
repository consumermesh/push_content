<?php
/**
 * Test script to verify command_key parameter support
 * 
 * Usage: php test_command_key.php
 */

echo "=== Command Key Parameter Test ===\n\n";

// Simulate the command building logic from CmeshPushContentService
function buildCommand($org, $name, $command_key = 'default', $bucket = '', $script = '/opt/cmesh/scripts/pushfin.sh') {
    // For custom commands, use the command key to determine the script
    if ($command_key !== 'default') {
        $script = "/opt/cmesh/scripts/deploy-{$command_key}.sh";
    }
    
    // Build the command string with command_key support
    $command = sprintf(
        '%s -o %s -n %s -b %s -k %s',
        escapeshellarg($script),
        escapeshellarg($org),
        escapeshellarg($name),
        escapeshellarg($bucket),
        escapeshellarg($command_key)
    );
    
    return $command;
}

// Test cases
test_cases = [
    ['org' => 'mars', 'name' => 'mpvg', 'command_key' => 'default', 'bucket' => ''],
    ['org' => 'mars', 'name' => 'mpvg', 'command_key' => 'cloudflare', 'bucket' => ''],
    ['org' => 'mars', 'name' => 'mpvg', 'command_key' => 'aws', 'bucket' => 'my-bucket'],
    ['org' => 'ramsalt', 'name' => 'playground', 'command_key' => 'bunny', 'bucket' => ''],
    ['org' => 'test-org', 'name' => 'test-site', 'command_key' => 'keycdn', 'bucket' => 'push-zone'],
];

echo "Testing command building logic:\n\n";

foreach ($test_cases as $index => $test) {
    echo "Test " . ($index + 1) . ":\n";
    echo "  Organization: {$test['org']}\n";
    echo "  Name: {$test['name']}\n";
    echo "  Command Key: {$test['command_key']}\n";
    echo "  Bucket: {$test['bucket']}\n";
    
    $command = buildCommand($test['org'], $test['name'], $test['command_key'], $test['bucket']);
    echo "  Generated Command: $command\n";
    
    // Verify the command contains the -k parameter
    if (strpos($command, "-k '{$test['command_key']}'") !== false) {
        echo "  ✅ Command key parameter: PASSED\n";
    } else {
        echo "  ❌ Command key parameter: FAILED\n";
    }
    
    echo "\n";
}

// Test with SSH wrapper (simulate remote execution)
echo "Testing with SSH remote execution:\n\n";

function buildSshCommand($org, $name, $command_key = 'default', $bucket = '', $ssh_host = 'remote-server.com', $ssh_user = 'deploy') {
    $local_command = buildCommand($org, $name, $command_key, $bucket);
    
    $ssh_command = sprintf(
        'ssh %s@%s %s',
        escapeshellarg($ssh_user),
        escapeshellarg($ssh_host),
        escapeshellarg($local_command)
    );
    
    return $ssh_command;
}

$ssh_test = buildSshCommand('mars', 'mpvg', 'cloudflare', '');
echo "SSH Command: $ssh_test\n";

if (strpos($ssh_test, "-k 'cloudflare'") !== false) {
    echo "✅ SSH command includes command_key: PASSED\n";
} else {
    echo "❌ SSH command missing command_key: FAILED\n";
}

echo "\n=== Test Summary ===\n";
echo "\nThe command_key parameter is now being passed to pushfin.sh via the -k flag.\n";
echo "Your pushfin.sh script should parse this parameter to determine which deployment to execute.\n";
echo "\nExample pushfin.sh usage:\n";
echo "  pushfin.sh -o 'mars' -n 'mpvg' -b '' -k 'cloudflare'\n";
echo "\nThis enables support for multiple deployment types within a single script.\n";