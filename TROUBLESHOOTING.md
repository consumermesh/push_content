# Troubleshooting Guide - Command Executor Module

## Output Not Showing or Updates Not Working

### Issue: No output appears when executing commands

**Possible Causes & Solutions:**

1. **PHP exec() function disabled**
   - Check: `php -i | grep disable_functions`
   - Solution: Remove `exec` from `disable_functions` in php.ini
   - Contact your hosting provider if on shared hosting

2. **Insufficient permissions**
   - The web server user needs write access to the temp directory
   - Check: `ls -la /tmp` (or your configured temp directory)
   - Solution: `chmod 755 /tmp` or contact system administrator

3. **JavaScript not loading**
   - Open browser console (F12) and check for errors
   - Clear Drupal cache: `drush cr`
   - Hard refresh browser: Ctrl+Shift+R (or Cmd+Shift+R on Mac)

4. **AJAX requests failing**
   - Check browser Network tab for failed requests
   - Look for 403/404/500 errors
   - Verify routes are accessible: `/admin/config/system/command-executor/status`

5. **Output file not being created**
   - Check temp directory: `ls -la /tmp/cmd_*`
   - Verify web server can write to temp: `touch /tmp/test_file.txt`
   - Check PHP temp directory setting: `php -i | grep temp`

### Issue: Commands complete but output is empty

**Possible Causes & Solutions:**

1. **Commands are failing silently**
   - Check the generated script file in `/tmp/cmd_*.sh`
   - Run it manually: `bash /tmp/cmd_XXXXX_script.sh`
   - Look for error messages

2. **Command path issues**
   - Commands may not be in PATH for web server user
   - Use full paths: `/usr/bin/df` instead of `df`
   - Or add to command: `export PATH=/usr/bin:/bin:$PATH && your-command`

3. **Permissions on commands**
   - Web server user may not have permission to run certain commands
   - Test as web server user: `sudo -u www-data command-to-test`

### Issue: Output appears delayed or not in real-time

**Possible Causes & Solutions:**

1. **stdbuf not available**
   - Check: `which stdbuf`
   - Install: `apt-get install coreutils` (Debian/Ubuntu) or `yum install coreutils` (RHEL/CentOS)
   - The module has a fallback, but buffering may occur

2. **Commands use internal buffering**
   - Some commands buffer output
   - Add explicit flush commands or use `unbuffer` from `expect` package

3. **Polling interval too slow**
   - Default is 2 seconds
   - Edit `js/command_executor.js` and change `pollInterval = setInterval(checkStatus, 2000);`
   - Use 1000 for 1-second polling, but may increase server load

### Issue: "Process still running" but command finished

**Possible Causes & Solutions:**

1. **Zombie process**
   - Check: `ps aux | grep defunct`
   - Usually cleans up on next poll
   - If persistent: `drush state:delete command_executor.current`

2. **PID file issues**
   - Old PID file pointing to new process
   - Clear manually: `rm /tmp/cmd_*_pid.txt`
   - Use stop button in UI

### Testing Command Execution Manually

1. **Check if exec() works:**
   ```php
   <?php
   exec('echo "test" 2>&1', $output, $return);
   var_dump($output, $return);
   ?>
   ```

2. **Test background execution:**
   ```bash
   # As web server user
   sudo -u www-data bash -c 'echo "test" > /tmp/test_output.log 2>&1 & echo $! > /tmp/test_pid.txt'
   # Check output
   cat /tmp/test_output.log
   # Check PID
   cat /tmp/test_pid.txt
   # Verify process
   ps aux | grep $(cat /tmp/test_pid.txt)
   ```

3. **Check JavaScript polling:**
   - Open browser console
   - Watch Network tab for XHR requests to `/admin/config/system/command-executor/status`
   - Should poll every 2 seconds
   - Check response contains output data

### Debug Mode

Add this to your settings.php for detailed logging:

```php
$config['system.logging']['error_level'] = 'verbose';
```

Then check logs:
- Drupal: `/admin/reports/dblog`
- PHP error log: Usually `/var/log/apache2/error.log` or `/var/log/php-fpm/error.log`
- Web server log: `/var/log/apache2/access.log` or `/var/log/nginx/access.log`

### Still Having Issues?

1. Clear ALL caches:
   ```bash
   drush cr
   drush cc css-js
   ```

2. Check file permissions recursively:
   ```bash
   ls -laR modules/custom/command_executor/
   ```

3. Verify module is properly installed:
   ```bash
   drush pm:list --filter=command_executor
   ```

4. Test with a simple command first:
   - Edit CommandExecutorForm.php
   - Change command1 to: `echo "test 1" && sleep 1 && echo "test 2" && sleep 1 && echo "test 3"`
   - Should see each line appear with 1 second delay

### Common Error Messages

**"Call to undefined method"**
- Run `drush cr` to clear cache
- Check all files are present and properly uploaded

**"Access denied"**
- Check user has "administer site configuration" permission
- Verify routing.yml is correct

**"Command not found"**
- Use full paths to commands
- Check PATH for web server user

**"Permission denied"**
- Web server user needs execute permissions
- Check script file permissions in /tmp
