/**
 * Episode Audio Player with Timestamp Capture
 */

(function (Drupal) {
  'use strict';

  /**
   * Episode Player behavior
   */
  Drupal.behaviors.episodePlayer = {
    attach: function (context, settings) {
      once('episode-player', '.episode-player', context).forEach(function (element) {
        var audio = element.querySelector('audio');
        var timer = element.querySelector('.episode-timer');

        if (audio) {
          // Update timer display
          function updateTimer() {
            if (!isNaN(audio.currentTime)) {
              var time = formatTime(audio.currentTime);
              timer.textContent = time;
            }
          }

          // Format time as MM:SS
          function formatTime(seconds) {
            var minutes = Math.floor(seconds / 60);
            var remainingSeconds = Math.floor(seconds % 60);
            return minutes.toString().padStart(2, '0') + ':' + 
                   remainingSeconds.toString().padStart(2, '0');
          }

          // Update timer every second when playing
          var timerInterval;
          
          audio.addEventListener('play', function() {
            timerInterval = setInterval(updateTimer, 100);
            element.classList.add('playing');
          });

          audio.addEventListener('pause', function() {
            clearInterval(timerInterval);
            element.classList.remove('playing');
          });

          audio.addEventListener('ended', function() {
            clearInterval(timerInterval);
            element.classList.remove('playing');
          });

          audio.addEventListener('timeupdate', updateTimer);

          // Handle timestamp capture buttons
          document.addEventListener('click', function(e) {
            if (e.target.classList.contains('song-timestamp-button')) {
              e.preventDefault();
              
              var button = e.target;
              var songId = button.dataset.songId;
              var fieldType = button.dataset.fieldType;
              var currentTime = formatTime(audio.currentTime);
              
              // Show immediate feedback
              button.textContent = 'Setting...';
              button.disabled = true;
              
              // AJAX call to update the song timestamp
              fetch('/admin/song-timestamp/update', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                  song_id: songId,
                  field_type: fieldType,
                  timestamp: currentTime
                })
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Update the display cell
                  var row = button.closest('tr');
                  var cell = row.querySelector('.timestamp-' + fieldType);
                  if (cell) {
                    cell.textContent = currentTime;
                  }
                  
                  // Show success feedback
                  button.textContent = '✓';
                  button.classList.remove('btn-primary');
                  button.classList.add('btn-success');
                  
                  // Reset button after 2 seconds
                  setTimeout(function() {
                    button.textContent = 'Set';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-primary');
                    button.disabled = false;
                  }, 2000);
                } else {
                  alert('Error updating timestamp: ' + data.message);
                  button.textContent = 'Set';
                  button.disabled = false;
                }
              })
              .catch(error => {
                alert('Error updating timestamp');
                button.textContent = 'Set';
                button.disabled = false;
              });
            }
          });

          // Initialize timer display
          updateTimer();
        }
      });
    }
  };

})(Drupal);