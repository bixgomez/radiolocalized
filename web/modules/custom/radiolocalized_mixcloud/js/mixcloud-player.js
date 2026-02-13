/**
 * @file
 * Mixcloud player integration for Radio Localized.
 *
 * Architecture: Mixcloud is the master clock. UI is a reflective layer.
 * We poll getCurrentTime() and update the display accordingly.
 * No seeking. No forced jumps. Graceful handling of imperfect data.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const POLL_INTERVAL = 300; // ms

  let widget = null;
  let songs = [];
  let lastActiveIndex = -1;
  let pollTimer = null;
  let isReady = false;

  /**
   * Determine which song is active based on current playback time.
   *
   * Primary logic: currentTime >= start && currentTime < end
   * Fallback: Most recent song whose start <= currentTime
   *
   * @param {number} currentTime - Current playback time in seconds.
   * @returns {number} - Index of active song, or -1 if none.
   */
  function getActiveSongIndex(currentTime) {
    let fallbackIndex = -1;
    let fallbackStart = -1;

    for (let i = 0; i < songs.length; i++) {
      const song = songs[i];
      const start = song.start ?? null;
      const end = song.end ?? null;

      // Skip songs without start time.
      if (start === null) {
        continue;
      }

      // Primary match: within range [start, end).
      if (currentTime >= start && (end === null || currentTime < end)) {
        return i;
      }

      // Track fallback: most recent song where start <= currentTime.
      if (start <= currentTime && start > fallbackStart) {
        fallbackIndex = i;
        fallbackStart = start;
      }
    }

    // No exact match - use fallback (handles gaps gracefully).
    return fallbackIndex;
  }

  /**
   * Update the UI to reflect the active song.
   *
   * @param {number} index - Index of active song.
   */
  function setActiveSong(index) {
    if (index === lastActiveIndex) {
      return; // No change, avoid DOM thrash.
    }

    lastActiveIndex = index;

    // Remove active class from all songs.
    const songElements = document.querySelectorAll('.song-teaser');
    songElements.forEach((el, i) => {
      el.classList.remove('song-teaser--active');
    });

    if (index < 0 || index >= songElements.length) {
      return;
    }

    // Add active class to current song.
    const activeElement = songElements[index];
    activeElement.classList.add('song-teaser--active');

    // Scroll into view if needed.
    activeElement.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
    });

    // Update map if song has location data.
    const song = songs[index];
    if (song && song.lat && song.lng) {
      updateMap(song.lat, song.lng, song.place);
    }
  }

  /**
   * Update the map to show the song's location.
   *
   * @param {number} lat - Latitude.
   * @param {number} lng - Longitude.
   * @param {string} place - Place name.
   */
  function updateMap(lat, lng, place) {
    // Dispatch custom event for map integration.
    const event = new CustomEvent('mixcloud:songLocation', {
      detail: { lat, lng, place },
    });
    document.dispatchEvent(event);
  }

  /**
   * Poll the widget for current time and update UI.
   */
  function pollPlaybackTime() {
    if (!widget || !isReady) {
      return;
    }

    widget.getCurrentTime().then(function (seconds) {
      const activeIndex = getActiveSongIndex(seconds);
      setActiveSong(activeIndex);
    }).catch(function (err) {
      // Widget may not be ready or playback hasn't started.
      // Fail silently.
    });
  }

  /**
   * Initialize the Mixcloud widget and start polling.
   */
  function initWidget() {
    const iframe = document.getElementById('mixcloud-player');
    if (!iframe || typeof Mixcloud === 'undefined') {
      return;
    }

    widget = Mixcloud.PlayerWidget(iframe);

    widget.ready.then(function () {
      isReady = true;
      console.log('Mixcloud player ready');

      // Start polling for playback time.
      pollTimer = setInterval(pollPlaybackTime, POLL_INTERVAL);

      // Also poll on play event.
      widget.events.play.on(function () {
        pollPlaybackTime();
      });
    }).catch(function (err) {
      console.error('Mixcloud widget failed to initialize:', err);
    });
  }

  /**
   * Drupal behavior for Mixcloud player.
   */
  Drupal.behaviors.mixcloudPlayer = {
    attach: function (context, settings) {
      once('mixcloud-player', '.mixcloud-player-wrapper', context).forEach(function (element) {
        // Load song data from drupalSettings.
        if (settings.mixcloudPlayer && settings.mixcloudPlayer.songs) {
          songs = settings.mixcloudPlayer.songs;
        }

        // Wait for Mixcloud API to load, then initialize.
        if (typeof Mixcloud !== 'undefined') {
          initWidget();
        } else {
          // API not loaded yet, wait for it.
          const checkInterval = setInterval(function () {
            if (typeof Mixcloud !== 'undefined') {
              clearInterval(checkInterval);
              initWidget();
            }
          }, 100);
        }
      });
    },

    detach: function (context, settings, trigger) {
      if (trigger === 'unload' && pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    },
  };

})(Drupal, drupalSettings, once);
