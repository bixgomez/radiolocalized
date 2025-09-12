<?php

namespace Drupal\song_sheets_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Google_Client;
use Google_Service_Sheets;
use Exception;

/**
 * Admin form for Song Sheets Import.
 */
class SongSheetsImportForm extends FormBase {

  /**
   * Google Sheets credentials path.
   */
  private const CREDENTIALS_PATH = '/test-sheets/radio-localized-episodes-8243df309e4a.json';

  /**
   * Google Sheets spreadsheet ID.
   */
  private const SPREADSHEET_ID = '1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'song_sheets_import_form';
  }

  /**
   * Create and configure Google Sheets service.
   *
   * @return \Google_Service_Sheets|null
   *   The Google Sheets service or NULL on failure.
   */
  private function createGoogleSheetsService() {
    try {
      $credentialsPath = \Drupal::root() . self::CREDENTIALS_PATH;
      
      if (!file_exists($credentialsPath)) {
        \Drupal::logger('song_sheets_import')->error('Google Sheets credentials file not found: @path', ['@path' => $credentialsPath]);
        return NULL;
      }

      $client = new Google_Client();
      $client->setApplicationName('Drupal Songs Import');
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $client->setAuthConfig($credentialsPath);
      
      return new Google_Service_Sheets($client);
    } catch (Exception $e) {
      \Drupal::logger('song_sheets_import')->error('Failed to create Google Sheets service: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $sheets = $this->getAvailableSheets();
    
    if (empty($sheets)) {
      $form['error'] = [
        '#markup' => '<p><strong>Error:</strong> Cannot connect to Google Sheets. Please check configuration.</p>',
      ];
      return $form;
    }

    // Get selected episode from URL parameter
    $request = \Drupal::request();
    $selectedSheet = $request->query->get('episode', $this->getDefaultSheet($sheets));
    
    $form['episode_grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['episode-grid']],
    ];
    
    $form['episode_grid']['title'] = [
      '#markup' => '<h3>' . $this->t('Select Episode') . '</h3>',
    ];
    
    // Build episode links
    $episodeLinks = '<ul class="episode-list">';
    foreach ($sheets as $sheetId => $sheetLabel) {
      $isSelected = ($sheetId == $selectedSheet);
      $classes = ['episode-item'];
      if ($isSelected) {
        $classes[] = 'selected';
      }
      
      $url = \Drupal\Core\Url::fromRoute('song_sheets_import.admin', [], ['query' => ['episode' => $sheetId]]);
      $episodeLinks .= '<li><a href="' . $url->toString() . '" class="' . implode(' ', $classes) . '">' . htmlspecialchars($sheetId) . '</a></li>';
    }
    $episodeLinks .= '</ul>';
    
    $form['episode_grid']['episodes'] = [
      '#markup' => $episodeLinks,
    ];
    
    // Add bulk import button at bottom of episode selection
    $form['episode_grid']['bulk_import_button'] = [
      '#type' => 'submit',
      '#value' => $this->t("💥 LET 'EM ALL RIP! Import ALL episodes"),
      '#submit' => ['::importAllEpisodes'],
      '#attributes' => [
        'class' => ['button--danger', 'bulk-import-button'],
        'onclick' => 'return confirm("Are you sure you want to import ALL episodes? This will process every episode and could take a while!");',
        'style' => 'margin-top: 20px; background-color: #d63031; display: block;',
      ],
    ];

    $form['column_headers'] = [
      '#type' => 'container',
      '#prefix' => '<div id="column-headers-wrapper">',
      '#suffix' => '</div>',
    ];

    // Load column headers for selected sheet
    if ($selectedSheet) {
      $form['column_headers']['headers'] = [
        '#markup' => $this->getColumnHeadersMarkup($selectedSheet),
      ];
      
      // Add the LET 'ER RIP import button
      $data = $this->getSheetData($selectedSheet);
      $songCount = count($data['rows']);
      
      $form['import_button'] = [
        '#type' => 'submit',
        '#value' => $this->t("🚀 LET 'ER RIP! Import @count songs from Episode @episode", [
          '@count' => $songCount,
          '@episode' => $selectedSheet,
        ]),
        '#submit' => ['::importEpisode'],
        '#attributes' => [
          'class' => ['button--danger', 'import-button'],
          'onclick' => 'return confirm("Are you sure you want to import ' . $songCount . ' songs? This cannot be undone!");',
        ],
      ];
    }
    
    // Add CSS and JavaScript
    $form['#attached']['library'][] = 'song_sheets_import/episode-grid';

    return $form;
  }

  /**
   * Get available sheets from Google Sheets.
   */
  private function getAvailableSheets() {
    $service = $this->createGoogleSheetsService();
    if (!$service) {
      return [];
    }

    try {
      $spreadsheet = $service->spreadsheets->get(self::SPREADSHEET_ID);
      
      $sheets = [];
      foreach ($spreadsheet->getSheets() as $sheet) {
        $title = $sheet->getProperties()->getTitle();
        if (is_numeric($title)) {
          $sheets[$title] = 'Episode ' . $title;
        }
      }
      
      return $sheets;
    } catch (Exception $e) {
      \Drupal::logger('song_sheets_import')->error('Failed to get available sheets: @error', ['@error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Get the default sheet (highest numerical episode).
   */
  private function getDefaultSheet($sheets) {
    if (empty($sheets)) {
      return NULL;
    }
    
    $numericKeys = array_keys($sheets);
    rsort($numericKeys, SORT_NUMERIC);
    return $numericKeys[0];
  }


  /**
   * Get field labels for song content type.
   */
  private function getSongFieldLabels() {
    $fieldManager = \Drupal::service('entity_field.manager');
    $fieldDefinitions = $fieldManager->getFieldDefinitions('node', 'song');
    
    $fieldLabels = [];
    foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
      // Skip base fields we don't want to map
      if (in_array($fieldName, ['nid', 'uuid', 'vid', 'langcode', 'type', 'revision_timestamp', 'revision_uid', 'revision_log', 'status', 'uid', 'created', 'changed', 'promote', 'sticky', 'default_langcode', 'revision_default', 'revision_translation_affected', 'path'])) {
        continue;
      }
      
      $fieldLabels[$fieldName] = $fieldDefinition->getLabel();
    }
    
    return $fieldLabels;
  }

  /**
   * Map Google Sheets columns to Drupal field labels.
   */
  private function mapColumnsToFields($headers) {
    $fieldLabels = $this->getSongFieldLabels();
    $mapping = [];
    
    foreach ($headers as $columnIndex => $columnHeader) {
      $columnHeader = trim($columnHeader);
      if (empty($columnHeader)) {
        continue;
      }
      
      // Special mapping cases for legacy column names
      if (strcasecmp($columnHeader, 'Song') === 0) {
        $mapping[$columnIndex] = ['field' => 'title', 'label' => 'Title', 'match' => 'special'];
        continue;
      }
      
      if (strcasecmp($columnHeader, 'Title') === 0) {
        $mapping[$columnIndex] = ['field' => 'title', 'label' => 'Title', 'match' => 'special'];
        continue;
      }
      
      if (strcasecmp($columnHeader, 'Length') === 0) {
        $mapping[$columnIndex] = ['field' => 'field_duration', 'label' => 'Duration', 'match' => 'special'];
        continue;
      }
      
      if (strcasecmp($columnHeader, 'Link') === 0) {
        $mapping[$columnIndex] = ['field' => 'field_links', 'label' => 'Links', 'match' => 'special'];
        continue;
      }
      
      if (strcasecmp($columnHeader, 'Year') === 0) {
        $mapping[$columnIndex] = ['field' => 'field_year_released', 'label' => 'Released', 'match' => 'special'];
        continue;
      }
      
      // Try exact match first
      foreach ($fieldLabels as $fieldName => $fieldLabel) {
        if (strcasecmp($columnHeader, $fieldLabel) === 0) {
          $mapping[$columnIndex] = ['field' => $fieldName, 'label' => $fieldLabel, 'match' => 'exact'];
          continue 2;
        }
      }
      
      // Try fuzzy match
      foreach ($fieldLabels as $fieldName => $fieldLabel) {
        if (stripos($fieldLabel, $columnHeader) !== false || stripos($columnHeader, $fieldLabel) !== false) {
          $mapping[$columnIndex] = ['field' => $fieldName, 'label' => $fieldLabel, 'match' => 'fuzzy'];
          continue 2;
        }
      }
      
      // No match found
      $mapping[$columnIndex] = ['field' => null, 'label' => null, 'match' => 'none'];
    }
    
    return $mapping;
  }

  /**
   * Get column headers markup for display.
   */
  private function getColumnHeadersMarkup($sheetName) {
    $title = $this->getSheetTitle($sheetName);
    $data = $this->getSheetData($sheetName);
    
    if (empty($data['headers']) || empty($data['rows'])) {
      return '<p><em>No data found.</em></p>';
    }
    
    $markup = '<h4>' . htmlspecialchars($title) . '</h4>';
    
    // Show field mapping
    $mapping = $this->mapColumnsToFields($data['headers']);
    $markup .= '<div class="field-mapping"><h5>Field Mapping:</h5><ul>';
    
    foreach ($data['headers'] as $index => $header) {
      if (empty(trim($header))) continue;
      
      $mapInfo = $mapping[$index] ?? ['field' => null, 'label' => null, 'match' => 'none'];
      $matchClass = 'mapping-' . $mapInfo['match'];
      
      if ($mapInfo['match'] === 'exact') {
        $markup .= '<li class="' . $matchClass . '"><strong>' . htmlspecialchars($header) . '</strong> → ' . htmlspecialchars($mapInfo['label']) . ' ✓</li>';
      } elseif ($mapInfo['match'] === 'special') {
        $markup .= '<li class="' . $matchClass . '"><strong>' . htmlspecialchars($header) . '</strong> → ' . htmlspecialchars($mapInfo['label']) . ' ★</li>';
      } elseif ($mapInfo['match'] === 'fuzzy') {
        $markup .= '<li class="' . $matchClass . '"><strong>' . htmlspecialchars($header) . '</strong> → ' . htmlspecialchars($mapInfo['label']) . ' ~</li>';
      } else {
        $markup .= '<li class="' . $matchClass . '"><strong>' . htmlspecialchars($header) . '</strong> → <em>No match found</em> ✗</li>';
      }
    }
    
    $markup .= '</ul></div>';
    
    // Find the song title column index (either "Song" or "Title")
    $songIndex = array_search('Song', $data['headers']);
    if ($songIndex === false) {
      $songIndex = array_search('Title', $data['headers']);
    }
    if ($songIndex === false) {
      return $markup . '<p><em>No "Song" or "Title" column found.</em></p>';
    }
    
    $markup .= '<ul>';
    $rowCount = 0;
    foreach ($data['rows'] as $row) {
      $songTitle = !empty($row[$songIndex]) ? $row[$songIndex] : '[No Title]';
      $markup .= '<li><strong>' . htmlspecialchars($songTitle) . '</strong>';
      
      $rowCount++;
      
      // Add other fields as sub-bullets (skip empty headers)
      $markup .= '<ul>';
      foreach ($data['headers'] as $index => $header) {
        if ($index !== $songIndex && !empty(trim($header)) && !empty($row[$index])) {
          $markup .= '<li>' . htmlspecialchars($header) . ': ' . htmlspecialchars($row[$index]) . '</li>';
        }
      }
      $markup .= '</ul></li>';
    }
    $markup .= '</ul>';
    
    return $markup;
  }

  /**
   * Parse episode title from sheet title.
   */
  private function parseEpisodeTitle($sheetTitle) {
    // Remove "Episode XXX" part and separators (- or :)
    $title = preg_replace('/^Episode\s+\d+\s*[-:]\s*/', '', $sheetTitle);
    return trim($title);
  }

  /**
   * Get title from specific sheet.
   */
  private function getSheetTitle($sheetName) {
    $service = $this->createGoogleSheetsService();
    if (!$service) {
      return 'Episode ' . $sheetName;
    }

    try {
      $range = $sheetName . '!1:1'; // First row has title
      $response = $service->spreadsheets_values->get(self::SPREADSHEET_ID, $range);
      $values = $response->getValues();
      
      return !empty($values[0][0]) ? $values[0][0] : 'Episode ' . $sheetName;
    } catch (Exception $e) {
      \Drupal::logger('song_sheets_import')->warning('Failed to get sheet title for @sheet: @error', ['@sheet' => $sheetName, '@error' => $e->getMessage()]);
      return 'Episode ' . $sheetName;
    }
  }

  /**
   * Get full data (headers + rows) from specific sheet.
   */
  private function getSheetData($sheetName) {
    $service = $this->createGoogleSheetsService();
    if (!$service) {
      return ['headers' => [], 'rows' => []];
    }

    try {
      $range = $sheetName . '!A2:AA100'; // Headers from row 2, data from rows 3+, get columns A-AA
      $response = $service->spreadsheets_values->get(self::SPREADSHEET_ID, $range);
      $values = $response->getValues();
      
      if (empty($values)) {
        return ['headers' => [], 'rows' => []];
      }
      
      $headers = array_shift($values); // First row is headers
      
      // Process rows to append empty header columns to preceding named columns
      $processedRows = [];
      
      foreach ($values as $rowIndex => $row) {
        // Find the last column with a non-empty header and append empty columns
        $lastNamedColumn = -1;
        for ($i = 0; $i < count($row); $i++) {
          $header = isset($headers[$i]) ? trim($headers[$i]) : '';
          if (!empty($header)) {
            $lastNamedColumn = $i;
          } else {
            // Empty header column - append data to the last named column
            if ($lastNamedColumn >= 0 && isset($row[$i]) && !empty(trim($row[$i]))) {
              $existingData = isset($row[$lastNamedColumn]) ? $row[$lastNamedColumn] : '';
              $row[$lastNamedColumn] = trim($existingData . ' | ' . trim($row[$i]));
              
            }
          }
        }
        
        $processedRows[] = $row;
      }
      
      return [
        'headers' => $headers,
        'rows' => $processedRows,
      ];
    } catch (Exception $e) {
      \Drupal::logger('song_sheets_import')->error('Failed to get sheet data for @sheet: @error', ['@sheet' => $sheetName, '@error' => $e->getMessage()]);
      return ['headers' => [], 'rows' => []];
    }
  }

  /**
   * Get headers from specific sheet.
   * 
   * @deprecated This method is unused and may be removed in future versions.
   */
  private function getSheetHeaders($sheetName) {
    $service = $this->createGoogleSheetsService();
    if (!$service) {
      return [];
    }

    try {
      $range = $sheetName . '!2:2'; // Second row has headers
      $response = $service->spreadsheets_values->get(self::SPREADSHEET_ID, $range);
      $values = $response->getValues();
      
      return !empty($values[0]) ? $values[0] : [];
    } catch (Exception $e) {
      \Drupal::logger('song_sheets_import')->warning('Failed to get sheet headers for @sheet: @error', ['@sheet' => $sheetName, '@error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Import all episodes submit handler with batch processing.
   */
  public function importAllEpisodes(array &$form, FormStateInterface $form_state) {
    $availableSheets = $this->getAvailableSheets();
    
    if (empty($availableSheets)) {
      \Drupal::messenger()->addError('No episodes found to import.');
      return;
    }
    
    // Set up batch processing
    $episodes = array_keys($availableSheets);
    $batch = [
      'title' => $this->t('Importing all episodes...'),
      'operations' => [],
      'finished' => '\Drupal\song_sheets_import\Form\SongSheetsImportForm::batchFinished',
      'progress_message' => $this->t('Processing episode @current of @total.'),
    ];
    
    // Process episodes in chunks of 5
    $chunks = array_chunk($episodes, 5);
    foreach ($chunks as $chunk) {
      $batch['operations'][] = [
        '\Drupal\song_sheets_import\Form\SongSheetsImportForm::batchProcess',
        [$chunk]
      ];
    }
    
    batch_set($batch);
  }

  /**
   * Import episode submit handler.
   */
  public function importEpisode(array &$form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $selectedSheet = $request->query->get('episode');
    
    if (!$selectedSheet) {
      \Drupal::messenger()->addError('No episode selected for import.');
      return;
    }
    
    try {
      // Get sheet data
      $sheetTitle = $this->getSheetTitle($selectedSheet);
      $data = $this->getSheetData($selectedSheet);
      
      if (empty($data['rows'])) {
        \Drupal::messenger()->addWarning('No songs found in this episode.');
        return;
      }
      
      // Create or find episode
      $episodeTitle = $this->parseEpisodeTitle($sheetTitle);
      $episode = $this->createOrFindEpisode($selectedSheet, $episodeTitle);
      
      // Import songs
      $imported = 0;
      $updated = 0;
      $skipped = 0;
      $mapping = $this->mapColumnsToFields($data['headers']);
      
      foreach ($data['rows'] as $row) {
        $result = $this->importSong($row, $data['headers'], $mapping, $episode);
        if ($result === 'imported') {
          $imported++;
        } elseif ($result === 'updated') {
          $updated++;
        } else {
          $skipped++;
        }
      }
      
      \Drupal::messenger()->addStatus(
        "Import complete! Created: $imported songs, Updated: $updated songs, Skipped: $skipped songs for Episode $selectedSheet: $episodeTitle"
      );
      
    } catch (\Exception $e) {
      \Drupal::messenger()->addError('Import failed: ' . $e->getMessage());
      \Drupal::logger('song_sheets_import')->error('Import error: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Create or find episode entity.
   */
  private function createOrFindEpisode($episodeNumber, $episodeTitle) {
    // Query for existing episode by episode number
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'episode')
      ->condition('field_episode_number', $episodeNumber)
      ->accessCheck(FALSE);
    
    $nids = $query->execute();
    
    if (!empty($nids)) {
      // Episode exists, return it
      $nid = reset($nids);
      return \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    }
    
    // Create new episode
    $episode = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'episode',
      'title' => $episodeTitle,
      'field_episode_number' => $episodeNumber,
      'uid' => \Drupal::currentUser()->id(),
      'status' => 1, // Published
    ]);
    
    $episode->save();
    
    \Drupal::messenger()->addStatus("Created new Episode $episodeNumber: $episodeTitle");
    
    return $episode;
  }

  /**
   * Import a single song.
   */
  private function importSong($row, $headers, $mapping, $episode) {
    // Extract song title from the row
    $songTitleIndex = null;
    foreach ($mapping as $index => $mapInfo) {
      if ($mapInfo['field'] === 'title') {
        $songTitleIndex = $index;
        break;
      }
    }
    
    if ($songTitleIndex === null || empty($row[$songTitleIndex])) {
      return 'skipped'; // No song title
    }
    
    $songTitle = trim($row[$songTitleIndex]);
    
    // Check if song already exists (title + episode number)
    // Get the episode number from our current episode
    $episodeNumber = $episode->get('field_episode_number')->value;
    
    // Debug logging - what are we looking for?
    \Drupal::logger('song_sheets_import')->info('DUPLICATE CHECK: Looking for song "@title" in Episode #@episode_num', [
      '@title' => $songTitle,
      '@episode_num' => $episodeNumber,
    ]);
    
    // Find songs with same title AND same episode number (using direct field match)
    $songQuery = \Drupal::entityQuery('node')
      ->condition('type', 'song')
      ->condition('title', $songTitle)
      ->condition('field_episode_number', $episodeNumber)
      ->accessCheck(FALSE);
    
    $existing = $songQuery->execute();
    
    \Drupal::logger('song_sheets_import')->info('DUPLICATE CHECK: Found @count songs with title "@title" in Episode #@episode_num', [
      '@count' => count($existing),
      '@title' => $songTitle,
      '@episode_num' => $episodeNumber,
    ]);
    
    if (!empty($existing)) {
      // Song exists - update empty fields and ensure episode reference is current
      $existingNid = reset($existing);
      $existingSong = \Drupal::entityTypeManager()->getStorage('node')->load($existingNid);
      
      // Debug logging
      \Drupal::logger('song_sheets_import')->info('Found existing song "@title" (ID: @id) in Episode #@episode_num', [
        '@title' => $songTitle,
        '@id' => $existingNid,
        '@episode_num' => $episodeNumber,
      ]);
      
      // Always update episode reference to current episode (in case episode was deleted/recreated)
      $currentEpisodeId = $existingSong->get('field_episode')->target_id;
      if ($currentEpisodeId != $episode->id()) {
        $existingSong->set('field_episode', $episode->id());
        $existingSong->save();
        \Drupal::logger('song_sheets_import')->info('Updated episode reference for song "@title" from episode ID @old_id to @new_id', [
          '@title' => $songTitle,
          '@old_id' => $currentEpisodeId ?: 'NULL',
          '@new_id' => $episode->id(),
        ]);
      }
      
      return $this->updateEmptyFields($existingSong, $row, $headers, $mapping);
    }
    
    // Create new song node
    $songData = [
      'type' => 'song',
      'title' => $songTitle,
      'field_episode' => $episode->id(),
      'field_episode_number' => $episodeNumber,
      'uid' => \Drupal::currentUser()->id(),
      'status' => 1,
    ];
    
    // Map other fields
    foreach ($mapping as $index => $mapInfo) {
      if ($mapInfo['field'] && $mapInfo['field'] !== 'title' && isset($row[$index]) && !empty(trim($row[$index]))) {
        $value = trim($row[$index]);
        
        // Handle different field types
        if (in_array($mapInfo['field'], ['field_year_released', 'field_year_recorded'])) {
          // Convert to integer
          $songData[$mapInfo['field']] = (int) $value;
        } elseif ($mapInfo['field'] === 'field_artist') {
          // Handle multiple artists (split by |)
          $songData[$mapInfo['field']] = $this->processArtists($value);
        } elseif ($mapInfo['field'] === 'field_links') {
          // Handle multiple links (split by |)
          $songData[$mapInfo['field']] = $this->processLinks($value);
        } elseif ($mapInfo['field'] === 'field_notes') {
          // Handle multiple notes (split by |)
          $songData[$mapInfo['field']] = $this->processNotes($value);
        } elseif (in_array($mapInfo['field'], ['field_start_time', 'field_end_time', 'field_duration'])) {
          // Validate time fields - skip if value doesn't look like time
          if ($this->isValidTimeFormat($value)) {
            $songData[$mapInfo['field']] = $value;
          }
        } else {
          $songData[$mapInfo['field']] = $value;
        }
      }
    }
    
    $song = \Drupal::entityTypeManager()->getStorage('node')->create($songData);
    $song->save();
    
    // Debug logging
    \Drupal::logger('song_sheets_import')->info('Created new song "@title" (ID: @id) in Episode #@episode_num', [
      '@title' => $songTitle,
      '@id' => $song->id(),
      '@episode_num' => $episodeNumber,
    ]);
    
    return 'imported';
  }

  /**
   * Update empty fields in existing song.
   */
  private function updateEmptyFields($existingSong, $row, $headers, $mapping) {
    $updated = FALSE;
    
    foreach ($mapping as $index => $mapInfo) {
      if (!$mapInfo['field'] || $mapInfo['field'] === 'title' || !isset($row[$index]) || empty(trim($row[$index]))) {
        continue;
      }
      
      $fieldName = $mapInfo['field'];
      $newValue = trim($row[$index]);
      
      // Check if field is currently empty
      $currentValue = $existingSong->get($fieldName)->getValue();
      $isEmpty = empty($currentValue) || (isset($currentValue[0]['value']) && empty($currentValue[0]['value']));
      
      if ($isEmpty) {
        // Field is empty, update it
        if (in_array($fieldName, ['field_year_released', 'field_year_recorded'])) {
          $existingSong->set($fieldName, (int) $newValue);
        } elseif ($fieldName === 'field_artist') {
          $existingSong->set($fieldName, $this->processArtists($newValue));
        } elseif ($fieldName === 'field_links') {
          $existingSong->set($fieldName, $this->processLinks($newValue));
        } elseif ($fieldName === 'field_notes') {
          $existingSong->set($fieldName, $this->processNotes($newValue));
        } elseif (in_array($fieldName, ['field_start_time', 'field_end_time', 'field_duration'])) {
          // Validate time fields - skip if value doesn't look like time
          if ($this->isValidTimeFormat($newValue)) {
            $existingSong->set($fieldName, $newValue);
          }
        } else {
          $existingSong->set($fieldName, $newValue);
        }
        $updated = TRUE;
      }
    }
    
    if ($updated) {
      $existingSong->save();
      return 'updated';
    }
    
    return 'skipped';
  }

  /**
   * Process artist names and return entity reference array.
   */
  private function processArtists($artistString) {
    // Split by pipe separator (from multi-column processing)
    $artistNames = explode('|', $artistString);
    $artistIds = [];
    
    foreach ($artistNames as $artistName) {
      $artistName = trim($artistName);
      if (empty($artistName)) {
        continue;
      }
      
      // Find or create artist node
      $artistId = $this->findOrCreateArtist($artistName);
      if ($artistId) {
        $artistIds[] = $artistId;
      }
    }
    
    return $artistIds;
  }

  /**
   * Find or create an artist node.
   */
  private function findOrCreateArtist($artistName) {
    // Query for existing artist by title
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'artist')
      ->condition('title', $artistName)
      ->accessCheck(FALSE);
    
    $nids = $query->execute();
    
    if (!empty($nids)) {
      // Artist exists, return the ID
      return reset($nids);
    }
    
    // Create new artist
    $artist = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'artist',
      'title' => $artistName,
      'uid' => \Drupal::currentUser()->id(),
      'status' => 1, // Published
    ]);
    
    $artist->save();
    
    return $artist->id();
  }

  /**
   * Process links and return array of link field values.
   */
  private function processLinks($linkString) {
    // Split by pipe separator (from multi-column processing)
    $links = explode('|', $linkString);
    $linkValues = [];
    
    foreach ($links as $link) {
      $link = trim($link);
      if (empty($link)) {
        continue;
      }
      
      // For now, just treat each as a URL with the URL as the title
      // Could be enhanced to parse "title|url" format if needed
      $linkValues[] = [
        'uri' => $link,
        'title' => $link,
      ];
    }
    
    return $linkValues;
  }

  /**
   * Process notes and return array of text field values.
   */
  private function processNotes($notesString) {
    // Split by pipe separator (from multi-column processing)
    $notes = explode('|', $notesString);
    $noteValues = [];
    
    foreach ($notes as $note) {
      $note = trim($note);
      if (empty($note)) {
        continue;
      }
      
      // Each note becomes a separate entry in the multiple field
      $noteValues[] = ['value' => $note];
    }
    
    return $noteValues;
  }

  /**
   * Validate if a value looks like a time format.
   */
  private function isValidTimeFormat($value) {
    // Check for various time formats: MM:SS, H:MM:SS, MM:SS.ms
    // Also allow empty values or values that are clearly times
    $timePatterns = [
      '/^\d{1,2}:\d{2}$/',           // MM:SS or H:MM
      '/^\d{1,2}:\d{2}:\d{2}$/',     // H:MM:SS
      '/^\d{1,2}:\d{2}\.\d+$/',      // MM:SS.milliseconds
    ];
    
    foreach ($timePatterns as $pattern) {
      if (preg_match($pattern, $value)) {
        return true;
      }
    }
    
    // If it contains letters (like "Miles Davis Quintet"), it's probably not a time
    if (preg_match('/[a-zA-Z]/', $value)) {
      return false;
    }
    
    return true;
  }

  /**
   * Batch process callback.
   */
  public static function batchProcess($episodes, &$context) {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['imported'] = 0;
      $context['sandbox']['updated'] = 0;
      $context['sandbox']['skipped'] = 0;
      $context['sandbox']['failed'] = [];
    }
    
    // Create temporary form instance to access methods
    $form = new static(\Drupal::configFactory());
    
    foreach ($episodes as $selectedSheet) {
      $context['message'] = t('Processing Episode @episode...', ['@episode' => $selectedSheet]);
      
      try {
        // Get sheet data
        $sheetTitle = $form->getSheetTitle($selectedSheet);
        $data = $form->getSheetData($selectedSheet);
        
        if (empty($data['rows'])) {
          \Drupal::messenger()->addWarning("No songs found in Episode $selectedSheet: $sheetTitle");
          $context['sandbox']['progress']++;
          continue;
        }
        
        // Create or find episode
        $episodeTitle = $form->parseEpisodeTitle($sheetTitle);
        $episode = $form->createOrFindEpisode($selectedSheet, $episodeTitle);
        
        // Import songs
        $episodeImported = 0;
        $episodeUpdated = 0;
        $episodeSkipped = 0;
        $mapping = $form->mapColumnsToFields($data['headers']);
        
        foreach ($data['rows'] as $row) {
          $result = $form->importSong($row, $data['headers'], $mapping, $episode);
          if ($result === 'imported') {
            $episodeImported++;
          } elseif ($result === 'updated') {
            $episodeUpdated++;
          } else {
            $episodeSkipped++;
          }
        }
        
        $context['sandbox']['imported'] += $episodeImported;
        $context['sandbox']['updated'] += $episodeUpdated;
        $context['sandbox']['skipped'] += $episodeSkipped;
        
        // Only show messages for episodes that had significant activity
        if ($episodeImported > 0 || $episodeUpdated > 5) {
          \Drupal::messenger()->addStatus(
            "Episode $selectedSheet: Created $episodeImported, Updated $episodeUpdated, Skipped $episodeSkipped songs"
          );
        }
        
      } catch (\Exception $e) {
        $context['sandbox']['failed'][] = $selectedSheet;
        \Drupal::messenger()->addError("Failed to import Episode $selectedSheet: " . $e->getMessage());
        \Drupal::logger('song_sheets_import')->error('Import error for episode @episode: @error', [
          '@episode' => $selectedSheet,
          '@error' => $e->getMessage()
        ]);
      }
      
      $context['sandbox']['progress']++;
      
      // Add delay to avoid API rate limiting
      sleep(1); // 1 second delay
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations, $elapsed) {
    if ($success) {
      // Calculate totals from all batch operations
      $totalImported = 0;
      $totalUpdated = 0;
      $totalSkipped = 0;
      $failedEpisodes = [];
      
      // Get totals from the last context (batch API doesn't pass results this way)
      // We'll display a simple completion message
      $message = t("Bulk import complete! Check the messages above for detailed results.");
      \Drupal::messenger()->addStatus($message);
    } else {
      \Drupal::messenger()->addError(t('The bulk import process encountered an error.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Regular form submission - do nothing
  }

}