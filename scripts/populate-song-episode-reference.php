<?php

/**
 * @file
 * Drush script to populate field_episode (entity reference) on songs
 * based on their field_episode_number.
 *
 * Usage:
 *   ddev drush php:script scripts/populate-song-episode-reference.php
 *   ddev drush php:script scripts/populate-song-episode-reference.php -- --execute
 */

// Check for --execute flag.
$execute = in_array('--execute', $extra ?? []) || in_array('--execute', $_SERVER['argv'] ?? []);
$dry_run = !$execute;

if ($dry_run) {
  echo "=== DRY RUN MODE ===\n";
  echo "No songs will be updated. Pass --execute to actually update.\n\n";
}
else {
  echo "=== EXECUTE MODE ===\n";
  echo "Songs WILL be updated!\n\n";
}

$storage = \Drupal::entityTypeManager()->getStorage('node');

// Step 1: Build a lookup of episode_number -> episode nid.
echo "Building episode number -> nid lookup...\n";

$episode_query = \Drupal::entityQuery('node')
  ->condition('type', 'episode')
  ->accessCheck(FALSE);

$episode_nids = $episode_query->execute();
$episode_nid_by_number = [];

foreach ($episode_nids as $nid) {
  $episode = $storage->load($nid);
  if ($episode && $episode->hasField('field_episode_number') && !$episode->get('field_episode_number')->isEmpty()) {
    $ep_num = $episode->get('field_episode_number')->value;
    $episode_nid_by_number[$ep_num] = $nid;
  }
}

echo "Found " . count($episode_nid_by_number) . " episodes with episode numbers.\n\n";

// Step 2: Find all songs with field_episode_number set.
echo "Finding songs with episode numbers...\n";

$song_query = \Drupal::entityQuery('node')
  ->condition('type', 'song')
  ->exists('field_episode_number')
  ->accessCheck(FALSE);

$song_nids = $song_query->execute();

echo "Found " . count($song_nids) . " songs with episode numbers.\n\n";

// Step 3: Process songs.
$updated_count = 0;
$skipped_already_set = 0;
$skipped_no_episode = 0;
$skipped_no_number = 0;

foreach ($song_nids as $song_nid) {
  $song = $storage->load($song_nid);

  if (!$song) {
    continue;
  }

  $song_title = $song->getTitle();

  // Get episode number from song.
  if (!$song->hasField('field_episode_number') || $song->get('field_episode_number')->isEmpty()) {
    $skipped_no_number++;
    continue;
  }

  $ep_num = $song->get('field_episode_number')->value;

  // Check if field_episode is already set.
  if ($song->hasField('field_episode') && !$song->get('field_episode')->isEmpty()) {
    $existing_ref = $song->get('field_episode')->target_id;
    $skipped_already_set++;
    // Uncomment to see skipped songs:
    // echo "SKIP (already set): Song $song_nid \"$song_title\" -> episode $existing_ref\n";
    continue;
  }

  // Find episode nid by episode number.
  if (!isset($episode_nid_by_number[$ep_num])) {
    echo "SKIP (no episode): Song $song_nid \"$song_title\" - episode number $ep_num not found\n";
    $skipped_no_episode++;
    continue;
  }

  $episode_nid = $episode_nid_by_number[$ep_num];

  if ($dry_run) {
    echo "WOULD UPDATE: Song $song_nid \"$song_title\" -> episode $episode_nid (ep #$ep_num)\n";
  }
  else {
    $song->set('field_episode', ['target_id' => $episode_nid]);
    $song->save();
    echo "UPDATED: Song $song_nid \"$song_title\" -> episode $episode_nid (ep #$ep_num)\n";
  }

  $updated_count++;
}

echo "\n=== SUMMARY ===\n";
echo "Songs processed: " . count($song_nids) . "\n";

if ($dry_run) {
  echo "Would update: $updated_count\n";
}
else {
  echo "Updated: $updated_count\n";
}

echo "Skipped (already has field_episode): $skipped_already_set\n";
echo "Skipped (episode not found): $skipped_no_episode\n";
echo "Skipped (no episode number): $skipped_no_number\n";

if ($dry_run && $updated_count > 0) {
  echo "\nRun with --execute to actually update:\n";
  echo "  ddev drush php:script scripts/populate-song-episode-reference.php -- --execute\n";
}
elseif (!$dry_run) {
  echo "\nDone! Run 'drush cr' to clear caches.\n";
}
