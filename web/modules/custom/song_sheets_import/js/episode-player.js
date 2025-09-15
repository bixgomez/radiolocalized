/**
 * Episode Audio Player with WaveSurfer.js and Timestamp Capture
 */

(function (Drupal) {
  "use strict";

  /**
   * Episode Player behavior
   */
  Drupal.behaviors.episodePlayer = {
    attach: function (context, settings) {
      once("episode-player", ".episode-player", context).forEach(function (
        element
      ) {
        var timer = element.querySelector(".episode-timer");
        var playPauseBtn = element.querySelector("#play-pause-btn");
        var playIcon = playPauseBtn.querySelector(".play-icon");
        var pauseIcon = playPauseBtn.querySelector(".pause-icon");
        var skipBack30 = element.querySelector("#skip-back-30");
        var skipBack15 = element.querySelector("#skip-back-15");
        var skipBack5 = element.querySelector("#skip-back-5");
        var skipForward5 = element.querySelector("#skip-forward-5");
        var skipForward15 = element.querySelector("#skip-forward-15");
        var skipForward30 = element.querySelector("#skip-forward-30");
        var wavesurfer;

        // Wait for WaveSurfer to be available
        if (typeof WaveSurfer === "undefined") {
          console.error("WaveSurfer.js not loaded");
          return;
        }

        // Get audio URL from the element's data attribute
        var audioUrl = element.dataset.audioUrl;
        if (!audioUrl) {
          console.error("Audio URL not found");
          return;
        }

        // Initialize WaveSurfer with optimizations for faster loading
        wavesurfer = WaveSurfer.create({
          container: element.querySelector("#waveform"),
          waveColor: "#4f46e5",
          progressColor: "#06b6d4",
          height: 80,
          normalize: true,
          backend: "WebAudio",
          responsive: true,
          // Use MediaElement as fallback for faster initial playback
          mediaControls: false,
          interact: true,
        });

        // Show loading state - disable all controls
        playPauseBtn.disabled = true;
        playIcon.textContent = "Loading...";
        pauseIcon.style.display = "none";
        playIcon.style.display = "inline";
        skipBack30.disabled = true;
        skipBack15.disabled = true;
        skipBack5.disabled = true;
        skipForward5.disabled = true;
        skipForward15.disabled = true;
        skipForward30.disabled = true;
        element.classList.add("loading");

        // Load the audio file
        wavesurfer.load(audioUrl);

        // Add loading progress feedback with better stages
        var loadingStage = "download";
        
        wavesurfer.on("loading", function (percent) {
          if (percent < 100) {
            playIcon.textContent = "Downloading " + Math.round(percent) + "%";
            loadingStage = "download";
          } else {
            playIcon.textContent = "Processing audio...";
            loadingStage = "processing";
          }
        });

        // Format time as MM:SS (for durations over 1 hour, show as MM:SS not HH:MM:SS)
        function formatTime(seconds) {
          var minutes = Math.floor(seconds / 60);
          var remainingSeconds = Math.floor(seconds % 60);
          return (
            minutes.toString().padStart(2, "0") +
            ":" +
            remainingSeconds.toString().padStart(2, "0")
          );
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
        playPauseBtn.addEventListener("click", function () {
          wavesurfer.playPause();
        });

        // Skip button handlers
        skipBack30.addEventListener("click", function () {
          skipToTime(-30);
        });

        skipBack15.addEventListener("click", function () {
          skipToTime(-15);
        });

        skipBack5.addEventListener("click", function () {
          skipToTime(-5);
        });

        skipForward5.addEventListener("click", function () {
          skipToTime(5);
        });

        skipForward15.addEventListener("click", function () {
          skipToTime(15);
        });

        skipForward30.addEventListener("click", function () {
          skipToTime(30);
        });

        // WaveSurfer event listeners
        // Add decode progress for waveform generation
        wavesurfer.on("decode", function () {
          if (loadingStage === "processing") {
            playIcon.textContent = "Building waveform...";
          }
        });

        wavesurfer.on("ready", function () {
          console.log("WaveSurfer ready");

          // Remove loading state and enable controls
          playPauseBtn.disabled = false;
          playIcon.textContent = "▶";
          pauseIcon.style.display = "none";
          playIcon.style.display = "inline";
          element.classList.remove("loading");

          // Enable skip buttons
          skipBack30.disabled = false;
          skipBack15.disabled = false;
          skipBack5.disabled = false;
          skipForward5.disabled = false;
          skipForward15.disabled = false;
          skipForward30.disabled = false;

          updateTimer();
        });

        wavesurfer.on("audioprocess", function () {
          updateTimer();
        });

        wavesurfer.on("seek", function () {
          updateTimer();
        });

        wavesurfer.on("play", function () {
          element.classList.add("playing");
          playIcon.style.display = "none";
          pauseIcon.style.display = "inline";
        });

        wavesurfer.on("pause", function () {
          element.classList.remove("playing");
          playIcon.style.display = "inline";
          pauseIcon.style.display = "none";
        });

        wavesurfer.on("finish", function () {
          element.classList.remove("playing");
          playIcon.style.display = "inline";
          pauseIcon.style.display = "none";
        });

        wavesurfer.on("error", function (error) {
          console.error("WaveSurfer error:", error);

          // Remove loading state and show error
          playPauseBtn.disabled = false;
          playIcon.textContent = "Error";
          playIcon.style.color = "red";
          pauseIcon.style.display = "none";
          playIcon.style.display = "inline";
          element.classList.remove("loading");
          element.classList.add("error");

          alert("Error loading audio file. Please try refreshing the page.");
        });

        // Handle GoTo buttons
        document.addEventListener("click", function (e) {
          if (e.target.classList.contains("song-goto-button")) {
            e.preventDefault();
            
            var button = e.target;
            var timestamp = button.dataset.timestamp;
            
            if (timestamp && timestamp !== '--:--' && wavesurfer) {
              // Parse MM:SS format to seconds
              var timeParts = timestamp.split(':');
              if (timeParts.length === 2) {
                var minutes = parseInt(timeParts[0], 10) || 0;
                var seconds = parseInt(timeParts[1], 10) || 0;
                var totalSeconds = minutes * 60 + seconds;
                
                // Get duration and calculate seek position
                var duration = wavesurfer.getDuration();
                if (duration > 0) {
                  var seekTo = totalSeconds / duration;
                  wavesurfer.seekTo(seekTo);
                  
                  // Visual feedback
                  button.textContent = '✓';
                  setTimeout(function() {
                    button.textContent = 'GoTo';
                  }, 1000);
                }
              }
            }
          }
          
          // Handle timestamp capture buttons
          if (e.target.classList.contains("song-timestamp-button")) {
            e.preventDefault();

            var button = e.target;
            var songId = button.dataset.songId;
            var fieldType = button.dataset.fieldType;
            var currentTime = formatTime(wavesurfer.getCurrentTime());
            var currentRow = button.closest("tr");
            var nextRow = currentRow ? currentRow.nextElementSibling : null;
            var isLastRow = !nextRow || nextRow.tagName !== 'TR';
            var episodeDuration = formatTime(wavesurfer.getDuration());

            // Find previous song info if we're setting a start time
            var previousSongId = null;
            if (fieldType === "start") {
              var previousRow = currentRow.previousElementSibling;
              if (previousRow && previousRow.tagName === "TR") {
                var prevButton = previousRow.querySelector(
                  '.song-timestamp-button[data-field-type="start"]'
                );
                if (prevButton) {
                  previousSongId = prevButton.dataset.songId;
                }
              }
            }

            // Show immediate feedback
            button.textContent = "Setting...";
            button.disabled = true;

            // Prepare request data
            var requestData = {
              song_id: songId,
              field_type: fieldType,
              timestamp: currentTime,
            };

            // Add previous song data if we found one
            if (previousSongId) {
              requestData.previous_song_id = previousSongId;
              requestData.previous_timestamp = currentTime;
            }

            // If this is the last song and we're setting the start time,
            // also set its end time to the episode's full duration.
            if (fieldType === 'start' && isLastRow && episodeDuration) {
              requestData.current_end_timestamp = episodeDuration;
            }

            // AJAX call to update the song timestamp
            fetch("/admin/song-timestamp/update", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded",
              },
              body: new URLSearchParams(requestData),
            })
              .then((response) => response.json())
              .then((data) => {
                if (data.success) {
                  // Update the current song's display cell
                  var cell = currentRow.querySelector(
                    ".timestamp-" + fieldType
                  );
                  if (cell) {
                    cell.textContent = currentTime;
                  }
                  
                  // Enable and update the corresponding GoTo button
                  var gotoButton = currentRow.querySelector(
                    '.song-goto-button[data-field-type="' + fieldType + '"]'
                  );
                  if (gotoButton) {
                    gotoButton.dataset.timestamp = currentTime;
                    gotoButton.disabled = false;
                  }

                  // If we set the current song's end time (last row), update UI.
                  if (requestData.current_end_timestamp) {
                    var endCell = currentRow.querySelector('.timestamp-end');
                    if (endCell) {
                      endCell.textContent = requestData.current_end_timestamp;
                    }
                    var endGoto = currentRow.querySelector('.song-goto-button[data-field-type="end"]');
                    if (endGoto) {
                      endGoto.dataset.timestamp = requestData.current_end_timestamp;
                      endGoto.disabled = false;
                    }
                  }

                  // If we updated a previous song's end time, update that display too
                  if (data.previous_updated && previousSongId) {
                    var previousRow = currentRow.previousElementSibling;
                    if (previousRow) {
                      var prevEndCell =
                        previousRow.querySelector(".timestamp-end");
                      if (prevEndCell) {
                        prevEndCell.textContent = currentTime;
                      }
                      
                    }
                  }

                  // Show success feedback
                  button.textContent = "✓";
                  button.classList.remove("btn-primary");
                  button.classList.add("btn-success");

                  // Reset button after 2 seconds
                  setTimeout(function () {
                    button.textContent = "Set";
                    button.classList.remove("btn-success");
                    button.classList.add("btn-primary");
                    button.disabled = false;
                  }, 2000);
                } else {
                  alert("Error updating timestamp: " + data.message);
                  button.textContent = "Set";
                  button.disabled = false;
                }
              })
              .catch((error) => {
                alert("Error updating timestamp");
                button.textContent = "Set";
                button.disabled = false;
              });
          }
        });

        // Store wavesurfer instance for global access
        element.wavesurfer = wavesurfer;
      });

      // Handle reset order button (outside the episode-player context)
      once("reset-order", "#reset-episode-order", context).forEach(function (
        button
      ) {
        button.addEventListener("click", function () {
          // Try multiple selectors to find the table
          var table =
            document.querySelector(".views-table tbody") ||
            document.querySelector("table tbody") ||
            document.querySelector(".view-content table tbody") ||
            document.querySelector(".episode-songs-table tbody") ||
            document.querySelector('[class*="view"] tbody');

          if (!table) {
            console.log(
              "Available tables:",
              document.querySelectorAll("table")
            );
            console.log(
              "Available tbody elements:",
              document.querySelectorAll("tbody")
            );
            alert("Could not find song table to reorder");
            return;
          }

          // Get all table rows
          var rows = Array.from(table.querySelectorAll("tr"));

          // Sort rows by track number (get from data attribute or parse from content)
          rows.sort(function (a, b) {
            // Try to get track number from row data or song ID
            var aTrackNum = getTrackNumber(a);
            var bTrackNum = getTrackNumber(b);
            return aTrackNum - bTrackNum;
          });

          // Remove all rows and re-append in sorted order
          rows.forEach(function (row) {
            table.removeChild(row);
          });
          rows.forEach(function (row) {
            table.appendChild(row);
          });

          // Show success message
          button.textContent = "✅ Ordered";
          setTimeout(function () {
            button.innerHTML = "🔄 Reset Order";
          }, 2000);
        });

        // Helper function to extract track number from table row
        function getTrackNumber(row) {
          // Try to get song ID from Set button and use that as fallback
          var setButton = row.querySelector(".song-timestamp-button");
          if (setButton && setButton.dataset.songId) {
            // Use song ID as fallback (newer songs will have higher IDs)
            return parseInt(setButton.dataset.songId) || 9999;
          }
          return 9999; // Fallback for rows without buttons
        }
      });
    },
  };
})(Drupal);
