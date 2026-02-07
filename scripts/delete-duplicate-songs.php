<?php

/**
 * @file
 * Drush script to delete duplicate song nodes, keeping the most recent.
 *
 * Usage:
 *   ddev drush php:script scripts/delete-duplicate-songs.php
 *   ddev drush php:script scripts/delete-duplicate-songs.php -- --execute
 *
 * By default, runs in dry-run mode (no deletions).
 * Pass --execute to actually delete duplicates.
 */

// Check for --execute flag.
$execute = in_array('--execute', $extra ?? []) || in_array('--execute', $_SERVER['argv'] ?? []);

$dry_run = !$execute;

if ($dry_run) {
  echo "=== DRY RUN MODE ===\n";
  echo "No songs will be deleted. Pass --execute to actually delete.\n\n";
}
else {
  echo "=== EXECUTE MODE ===\n";
  echo "Duplicates WILL be deleted!\n\n";
}

// Query all song nodes.
$query = \Drupal::entityQuery('node')
  ->condition('type', 'song')
  ->accessCheck(FALSE)
  ->sort('nid', 'ASC');

$nids = $query->execute();

if (empty($nids)) {
  echo "No song nodes found.\n";
  return;
}

echo "Found " . count($nids) . " total song nodes.\n\n";

// Load all songs and group by exact title.
$storage = \Drupal::entityTypeManager()->getStorage('node');
$songs_by_title = [];

foreach ($nids as $nid) {
  $song = $storage->load($nid);
  if (!$song) {
    continue;
  }

  $title = $song->getTitle();

  if (!isset($songs_by_title[$title])) {
    $songs_by_title[$title] = [];
  }

  $songs_by_title[$title][] = [
    'nid' => $nid,
    'title' => $title,
    'created' => $song->getCreatedTime(),
    'episode_number' => $song->hasField('field_episode_number') ? $song->get('field_episode_number')->value : NULL,
  ];
}

// Find duplicates (titles with more than one song).
$duplicates = array_filter($songs_by_title, function($songs) {
  return count($songs) > 1;
});

if (empty($duplicates)) {
  echo "No duplicate songs found!\n";
  return;
}

echo "Found " . count($duplicates) . " titles with duplicates.\n\n";

$total_to_delete = 0;
$deleted_count = 0;
$kept_count = 0;

foreach ($duplicates as $title => $songs) {
  // Sort by nid descending - highest (most recent) first.
  usort($songs, function($a, $b) {
    return $b['nid'] - $a['nid'];
  });

  $keep = array_shift($songs); // Keep the most recent (highest nid).
  $to_delete = $songs; // Delete the rest.

  echo "Title: \"" . $title . "\"\n";
  echo "  KEEP: nid=" . $keep['nid'] . " (Episode: " . ($keep['episode_number'] ?: 'N/A') . ")\n";

  foreach ($to_delete as $song_info) {
    echo "  DELETE: nid=" . $song_info['nid'] . " (Episode: " . ($song_info['episode_number'] ?: 'N/A') . ")\n";
    $total_to_delete++;

    if (!$dry_run) {
      $song = $storage->load($song_info['nid']);
      if ($song) {
        $song->delete();
        $deleted_count++;
      }
    }
  }

  $kept_count++;
  echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Unique titles with duplicates: " . count($duplicates) . "\n";
echo "Songs to keep: " . $kept_count . "\n";

if ($dry_run) {
  echo "Songs that WOULD be deleted: " . $total_to_delete . "\n";
  echo "\nRun with --execute to actually delete these songs:\n";
  echo "  ddev drush php:script scripts/delete-duplicate-songs.php -- --execute\n";
}
else {
  echo "Songs deleted: " . $deleted_count . "\n";
  echo "\nDone! You may want to run 'drush cr' to clear caches.\n";
}
