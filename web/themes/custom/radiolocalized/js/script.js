/**
 * Scripts for Radio Localized
 **/

(function (Drupal, once) {

  Drupal.behaviors.getCoordsFromLinks = {
    attach(context) {

      // Only initialize the map once per page load.
      once('leaflet-map-init', '#map', context).forEach(function(mapContainer) {

        // Display default map.
        const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        })

        // Initiate map with arbitrary default coordinates.
        const map = L.map(mapContainer, {
          center: [40, -100],
          zoom: 10,
          layers: [osm]
        });

      // Grab all song teasers that appear on the page.
      const songTeasers = mapContainer.ownerDocument.querySelectorAll('.song-teaser')
      const contentRegion = mapContainer.ownerDocument.querySelector('.region--content')

      const keepPanelInView = function(songInfoEl) {
        if (!contentRegion || !songInfoEl) {
          return
        }

        const revealPadding = 28
        const getDurationMs = function(el) {
          const raw = window.getComputedStyle(el).transitionDuration.split(',')[0].trim()
          if (!raw) {
            return 0
          }
          if (raw.endsWith('ms')) {
            return parseFloat(raw)
          }
          if (raw.endsWith('s')) {
            return parseFloat(raw) * 1000
          }
          return 0
        }

        const ensureVisible = function() {
          const contentRect = contentRegion.getBoundingClientRect()
          const panelRect = songInfoEl.getBoundingClientRect()
          const panelMarginBottom = parseFloat(window.getComputedStyle(songInfoEl).marginBottom) || 0
          const panelBottom = panelRect.bottom + panelMarginBottom

          if (panelBottom > contentRect.bottom - revealPadding) {
            const delta = panelBottom - contentRect.bottom + revealPadding
            contentRegion.scrollBy({
              top: delta,
              behavior: 'smooth'
            })
          }
        }

        const durationMs = getDurationMs(songInfoEl)
        const endDelay = Math.max(0, durationMs + 40)

        // Run immediately, once during the reveal, and once after it completes.
        window.requestAnimationFrame(ensureVisible)
        window.setTimeout(ensureVisible, Math.round(durationMs * 0.5))
        window.setTimeout(ensureVisible, endDelay)
      }

      const setPanelMaxHeight = function(songInfoEl) {
        if (!songInfoEl) {
          return
        }
        songInfoEl.style.setProperty('--song-info-max-height', songInfoEl.scrollHeight + 'px')
      }

      // If there are any, do the things.
      if (songTeasers.length) {

        // Cache interactive elements and map points to avoid repeated DOM queries.
        const infoButtons = new Array()
        const songInfos = new Array()
        const songWrappers = new Array()
        const points = new Array()
        let bounds = null

        // Loop through all song teasers found on the page.
        songTeasers.forEach(function (songTeaser) {

          // Get the latitude & longitude for this song.
          const latEl = songTeaser.querySelector('li.lat')
          const lonEl = songTeaser.querySelector('li.lon')

          let thisLat = null
          let thisLon = null
          let hasValidCoords = false

          if (latEl && lonEl) {
            thisLat = parseFloat(latEl.innerText.trim())
            thisLon = parseFloat(lonEl.innerText.trim())
            hasValidCoords = Number.isFinite(thisLat) && Number.isFinite(thisLon)
          }

          if (hasValidCoords) {
            points.push([thisLat, thisLon])

            // Add a marker for this song location to the map.
            L.marker([thisLat, thisLon]).addTo(map)
          }

          // Fly to that point when clicking the info button.
          const infoButton = songTeaser.querySelector('.button--info')
          const songInfo = songTeaser.nextElementSibling
          const songWrapper = songTeaser.closest('.song-wrapper')

          if (infoButton) {
            infoButtons.push(infoButton)
            if (songInfo && songInfo.classList.contains('song-info')) {
              songInfos.push(songInfo)
            }
            if (songWrapper) {
              songWrappers.push(songWrapper)
            }

            infoButton.addEventListener('click', function() {
              // Reset all other buttons, song-info panels, and wrappers.
              infoButtons.forEach(function(btn) {
                if (btn !== infoButton) {
                  btn.classList.remove('active')
                }
              })

              songInfos.forEach(function(info) {
                if (info !== songInfo) {
                  info.classList.remove('active')
                }
              })

              songWrappers.forEach(function(wrapper) {
                if (wrapper !== songWrapper) {
                  wrapper.classList.remove('song--active')
                }
              })

              // Toggle the active class for icon rotation
              const isActive = infoButton.classList.toggle('active')
              infoButton.setAttribute('aria-expanded', isActive ? 'true' : 'false')

              // Toggle song-info visibility
              if (songInfo && songInfo.classList.contains('song-info')) {
                if (isActive) {
                  setPanelMaxHeight(songInfo)
                }
                songInfo.classList.toggle('active', isActive)
                if (isActive) {
                  // Recalculate once expanded to keep height in sync with rendered content.
                  window.requestAnimationFrame(function() {
                    setPanelMaxHeight(songInfo)
                  })
                  keepPanelInView(songInfo)
                }
              }

              // Toggle song--active class on wrapper
              if (songWrapper) {
                songWrapper.classList.toggle('song--active', isActive)
              }

              if (isActive && hasValidCoords) {
                // Fly to this song's location
                map.flyTo([thisLat, thisLon], 16, {
                  animate: true,
                  duration: 1.75
                })
              } else if (bounds && bounds.isValid()) {
                // Reset map to show all points
                map.flyToBounds(bounds, {
                  animate: true,
                  duration: 1.75,
                  padding: [50, 50]
                })
              }
            })
          }

        })
        // Stop here if no valid coordinates were found.
        if (!points.length) {
          return
        }

        // Center the map at the average of all lats & lons, at a zoom level of 8.
        // map.setView(new L.LatLng(avgLat, avgLon), 8);

        // Center the map at a zoom level that accommodates all of the points.
        bounds = new L.LatLngBounds(points)
        map.fitBounds(bounds, { padding: [50, 50] })

        // After 8 seconds, zoom out a bit, centering on the average lat & lon.
        // TODO: Make sure this does NOT happen if we have entered another song tile!
        /*
        const resetMap = async () => {
          await sleep(8000)
          map.flyTo([avgLat, avgLon], 9, {
            animate: true,
            duration: 1.5
          })
        } 
        */
        
        /*
        const sleep = async (milliseconds) => {
          await new Promise(resolve => {
            return setTimeout(resolve, milliseconds)
          })
        }
        */

        }
      }); // End once('leaflet-map-init')
    }
  };

}(Drupal, once))
