document.addEventListener('DOMContentLoaded', function () {
  if (document.querySelectorAll('#map').length > 0)
  {
    if (document.querySelector('html').lang)
      lang = document.querySelector('html').lang;
    else
      lang = 'en';

    var js_file = document.createElement('script');
    js_file.type = 'text/javascript';
    js_file.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAa7NybO8xqWyAhp6-pJhWm8V_Rm5_fVwM&callback=initMap&signed_in=true&language=' + lang;
    document.getElementsByTagName('head')[0].appendChild(js_file);
  }
});

// variable to hold a map
var map;

// variable to hold current active InfoWindow
var activeInfoWindow;

function initMap() {
  map = new google.maps.Map(document.getElementById('map'), {
    center: {lat: 53.7, lng: -1.5},
    zoom: 9
  });

  fetch('markers.json')
    .then(function(response){return response.json()})
    .then(plotMarkers);
}

var markers;
var bounds;

function plotMarkers(m) {
  markers = [];
  bounds = new google.maps.LatLngBounds();

  // Loop through the markers.
  m.forEach(function (marker) {

    var position = new google.maps.LatLng(marker.lat, marker.lng);
    var title = marker.title;
    var description = marker.description;

    var thismarker = new google.maps.Marker({
      position: position,
      map: map,
      title: title,
      animation: google.maps.Animation.DROP
    });

    // save marker
    markers.push(thismarker);

    // create an InfoWindow
    var infoWnd = new google.maps.InfoWindow();

    infoWnd.setContent('<h3>'+title+'</h3><p>'+description+'</p>');

    // add listener on InfoWindow - close last infoWindow  before opening new one
    google.maps.event.addListener(thismarker, 'mouseover', function(){

      //Close active window if exists - [one might expect this to be default behaviour no?]
      if (activeInfoWindow != null) activeInfoWindow.close();

      // Open InfoWindow
      infoWnd.open(map, thismarker);

      // Store new open InfoWindow in global variable
      activeInfoWindow = infoWnd;
    });

    bounds.extend(position);
  });

  map.fitBounds(bounds);
}

function highlightLocation($lat,$lng,$title,$description) {
  console.log('Entering ' + $lat + ', ' + $lng);

  markers = [];

  var position = new google.maps.LatLng($lat, $lng);
  var title = $title;
  var description = $description;

  var thismarker = new google.maps.Marker({
    position: position,
    map: map,
    title: title,
    // animation: google.maps.Animation.DROP
  });
  markers.push(thismarker);

  map = thismarker.getMap();
  map.panTo(thismarker.getPosition());
  smoothZoom(map, 13, map.getZoom()); // call smoothZoom, parameters map, final zoomLevel, and starting zoom level

  console.log(markers);
}

function leaveLocation() {
  console.log('Leaving!');
  markers[0].setMap(null);
}

// the smooth zoom function
function smoothZoom (map, max, cnt) {
  if (cnt >= max) {
    return;
  }
  else {
    z = google.maps.event.addListener(map, 'zoom_changed', function(event){
      google.maps.event.removeListener(z);
      smoothZoom(map, max, cnt + 1);
    });
    setTimeout(function(){map.setZoom(cnt)}, 80); // 80ms is what I found to work well on my system -- it might not work well on all systems
  }
}
