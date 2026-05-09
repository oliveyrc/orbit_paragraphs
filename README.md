# Orbit Paragraphs

<img src="assets/orbit-paragraphs-logo.svg" alt="Orbit Paragraphs logo" width="150" height="150" />

Orbit Paragraphs is a custom Drupal 11 module that provides common Paragraph fields and reports for Orbit projects.

## Requirements

- Drupal 11
- PHP 8.4 or later
- Paragraphs

## Drush commands

Create a new Paragraph type:

```bash
drush orbit-paragraphs:create "Hero Banner"
```

Create a Paragraph type with an explicit machine name and description:

```bash
drush orbit-paragraphs:create "CTA" --machine-name=cta --description="Call to action paragraph."
```

## License

This module is licensed under the MIT License.
