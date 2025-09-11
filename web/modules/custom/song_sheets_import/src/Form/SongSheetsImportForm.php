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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'song_sheets_import_form';
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
    }
    
    // Add CSS and JavaScript
    $form['#attached']['library'][] = 'song_sheets_import/episode-grid';

    return $form;
  }

  /**
   * Get available sheets from Google Sheets.
   */
  private function getAvailableSheets() {
    try {
      $credentialsPath = \Drupal::root() . '/test-sheets/radio-localized-episodes-8243df309e4a.json';
      $spreadsheetId = '1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY';

      $client = new Google_Client();
      $client->setApplicationName('Drupal Songs Import');
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $client->setAuthConfig($credentialsPath);
      
      $service = new Google_Service_Sheets($client);
      $spreadsheet = $service->spreadsheets->get($spreadsheetId);
      
      $sheets = [];
      foreach ($spreadsheet->getSheets() as $sheet) {
        $title = $sheet->getProperties()->getTitle();
        if (is_numeric($title)) {
          $sheets[$title] = 'Episode ' . $title;
        }
      }
      
      return $sheets;
    } catch (Exception $e) {
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
      
      if (strcasecmp($columnHeader, 'Length') === 0) {
        $mapping[$columnIndex] = ['field' => 'field_duration', 'label' => 'Duration', 'match' => 'special'];
        continue;
      }
      
      if (strcasecmp($columnHeader, 'Link') === 0) {
        $mapping[$columnIndex] = ['field' => 'field_links', 'label' => 'Links', 'match' => 'special'];
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
    
    // Find the "Song" column index
    $songIndex = array_search('Song', $data['headers']);
    if ($songIndex === false) {
      return $markup . '<p><em>No "Song" column found.</em></p>';
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
   * Get title from specific sheet.
   */
  private function getSheetTitle($sheetName) {
    try {
      $credentialsPath = \Drupal::root() . '/test-sheets/radio-localized-episodes-8243df309e4a.json';
      $spreadsheetId = '1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY';
      $range = $sheetName . '!1:1'; // First row has title

      $client = new Google_Client();
      $client->setApplicationName('Drupal Songs Import');
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $client->setAuthConfig($credentialsPath);
      
      $service = new Google_Service_Sheets($client);
      $response = $service->spreadsheets_values->get($spreadsheetId, $range);
      $values = $response->getValues();
      
      return !empty($values[0][0]) ? $values[0][0] : 'Episode ' . $sheetName;
    } catch (Exception $e) {
      return 'Episode ' . $sheetName;
    }
  }

  /**
   * Get full data (headers + rows) from specific sheet.
   */
  private function getSheetData($sheetName) {
    try {
      $credentialsPath = \Drupal::root() . '/test-sheets/radio-localized-episodes-8243df309e4a.json';
      $spreadsheetId = '1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY';
      $range = $sheetName . '!A2:AA100'; // Headers from row 2, data from rows 3+, get columns A-AA

      $client = new Google_Client();
      $client->setApplicationName('Drupal Songs Import');
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $client->setAuthConfig($credentialsPath);
      
      $service = new Google_Service_Sheets($client);
      $response = $service->spreadsheets_values->get($spreadsheetId, $range);
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
      return ['headers' => [], 'rows' => []];
    }
  }

  /**
   * Get headers from specific sheet.
   */
  private function getSheetHeaders($sheetName) {
    try {
      $credentialsPath = \Drupal::root() . '/test-sheets/radio-localized-episodes-8243df309e4a.json';
      $spreadsheetId = '1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY';
      $range = $sheetName . '!2:2'; // Second row has headers

      $client = new Google_Client();
      $client->setApplicationName('Drupal Songs Import');
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $client->setAuthConfig($credentialsPath);
      
      $service = new Google_Service_Sheets($client);
      $response = $service->spreadsheets_values->get($spreadsheetId, $range);
      $values = $response->getValues();
      
      return !empty($values[0]) ? $values[0] : [];
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submit handling will be added later.
  }

}