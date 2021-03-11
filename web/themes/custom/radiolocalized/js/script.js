/**
 * Scripts for Radio Localized
 **/

(function ($, Drupal) {

  // Leaflet as per : https://leafletjs.com/examples/quick-start/
  // Scoping jq vars : https://stackoverflow.com/questions/4153855/variable-scope-outside-jquery-function
  Drupal.behaviors.simpleLeafletSetup = {
    attach: function attach(context, settings) {

      $('body', context).once('myLeafletSetup').each(function () {

        // If we want to dynamically place the map div.  Right now we don't.
        // const placeMapHere = $('article.node--type-episode div.layout__region--second');
        // const removeThis = $(placeMapHere).find('div.block');
        // $( removeThis ).remove();
        // $( placeMapHere ).append( '<div id="mapid"></div>' );

        $leafletMap = L.map('leaflet-map');
        // $leafletMap = L.map('leaflet-map').setView([53.47938, -2.247311], 9);

        L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiYml4Z29tZXoiLCJhIjoiY2trbWFqM2NyMGcxNDJvcnJsczJuZGtwOSJ9.BMugFXrzYKMYDJYcMfCwag', {
          attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
          maxZoom: 18,
          id: 'mapbox/streets-v11',
          tileSize: 512,
          zoomOffset: -1,
          accessToken: 'pk.eyJ1IjoiYml4Z29tZXoiLCJhIjoiY2trbWFqM2NyMGcxNDJvcnJsczJuZGtwOSJ9.BMugFXrzYKMYDJYcMfCwag'
        }).addTo($leafletMap);

        // var marker = L.marker([51.5, -0.09]).addTo($mymap);
      });

    }
  };

  Drupal.behaviors.getCoordsFromLinks = {
    attach: function attach(context, settings) {
      var $song_teasers = $(context).find('.song-teaser');
      if ($song_teasers.length) {

        // Declare array of all lats & lons.
        var allLats = new Array();
        var allLons = new Array();

        // Loop through all song teasers found on the page.
        $.each($song_teasers, function(index, value) {

          // Get the lat & lon for this song, and push to the array.
          var thisLink = $(this).find('a');
          var thisLat = $(this).find('li.lat');
          var thisLon = $(this).find('li.lon');
          var thisLatVal = $(thisLat).text();
          var thisLonVal = $(thisLon).text();
          allLats.push(thisLatVal);
          allLons.push(thisLonVal);

          $(thisLink).mouseenter(function() {
            centerMap(thisLatVal,thisLonVal,'flyTo', 13);
            addMarker(thisLatVal,thisLonVal);
          });

          $(thisLink).mouseleave(function() {
            centerMap(thisLatVal,thisLonVal,'flyTo', 12);
          });
        });

        console.log(allLats);
        console.log(allLons);

        var maxLat = Math.max.apply(Math,allLats);
        var minLat = Math.min.apply(Math,allLats);
        var maxLon = Math.max.apply(Math,allLons);
        var minLon = Math.min.apply(Math,allLons);

        var avgLat = (maxLat + minLat)/2;
        var avgLon = (maxLon + minLon)/2;
        centerMap(avgLat,avgLon,'setView', 8);

      }
    }
  };

  function centerMap(lat,lon,method,zoom) {
    console.log('centering map at ' + lat + ', ' + lon);
    console.log('method = ' + method);

    if ($.isNumeric(zoom) === false ) {
      let zoom = 10;
    }

    console.log('zoom = ' + zoom);

    if (method === 'flyTo') {
      console.log('flying to it');
      $leafletMap.flyTo(new L.LatLng(lat,lon), zoom);
    }
    else if (method === 'panTo') {
      console.log('panning to it');
      $leafletMap.panTo(new L.LatLng(lat,lon), zoom);
    }
    else {
      console.log('just setting it');
      $leafletMap.setView(new L.LatLng(lat,lon), zoom);
    }
  }

  function addMarker(lat,lon) {
    console.log('adding marker at ' + lat + ', ' + lon);
    var targetLatLng = L.latLng(lat, lon);

    // https://stackoverflow.com/questions/58681396/check-if-a-location-on-a-leaflet-map-is-a-marker-or-not
    // $leafletMap.eachLayer(function(layer) {
    //   console.log(layer);
    //   if (layer instanceof L.Marker) {
    //     if (layer.getLatLng() === targetLatLng) {
    //       console.log('already a marker here!');
    //     }
    //   }
    // });

    var marker = L.marker([lat, lon]).addTo($leafletMap);
  }

  function displayInfo(info) {
    var $info_dest = $('#js-info');
    $info_dest.text(info);
  }

})(jQuery, Drupal);
