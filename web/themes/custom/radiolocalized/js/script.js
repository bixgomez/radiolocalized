/**
 * Scripts for Radio Localized
 **/

(function (Drupal, once) {
  
  Drupal.behaviors.getCoordsFromLinks = {
    attach(context) {

      // Display default map.
      const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoiYml4Z29tZXoiLCJhIjoiY2trbWFqM2NyMGcxNDJvcnJsczJuZGtwOSJ9.BMugFXrzYKMYDJYcMfCwag', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        accessToken: 'pk.eyJ1IjoiYml4Z29tZXoiLCJhIjoiY2trbWFqM2NyMGcxNDJvcnJsczJuZGtwOSJ9.BMugFXrzYKMYDJYcMfCwag'
      })

      // Initiate map with arbitrary default coordinates.
      const map = L.map('map', {
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
          let thisLat = songTeaser.querySelector('li.lat').innerText
          let thisLon = songTeaser.querySelector('li.lon').innerText

          // Add each lat/lon pair to the appropriate array.
          allLats.push(thisLat)
          allLons.push(thisLon)

          // Add a marker for this song location to the map
          L.marker([thisLat,thisLon]).addTo(map)

          // Fly to that point when rolling onto the song title.
          songTeaser.addEventListener("mouseenter", function(e) {
            songTeaser.classList.add("hovering")
            map.flyTo([thisLat, thisLon], 16, {
              animate: true,
              duration: 1.75
            })
          })

          // Zoom out a little when rolling off of the song title.
          songTeaser.addEventListener("mouseleave", function(e) {
            songTeaser.classList.remove("hovering")
            map.flyTo([thisLat, thisLon], 11, {
              animate: true,
              duration: 1.5
            })
            // resetMap()
          })

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
    }
  };

  Drupal.behaviors.songModal = {
    attach(context) {

      const foo1 = 'bar1'
      const foo2 = 'bar2'
      console.log('here we are')
      const songLinks = document.querySelectorAll('button.button--info')
      const modalOuter = document.querySelector('.modal-outer')
      const modalInner = document.querySelector('.modal-inner')
      // console.log(songLinks)
      // console.log(modalOuter)
      // console.log(modalInner)

      // songLinks.forEach(a => a.addEventListener('click', handleSongLinkClick))

      songLinks.forEach(function (songLink, index) {
        songLink.addEventListener('click', handleSongLinkClick)
      })

      function handleSongLinkClick(event) {
        const thisSong = event.currentTarget.closest('.song-teaser')
        const thisSongTitle = thisSong.querySelector('.data--song-title')?.textContent
        const thisSongYear = thisSong.querySelector('.data--song-year')?.textContent
        const thisSongAlbum = thisSong.querySelector('.data--song-album')?.textContent
        const thisSongArtist = thisSong.querySelector('.data--artist')?.textContent
        const thisSongDescription = thisSong.querySelector('.data--song-description')?.textContent
        const thisSongLocation = thisSong.querySelector('.data--song-location')?.textContent

        const thisSongTitleDisplay = (typeof thisSongTitle !== "undefined") ? thisSongTitle : '';
        const thisSongYearDisplay = (typeof thisSongYear !== "undefined") ? thisSongYear : '';
        const thisSongAlbumDisplay = (typeof thisSongAlbum !== "undefined") ? thisSongAlbum : '';
        const thisSongArtistDisplay = (typeof thisSongArtist !== "undefined") ? thisSongArtist : '';
        const thisSongLocationDisplay = (typeof thisSongLocation !== "undefined") ? thisSongLocation : '';
        const thisSongDescriptionDisplay = (typeof thisSongDescription !== "undefined") ? thisSongDescription : '';

        // console.log(thisSongTitle)
        // console.log(thisSongYear)
        // console.log(thisSongArtist)
        // console.log(thisSongAlbum)
        // console.log(thisSongLocation)
        // console.log(thisSongDescription)
        modalOuter.classList.add('open')
        modalInner.innerHTML = `
        <div class="song-info">
          <h1 class="song-info__title">${thisSongTitleDisplay}</h1>
          <h2 class="song-info__stats">${thisSongYearDisplay} ${thisSongArtistDisplay} ${thisSongAlbumDisplay}</h2>
          <h3 class="song-info__location">${thisSongLocationDisplay}</h3>
          <p class="song-info__description">${thisSongDescriptionDisplay}</p>
        </div>
        `
      }

      // If click outside the inner modal, the modal closes.
      modalOuter.addEventListener('click', function(e) {
        const isOutside = !e.target.closest('.modal-inner')
        if (isOutside) {
          closeModal()
        }
      })

      // If we hit the escape key, the modal closes.
      window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeModal()
        }
      })

      // Function that closes the modal.
      function closeModal() {
        modalOuter.classList.remove('open')
      }

    }
  }

}(Drupal, once))
