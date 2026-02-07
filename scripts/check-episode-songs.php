<?php

/**
 * @file
 * Quick script to check if episodes have songs linked via field_song.
 *
 * Usage:
 *   ddev drush php:script scripts/check-episode-songs.php
 */

// Check a few episodes in the current database.
$query = \Drupal::entityQuery('node')
  ->condition('type', 'episode')
  ->accessCheck(FALSE)
  ->range(0, 5);

$nids = $query->execute();

$storage = \Drupal::entityTypeManager()->getStorage('node');

echo "Checking episodes in CURRENT database:\n\n";

foreach ($nids as $nid) {
  $episode = $storage->load($nid);
  if (!$episode) continue;

  $title = $episode->getTitle();
  $ep_num = $episode->hasField('field_episode_number') ? $episode->get('field_episode_number')->value : 'N/A';

  $song_count = 0;
  if ($episode->hasField('field_song')) {
    $songs = $episode->get('field_song')->referencedEntities();
    $song_count = count($songs);
  }

  echo "Episode $ep_num: \"$title\" (nid $nid) - $song_count songs in field_song\n";
}

echo "\n";

// Now check the old database.
$database = \Drupal\Core\Database\Database::getConnection();
$db_options = $database->getConnectionOptions();

try {
  $old_pdo = new PDO(
    "mysql:host={$db_options['host']};port={$db_options['port']};dbname=radiolocalized_old",
    $db_options['username'],
    $db_options['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  echo "Checking episodes in OLD database:\n\n";

  // Get a few episodes and their song counts.
  $query = "
    SELECT
      nfd.nid,
      nfd.title,
      fen.field_episode_number_value AS ep_num,
      COUNT(fs.field_song_target_id) AS song_count
    FROM node_field_data nfd
    LEFT JOIN node__field_episode_number fen ON fen.entity_id = nfd.nid
    LEFT JOIN node__field_song fs ON fs.entity_id = nfd.nid
    WHERE nfd.type = 'episode'
    GROUP BY nfd.nid, nfd.title, fen.field_episode_number_value
    ORDER BY fen.field_episode_number_value
    LIMIT 10
  ";

  $stmt = $old_pdo->query($query);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    echo "Episode {$row['ep_num']}: \"{$row['title']}\" (nid {$row['nid']}) - {$row['song_count']} songs in field_song\n";
  }

} catch (PDOException $e) {
  echo "Could not connect to old database: " . $e->getMessage() . "\n";
}
