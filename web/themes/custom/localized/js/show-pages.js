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
var center;
var map;
var marker;
var infowindow;

function initMap() {
  center = {lat: 41.8781, lng: -87.6298};
  map = new google.maps.Map(document.getElementById('map'), {
    zoom: 10,
    center: center
  });
  var marker = new google.maps.Marker({
    position: center,
    map: map
  });
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
