(function ($, Drupal, drupalSettings) {
  'use strict';

  // VERSION 3.3 - Fixed polling stop logic
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
            console.log('Status check:', data.is_running ? 'Running' : 'Not running', 'Output length:', (data.output || '').length);
            
            // Update output textarea if there's content
            var $output = $('#command-output');
            if ($output.length > 0 && data.output) {
              $output.val(data.output);
              
              // Scroll to bottom of output
              var outputElement = $output[0];
              outputElement.scrollTop = outputElement.scrollHeight;
            }

            // If command is not running, stop polling
            if (!data.is_running && pollInterval !== null) {
              console.log('Command not running, stopping polling');
              stopPolling();
              
              // Check if we need to refresh the form to show completion status
              var hasStopButton = $('[data-drupal-selector="edit-stop"]').length > 0;
              if (hasStopButton) {
                console.log('Stop button still visible, triggering form refresh');
                $('[data-drupal-selector="refresh-trigger"]').click();
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
          console.log('Starting polling (3 second interval)...');
          // Check immediately first
          checkStatus();
          // Then poll every 3 seconds
          pollInterval = setInterval(checkStatus, 3000);
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

      // Only check status on page load if Stop button exists (command already running)
      if ($('[data-drupal-selector="edit-stop"]').length > 0) {
        console.log('Stop button found - command is running, starting polling');
        startPolling();
      }

      // Listen for the Push button click to start polling
      $form.on('click', '[data-drupal-selector="edit-submit"]', function() {
        console.log('Push button clicked, will start polling after form submission');
        // Start polling after a short delay to allow the form to submit
        setTimeout(function() {
          console.log('Starting polling after button click');
          startPolling();
        }, 1000);
      });

      // After AJAX form submission, ensure polling is active if needed
      $(document).ajaxComplete(function(event, xhr, settings) {
        // Check if this was our form submission
        if (settings.url && settings.url.indexOf('/admin/config/system/cmesh-push-content') !== -1) {
          console.log('Form submitted via AJAX');
          
          // Wait a moment for the DOM to update
          setTimeout(function() {
            // If Stop button now exists and we're not already polling, start
            if ($('[data-drupal-selector="edit-stop"]').length > 0 && pollInterval === null) {
              console.log('Stop button detected after submission, starting polling');
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
