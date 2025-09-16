<?php

namespace Drupal\song_sheets_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for updating song timestamps via AJAX.
 */
class TimestampController extends ControllerBase {

  /**
   * Update song timestamp.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function updateTimestamp(Request $request) {
    $song_id = $request->request->get('song_id');
    $field_type = $request->request->get('field_type');
    $timestamp = $request->request->get('timestamp');
    // Optional: if setting the last song's start, also set its end time.
    $current_end_timestamp = $request->request->get('current_end_timestamp');
    $previous_song_id = $request->request->get('previous_song_id');
    $previous_timestamp = $request->request->get('previous_timestamp');

    // Validate inputs
    if (empty($song_id) || empty($field_type) || empty($timestamp)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Missing required parameters',
      ], 400);
    }

    // Validate field type
    if (!in_array($field_type, ['start', 'end', 'duration'])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid field type',
      ], 400);
    }

    // Validate timestamp format (MM:SS) - allow up to 3-digit minutes.
    if (!preg_match('/^\d{1,3}:\d{2}$/', $timestamp)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid timestamp format',
      ], 400);
    }

    if (!empty($current_end_timestamp) && !preg_match('/^\d{1,3}:\d{2}$/', $current_end_timestamp)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid end timestamp format',
      ], 400);
    }

    try {
      // Load the song node
      $song = $this->entityTypeManager()->getStorage('node')->load($song_id);
      
      if (!$song || $song->bundle() !== 'song') {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Song not found',
        ], 404);
      }

      // Check access
      if (!$song->access('update')) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Access denied',
        ], 403);
      }

      // Update fields. If setting start time and an end value is provided for
      // the current (last) song, set both before saving.
      if ($field_type === 'start') {
        $song->set('field_start_time', $timestamp);
        if (!empty($current_end_timestamp)) {
          $song->set('field_end_time', $current_end_timestamp);
        }
      }
      elseif ($field_type === 'end') {
        $song->set('field_end_time', $timestamp);
      }
      else { // duration
        $song->set('field_duration', $timestamp);
      }

      // If this song now has both start and end, compute and set duration.
      $duration_value = NULL;
      $start_value = $song->get('field_start_time')->value;
      $end_value = $song->get('field_end_time')->value;
      if (!empty($start_value) && !empty($end_value)) {
        $start_secs = $this->mmSsToSeconds($start_value);
        $end_secs = $this->mmSsToSeconds($end_value);
        if ($end_secs >= $start_secs) {
          $duration_value = $this->secondsToMmSs($end_secs - $start_secs);
          $song->set('field_duration', $duration_value);
        }
      }

      $song->save();

      // If setting a start time and we have a previous song, update its end time
      $previous_updated = FALSE;
      $previous_duration = NULL;
      if ($field_type === 'start' && !empty($previous_song_id) && !empty($previous_timestamp)) {
        $previous_song = $this->entityTypeManager()->getStorage('node')->load($previous_song_id);
        if ($previous_song && $previous_song->bundle() === 'song' && $previous_song->access('update')) {
          $previous_song->set('field_end_time', $previous_timestamp);
          // Compute and set previous duration if possible.
          $prev_start = $previous_song->get('field_start_time')->value;
          if (!empty($prev_start)) {
            $prev_start_secs = $this->mmSsToSeconds($prev_start);
            $prev_end_secs = $this->mmSsToSeconds($previous_timestamp);
            if ($prev_end_secs >= $prev_start_secs) {
              $previous_duration = $this->secondsToMmSs($prev_end_secs - $prev_start_secs);
              $previous_song->set('field_duration', $previous_duration);
            }
          }
          $previous_song->save();
          $previous_updated = TRUE;
          
          // Log the previous song update
          $this->getLogger('song_sheets_import')->info(
            'Updated end time for previous song "@prev_title" (ID: @prev_id) to @timestamp',
            [
              '@prev_title' => $previous_song->getTitle(),
              '@prev_id' => $previous_song_id,
              '@timestamp' => $previous_timestamp,
            ]
          );
        }
      }

      // Log the main update
      $this->getLogger('song_sheets_import')->info(
        'Updated @field_type timestamp for song "@title" (ID: @id) to @timestamp',
        [
          '@field_type' => $field_type,
          '@title' => $song->getTitle(),
          '@id' => $song_id,
          '@timestamp' => $timestamp,
        ]
      );

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Timestamp updated successfully',
        'previous_updated' => $previous_updated,
        'data' => [
          'song_id' => $song_id,
          'field_type' => $field_type,
          'timestamp' => $timestamp,
          'current_end_timestamp' => $current_end_timestamp,
          'duration' => $duration_value,
          'start' => $song->get('field_start_time')->value,
          'end' => $song->get('field_end_time')->value,
          'previous_duration' => $previous_duration,
        ],
      ]);

    } catch (\Exception $e) {
      $this->getLogger('song_sheets_import')->error(
        'Error updating timestamp: @message',
        ['@message' => $e->getMessage()]
      );

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'An error occurred while updating the timestamp',
      ], 500);
    }
  }

  /**
   * Access callback for timestamp updates.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Allow users who can administer site configuration or edit songs
    return AccessResult::allowedIfHasPermissions($account, [
      'administer site configuration',
      'edit any song content',
      'edit own song content',
    ], 'OR');
  }

  /**
   * Convert an MM:SS string to seconds.
   */
  private function mmSsToSeconds(string $mmss): int {
    $parts = explode(':', $mmss);
    if (count($parts) !== 2) {
      return 0;
    }
    $m = (int) $parts[0];
    $s = (int) $parts[1];
    return max(0, $m * 60 + $s);
  }

  /**
   * Convert seconds to MM:SS string.
   */
  private function secondsToMmSs(int $seconds): string {
    $seconds = max(0, $seconds);
    $m = (int) floor($seconds / 60);
    $s = $seconds % 60;
    return str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $s, 2, '0', STR_PAD_LEFT);
  }
}
