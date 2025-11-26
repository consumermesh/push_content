#!/usr/bin/env php
<?php
/**
 * Debug script for AJAX 500 errors in cmesh_push_content module
 * 
 * Usage: php debug_ajax_500.php
 */

echo "=== Cmesh Push Content AJAX 500 Error Debugger ===\n\n";

// Check PHP configuration
echo "1. PHP Configuration:\n";
echo "   disable_functions: " . ini_get('disable_functions') . "\n";
echo "   memory_limit: " . ini_get('memory_limit') . "\n";
echo "   max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "   error_reporting: " . ini_get('error_reporting') . "\n";
echo "   display_errors: " . ini_get('display_errors') . "\n\n";

// Check if Drupal is available
echo "2. Drupal Environment:\n";
if (function_exists('drupal_get_path')) {
    echo "   ✅ Drupal functions available\n";
} elseif (file_exists('web/sites/default/settings.php')) {
    echo "   ⚠️  Drupal bootstrap needed\n";
} else {
    echo "   ❌ Drupal not detected\n";
}

// Check file permissions
echo "\n3. File System Checks:\n";
$temp_dir = sys_get_temp_dir();
echo "   Temp directory: $temp_dir\n";
echo "   Writable: " . (is_writable($temp_dir) ? '✅' : '❌') . "\n";

// Check if we can create files
test_file_creation($temp_dir);

// Check SSH connectivity (if SSH commands are involved)
echo "\n4. SSH Command Tests:\n";
test_ssh_commands();

// Check exec function
echo "\n5. Command Execution Tests:\n";
test_exec_function();

// Test AJAX endpoint simulation
echo "\n6. AJAX Endpoint Simulation:\n";
test_ajax_endpoint();

echo "\n=== Debug Complete ===\n";

/**
 * Test file creation in temp directory
 */
function test_file_creation($temp_dir) {
    $test_file = $temp_dir . '/cmesh_test_' . uniqid() . '.txt';
    
    if (file_put_contents($test_file, "test content")) {
        echo "   ✅ File creation: OK\n";
        if (unlink($test_file)) {
            echo "   ✅ File deletion: OK\n";
        } else {
            echo "   ❌ File deletion: Failed\n";
        }
    } else {
        echo "   ❌ File creation: Failed\n";
    }
}

/**
 * Test SSH-related commands
 */
function test_ssh_commands() {
    $commands = [
        'which ssh' => 'SSH client',
        'which rsync' => 'Rsync',
        'which stdbuf' => 'Stdbuf (for unbuffered output)',
    ];
    
    foreach ($commands as $cmd => $desc) {
        $output = [];
        $return = 0;
        exec($cmd . ' 2>/dev/null', $output, $return);
        echo "   $desc: " . ($return === 0 ? '✅ ' . implode('', $output) : '❌ Not found') . "\n";
    }
    
    // Test SSH key directory
    $ssh_dir = $_SERVER['HOME'] . '/.ssh';
    if (is_dir($ssh_dir)) {
        echo "   SSH directory exists: ✅\n";
        if (is_readable($ssh_dir)) {
            echo "   SSH directory readable: ✅\n";
        } else {
            echo "   SSH directory readable: ❌\n";
        }
    } else {
        echo "   SSH directory: ❌ Not found\n";
    }
}

/**
 * Test exec function
 */
function test_exec_function() {
    if (function_exists('exec')) {
        echo "   ✅ exec() function exists\n";
        
        // Test basic command
        $output = [];
        $return = 0;
        exec('echo "test" 2>&1', $output, $return);
        
        if ($return === 0 && $output[0] === 'test') {
            echo "   ✅ Basic exec test: OK\n";
        } else {
            echo "   ❌ Basic exec test: Failed (return: $return, output: " . implode(', ', $output) . ")\n";
        }
        
        // Test background execution
        $temp_file = sys_get_temp_dir() . '/exec_test_' . uniqid() . '.txt';
        exec('echo "background test" > ' . escapeshellarg($temp_file) . ' 2>&1 & echo $!', $output, $return);
        
        sleep(1); // Wait for background process
        
        if (file_exists($temp_file) && file_get_contents($temp_file) === "background test\n") {
            echo "   ✅ Background exec test: OK\n";
            unlink($temp_file);
        } else {
            echo "   ❌ Background exec test: Failed\n";
            if (file_exists($temp_file)) unlink($temp_file);
        }
        
    } else {
        echo "   ❌ exec() function: Not available\n";
    }
}

/**
 * Simulate AJAX endpoint call
 */
function test_ajax_endpoint() {
    // Test if we can access the route
    $module_path = dirname(__FILE__);
    $controller_file = $module_path . '/src/Controller/CmeshPushContentController.php';
    
    if (file_exists($controller_file)) {
        echo "   ✅ Controller file exists\n";
        
        // Check for required methods
        $content = file_get_contents($controller_file);
        if (strpos($content, 'executeCommand') !== false) {
            echo "   ✅ executeCommand method found\n";
        } else {
            echo "   ❌ executeCommand method missing\n";
        }
    } else {
        echo "   ❌ Controller file missing\n";
    }
    
    // Test permission check
    echo "   Permission check simulation: cmesh_push_content push_content_dev\n";
    echo "   (This would be checked by Drupal's permission system)\n";
}