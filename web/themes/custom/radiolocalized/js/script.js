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
            console.log(thisLatVal);
            console.log(thisLonVal);
          });

        });

      }

    }
  };

})(jQuery, Drupal);
