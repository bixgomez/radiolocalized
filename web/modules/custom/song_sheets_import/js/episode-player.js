/**
 * Episode Audio Player with WaveSurfer.js and Timestamp Capture
 */

(function (Drupal) {
  'use strict';

  /**
   * Episode Player behavior
   */
  Drupal.behaviors.episodePlayer = {
    attach: function (context, settings) {
      once('episode-player', '.episode-player', context).forEach(function (element) {
        var timer = element.querySelector('.episode-timer');
        var playPauseBtn = element.querySelector('#play-pause-btn');
        var playIcon = playPauseBtn.querySelector('.play-icon');
        var pauseIcon = playPauseBtn.querySelector('.pause-icon');
        var skipBack30 = element.querySelector('#skip-back-30');
        var skipBack15 = element.querySelector('#skip-back-15');
        var skipBack5 = element.querySelector('#skip-back-5');
        var skipForward5 = element.querySelector('#skip-forward-5');
        var skipForward15 = element.querySelector('#skip-forward-15');
        var skipForward30 = element.querySelector('#skip-forward-30');
        var wavesurfer;

        // Wait for WaveSurfer to be available
        if (typeof WaveSurfer === 'undefined') {
          console.error('WaveSurfer.js not loaded');
          return;
        }

        // Get audio URL from the element's data attribute
        var audioUrl = element.dataset.audioUrl;
        if (!audioUrl) {
          console.error('Audio URL not found');
          return;
        }

        // Initialize WaveSurfer
        wavesurfer = WaveSurfer.create({
          container: element.querySelector('#waveform'),
          waveColor: '#4f46e5',
          progressColor: '#06b6d4',
          height: 80,
          normalize: true,
          backend: 'WebAudio',
          responsive: true
        });

        // Load the audio file
        wavesurfer.load(audioUrl);

        // Format time as MM:SS
        function formatTime(seconds) {
          var minutes = Math.floor(seconds / 60);
          var remainingSeconds = Math.floor(seconds % 60);
          return minutes.toString().padStart(2, '0') + ':' + 
                 remainingSeconds.toString().padStart(2, '0');
        }

        // Update timer display
        function updateTimer() {
          if (wavesurfer) {
            var currentTime = wavesurfer.getCurrentTime();
            timer.textContent = formatTime(currentTime);
          }
        }

        // Helper function to skip to specific time with bounds checking
        function skipToTime(seconds) {
          var currentTime = wavesurfer.getCurrentTime();
          var duration = wavesurfer.getDuration();
          var newTime = Math.max(0, Math.min(duration, currentTime + seconds));
          wavesurfer.seekTo(newTime / duration);
        }

        // Play/Pause button handler
        playPauseBtn.addEventListener('click', function() {
          wavesurfer.playPause();
        });

        // Skip button handlers
        skipBack30.addEventListener('click', function() {
          skipToTime(-30);
        });

        skipBack15.addEventListener('click', function() {
          skipToTime(-15);
        });

        skipBack5.addEventListener('click', function() {
          skipToTime(-5);
        });

        skipForward5.addEventListener('click', function() {
          skipToTime(5);
        });

        skipForward15.addEventListener('click', function() {
          skipToTime(15);
        });

        skipForward30.addEventListener('click', function() {
          skipToTime(30);
        });

        // WaveSurfer event listeners
        wavesurfer.on('ready', function() {
          console.log('WaveSurfer ready');
          updateTimer();
        });

        wavesurfer.on('audioprocess', function() {
          updateTimer();
        });

        wavesurfer.on('seek', function() {
          updateTimer();
        });

        wavesurfer.on('play', function() {
          element.classList.add('playing');
          playIcon.style.display = 'none';
          pauseIcon.style.display = 'inline';
        });

        wavesurfer.on('pause', function() {
          element.classList.remove('playing');
          playIcon.style.display = 'inline';
          pauseIcon.style.display = 'none';
        });

        wavesurfer.on('finish', function() {
          element.classList.remove('playing');
          playIcon.style.display = 'inline';
          pauseIcon.style.display = 'none';
        });

        // Handle timestamp capture buttons
        document.addEventListener('click', function(e) {
          if (e.target.classList.contains('song-timestamp-button')) {
            e.preventDefault();
            
            var button = e.target;
            var songId = button.dataset.songId;
            var fieldType = button.dataset.fieldType;
            var currentTime = formatTime(wavesurfer.getCurrentTime());
            
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

        // Store wavesurfer instance for global access
        element.wavesurfer = wavesurfer;
      });
    }
  };

})(Drupal);