<?php

namespace Drupal\radiolocalized_mixcloud\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\node\NodeInterface;

/**
 * Provides the Mixcloud Player block.
 *
 * @Block(
 *   id = "mixcloud_player",
 *   admin_label = @Translation("Mixcloud Player"),
 *   category = @Translation("Radio Localized"),
 * )
 */
class MixcloudPlayerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');

    // Always return cache metadata so Drupal re-evaluates per route.
    $empty = [
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];

    if (!$node instanceof NodeInterface || $node->bundle() !== 'episode') {
      \Drupal::logger('mixcloud')->debug('Not an episode node');
      return $empty;
    }

    if (!$node->hasField('field_mixcloud_url') || $node->get('field_mixcloud_url')->isEmpty()) {
      \Drupal::logger('mixcloud')->debug('No mixcloud URL on node @id', ['@id' => $node->id()]);
      $empty['#cache']['tags'] = ['node:' . $node->id()];
      return $empty;
    }

    $url = $node->get('field_mixcloud_url')->uri;
    \Drupal::logger('mixcloud')->debug('Mixcloud URL: @url', ['@url' => $url]);

    // Extract the feed path from the URL.
    // e.g., https://www.mixcloud.com/radiolocalized/radio-localized-032-chile/
    // becomes /radiolocalized/radio-localized-032-chile/
    $feed = str_replace('https://www.mixcloud.com', '', $url);

    // Ensure feed starts with /
    if (strpos($feed, '/') !== 0) {
      $feed = '/' . $feed;
    }

    // Ensure feed ends with /
    if (substr($feed, -1) !== '/') {
      $feed .= '/';
    }

    // Get song data for JS.
    $songs = $this->getSongData($node);

    return [
      '#type' => 'inline_template',
      '#template' => '<div class="mixcloud-player-wrapper" data-episode-id="{{ episode_id }}">
        <iframe
          id="mixcloud-player"
          width="100%"
          height="120"
          src="https://www.mixcloud.com/widget/iframe/?feed={{ feed|url_encode }}&hide_cover=1&light=0"
          frameborder="0"
          allow="autoplay"
        ></iframe>
      </div>',
      '#context' => [
        'feed' => $feed,
        'episode_id' => $node->id(),
      ],
      '#attached' => [
        'library' => ['radiolocalized_mixcloud/player'],
        'drupalSettings' => [
          'mixcloudPlayer' => [
            'feed' => $feed,
            'episodeId' => $node->id(),
            'songs' => $songs,
          ],
        ],
      ],
    ];
  }

  /**
   * Get song data with timestamps for the episode.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The episode node.
   *
   * @return array
   *   Array of song data with start/end times.
   */
  protected function getSongData(NodeInterface $node) {
    $songs = [];

    if (!$node->hasField('field_song') || $node->get('field_song')->isEmpty()) {
      return $songs;
    }

    foreach ($node->get('field_song') as $delta => $item) {
      $song_node = $item->entity;
      if (!$song_node) {
        continue;
      }

      $song_data = [
        'id' => $song_node->id(),
        'title' => $song_node->label(),
        'delta' => $delta,
      ];

      // Get start time if available.
      if ($song_node->hasField('field_start_time') && !$song_node->get('field_start_time')->isEmpty()) {
        $song_data['start'] = (float) $song_node->get('field_start_time')->value;
      }

      // Get end time if available.
      if ($song_node->hasField('field_end_time') && !$song_node->get('field_end_time')->isEmpty()) {
        $song_data['end'] = (float) $song_node->get('field_end_time')->value;
      }

      // Get place data if available.
      if ($song_node->hasField('field_place') && !$song_node->get('field_place')->isEmpty()) {
        $place = $song_node->get('field_place')->entity;
        if ($place && $place->hasField('field_location') && !$place->get('field_location')->isEmpty()) {
          $location = $place->get('field_location')->first();
          if ($location) {
            $song_data['lat'] = $location->lat;
            $song_data['lng'] = $location->lng;
            $song_data['place'] = $place->label();
          }
        }
      }

      $songs[] = $song_data;
    }

    // Sort by start time.
    usort($songs, function ($a, $b) {
      $a_start = $a['start'] ?? PHP_INT_MAX;
      $b_start = $b['start'] ?? PHP_INT_MAX;
      return $a_start <=> $b_start;
    });

    return $songs;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    return parent::getCacheTags();
  }

}
