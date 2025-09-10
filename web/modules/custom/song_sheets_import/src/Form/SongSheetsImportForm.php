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

    $form['sheet_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Sheet to Import'),
      '#options' => $sheets,
      '#default_value' => $this->getDefaultSheet($sheets),
      '#description' => $this->t('Choose which episode sheet to import songs from.'),
      '#ajax' => [
        'callback' => '::updateColumnHeaders',
        'wrapper' => 'column-headers-wrapper',
      ],
    ];

    $form['column_headers'] = [
      '#type' => 'container',
      '#prefix' => '<div id="column-headers-wrapper">',
      '#suffix' => '</div>',
    ];

    // Load column headers for default sheet
    $defaultSheet = $this->getDefaultSheet($sheets);
    if ($defaultSheet) {
      $form['column_headers']['headers'] = [
        '#markup' => $this->getColumnHeadersMarkup($defaultSheet),
      ];
    }

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
   * AJAX callback for sheet selection.
   */
  public function updateColumnHeaders(array &$form, FormStateInterface $form_state) {
    $selectedSheet = $form_state->getValue('sheet_select');
    $form['column_headers']['headers']['#markup'] = $this->getColumnHeadersMarkup($selectedSheet);
    return $form['column_headers'];
  }

  /**
   * Get column headers markup for display.
   */
  private function getColumnHeadersMarkup($sheetName) {
    $title = $this->getSheetTitle($sheetName);
    $headers = $this->getSheetHeaders($sheetName);
    
    if (empty($headers)) {
      return '<p><em>No column headers found.</em></p>';
    }
    
    $markup = '<h4>' . htmlspecialchars($title) . '</h4><ul>';
    foreach ($headers as $header) {
      $markup .= '<li><strong>' . htmlspecialchars($header) . '</strong></li>';
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