/**
 * Scripts for Radio Localized
 **/

(function ($, Drupal) {

  Drupal.behaviors.getCoordsFromLinks = {
    attach: function attach(context, settings) {
      var $song_teasers = $(context).find('.song-teaser');
      if ($song_teasers.length) {
        $.each($song_teasers, function(index, value) {
          var thisLink = $(this).find('a');
          var thisLat = $(this).find('li.lat');
          var thisLon = $(this).find('li.lon');
          var thisLatVal = $(thisLat).text();
          var thisLonVal = $(thisLon).text();
          $(thisLink).mouseenter(function() {
            displayInfo('Lat/Lon: ' + thisLatVal + ', ' + thisLonVal);
            centerMap(thisLatVal,thisLonVal)
          });
        });
      }
    }
  };

  // Scoping jq vars : https://stackoverflow.com/questions/4153855/variable-scope-outside-jquery-function
  Drupal.behaviors.simpleLeafletSetup = {
    attach: function attach(context, settings) {

      $('body', context).once('myLeafletSetup').each(function () {

        displayInfo('Leaflet comin at ya');

        $mymap = L.map('mapid').setView([51.505, -0.09], 13);

        L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiYml4Z29tZXoiLCJhIjoiY2trbWFqM2NyMGcxNDJvcnJsczJuZGtwOSJ9.BMugFXrzYKMYDJYcMfCwag', {
          attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
          maxZoom: 18,
          id: 'mapbox/streets-v11',
          tileSize: 512,
          zoomOffset: -1,
          accessToken: 'your.mapbox.access.token'
        }).addTo($mymap);

        var marker = L.marker([51.5, -0.09]).addTo($mymap);

      });

    }
  };

  function centerMap(lat,lon) {
    var $info_dest = $('#js-info');
    $info_dest.text('lat/lon:' + lat + lon);
    var marker = L.marker([lat, lon]).addTo($mymap);
  }

  function displayInfo(info) {
    var $info_dest = $('#js-info');
    $info_dest.text(info);
  }

})(jQuery, Drupal);
