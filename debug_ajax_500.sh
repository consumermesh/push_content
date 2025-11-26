#!/bin/bash

# Debug script for AJAX 500 errors in cmesh_push_content module
# Usage: ./debug_ajax_500.sh

echo "=== Cmesh Push Content AJAX 500 Error Debugger ==="
echo ""
echo "This script will help identify why AJAX form submission fails with 500 error"
echo "when using CmeshPushContentService with SSH remote execution."
echo ""

# Check if running as web user (or simulate)
echo "1. User Context Check:"
echo "   Current user: $(whoami)"
echo "   Home directory: $HOME"
echo "   Shell: $SHELL"
echo ""

# Check system commands
echo "2. System Command Availability:"
commands=("ssh" "rsync" "stdbuf" "bash" "ps" "pgrep" "kill")
for cmd in "${commands[@]}"; do
    if command -v "$cmd" >/dev/null 2>&1; then
        echo "   ✅ $cmd: $(which $cmd)"
    else
        echo "   ❌ $cmd: Not found"
    fi
done
echo ""

# Check temp directory
echo "3. Temp Directory Checks:"
temp_dir="/tmp"
echo "   Temp directory: $temp_dir"
if [ -d "$temp_dir" ]; then
    echo "   ✅ Directory exists"
    if [ -w "$temp_dir" ]; then
        echo "   ✅ Directory writable"
        # Test file creation
        test_file="$temp_dir/cmesh_test_$$.txt"
        if echo "test" > "$test_file" 2>/dev/null; then
            echo "   ✅ File creation test: PASSED"
            rm -f "$test_file"
        else
            echo "   ❌ File creation test: FAILED"
        fi
    else
        echo "   ❌ Directory not writable"
    fi
else
    echo "   ❌ Directory doesn't exist"
fi
echo ""

# Check SSH configuration
echo "4. SSH Configuration:"
ssh_dir="$HOME/.ssh"
if [ -d "$ssh_dir" ]; then
    echo "   ✅ SSH directory exists: $ssh_dir"
    ls -la "$ssh_dir" | grep -E "(id_rsa|keycdn|config)" | head -5
else
    echo "   ❌ SSH directory not found: $ssh_dir"
fi
echo ""

# Check background process capabilities
echo "5. Background Process Test:"
echo "   Testing background command execution..."
test_output="/tmp/cmesh_bg_test_$$.out"
test_pid="/tmp/cmesh_bg_test_$$.pid"

# Test basic background execution
bash -c "echo 'background test' > '$test_output' 2>&1 & echo \$! > '$test_pid'" 2>/dev/null
sleep 1

if [ -f "$test_output" ]; then
    echo "   ✅ Background execution: Content created"
    echo "   Content: $(cat "$test_output")"
else
    echo "   ❌ Background execution: No output file created"
fi

if [ -f "$test_pid" ]; then
    pid=$(cat "$test_pid")
    echo "   PID file created: $pid"
    if ps -p "$pid" >/dev/null 2>&1; then
        echo "   ❌ Process still running (unexpected)"
    else
        echo "   ✅ Process completed"
    fi
else
    echo "   ❌ PID file not created"
fi

# Cleanup
rm -f "$test_output" "$test_pid"
echo ""

# Test command execution with pipefail
echo "6. Command Execution Test (with pipefail):"
test_script="/tmp/cmesh_pipefail_test_$$.sh"
cat > "$test_script" << 'EOF'
#!/bin/bash
set -o pipefail
exec 2>&1
echo "Test command execution"
sleep 0.1
exit_code=$?
echo "[Command completed with exit code: $exit_code]"
exit $exit_code
EOF

chmod +x "$test_script"

# Execute and capture results
output=$(bash "$test_script" 2>&1)
exit_code=$?

if [ $exit_code -eq 0 ]; then
    echo "   ✅ Script execution: PASSED (exit code: $exit_code)"
else
    echo "   ❌ Script execution: FAILED (exit code: $exit_code)"
fi

echo "   Output: $output"
rm -f "$test_script"
echo ""

# Check for potential permission issues
echo "7. Permission Checks:"
echo "   Current umask: $(umask)"
echo "   Web server user check:"

# Try to detect web server user
if command -v ps >/dev/null 2>&1; then
    web_users=$(ps aux | grep -E "(apache|httpd|nginx|www-data|http)" | grep -v grep | awk '{print $1}' | sort -u | head -3)
    if [ -n "$web_users" ]; then
        echo "   Detected web users: $web_users"
    fi
fi

echo ""

# Check environment variables
echo "8. Environment Variables:"
echo "   PATH: $PATH"
echo "   HOME: $HOME"
echo "   USER: $USER"
echo "   SHELL: $SHELL"

# Check for any SSH-related env vars
echo "   SSH-related variables:"
env | grep -i ssh | sed 's/^/   /'
echo ""

# Test rsync if available
echo "9. Rsync Test (if available):"
if command -v rsync >/dev/null 2>&1; then
    # Test local rsync
    test_src="/tmp/cmesh_rsync_src_$$"
    test_dst="/tmp/cmesh_rsync_dst_$$"
    mkdir -p "$test_src"
    echo "test content" > "$test_src/test.txt"
    
    if rsync -av "$test_src/" "$test_dst/" 2>/dev/null; then
        echo "   ✅ Local rsync: PASSED"
    else
        echo "   ❌ Local rsync: FAILED"
    fi
    
    # Cleanup
    rm -rf "$test_src" "$test_dst"
else
    echo "   Rsync not available"
fi
echo ""

# Check for SELinux or AppArmor
echo "10. Security Module Check:"
if command -v getenforce >/dev/null 2>&1; then
    echo "   SELinux: $(getenforce)"
elif [ -f "/sys/fs/selinux/enforce" ]; then
    echo "   SELinux: $(cat /sys/fs/selinux/enforce) (enforcing=1)"
else
    echo "   SELinux: Not detected"
fi

if command -v aa-status >/dev/null 2>&1; then
    echo "   AppArmor: Loaded profiles detected"
elif [ -d "/sys/kernel/security/apparmor" ]; then
    echo "   AppArmor: Present but aa-status not available"
else
    echo "   AppArmor: Not detected"
fi
echo ""

# Check module files
echo "11. Module File Check:"
module_dir="$(dirname "$0")"
echo "   Module directory: $module_dir"

key_files=(
    "src/Controller/CmeshPushContentController.php"
    "src/Form/CmeshPushContentForm.php" 
    "src/Service/CmeshPushContentService.php"
    "cmesh_push_content.routing.yml"
    "cmesh_push_content.permissions.yml"
)

for file in "${key_files[@]}"; do
    if [ -f "$module_dir/$file" ]; then
        echo "   ✅ $file: Present"
    else
        echo "   ❌ $file: Missing"
    fi
done
echo ""

echo "=== Debug Complete ==="
echo ""
echo "Next steps:"
echo "1. Check browser console for JavaScript errors"
echo "2. Check web server error logs (if accessible)"
echo "3. Test manual command execution as web user"
echo "4. Verify SSH keys and remote server connectivity"
echo "5. Check Drupal watchdog logs: drush watchdog:show --type=cmesh_push_content"