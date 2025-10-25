(function ($, Drupal, drupalSettings) {
  'use strict';

  // VERSION 2.0 - Fixed once() error - No once() used anymore
  // Global state to track if we've already initialized
  var initialized = false;
  var pollInterval = null;
  var isPolling = false;

  Drupal.behaviors.cmeshPushContent = {
    attach: function (context, settings) {
      // Only initialize once
      if (initialized) {
        return;
      }
      
      var $form = $('#cmesh-push-content-form', context);
      
      if ($form.length === 0) {
        return;
      }

      // Mark as initialized
      initialized = true;

      var statusUrl = drupalSettings.cmeshPushContent.statusUrl;

      /**
       * Check command status and update UI.
       */
      function checkStatus() {
        if (isPolling) {
          return; // Prevent overlapping requests
        }

        isPolling = true;

        $.ajax({
          url: statusUrl,
          method: 'GET',
          dataType: 'json',
          cache: false,
          success: function (data) {
            console.log('Status check:', data.is_running ? 'Running' : (data.completed ? 'Completed' : 'Not running'));
            
            // Update output textarea
            var $output = $('#command-output');
            if ($output.length > 0) {
              $output.val(data.output || '');
              
              // Scroll to bottom of output
              var outputElement = $output[0];
              outputElement.scrollTop = outputElement.scrollHeight;
            }

            // Check what buttons are currently visible
            var hasStopButton = $('#edit-stop').length > 0;

            // If command is running, ensure we're polling
            if (data.is_running) {
              if (pollInterval === null) {
                startPolling();
              }
            } else {
              // Command not running (completed or never started)
              if (pollInterval !== null) {
                // Was polling but command finished
                console.log('Command finished, stopping polling');
                stopPolling();
                
                // If we still see the Stop button, the form needs to refresh
                // to show the completion message and Clear Output button
                if (hasStopButton) {
                  console.log('Triggering form refresh to show completion status');
                  // Click the hidden refresh button to trigger AJAX form rebuild
                  $('#refresh-trigger').click();
                }
              }
            }
          },
          error: function (xhr, status, error) {
            console.error('Error checking status:', error);
            console.log('Status URL:', statusUrl);
          },
          complete: function () {
            isPolling = false;
          }
        });
      }

      /**
       * Start polling for status updates.
       */
      function startPolling() {
        if (pollInterval === null) {
          console.log('Starting polling...');
          // Poll every 1 second for more responsive updates
          pollInterval = setInterval(checkStatus, 1000);
        }
      }

      /**
       * Stop polling for status updates.
       */
      function stopPolling() {
        if (pollInterval !== null) {
          console.log('Stopping polling...');
          clearInterval(pollInterval);
          pollInterval = null;
        }
      }

      // Check status immediately on page load/attach
      checkStatus();

      // If a command is already running (form has stop button), start polling
      if ($('#edit-stop').length > 0) {
        console.log('Stop button found - command is running');
        startPolling();
      }

      // After AJAX form submission, check if we should start polling
      $(document).ajaxComplete(function(event, xhr, settings) {
        // Check if this was our form submission
        if (settings.url && settings.url.indexOf('/admin/config/system/cmesh-push-content') !== -1) {
          console.log('Form submitted via AJAX, checking status...');
          setTimeout(function() {
            checkStatus();
            // Check again if we should be polling
            if ($('#edit-stop').length > 0) {
              startPolling();
            }
          }, 500);
        }
      });

      // Clean up when leaving the page
      $(window).on('beforeunload', function () {
        stopPolling();
      });
    },
    
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        // Clean up
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
        initialized = false;
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
