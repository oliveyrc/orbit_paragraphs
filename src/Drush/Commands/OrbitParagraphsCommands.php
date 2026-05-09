<?php

declare(strict_types=1);

namespace Drupal\orbit_paragraphs\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

use function dt;

/**
 * Drush commands for Orbit Paragraphs.
 */
final class OrbitParagraphsCommands extends DrushCommands {

    /**
     * Constructs an OrbitParagraphsCommands object.
     */
    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct();
    }

    /**
     * Create a paragraph type.
     */
    #[CLI\Command(name: 'orbit-paragraphs:create', aliases: ['opc'])]
    #[CLI\Argument(name: 'label', description: 'The human-readable paragraph type label.')]
    #[CLI\Option(name: 'machine-name', description: 'The paragraph type machine name. Defaults to a generated value from the label.')]
    #[CLI\Option(name: 'description', description: 'A short description of the paragraph type.')]
    #[CLI\Usage(name: 'drush orbit-paragraphs:create "Hero Banner"', description: 'Create a Hero Banner paragraph type.')]
    #[CLI\Usage(name: 'drush orbit-paragraphs:create "CTA" --machine-name=cta --description="Call to action paragraph."', description: 'Create a paragraph type with an explicit machine name and description.')]
    public function createParagraphType(
        string $label,
        array $options = [
      'machine-name' => InputOption::VALUE_REQUIRED,
      'description' => InputOption::VALUE_OPTIONAL,
        ],
    ): void {
        $machine_name = $options['machine-name'] ?: $this->machineNameFromLabel($label);
        $description = $options['description'] ?: '';

        if (!preg_match('/^[a-z0-9_]+$/', $machine_name)) {
            throw new \InvalidArgumentException(
                dt(
                    'The machine name "@machine_name" must contain only lowercase letters, numbers, and underscores.', [
                    '@machine_name' => $machine_name,
                    ]
                )
            );
        }

        $storage = $this->entityTypeManager->getStorage('paragraphs_type');

        if ($storage->load($machine_name)) {
            throw new \InvalidArgumentException(
                dt(
                    'The paragraph type "@machine_name" already exists.', [
                    '@machine_name' => $machine_name,
                    ]
                )
            );
        }

        $paragraph_type = $storage->create(
            [
            'id' => $machine_name,
            'label' => $label,
            'description' => $description,
            'behavior_plugins' => [],
            ]
        );
        $paragraph_type->save();

        $this->logger()->success(
            dt(
                'Created paragraph type "@label" (@machine_name).', [
                '@label' => $label,
                '@machine_name' => $machine_name,
                ]
            )
        );
    }

    /**
     * Generates a paragraph type machine name from a label.
     */
    protected function machineNameFromLabel(string $label): string {
        $machine_name = mb_strtolower($label);
        $machine_name = preg_replace('/[^a-z0-9]+/', '_', $machine_name);
        $machine_name = trim((string) $machine_name, '_');

        if ($machine_name === '') {
            throw new \InvalidArgumentException(
                dt(
                    'Unable to generate a machine name from the label "@label". Use --machine-name to provide one.', [
                    '@label' => $label,
                    ]
                )
            );
        }

    return $machine_name;
    }

}
