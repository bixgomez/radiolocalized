<?php

/**
 * @file
 * Drush script to restore episode->song relationships from old database.
 *
 * Usage:
 *   ddev drush php:script scripts/restore-episode-songs.php
 *   ddev drush php:script scripts/restore-episode-songs.php -- --execute
 */

// Check for --execute flag.
$execute = in_array('--execute', $extra ?? []) || in_array('--execute', $_SERVER['argv'] ?? []);
$dry_run = !$execute;

if ($dry_run) {
  echo "=== DRY RUN MODE ===\n";
  echo "No episodes will be updated. Pass --execute to actually update.\n\n";
}
else {
  echo "=== EXECUTE MODE ===\n";
  echo "Episodes WILL be updated!\n\n";
}

// Get database connection info from Drupal settings.
$database = \Drupal\Core\Database\Database::getConnection();
$db_options = $database->getConnectionOptions();

// Connect to the old database.
try {
  $old_pdo = new PDO(
    "mysql:host={$db_options['host']};port={$db_options['port']};dbname=radiolocalized_old",
    $db_options['username'],
    $db_options['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
  echo "Connected to old database.\n\n";
}
catch (PDOException $e) {
  echo "ERROR: Could not connect to old database.\n";
  echo "Error: " . $e->getMessage() . "\n";
  return;
}

// Query old database for episode -> song relationships.
// We need: episode_number -> list of song titles (in order).
$query = "
  SELECT
    fen.field_episode_number_value AS episode_number,
    nfd_song.title AS song_title,
    fs.delta AS song_delta
  FROM node__field_song fs
  INNER JOIN node__field_episode_number fen ON fen.entity_id = fs.entity_id
  INNER JOIN node_field_data nfd_song ON nfd_song.nid = fs.field_song_target_id
  ORDER BY fen.field_episode_number_value, fs.delta
";

$stmt = $old_pdo->query($query);
$old_mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($old_mappings)) {
  echo "No episode-song mappings found in old database.\n";
  return;
}

echo "Found " . count($old_mappings) . " episode-song relationships in old database.\n\n";

// Group by episode number.
$songs_by_episode = [];
foreach ($old_mappings as $row) {
  $ep_num = $row['episode_number'];
  if (!isset($songs_by_episode[$ep_num])) {
    $songs_by_episode[$ep_num] = [];
  }
  $songs_by_episode[$ep_num][] = $row['song_title'];
}

echo "Episodes with songs: " . count($songs_by_episode) . "\n\n";

// Build a lookup of song title -> nid in current database.
$song_query = \Drupal::entityQuery('node')
  ->condition('type', 'song')
  ->accessCheck(FALSE);

$song_nids = $song_query->execute();
$storage = \Drupal::entityTypeManager()->getStorage('node');

$song_nid_by_title = [];
foreach ($song_nids as $nid) {
  $song = $storage->load($nid);
  if ($song) {
    $title = $song->getTitle();
    // If multiple songs have same title, use the first one found.
    if (!isset($song_nid_by_title[$title])) {
      $song_nid_by_title[$title] = $nid;
    }
  }
}

echo "Songs in current database: " . count($song_nid_by_title) . " unique titles\n\n";

// Now update episodes in current database.
$updated_count = 0;
$skipped_count = 0;
$missing_songs = [];

foreach ($songs_by_episode as $ep_num => $song_titles) {
  // Find episode by episode number in current database.
  $ep_query = \Drupal::entityQuery('node')
    ->condition('type', 'episode')
    ->condition('field_episode_number', $ep_num)
    ->accessCheck(FALSE)
    ->range(0, 1);

  $ep_nids = $ep_query->execute();

  if (empty($ep_nids)) {
    echo "SKIP: Episode $ep_num not found in current database.\n";
    $skipped_count++;
    continue;
  }

  $ep_nid = reset($ep_nids);
  $episode = $storage->load($ep_nid);

  if (!$episode) {
    echo "SKIP: Could not load episode $ep_num (nid $ep_nid).\n";
    $skipped_count++;
    continue;
  }

  // Build array of song nids.
  $song_refs = [];
  $found_count = 0;
  $missing_count = 0;

  foreach ($song_titles as $song_title) {
    if (isset($song_nid_by_title[$song_title])) {
      $song_refs[] = ['target_id' => $song_nid_by_title[$song_title]];
      $found_count++;
    }
    else {
      $missing_count++;
      if (!isset($missing_songs[$song_title])) {
        $missing_songs[$song_title] = [];
      }
      $missing_songs[$song_title][] = $ep_num;
    }
  }

  if (empty($song_refs)) {
    echo "SKIP: Episode $ep_num - no matching songs found in current database.\n";
    $skipped_count++;
    continue;
  }

  $ep_title = $episode->getTitle();

  if ($dry_run) {
    echo "WOULD UPDATE: Episode $ep_num \"$ep_title\" (nid $ep_nid) - $found_count songs";
    if ($missing_count > 0) {
      echo " ($missing_count missing)";
    }
    echo "\n";
  }
  else {
    $episode->set('field_song', $song_refs);
    $episode->save();
    echo "UPDATED: Episode $ep_num \"$ep_title\" (nid $ep_nid) - $found_count songs";
    if ($missing_count > 0) {
      echo " ($missing_count missing)";
    }
    echo "\n";
  }

  $updated_count++;
}

echo "\n=== SUMMARY ===\n";
echo "Episodes in old data: " . count($songs_by_episode) . "\n";

if ($dry_run) {
  echo "Would update: $updated_count\n";
}
else {
  echo "Updated: $updated_count\n";
}

echo "Skipped: $skipped_count\n";

if (!empty($missing_songs)) {
  echo "\nSongs not found in current database (" . count($missing_songs) . "):\n";
  foreach (array_slice($missing_songs, 0, 10) as $title => $eps) {
    echo "  - \"$title\" (episodes: " . implode(', ', $eps) . ")\n";
  }
  if (count($missing_songs) > 10) {
    echo "  ... and " . (count($missing_songs) - 10) . " more\n";
  }
}

if ($dry_run && $updated_count > 0) {
  echo "\nRun with --execute to actually update:\n";
  echo "  ddev drush php:script scripts/restore-episode-songs.php -- --execute\n";
}
elseif (!$dry_run) {
  echo "\nDone! Run 'drush cr' to clear caches.\n";
}
