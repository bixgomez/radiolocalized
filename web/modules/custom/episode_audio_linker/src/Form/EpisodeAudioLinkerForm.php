<?php

namespace Drupal\episode_audio_linker\Form;

use Drupal; // Global helper for logger and entityTypeManager.
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form to associate audio media with Episode nodes by episode number.
 */
class EpisodeAudioLinkerForm extends FormBase {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getFormId(): string {
    return 'episode_audio_linker_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Quick counts for context.
    $media_count = (int) Drupal::entityQuery('media')
      ->condition('bundle', 'audio')
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    $episode_count = (int) Drupal::entityQuery('node')
      ->condition('type', 'episode')
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    $form['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('This tool links audio media to Episodes by parsing the episode number from media titles formatted like "Radio Localized 001 - Title.mp3".'),
    ];

    $form['stats'] = [
      '#type' => 'item',
      '#markup' => $this->t('Audio media: @m, Episodes: @e', ['@m' => $media_count, '@e' => $episode_count]),
    ];

    $form['overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overwrite existing audio on Episodes'),
      '#default_value' => TRUE,
      '#description' => $this->t('If checked, replaces any existing Audio File reference on Episodes.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Linking'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $overwrite = (bool) $form_state->getValue('overwrite');

    // Collect all audio media IDs to process.
    $media_ids = Drupal::entityQuery('media')
      ->condition('bundle', 'audio')
      ->accessCheck(FALSE)
      ->execute();

    $media_ids = array_values($media_ids);

    // Chunk into manageable sizes.
    $chunks = array_chunk($media_ids, 25);

    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Associating audio media to Episodes'))
      ->setFinishCallback([static::class, 'batchFinished'])
      ->setInitMessage($this->t('Starting...'))
      ->setProgressMessage($this->t('Processed @current out of @total chunks.'));

    foreach ($chunks as $chunk) {
      $batch_builder->addOperation([static::class, 'processChunk'], [$chunk, $overwrite]);
    }

    batch_set($batch_builder->toArray());
  }

  /**
   * Batch operation to process a chunk of media IDs.
   */
  public static function processChunk(array $media_ids, bool $overwrite, array &$context): void {
    $etm = Drupal::entityTypeManager();
    $media_storage = $etm->getStorage('media');
    $node_storage = $etm->getStorage('node');

    $pattern = '/Radio\s*Localized\s*(\d{1,3})\b/i';

    if (!isset($context['results'])) {
      $context['results'] = [
        'total' => 0,
        'updated' => 0,
        'no_episode' => 0,
        'conflicts' => 0,
        'title_no_match' => 0,
      ];
    }

    $medias = $media_storage->loadMultiple($media_ids);
    foreach ($medias as $media) {
      $context['results']['total']++;
      $title = (string) $media->label();

      if (!preg_match($pattern, $title, $m)) {
        $context['results']['title_no_match']++;
        Drupal::logger('episode_audio_linker')->notice('Skip (no match): media @mid title: @t', ['@mid' => $media->id(), '@t' => $title]);
        continue;
      }

      $raw = $m[1];
      $episode_number = (int) ltrim($raw, '0');
      if ($episode_number === 0 && $raw !== '0') {
        $episode_number = (int) $raw;
      }

      $episode_ids = Drupal::entityQuery('node')
        ->condition('type', 'episode')
        ->condition('field_episode_number', $episode_number)
        ->accessCheck(FALSE)
        ->execute();

      $count = count($episode_ids);
      if ($count === 0) {
        $context['results']['no_episode']++;
        Drupal::logger('episode_audio_linker')->warning('No episode for media @mid (title: @t, ep: @n)', [
          '@mid' => $media->id(), '@t' => $title, '@n' => $episode_number,
        ]);
        continue;
      }
      if ($count > 1) {
        $context['results']['conflicts']++;
        Drupal::logger('episode_audio_linker')->warning('Multiple episodes for media @mid (title: @t, ep: @n, matches: @c)', [
          '@mid' => $media->id(), '@t' => $title, '@n' => $episode_number, '@c' => $count,
        ]);
        continue;
      }

      $episode_id = reset($episode_ids);
      /** @var \Drupal\node\Entity\Node $episode */
      $episode = $node_storage->load($episode_id);
      if (!$episode) {
        $context['results']['no_episode']++;
        Drupal::logger('episode_audio_linker')->error('Failed loading episode @eid for media @mid', ['@eid' => $episode_id, '@mid' => $media->id()]);
        continue;
      }

      // Respect overwrite flag.
      if (!$overwrite && !$episode->get('field_audio_file')->isEmpty()) {
        continue;
      }

      $episode->set('field_audio_file', [['target_id' => $media->id()]]);

      try {
        $episode->save();
        $context['results']['updated']++;
      }
      catch (\Throwable $e) {
        Drupal::logger('episode_audio_linker')->error('Save failed for media @mid to episode @eid: @msg', [
          '@mid' => $media->id(), '@eid' => $episode_id, '@msg' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Batch finish callback.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    if (!$success) {
      Drupal::messenger()->addError(t('The batch did not complete. Check logs for details.'));
      return;
    }

    $summary = [
      'Media scanned' => $results['total'] ?? 0,
      'Linked updated' => $results['updated'] ?? 0,
      'No episode' => $results['no_episode'] ?? 0,
      'Conflicts' => $results['conflicts'] ?? 0,
      'Title no-match' => $results['title_no_match'] ?? 0,
    ];

    $lines = [];
    foreach ($summary as $k => $v) {
      $lines[] = $k . ': ' . $v;
    }
    Drupal::messenger()->addStatus(implode("\n", $lines));
  }

}
