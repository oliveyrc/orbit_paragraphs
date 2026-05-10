# Orbit Paragraphs

<img src="assets/orbit-paragraphs-logo.svg" alt="Orbit Paragraphs logo" width="150" height="150" />

Orbit Paragraphs is a custom Drupal 11 module that provides common Paragraph fields and reports for Orbit projects.

## Requirements

- Drupal 11
- PHP 8.4 or later
- Paragraphs
- Paragraphs Editor Enhancements

## Features

- Interactive Paragraph bundle creation via Drush.
- Paragraph category tagging using Paragraphs Editor Enhancements.
- Multiple categories can be assigned in one command (optional with default "None" option).
- Automatic Field Group setup on the bundle manage form display.
- Field Group parent tab: Tabs.
- Field Group child tab: Content.
- Field Group child tab: Settings.
- Published checkbox automatically placed in Settings tab.
- Page Sections admin report with usage analytics and visual browsing.
- Section icon/image preview in grid layout.
- Modal window showing latest 10 pages using each section.
- Usage count badges on section cards.

## Drush commands

Create a new Paragraph type interactively:

```bash
drush orbit-paragraphs:create
```

Create a new Paragraph type with a supplied label and prompted description:

```bash
drush orbit-paragraphs:create "Hero Banner"
```

Create a Paragraph type with an explicit machine name and description:

```bash
drush orbit-paragraphs:create "CTA" --machine-name=cta --description="Call to action paragraph."
```

Create a Paragraph type and assign a single category:

```bash
drush orbit-paragraphs:create "Hero Banner" --category=media
```

Create a Paragraph type and assign multiple categories:

```bash
drush orbit-paragraphs:create "Feature Grid" --category=text,media,news
```

When no category is provided, the command prompts you to select one or more categories, with a
default option for no category.
After creation, the command also configures the bundle's default paragraph form display with a
Tabs wrapper containing Content and Settings child tabs.
The published checkbox is automatically placed in the Settings tab.

## Admin Pages

### Page Sections Report

Access via **Administration > Reports > Page Sections** or **Administration > Structure > Paragraph types > Page sections**.

This report displays all available paragraph section types (bundles) in an easy-to-browse grid layout:

- **Section Icon**: Displays the configured paragraph type icon/image at full width with rounded corners.
- **Section Title**: The label of the paragraph type.
- **Usage Count**: Badge showing the number of pages currently using this section (e.g., "5 pages" or "1 page").
- **Description**: The configured description for the section.
- **View Latest 10 Pages**: Modal link (only shown for sections with active usage) that opens a dialog listing the latest 10 pages using that section with their content type and last updated time.

Unused sections display a "Not used yet" indicator instead of the modal link.

## Related Orbit Modules

- [Orbit Paragraphs](https://github.com/oliveyrc/orbit_paragraphs)
- [Orbit Webform](https://github.com/oliveyrc/orbit_webform)

## License

This module is licensed under the MIT License.
