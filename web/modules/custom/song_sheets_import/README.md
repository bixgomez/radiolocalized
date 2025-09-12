# Song Sheets Import Module

A custom Drupal module that imports song data from Google Sheets into Drupal song nodes.

## Overview

This module provides a complete solution for importing song data from Google Sheets, including:
- Episode-based import with visual episode selection
- Automatic field mapping from Google Sheets columns to Drupal fields
- Bulletproof duplicate detection system
- Batch processing for bulk imports
- Comprehensive error handling and logging

## Requirements

- Drupal 11
- Google API Client Library (`google/apiclient` installed via Composer)
- Google Cloud Project with Sheets API enabled
- Service account with read access to target Google Sheets

## Content Types Required

### Song Content Type (`song`)
Required fields:
- `title` - Song title
- `field_episode` - Entity reference to episode
- `field_episode_number` - Integer field for episode number
- `field_artist` - Entity reference to artist(s)
- `field_composer` - Text field
- `field_album` - Text field
- `field_label` - Text field
- `field_place` - Entity reference to place
- `field_year_released` - Integer field (1895-2100)
- `field_year_recorded` - Integer field (1895-2100)
- `field_start_time` - String field (MM:SS format)
- `field_end_time` - String field (MM:SS format)
- `field_duration` - String field (MM:SS format)
- `field_links` - Link field (multiple values)
- `field_notes` - Text field (multiple values)

### Episode Content Type (`episode`)
Required fields:
- `title` - Episode title
- `field_episode_number` - Integer field for episode number

### Artist Content Type (`artist`)
Required fields:
- `title` - Artist name

## Installation

1. **Install Google API Client:**
   ```bash
   composer require google/apiclient:^2.12
   ```

2. **Place credentials file:**
   - Place your Google service account JSON file in `/test-sheets/`
   - Update the credentials path in `SongSheetsImportForm.php` if different

3. **Enable the module:**
   ```bash
   drush en song_sheets_import
   ```

4. **Import configuration:**
   ```bash
   drush cim
   ```

## Configuration

### Google Sheets Setup

1. **Sheet Structure:**
   - Row 1: Episode title (e.g., "Episode 041: Elephant 6 Recording Company")
   - Row 2: Column headers (Song, Artist, Album, Year, etc.)
   - Row 3+: Song data

2. **Sheet Naming:**
   - Use numeric sheet names (e.g., "41", "42", "43")
   - Each sheet represents one episode

3. **Column Headers:**
   The module automatically maps common column names:
   - `Song` or `Title` → Song title
   - `Artist` → Artist field
   - `Album` → Album field
   - `Year` → Year Released field
   - `Label` → Label field
   - `Length` → Duration field
   - `Link` → Links field
   - `Start Time` → Start Time field
   - `End Time` → End Time field
   - `Notes` → Notes field

### Module Configuration

Update these constants in `SongSheetsImportForm.php`:
- `$credentialsPath` - Path to Google service account JSON file
- `$spreadsheetId` - Your Google Sheets document ID

## Usage

### Admin Interface

Navigate to **Configuration > Content authoring > Song Sheets Import** (`/admin/config/content/song-sheets-import`)

### Import Process

1. **Episode Selection:**
   - Visual grid shows all available episodes
   - Click an episode to preview its data
   - See field mapping and song count

2. **Single Episode Import:**
   - Click "🚀 LET 'ER RIP!" button for selected episode
   - Shows import statistics (created/updated/skipped)

3. **Bulk Import:**
   - Click "💥 LET 'EM ALL RIP!" to import all episodes
   - Uses batch processing for performance
   - Processes episodes in chunks of 5

## Key Features

### 🛡️ Bulletproof Duplicate Detection

The module uses a dual-field system for rock-solid duplicate detection:

**Fields Used:**
- `field_episode` (entity reference) - For relationships and Views
- `field_episode_number` (integer) - For bulletproof duplicate detection

**How It Works:**
```php
// Finds duplicates by title + episode number
$songQuery = \Drupal::entityQuery('node')
  ->condition('type', 'song')
  ->condition('title', $songTitle)
  ->condition('field_episode_number', $episodeNumber)
  ->accessCheck(FALSE);
```

**Benefits:**
- ✅ Works even if episodes are deleted/recreated
- ✅ Automatically reconnects orphaned songs to new episodes
- ✅ No unwanted duplicates
- ✅ Maintains data relationships

### 🔧 Smart Field Processing

- **Artist Handling:** Creates artist nodes automatically
- **Time Validation:** Validates MM:SS format for time fields
- **Multi-value Processing:** Handles pipe-separated values for links and notes
- **Empty Field Updates:** Only updates empty fields in existing songs

### 📊 Comprehensive Logging

All import activities are logged with details:
- Duplicate detection decisions
- Episode reconnections
- Field mappings
- Import statistics
- Error conditions

Check logs at **Reports > Recent log messages** or:
```bash
drush watchdog:show --type=song_sheets_import
```

## Data Flow

1. **Sheet Detection:** Scans Google Sheets for numeric sheet names
2. **Data Retrieval:** Fetches episode title and song data
3. **Field Mapping:** Maps Google columns to Drupal fields
4. **Episode Processing:** Creates/finds episode nodes
5. **Song Processing:** For each song:
   - Check for duplicates (title + episode number)
   - Update episode reference if needed
   - Update empty fields
   - Create new song if not found
6. **Batch Processing:** Handles large imports efficiently

## Troubleshooting

### Common Issues

**"No data found" error:**
- Check Google Sheets API permissions
- Verify service account has access to the sheet
- Confirm credentials file path is correct

**Import creates duplicates:**
- Ensure `field_episode_number` is properly configured
- Check that episode numbers are populated during import

**Field mapping issues:**
- Review column headers in Google Sheets
- Check field mapping display in admin interface
- Verify field machine names match expected values

### Debug Logging

Enable detailed logging by checking the import logs:
```bash
drush watchdog:show --type=song_sheets_import --tail
```

## File Structure

```
song_sheets_import/
├── README.md
├── song_sheets_import.info.yml
├── song_sheets_import.routing.yml
└── src/
    └── Form/
        └── SongSheetsImportForm.php
```

## Contributing

This module was developed specifically for the Radio Localized project but can be adapted for other Google Sheets import needs.

## License

GPL-2.0-or-later

## Support

For issues related to this module, check the import logs and verify your Google Sheets configuration matches the expected format.