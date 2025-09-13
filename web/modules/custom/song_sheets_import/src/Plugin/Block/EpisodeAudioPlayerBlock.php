<?php

namespace Drupal\song_sheets_import\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides an Episode Audio Player block.
 *
 * @Block(
 *   id = "episode_audio_player",
 *   admin_label = @Translation("Episode Audio Player"),
 *   category = @Translation("Custom")
 * )
 */
class EpisodeAudioPlayerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EpisodeAudioPlayerBlock.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Check if we're on an episode page (episodes/%)
    if ($this->routeMatch->getRouteName() == 'view.episodes.page_episode_songs') {
      $episode_number = $this->routeMatch->getParameter('arg_0');
      
      if ($episode_number) {
        // Find the episode node by episode number
        $episode_query = $this->entityTypeManager->getStorage('node')->getQuery()
          ->condition('type', 'episode')
          ->condition('field_episode_number', $episode_number)
          ->accessCheck(FALSE)
          ->range(0, 1);
        
        $episode_nids = $episode_query->execute();
        
        if (!empty($episode_nids)) {
          $episode_nid = reset($episode_nids);
          $episode = $this->entityTypeManager->getStorage('node')->load($episode_nid);
          
          if ($episode && $episode->hasField('field_audio_file') && !$episode->get('field_audio_file')->isEmpty()) {
            $audio_media = $episode->get('field_audio_file')->entity;
            
            if ($audio_media && $audio_media->hasField('field_media_audio_file') && !$audio_media->get('field_media_audio_file')->isEmpty()) {
              $audio_file = $audio_media->get('field_media_audio_file')->entity;
              
              if ($audio_file) {
                $audio_url = \Drupal::service('file_url_generator')->generateAbsoluteString($audio_file->getFileUri());
                $episode_title = $episode->getTitle();
                
                return [
                  '#theme' => 'episode_audio_player',
                  '#episode_title' => $episode_title,
                  '#episode_number' => $episode_number,
                  '#audio_url' => $audio_url,
                  '#attached' => [
                    'library' => ['song_sheets_import/episode-player'],
                  ],
                ];
              }
            }
          }
        }
      }
    }
    
    // Return empty if no audio found
    return [];
  }

}