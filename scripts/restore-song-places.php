<?php

/**
 * @file
 * Drush script to restore song-place relationships from old database.
 *
 * Prerequisites:
 *   1. Create the old database:
 *      ddev mysql -e "CREATE DATABASE IF NOT EXISTS radiolocalized_old;"
 *
 *   2. Import the old dump:
 *      gunzip -c ../radiolocalized_old/2026.01.31--demo-version-backup.sql.gz | ddev mysql radiolocalized_old
 *
 * Usage:
 *   ddev drush php:script scripts/restore-song-places.php
 *   ddev drush php:script scripts/restore-song-places.php -- --execute
 *
 * By default, runs in dry-run mode (no updates).
 * Pass --execute to actually update songs.
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

// Get database connection info from Drupal settings.
$database = \Drupal\Core\Database\Database::getConnection();
$db_options = $database->getConnectionOptions();

// Connect to the old database using PDO directly.
$old_db_name = 'radiolocalized_old';

try {
  $old_pdo = new PDO(
    "mysql:host={$db_options['host']};port={$db_options['port']};dbname={$old_db_name}",
    $db_options['username'],
    $db_options['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
  echo "Connected to old database: {$old_db_name}\n\n";
}
catch (PDOException $e) {
  echo "ERROR: Could not connect to old database '{$old_db_name}'.\n";
  echo "Make sure you've created and imported it:\n";
  echo "  ddev mysql -e \"CREATE DATABASE IF NOT EXISTS radiolocalized_old;\"\n";
  echo "  gunzip -c ../radiolocalized_old/2026.01.31--demo-version-backup.sql.gz | ddev mysql radiolocalized_old\n\n";
  echo "Error: " . $e->getMessage() . "\n";
  return;
}

// Query old database for song title -> place nid mappings.
// Drupal stores entity reference fields in tables like node__field_place.
$query = "
  SELECT
    nfd.title AS song_title,
    fp.field_place_target_id AS place_nid
  FROM node_field_data nfd
  INNER JOIN node__field_place fp ON fp.entity_id = nfd.nid
  WHERE nfd.type = 'song'
    AND fp.field_place_target_id IS NOT NULL
  ORDER BY nfd.title
";

$stmt = $old_pdo->query($query);
$old_mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($old_mappings)) {
  echo "No song-place mappings found in old database.\n";
  echo "Checking if the field table exists...\n";

  // Debug: check what tables exist.
  $tables = $old_pdo->query("SHOW TABLES LIKE '%place%'")->fetchAll(PDO::FETCH_COLUMN);
  echo "Tables matching 'place': " . implode(', ', $tables) . "\n";
  return;
}

echo "Found " . count($old_mappings) . " song-place mappings in old database.\n\n";

// Build a lookup by song title.
// Note: If multiple songs have same title with different places, we'll use the first one.
$place_by_title = [];
foreach ($old_mappings as $row) {
  $title = $row['song_title'];
  if (!isset($place_by_title[$title])) {
    $place_by_title[$title] = $row['place_nid'];
  }
}

echo "Unique song titles with places: " . count($place_by_title) . "\n\n";

// Now query the current database for songs and update them.
$storage = \Drupal::entityTypeManager()->getStorage('node');

$query = \Drupal::entityQuery('node')
  ->condition('type', 'song')
  ->accessCheck(FALSE);

$nids = $query->execute();

echo "Found " . count($nids) . " songs in current database.\n\n";

$updated_count = 0;
$already_set_count = 0;
$no_match_count = 0;
$place_missing_count = 0;

foreach ($nids as $nid) {
  $song = $storage->load($nid);
  if (!$song) {
    continue;
  }

  $title = $song->getTitle();

  // Check if this song has a matching place in the old data.
  if (!isset($place_by_title[$title])) {
    $no_match_count++;
    continue;
  }

  $old_place_nid = $place_by_title[$title];

  // Check if the song already has a place set.
  if ($song->hasField('field_place') && !$song->get('field_place')->isEmpty()) {
    $current_place = $song->get('field_place')->target_id;
    if ($current_place == $old_place_nid) {
      $already_set_count++;
      continue;
    }
    // Different place set - we'll update it anyway.
  }

  // Verify the place node exists in current database.
  $place_node = $storage->load($old_place_nid);
  if (!$place_node || $place_node->bundle() !== 'place') {
    echo "WARNING: Place nid {$old_place_nid} not found for song \"{$title}\" (nid {$nid})\n";
    $place_missing_count++;
    continue;
  }

  if ($dry_run) {
    echo "WOULD UPDATE: \"{$title}\" (nid {$nid}) -> Place: \"{$place_node->getTitle()}\" (nid {$old_place_nid})\n";
  }
  else {
    $song->set('field_place', $old_place_nid);
    $song->save();
    echo "UPDATED: \"{$title}\" (nid {$nid}) -> Place: \"{$place_node->getTitle()}\" (nid {$old_place_nid})\n";
  }

  $updated_count++;
}

echo "\n=== SUMMARY ===\n";
echo "Songs in current DB: " . count($nids) . "\n";
echo "Songs with matching place data: " . ($updated_count + $already_set_count) . "\n";
echo "Already correctly set: " . $already_set_count . "\n";

if ($dry_run) {
  echo "Would update: " . $updated_count . "\n";
}
else {
  echo "Updated: " . $updated_count . "\n";
}

echo "No match in old data: " . $no_match_count . "\n";
echo "Place node missing: " . $place_missing_count . "\n";

if ($dry_run && $updated_count > 0) {
  echo "\nRun with --execute to actually update these songs:\n";
  echo "  ddev drush php:script scripts/restore-song-places.php -- --execute\n";
}
elseif (!$dry_run) {
  echo "\nDone! You may want to run 'drush cr' to clear caches.\n";
}
