/**
 * @file
 * Mixcloud player integration for Radio Localized.
 *
 * Architecture: Mixcloud is the master clock. UI is a reflective layer.
 * We listen to the progress event and update the display accordingly.
 * No seeking. No forced jumps. Graceful handling of imperfect data.
 *
 * Timestamps are read directly from song-teaser DOM elements via data attributes.
 */

(function (Drupal, once) {
  'use strict';

  let widget = null;
  let songs = []; // Array of {element, start, end}
  let lastActiveElement = null;

  /**
   * Parse a timestamp string (MM:SS or M:SS) into seconds.
   *
   * @param {string} timestamp - Timestamp like "1:30" or "12:45".
   * @returns {number|null} - Time in seconds, or null if empty/invalid.
   */
  function parseTimestamp(timestamp) {
    if (!timestamp || timestamp === '') {
      return null;
    }

    if (timestamp.indexOf(':') !== -1) {
      const parts = timestamp.split(':');
      const minutes = parseInt(parts[0], 10) || 0;
      const seconds = parseInt(parts[1], 10) || 0;
      return (minutes * 60) + seconds;
    }

    // Fallback: assume it's already seconds.
    const parsed = parseInt(timestamp, 10);
    return isNaN(parsed) ? null : parsed;
  }

  /**
   * Load song data from DOM elements.
   * Each .song-teaser has data-start and data-end attributes.
   * We store the parent .song-wrapper as the element to add the active class to.
   */
  function loadSongsFromDOM() {
    songs = [];
    const songElements = document.querySelectorAll('.song-teaser');

    songElements.forEach(function (el) {
      const start = parseTimestamp(el.dataset.start);
      const end = parseTimestamp(el.dataset.end);
      const wrapper = el.closest('.song-wrapper');

      songs.push({
        element: wrapper || el,
        teaser: el,
        start: start,
        end: end,
      });
    });

    // DEBUG: Log loaded songs
    console.log('=== Mixcloud Player Init ===');
    console.log('Total songs loaded from DOM:', songs.length);
    songs.forEach(function (song, i) {
      const startMM = song.start !== null
        ? Math.floor(song.start / 60) + ':' + String(Math.floor(song.start % 60)).padStart(2, '0')
        : 'null';
      const endMM = song.end !== null
        ? Math.floor(song.end / 60) + ':' + String(Math.floor(song.end % 60)).padStart(2, '0')
        : 'null';
      console.log('Song ' + i + ': start=' + song.start + ' (' + startMM + '), end=' + song.end + ' (' + endMM + ')');
    });
  }

  /**
   * Find the active song based on current playback time.
   *
   * Primary logic: currentTime >= start && currentTime < end
   * Fallback: Most recent song whose start <= currentTime
   *
   * @param {number} currentTime - Current playback time in seconds.
   * @returns {object|null} - Song object with element, or null if none.
   */
  function getActiveSong(currentTime) {
    let fallbackSong = null;
    let fallbackStart = -1;

    for (let i = 0; i < songs.length; i++) {
      const song = songs[i];

      // Skip songs without start time.
      if (song.start === null) {
        continue;
      }

      // Primary match: within range [start, end).
      if (currentTime >= song.start && (song.end === null || currentTime < song.end)) {
        return song;
      }

      // Track fallback: most recent song where start <= currentTime.
      if (song.start <= currentTime && song.start > fallbackStart) {
        fallbackSong = song;
        fallbackStart = song.start;
      }
    }

    // No exact match - use fallback (handles gaps gracefully).
    return fallbackSong;
  }

  /**
   * Update the UI to reflect the active song.
   *
   * @param {object|null} song - Song object with element property.
   */
  function setActiveSong(song) {
    const newElement = song ? song.element : null;

    if (newElement === lastActiveElement) {
      return; // No change, avoid DOM thrash.
    }

    // Remove active class from previous song.
    if (lastActiveElement) {
      lastActiveElement.classList.remove('song--active');
    }

    lastActiveElement = newElement;

    if (!newElement) {
      return;
    }

    // Add active class to current song.
    newElement.classList.add('song--active');

    // Trigger click on the song's button to expand info/update map.
    const button = newElement.querySelector('.button--info');
    if (button) {
      button.click();
    }

    // Scroll into view if needed.
    newElement.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
    });
  }

  /**
   * Handle progress event from Mixcloud widget.
   *
   * @param {number} position - Current playback position in seconds.
   * @param {number} duration - Total duration in seconds.
   */
  function onProgress(position, duration) {
    // DEBUG
    console.log('Position:', position, '(' + Math.floor(position / 60) + ':' + String(Math.floor(position % 60)).padStart(2, '0') + ')');

    const activeSong = getActiveSong(position);
    setActiveSong(activeSong);
  }

  /**
   * Initialize the Mixcloud widget and set up event listeners.
   */
  function initWidget() {
    const iframe = document.getElementById('mixcloud-player');
    if (!iframe || typeof Mixcloud === 'undefined') {
      return;
    }

    widget = Mixcloud.PlayerWidget(iframe);

    widget.ready.then(function () {
      // Listen to progress event for playback time updates.
      widget.events.progress.on(onProgress);
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
        // Load song timestamps from DOM elements.
        loadSongsFromDOM();

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
  };

})(Drupal, once);
