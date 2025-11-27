#!/bin/bash

# Diagnostic script for rsync SSH protocol mismatch issue
# Run this to identify what's causing the "protocol version mismatch" error

echo "=== Rsync SSH Protocol Diagnostic ==="
echo ""

# Test 1: Basic SSH connectivity
echo "1. Testing basic SSH connectivity..."
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "echo 'SSH test successful'" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "   ✅ Basic SSH: PASSED"
else
    echo "   ❌ Basic SSH: FAILED"
fi
echo ""

# Test 2: SSH with http user
echo "2. Testing SSH with http user context..."
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http echo 'http user test'" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "   ✅ HTTP user SSH: PASSED"
else
    echo "   ❌ HTTP user SSH: FAILED"
fi
echo ""

# Test 3: Check for shell output interference
echo "3. Checking for shell output that might interfere..."
echo "   Testing interactive shell (should be silent):"
output=$(ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "echo test" 2>&1)
if [ "$output" = "test" ]; then
    echo "   ✅ Interactive shell: CLEAN"
else
    echo "   ⚠️  Interactive shell: OUTPUT DETECTED"
    echo "   Output: '$output'"
fi
echo ""

# Test 4: Check http user's shell environment
echo "4. Checking http user's shell environment..."
http_output=$(ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http echo test" 2>&1)
if [ "$http_output" = "test" ]; then
    echo "   ✅ HTTP user shell: CLEAN"
else
    echo "   ⚠️  HTTP user shell: OUTPUT DETECTED"
    echo "   Output: '$http_output'"
fi
echo ""

# Test 5: Test rsync directly
echo "5. Testing rsync directly (this might show the actual error)..."
echo "   Attempting rsync dry-run to KeyCDN..."
# Create a small test file
touch /tmp/rsync_test_$$
rsync_output=$(rsync -rtvz --dry-run /tmp/rsync_test_$$ spfoos@rsync.keycdn.com:test-zone/ 2>&1)
rsync_result=$?
if [ $rsync_result -eq 0 ]; then
    echo "   ✅ Direct rsync: PASSED"
elif echo "$rsync_output" | grep -q "protocol version mismatch"; then
    echo "   ❌ Direct rsync: PROTOCOL MISMATCH (this is the issue)"
    echo "   Error: $rsync_output"
else
    echo "   ⚠️  Direct rsync: OTHER ERROR"
    echo "   Error: $rsync_output"
fi
rm -f /tmp/rsync_test_$$
echo ""

# Test 6: Test SSH with various options
echo "6. Testing SSH with protocol-friendly options..."
ssh_options="-o StrictHostKeyChecking=no -o LogLevel=ERROR -o BatchMode=yes"
clean_output=$(ssh $ssh_options -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "echo test" 2>&1)
if [ "$clean_output" = "test" ]; then
    echo "   ✅ Clean SSH options: WORKING"
else
    echo "   ⚠️  Clean SSH options: STILL OUTPUT"
    echo "   Output: '$clean_output'"
fi
echo ""

# Test 7: Check remote shell startup files
echo "7. Checking remote shell startup files..."
echo "   Looking for potential output sources..."

# Check common files
startup_files=(".bashrc" ".bash_profile" ".profile" ".zshrc" "/etc/motd" "/etc/profile")
for file in "${startup_files[@]}"; do
    echo "   Checking ~/$file:"
    file_check=$(ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "test -f ~/$file && echo 'EXISTS' || echo 'NOT FOUND'" 2>/dev/null)
    if [ "$file_check" = "EXISTS" ]; then
        # Look for echo/print statements
        echo_output=$(ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "grep -n 'echo\|print\|printf' ~/$file 2>/dev/null | head -3" 2>/dev/null)
        if [ -n "$echo_output" ]; then
            echo "     ⚠️  Found output statements:"
            echo "$echo_output" | sed 's/^/       /'
        else
            echo "     ✅ No obvious output statements"
        fi
    else
        echo "     Not found"
    fi
done
echo ""

# Test 8: Test the exact command structure from pushfin.sh
echo "8. Testing the exact command structure from pushfin.sh..."
echo "   Simulating: sudo -u http bash -c 'echo test'"
exact_output=$(ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http bash -c 'echo test'" 2>&1)
if [ "$exact_output" = "test" ]; then
    echo "   ✅ Exact command structure: CLEAN"
else
    echo "   ⚠️  Exact command structure: OUTPUT DETECTED"
    echo "   Output: '$exact_output'"
fi
echo ""

# Test 9: Test with non-interactive shell
echo "9. Testing with non-interactive shell..."
nonint_output=$(ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http bash --norc -c 'echo test'" 2>&1)
if [ "$nonint_output" = "test" ]; then
    echo "   ✅ Non-interactive shell: CLEAN"
else
    echo "   ⚠️  Non-interactive shell: STILL OUTPUT"
    echo "   Output: '$nonint_output'"
fi
echo ""

echo "=== Diagnostic Summary ==="
echo ""
echo "Based on these tests, the issue is likely:"
echo ""

if [ "$http_output" != "test" ]; then
    echo "1. HTTP user shell is outputting text - check ~/.bashrc, ~/.profile, etc."
fi

if echo "$rsync_output" | grep -q "protocol version mismatch"; then
    echo "2. Direct rsync also fails - check KeyCDN credentials and connectivity"
fi

if [ "$exact_output" != "test" ]; then
    echo "3. The exact command structure produces output - check remote shell configuration"
fi

echo ""
echo "Recommended fixes:"
echo "1. Clean remote shell startup files (remove echo statements)"
echo "2. Use non-interactive shell: bash --norc -c"
echo "3. Add SSH options: -o LogLevel=ERROR -o BatchMode=yes"
echo "4. Check remote /opt/cmesh/scripts/pushfin.sh for output before rsync"
echo "5. Ensure KeyCDN credentials are properly configured"
echo ""
echo "Run: ssh backend@fin.consumermesh.com"
echo "Then: sudo -u http bash"
echo "Check: ~/.bashrc, ~/.profile, /etc/motd, etc."
echo "Remove any echo, print, or printf statements"