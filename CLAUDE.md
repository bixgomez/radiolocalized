# CLAUDE.md - Project Notes

This file contains notes and reference information for Claude Code assistance.

## Project Structure
- Project root: /Users/gilbert/Sites/LINODE/var/www/html/radiolocalized
- Web root: /Users/gilbert/Sites/LINODE/var/www/html/radiolocalized/web
- Git repository: Yes
- Current branch: main
- Project type: Drupal (composer-based)

## Commands
(Commands will be added here as discovered)

## Notes
(Project-specific notes will be added here)

# Google Sheets to Drupal 11 Import Project Summary

## Project Overview
Setting up a system to import song data from Google Sheets into a Drupal 11 website running on DDEV.  Each row in the Google Sheets becomes a node of content type `song`.

## Completed Setup

### Google Cloud Configuration
- **Project Name:** Radio Localized Episodes
- **Project ID:** `radio-localized-episodes`
- **Google Sheets API:** Enabled
- **Service Account Created:** `radio-localized-spreadsheets@radio-localized-episodes.iam.gserviceaccount.com`
- **JSON Credentials File:** `radio-localized-episodes-8243df309e4a.json`
- **Google Sheets:** Already shared with the service account

### Test Implementation
Successfully created and tested a PHP script that connects to Google Sheets and retrieves data.

**Test Location:** `/web/test-sheets/test-sheets.php`

**Working Test Script:**
```php
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
```

### Verified Data Structure
The Google Sheets contain these columns (from sheet "41" example):
- Song
- Artist  
- Album
- Year
- Label
- Length (appears twice - duration and actual playtime)
- Link (Wikipedia URLs)
- Start Time
- End Time  
- Notes

**Sample Data Retrieved:**
```
Episode 041: Elephant 6 Recording Company
Song | Artist | Album | Year | Label | Length | Link | Start Time | End Time | Length | Notes
A Warning! | The Music Tapes | 1st Imaginary Symphony for Nomad | 1999 | Merge Records | 1:30 | https://en.wikipedia.org/wiki/1st_Imaginary_Symphony_for_Nomad | 0:00:00 | 0:01:00 | 0:01:00
```

## Current Environment
- **Drupal Version:** 11
- **Development Environment:** DDEV
- **Google API Client:** Installed via Composer (`google/apiclient:^2.12`) in main Drupal vendor directory
- **Content Type:** `song` (fully configured with all necessary fields)
- **Import Module:** `song_sheets_import` (enabled and functional)

## Key Information
- **Google Sheets ID:** `1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY`
- **Sheet Names:** Numerical (e.g., "41", "42", etc.)
- **JSON Credentials Location:** Currently in `/web/test-sheets/radio-localized-episodes-8243df309e4a.json`

## Requirements & Constraints
1. **Manual Import:** No cron jobs needed; imports will be triggered manually
2. **Multiple Sheets:** Data comes from multiple tabs/sheets in the same Google Sheets file
3. **Column Variations:** Some sheets may have slightly different columns (needs normalization)
4. **Time Field Parsing:** Time fields need parsing during import
5. **User Preferences:** 
   - Prefers step-by-step explanations
   - Small chunks of information at a time
   - No code unless specifically requested
   - No custom fields/content types without discussion

## Development Progress

### ✅ Completed
1. **Custom Drupal Module (`song_sheets_import`)**
   - ✅ Module created and enabled
   - ✅ Admin interface at `/admin/config/content/song-sheets-import`
   - ✅ Episode grid navigation (URL-based, no JavaScript dependencies)
   - ✅ Real-time preview of sheet data

2. **Song Content Type Fields**
   - ✅ All necessary fields configured:
     - Episode (entity reference)
     - Artist, Composer, Album, Label, Place (text fields)
     - Year Released, Year Recorded (integer fields, 1895-2100 range)
     - Start Time, End Time, Duration (MM:SS format)
     - Links field (multiple URL/title pairs)
   - ✅ Proper field ordering and labeling
   - ✅ Integer year fields for proper data validation and storage

3. **Data Processing**
   - ✅ Google Sheets API integration working
   - ✅ Multi-column field handling (Notes and other spanning fields)
   - ✅ Automatic combination of empty header columns with preceding named columns
   - ✅ Data preview showing song titles with field details
   - ✅ Field mapping system (Google Sheets columns → Drupal field labels)

### 🔄 Next Steps
1. **Column Mapping Interface**
   - Google columns → Drupal fields mapping
   - Handle field variations between sheets

2. **Import Functionality**
   - Create song nodes from selected sheet data
   - Handle time field conversions (text to MM:SS format)
   - Duplicate detection and handling

3. **Error Handling & Logging**
   - Import progress display
   - Failed row tracking and reporting
   - Import statistics and summaries

## Technical Considerations

### Column Mapping Strategy
Need to handle variations like:
- "Composer" vs "Written By" → map to same field
- Missing columns in some sheets
- Extra columns that don't map to fields

### Time Field Parsing
Convert various formats:
- "3:45" → 225 seconds
- "3 minutes 45 seconds" → 225 seconds
- "0:03:45" → 225 seconds

### Duplicate Handling Options
- **Skip:** Don't import if matching song exists
- **Update:** Overwrite existing node with new data
- **Create New:** Allow duplicates (not recommended)

## Important Notes
- The Google API connection is confirmed working
- All Google-side permissions are properly configured
- Service account has read access to all necessary sheets
- Google API client library is already installed in Drupal

### Field Type Changes in Drupal
**Important:** You cannot change the field type of an existing field in Drupal. If you need to change a field from `string` to `integer` (or any other type change), you must:

1. **Delete the old field configs** (both storage and field instance)
2. **Create new fields** - you can use the same machine names if desired
3. **Update all form/view display references** (if machine names changed)
4. **Run config import** to apply changes
5. **Export config** (`drush cex`) to sync UUIDs and prevent persistent update notifications

**Strategy Note:** If other systems depend on specific machine names (like field mapping logic), it's better to keep the original machine names when recreating fields. We initially tried different machine names (`field_released_year`) but reverted to the originals (`field_year_released`) to maintain compatibility with the field mapping system that matches Google Sheets columns to Drupal field labels.

## Files & Locations Reference
```
/var/www/html/                           # DDEV project root
├── composer.json                        # Contains google/apiclient dependency
├── vendor/                              # Google API library installed here
│   └── google/
├── web/
│   ├── modules/
│   │   └── custom/
│   │       └── song_sheets_import/      # Custom import module
│   │           ├── song_sheets_import.info.yml
│   │           ├── song_sheets_import.routing.yml
│   │           └── src/Form/SongSheetsImportForm.php
│   └── test-sheets/                     # Current test location
│       ├── test-sheets.php              # Working test script
│       └── radio-localized-episodes-8243df309e4a.json
```

## Credentials & API Details
```
Google Cloud Project: radio-localized-episodes
Service Account Email: radio-localized-spreadsheets@radio-localized-episodes.iam.gserviceaccount.com
Spreadsheet ID: 1AjmCYXG636IaNc3fkdPhpf3JD0P6bnRrT-IKkRWO-JY
API Scope: Google_Service_Sheets::SPREADSHEETS_READONLY
```

## Success Criteria
- ✅ Google Sheets API connection established
- ✅ Data successfully retrieved from sheets
- ✅ Drupal module created (`song_sheets_import`)
- ✅ Admin UI implemented with episode dropdown
- ✅ Multi-column field handling (Notes and other spanning fields)
- ✅ Song content type fields configured (Composer, Year Released, Year Recorded)
- ⬜ Import process handles all data correctly
- ⬜ Error handling and logging in place
- ⬜ Documentation completed

⏺ Looking at the CLAUDE.md file, I can see it contains a comprehensive Google Sheets to Drupal import project summary that's separate from our current field configuration
   work. The file documents:

  Key Points from CLAUDE.md:

  1. Google Sheets Integration Project - A system to import song data from Google Sheets into Drupal
  2. Working API Connection - Google Sheets API is already set up and tested
  3. Song Content Type - References a song content type that "already created with fields"

  Important Observation:

  The CLAUDE.md mentions that the song content type was "already created with fields", but based on our work today, we've been building out the complete field structure
  for the song content type with:

  - Episode (entity reference)
  - Artist, Album, Year, Label, Place fields
  - Start Time, End Time, Duration (MM:SS format)
  - Links field (multiple URL/title pairs)

  Alignment Check:

  The Google Sheets data structure mentioned in CLAUDE.md includes:
  - Song, Artist, Album, Year, Label ✅ matches our fields
  - Length, Start Time, End Time ✅ matches our timing fields
  - Link ✅ matches our Links field
  - Episode info ✅ matches our Episode field

  Our field configuration work today perfectly aligns with and completes the foundation needed for the Google Sheets import project described in CLAUDE.md. The song
  content type is now fully configured to receive all the data that will be imported from the sheets!

  Should we update the CLAUDE.md file to reflect the completed field configuration, or is there something specific you wanted me to review?

> No, that's ok - we need to move on to the " Google Sheets to Drupal 11 Import Project"
  ⎿  ⧉ Selected 1 lines from CLAUDE.md in Visual Studio Code

⏺ Perfect! Let's move on to the Google Sheets to Drupal 11 Import Project. Based on the CLAUDE.md file, you have a solid foundation already established:

  ✅ Already Complete:
  - Google Sheets API connection working
  - Service account configured with proper permissions
  - Test script successfully retrieving data
  - Song content type now fully configured with all necessary fields

  🎯 Next Step: Create the custom Drupal module

  The logical next step is to create the song_sheets_import module. This will provide:
  - Admin interface to select which sheet to import
  - Column mapping between Google Sheets and your Drupal fields
  - Data preview and import functionality
  - Error handling and reporting

  Would you like me to start by creating the basic module structure (song_sheets_import.info.yml, routing, etc.), or do you want to discuss the approach first?

  The module will need to:
  1. Handle Google API authentication
  2. Fetch data from selected sheets
  3. Map the Google Sheets columns to your song fields
  4. Convert time formats (like "3:45" to MM:SS format)
  5. Create song nodes with proper field values