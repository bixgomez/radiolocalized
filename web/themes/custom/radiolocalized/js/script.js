/**
 * Scripts for Radio Localized
 **/

(function (Drupal, once) {

  Drupal.behaviors.getCoordsFromLinks = {
    attach(context) {

      // Only initialize the map once per page load.
      once('leaflet-map-init', '#map', context).forEach(function(mapContainer) {

        // Display default map.
        const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png?access_token=***REMOVED***', {
          maxZoom: 19,
          attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
          accessToken: '***REMOVED***'
        })

        // Initiate map with arbitrary default coordinates.
        const map = L.map(mapContainer, {
          center: [40, -100],
          zoom: 10,
          layers: [osm]
        });

      // Grab all song teasers that appear on the page.
      const songTeasers = document.querySelectorAll('.song-teaser')

      // If there are any, do the things.
      if (songTeasers.length) {

        // Initiate arrays of all latitudes & all longitudes.
        const allLats = new Array()
        const allLons = new Array()

        // Loop through all song teasers found on the page.
        songTeasers.forEach(function (songTeaser, index) {

          // Get the latitude & longitude for this song.
          const latEl = songTeaser.querySelector('li.lat')
          const lonEl = songTeaser.querySelector('li.lon')

          // Skip songs without coordinates.
          if (!latEl || !lonEl) {
            return
          }

          let thisLat = latEl.innerText
          let thisLon = lonEl.innerText

          // Add each lat/lon pair to the appropriate array.
          allLats.push(thisLat)
          allLons.push(thisLon)

          // Add a marker for this song location to the map
          L.marker([thisLat,thisLon]).addTo(map)

          // Fly to that point when clicking the info button.
          const infoButton = songTeaser.querySelector('.button--info')
          if (infoButton) {
            infoButton.addEventListener('click', function(e) {
              map.flyTo([thisLat, thisLon], 16, {
                animate: true,
                duration: 1.75
              })
            })
          }

        })
        
        // Find minimum and maximum lats and lons.
        let maxLat = Math.max.apply(Math,allLats)
        let minLat = Math.min.apply(Math,allLats)
        let maxLon = Math.max.apply(Math,allLons)
        let minLon = Math.min.apply(Math,allLons)

        // Calculate average of all lats & lons.
        let avgLat = (maxLat + minLat)/2;
        let avgLon = (maxLon + minLon)/2;

        // Center the map at the average of all lats & lons, at a zoom level of 8.
        // map.setView(new L.LatLng(avgLat, avgLon), 8);

        // Center the map at a zoom level that accommodates all of the points.
        var bounds = new L.LatLngBounds([[maxLat,maxLon], [minLat,minLon]])
        map.fitBounds(bounds)

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
