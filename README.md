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
- Multiple categories can be assigned in one command.
- Automatic Field Group setup on the bundle manage form display.
- Field Group parent tab: Tabs.
- Field Group child tab: Content.
- Field Group child tab: Settings.

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

When no category is provided, the command prompts you to select one or more categories.
After creation, the command also configures the bundle's default paragraph form display with a
Tabs wrapper containing Content and Settings child tabs.

## License

This module is licensed under the MIT License.
