# PHP-FPM Process Execution Issues

## The Problem

Commands work on Apache (mod_php) but fail on PHP-FPM, even simple commands like `date`.

This is NOT a memory issue - it's PHP-FPM's **process isolation and security restrictions**.

## Why PHP-FPM is Different

| Feature | Apache mod_php | PHP-FPM |
|---------|---------------|---------|
| Process Model | Runs as Apache child | Separate process pool |
| User Context | Apache user | FPM pool user |
| Environment | Full shell env | Minimal env |
| Process Spawning | Easy | Restricted |
| Background Jobs | Works | Often fails |
| Shell Access | Full | Limited |

## Common PHP-FPM Issues

### 1. Restricted exec()
PHP-FPM pools often have `disable_functions`:
```ini
disable_functions = exec,shell_exec,system,passthru,proc_open,popen
```

### 2. Limited Environment
PHP-FPM runs with minimal environment variables:
- No PATH properly set
- No USER/HOME variables
- No shell initialization files
- Limited process tree access

### 3. Process Isolation
- FPM runs in separate security context
- Can't spawn background processes easily
- Background jobs get killed when FPM request ends
- Process groups are isolated

### 4. SELinux/AppArmor
- May block process execution
- Prevents writing to certain directories
- Blocks network access from spawned processes

## Why Background Execution Fails

```php
exec('command > /tmp/output.log 2>&1 & echo $! > /tmp/pid.txt');
```

This fails with PHP-FPM because:
1. FPM request ends
2. FPM kills child processes
3. Background process (`&`) gets terminated
4. Output file never written

## Solutions

### Solution 1: Use Systemd Service (RECOMMENDED)

Completely decouple builds from PHP-FPM.

**Create:** `/etc/systemd/system/cmesh-build@.service`

```ini
[Unit]
Description=Cmesh Build for %i
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/opt/cmesh

# Environment
Environment="NODE_OPTIONS=--max-old-space-size=8192"
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
Environment="HOME=/var/www"

# The actual command
ExecStart=/opt/cmesh/scripts/pushfin.sh -o %i -n %I

# Logging
StandardOutput=append:/var/log/cmesh/build-%i.log
StandardError=append:/var/log/cmesh/build-%i.log

# Resource limits
MemoryMax=10G
CPUQuota=400%%
TimeoutSec=900

# Restart on failure
Restart=on-failure
RestartSec=10s

[Install]
WantedBy=multi-user.target
```

**Create log directory:**
```bash
sudo mkdir -p /var/log/cmesh
sudo chown www-data:www-data /var/log/cmesh
```

**Reload systemd:**
```bash
sudo systemctl daemon-reload
```

**Test manually:**
```bash
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
sudo journalctl -u cmesh-build@mars-mpvg -f
```

### Solution 2: Use a Queue File

Write build requests to a file, separate daemon processes them.

**Create queue directory:**
```bash
sudo mkdir -p /var/spool/cmesh/queue
sudo chown www-data:www-data /var/spool/cmesh/queue
```

**Create daemon script:** `/opt/cmesh/scripts/build-daemon.sh`

```bash
#!/bin/bash

QUEUE_DIR="/var/spool/cmesh/queue"
LOG_DIR="/var/log/cmesh"

echo "Build daemon started at $(date)"

while true; do
  # Process each job file
  for job in "$QUEUE_DIR"/*.job; do
    [ -e "$job" ] || continue
    
    echo "Processing job: $job"
    
    # Read job details
    source "$job"
    
    # Move to processing
    mv "$job" "${job}.processing"
    
    # Execute build
    /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME" \
      > "$LOG_DIR/build-${ORG}-${NAME}.log" 2>&1
    
    # Mark as done
    mv "${job}.processing" "${job}.done"
    
    echo "Job completed: $job"
  done
  
  # Wait before next check
  sleep 2
done
```

**Make executable:**
```bash
chmod +x /opt/cmesh/scripts/build-daemon.sh
```

**Create systemd service:** `/etc/systemd/system/cmesh-build-daemon.service`

```ini
[Unit]
Description=Cmesh Build Daemon
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/opt/cmesh/scripts/build-daemon.sh
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**Enable and start:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable cmesh-build-daemon
sudo systemctl start cmesh-build-daemon
sudo systemctl status cmesh-build-daemon
```

### Solution 3: Use at Command

Schedule builds to run immediately but outside PHP-FPM context.

**Install at:**
```bash
sudo apt-get install at
sudo systemctl enable atd
sudo systemctl start atd
```

**Allow www-data to use at:**
```bash
echo "www-data" | sudo tee -a /etc/at.allow
```

**Test:**
```bash
echo "/opt/cmesh/scripts/pushfin.sh -o mars -n mpvg" | at now
atq  # List queued jobs
```

### Solution 4: Use Supervisor

Process manager that keeps builds running.

**Install:**
```bash
sudo apt-get install supervisor
```

**Create config:** `/etc/supervisor/conf.d/cmesh-builds.conf`

```ini
[program:cmesh-build-worker]
command=/opt/cmesh/scripts/build-worker.sh
directory=/opt/cmesh
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/cmesh/worker.err.log
stdout_logfile=/var/log/cmesh/worker.out.log
environment=NODE_OPTIONS="--max-old-space-size=8192",PATH="/usr/local/bin:/usr/bin:/bin"
```

**Reload:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cmesh-build-worker
```

## Implementing in Module

### Option A: Systemd Integration

Update the service to trigger systemd instead of exec:

```php
public function executeCommand($command) {
  // Parse command to get org and name
  if (preg_match('/-o\s+\'?([^\']+)\'?\s+-n\s+\'?([^\']+)\'?/', $command, $matches)) {
    $org = $matches[1];
    $name = $matches[2];
    $service_name = "cmesh-build@{$org}-{$name}";
    
    // Start systemd service
    exec("systemctl start " . escapeshellarg($service_name), $output, $return);
    
    if ($return !== 0) {
      throw new \Exception("Failed to start service: $service_name");
    }
    
    // Get the service PID
    $pid = trim(shell_exec("systemctl show -p MainPID --value " . escapeshellarg($service_name)));
    
    // Store in state
    $process_id = uniqid('cmd_', TRUE);
    $this->state->set('cmesh_push_content.current', [
      'process_id' => $process_id,
      'command' => $command,
      'pid' => $pid,
      'service_name' => $service_name,
      'log_file' => "/var/log/cmesh/build-{$org}-{$name}.log",
      'started' => time(),
    ]);
    
    return [
      'process_id' => $process_id,
      'pid' => $pid,
    ];
  }
}

public function getStatus() {
  $process_info = $this->state->get('cmesh_push_content.current');
  
  if (!$process_info) {
    return NULL;
  }
  
  // Check service status
  $is_running = FALSE;
  if (!empty($process_info['service_name'])) {
    exec("systemctl is-active " . escapeshellarg($process_info['service_name']), $output);
    $is_running = (trim($output[0]) === 'active');
  }
  
  // Read log file
  $output = '';
  if (!empty($process_info['log_file']) && file_exists($process_info['log_file'])) {
    $output = file_get_contents($process_info['log_file']);
  }
  
  // If not running and not completed, mark as completed
  if (!$is_running && !isset($process_info['completed'])) {
    $process_info['completed'] = time();
    $process_info['final_output'] = $output;
    $this->state->set('cmesh_push_content.current', $process_info);
  }
  
  return [
    'is_running' => $is_running,
    'output' => $output,
    'command' => $process_info['command'],
    'started' => $process_info['started'],
    'completed' => $process_info['completed'] ?? NULL,
    'pid' => $process_info['pid'],
  ];
}
```

### Option B: Queue File Integration

```php
public function executeCommand($command) {
  if (preg_match('/-o\s+\'?([^\']+)\'?\s+-n\s+\'?([^\']+)\'?/', $command, $matches)) {
    $org = $matches[1];
    $name = $matches[2];
    
    $queue_dir = '/var/spool/cmesh/queue';
    $job_id = uniqid('job_', TRUE);
    $job_file = "$queue_dir/$job_id.job";
    
    // Write job file
    $job_content = "ORG=" . escapeshellarg($org) . "\n";
    $job_content .= "NAME=" . escapeshellarg($name) . "\n";
    $job_content .= "STARTED=" . time() . "\n";
    
    file_put_contents($job_file, $job_content);
    
    // Store in state
    $this->state->set('cmesh_push_content.current', [
      'process_id' => $job_id,
      'command' => $command,
      'job_file' => $job_file,
      'log_file' => "/var/log/cmesh/build-{$org}-{$name}.log",
      'started' => time(),
    ]);
    
    return [
      'process_id' => $job_id,
      'pid' => $job_id,
    ];
  }
}

public function getStatus() {
  $process_info = $this->state->get('cmesh_push_content.current');
  
  if (!$process_info) {
    return NULL;
  }
  
  // Check if job file exists
  $is_running = FALSE;
  if (!empty($process_info['job_file'])) {
    $job_file = $process_info['job_file'];
    $is_running = file_exists($job_file) || file_exists("$job_file.processing");
  }
  
  // Read log
  $output = '';
  if (!empty($process_info['log_file']) && file_exists($process_info['log_file'])) {
    $output = file_get_contents($process_info['log_file']);
  }
  
  // Mark completed if done
  if (!$is_running && !isset($process_info['completed'])) {
    $process_info['completed'] = time();
    $process_info['final_output'] = $output;
    $this->state->set('cmesh_push_content.current', $process_info);
  }
  
  return [
    'is_running' => $is_running,
    'output' => $output,
    'command' => $process_info['command'],
    'started' => $process_info['started'],
    'completed' => $process_info['completed'] ?? NULL,
    'pid' => $process_info['process_id'],
  ];
}
```

### Option C: at Command Integration

```php
public function executeCommand($command) {
  $temp_dir = $this->fileSystem->getTempDirectory();
  $process_id = uniqid('cmd_', TRUE);
  $output_file = "/var/log/cmesh/$process_id.log";
  
  // Create script to run via at
  $script_content = "#!/bin/bash\n";
  $script_content .= "exec > $output_file 2>&1\n";
  $script_content .= "$command\n";
  $script_content .= "echo \"[Command completed with exit code: \$?]\"\n";
  
  $script_file = "$temp_dir/$process_id.sh";
  file_put_contents($script_file, $script_content);
  chmod($script_file, 0755);
  
  // Schedule with at
  $at_command = "echo " . escapeshellarg($script_file) . " | at now";
  exec($at_command, $output, $return);
  
  if ($return !== 0) {
    throw new \Exception("Failed to schedule command");
  }
  
  // Store state
  $this->state->set('cmesh_push_content.current', [
    'process_id' => $process_id,
    'command' => $command,
    'output_file' => $output_file,
    'script_file' => $script_file,
    'started' => time(),
  ]);
  
  return [
    'process_id' => $process_id,
    'pid' => $process_id,
  ];
}
```

## Debugging PHP-FPM Issues

### Check if exec is disabled
```bash
php -r "echo ini_get('disable_functions');"
```

### Check FPM pool config
```bash
cat /etc/php/8.2/fpm/pool.d/www.conf | grep -E "(user|group|disable_functions)"
```

### Test exec from FPM
Create test.php:
```php
<?php
exec('date 2>&1', $output, $return);
echo "Return: $return\n";
echo "Output: " . implode("\n", $output);
```

Access via web and check output.

### Check process tree
```bash
ps auxf | grep php-fpm
# Shows if processes are isolated
```

### Check SELinux
```bash
getenforce
# If 'Enforcing', check:
sudo ausearch -m avc -ts recent
```

## Recommendation

**Use systemd service** (Option A) because:
1. ✅ Complete isolation from PHP-FPM
2. ✅ Proper process management
3. ✅ Built-in logging
4. ✅ Resource limits
5. ✅ Restart on failure
6. ✅ Status monitoring
7. ✅ Works with any PHP setup

This is the most robust, production-ready solution.
