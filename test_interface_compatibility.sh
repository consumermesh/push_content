#!/bin/bash

# Test script to verify CommandExecutorInterface compatibility
# between CmeshPushContentService and SystemdCommandExecutorService

echo "=== CommandExecutorInterface Compatibility Test ==="
echo ""

# Check if we're in the right directory
if [ ! -f "src/Service/CommandExecutorInterface.php" ]; then
    echo "❌ Error: Not in cmesh_push_content module directory"
    echo "Please run this script from the module root directory"
    exit 1
fi

echo "1. Checking interface definition..."
if grep -q "executeCommandDirect" src/Service/CommandExecutorInterface.php; then
    echo "   ✅ executeCommandDirect() method found in interface"
else
    echo "   ❌ executeCommandDirect() method missing from interface"
fi
echo ""

echo "2. Checking CmeshPushContentService implementation..."
if grep -q "public function executeCommandDirect" src/Service/CmeshPushContentService.php; then
    echo "   ✅ CmeshPushContentService implements executeCommandDirect()"
    
    # Check if it delegates to executeCommand
    if grep -A 10 "public function executeCommandDirect" src/Service/CmeshPushContentService.php | grep -q "executeCommand"; then
        echo "   ✅ CmeshPushContentService delegates to executeCommand()"
    else
        echo "   ⚠️  CmeshPushContentService may not delegate to executeCommand()"
    fi
else
    echo "   ❌ CmeshPushContentService missing executeCommandDirect() implementation"
fi
echo ""

echo "3. Checking SystemdCommandExecutorService implementation..."
if grep -q "public function executeCommandDirect" src/Service/SystemdCommandExecutorService.php; then
    echo "   ✅ SystemdCommandExecutorService implements executeCommandDirect()"
else
    echo "   ❌ SystemdCommandExecutorService missing executeCommandDirect() implementation"
fi
echo ""

echo "4. Checking form usage..."
if grep -q "executeCommandDirect" src/Form/CmeshPushContentForm.php; then
    echo "   ✅ Form uses executeCommandDirect() method"
    
    # Check if it's in executeEnvCommand
    if grep -A 5 -B 5 "executeCommandDirect" src/Form/CmeshPushContentForm.php | grep -q "executeEnvCommand"; then
        echo "   ✅ Form calls executeCommandDirect() in executeEnvCommand()"
    fi
else
    echo "   ❌ Form doesn't use executeCommandDirect() method"
fi
echo ""

echo "5. PHP Syntax Check..."
php_files=(
    "src/Service/CommandExecutorInterface.php"
    "src/Service/CmeshPushContentService.php"
    "src/Service/SystemdCommandExecutorService.php"
    "src/Form/CmeshPushContentForm.php"
)

for file in "${php_files[@]}"; do
    if [ -f "$file" ]; then
        if php -l "$file" >/dev/null 2>&1; then
            echo "   ✅ $file: Syntax OK"
        else
            echo "   ❌ $file: Syntax Error"
            php -l "$file"
        fi
    fi
done
echo ""

echo "6. Method Signature Comparison..."
echo "   Interface method signature:"
grep -A 5 "public function executeCommandDirect" src/Service/CommandExecutorInterface.php | head -6

echo ""
echo "   CmeshPushContentService implementation:"
grep -A 5 "public function executeCommandDirect" src/Service/CmeshPushContentService.php | head -6

echo ""
echo "   SystemdCommandExecutorService implementation:"
grep -A 5 "public function executeCommandDirect" src/Service/SystemdCommandExecutorService.php | head -6

echo ""
echo "=== Test Summary ==="
echo ""
echo "Both services should now implement executeCommandDirect() with compatible signatures."
echo "CmeshPushContentService should build commands from parameters and delegate to executeCommand()."
echo "SystemdCommandExecutorService should handle parameters directly with systemd services."
echo ""
echo "Next steps:"
echo "1. Clear Drupal cache: drush cr"
echo "2. Test the form with both services"
echo "3. Check logs for any errors"
echo "4. Verify SSH remote execution works with CmeshPushContentService"