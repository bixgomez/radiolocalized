<?php
// Load the main Drupal vendor autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Path to your credentials file
$credentialsPath = __DIR__ . '/radio-localized-episodes-8243df309e4a.json';

// Your spreadsheet details
$spreadsheetId = '1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY';
$range = '41!A1:Z10';  // Get first 10 rows from sheet "41"

try {
    // Create Google client
    $client = new Google_Client();
    $client->setApplicationName('Drupal Songs Import Test');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
    $client->setAuthConfig($credentialsPath);
    
    // Create Sheets service
    $service = new Google_Service_Sheets($client);
    
    // Fetch the data
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    // Display results
    if (empty($values)) {
        print "No data found.\n";
    } else {
        print "Successfully connected! Here's what we found:\n\n";
        foreach ($values as $row) {
            print implode(' | ', $row) . "\n";
        }
    }
    
} catch (Exception $e) {
    print "Error: " . $e->getMessage() . "\n";
}
