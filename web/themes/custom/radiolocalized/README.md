# Radio Localized Theme

A custom Drupal 10 theme built specifically for the Radio Localized website, featuring interactive maps, episode-specific layouts, and a responsive design system.

## Features

- **Interactive Mapping**: Leaflet.js integration for displaying song locations
- **Episode-Specific Layouts**: Custom page layouts for radio episodes with map regions
- **Responsive Grid System**: CSS Grid-based layouts that adapt to different screen sizes
- **Custom Song Teasers**: Interactive song cards with location data and modal overlays
- **Live Development Workflow**: Gulp + LiveReload for efficient development

## Architecture

### Sass Structure (Semi-Atomic Design)
```
sass/
├── 00_functions/          # Sass functions (font sizing, strip units)
├── 01_config/            # Configuration (variables, mixins, breakpoints)
├── 02_base/              # Base element styles
├── 03_drupal/            # Drupal-specific components (tabs, messages, etc.)
├── 04_layout/            # Layout systems (grid, regions)
├── 05_typography/        # Typography styles
├── 06_elements/          # UI elements (scrollbars, etc.)
├── 07_navigation/        # Navigation components
└── 08_components/        # Page-specific components (episodes, songs)
```

### Template Structure
```
templates/
├── block/                # Block templates (branding, maps, copyright)
├── components/           # Component templates (song teasers, fields)
├── layout/               # Layout templates (pages, regions, HTML)
├── misc/                 # Utility templates (status messages)
└── navigation/           # Menu and navigation templates
```

## Development Workflow

### Prerequisites
- Node.js and npm
- Gulp CLI: `npm install -g gulp-cli`

### Setup
1. **Install dependencies**
   ```bash
   cd web/themes/custom/radiolocalized
   npm install
   ```

2. **Start development workflow**
   ```bash
   gulp
   ```
   This starts the default task which:
   - Compiles Sass to CSS with sourcemaps
   - Watches for file changes
   - Triggers LiveReload on changes

### Available Gulp Tasks
- `gulp` or `gulp default` - Compile Sass and start watching
- `gulp sass` - Compile Sass files once
- `gulp watch` - Watch for Sass changes and trigger LiveReload

### Dependencies
- **Sass Compilation**: `gulp-sass` with Dart Sass
- **Autoprefixer**: `gulp-autoprefixer` for vendor prefixes
- **LiveReload**: `gulp-livereload` for live browser updates
- **Sass Globbing**: `gulp-sass-glob` for importing entire directories
- **Breakpoint Sass**: Responsive breakpoint management
- **Font Awesome**: Icon library integration

## Design System

### Typography
- **Primary Font**: Barlow (body text)
- **Heading Font**: Barlow Condensed (headings, navigation)
- **Responsive Font Sizing**: Viewport-based scaling available

### Color Palette
- **Primary**: Desaturated blue (#4b648b)
- **Secondary**: Dark purple (#303155)
- **Grays**: Systematic gray scale from lightest to darkest
- **Interactive**: Link colors with hover states

### Breakpoints
- **XXS**: 0px
- **XS**: 320px  
- **SM**: 544px
- **MD**: 768px
- **LG**: 960px
- **XL**: 1200px

### Layout System
- **CSS Grid**: Primary layout method
- **Flexbox**: Component-level layouts
- **Responsive Regions**: Header, navigation, content, map, footer
- **Episode Layout**: Special 60/40 split for content and map

## Key Features

### Interactive Maps
- **Leaflet.js Integration**: Custom map implementation
- **Song Location Mapping**: Each song displays its geographic origin
- **Modal Overlays**: Detailed song information in popup modals

### Episode Pages
- **Custom Layout**: Special grid layout with map region
- **Song Teasers**: Interactive cards with hover effects
- **Location Data**: Coordinates and place information for each song

### Responsive Design
- **Mobile-First**: Designed for mobile devices first
- **Flexible Grids**: Adapts to different screen sizes
- **Touch-Friendly**: Optimized for touch interactions

## File Structure

### Critical Files
- `radiolocalized.info.yml` - Theme definition and regions
- `radiolocalized.libraries.yml` - Asset library definitions
- `radiolocalized.theme` - PHP theme hooks and functions
- `gulpfile.js` - Build process configuration
- `package.json` - Node.js dependencies

### Custom JavaScript
- `js/script.js` - Custom theme JavaScript (maps, interactions)

### Regions Defined
- **Header**: Site branding and page titles
- **Navigation**: Main menu and navigation elements  
- **Info**: Administrative information (hidden for anonymous users)
- **Content**: Main page content
- **Map**: Interactive map display (episode pages only)
- **Footer**: Site footer with utilities

## Browser Support
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Chrome Mobile)
- Progressive enhancement for older browsers

## Notes
- Built as a modified version of the developer's portfolio theme
- Maintains original Gulp + LiveReload workflow by design choice
- Semi-atomic Sass structure inspired by Brad Frost's methodology
- All custom JavaScript for map functionality is contained within the theme