<?php

declare(strict_types=1);

namespace Drupal\orbit_paragraphs\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\paragraphs_ee\ParagraphsCategoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drush commands for Orbit Paragraphs.
 */
final class OrbitParagraphsCommands extends DrushCommands
{

    /**
     * Constructs an OrbitParagraphsCommands object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     */
    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct();
    }

    /**
     * Creates a paragraph type.
     *
     * @param string|null $label
     *   The paragraph type label.
     * @param array<string, mixed> $options
     *   Command options.
     *
     * @return void
     *   Returns no value.
     */
    #[CLI\Command(name: 'orbit-paragraphs:create', aliases: ['opc'])]
    #[CLI\Argument(
        name: 'label',
        description: 'Paragraph type label. Prompts if omitted.',
    )]
    #[CLI\Option(
        name: 'machine-name',
        description: 'Machine name. Defaults from the label.',
    )]
    #[CLI\Option(
        name: 'description',
        description: 'Paragraph type description. Prompts if omitted.',
    )]
    #[CLI\Option(
        name: 'category',
        description: 'Paragraph category machine name. Prompts if omitted.',
    )]
    #[CLI\Usage(
        name: 'drush orbit-paragraphs:create',
        description: 'Prompt for a paragraph type label, description, and category.',
    )]
    #[CLI\Usage(
        name: 'drush orbit-paragraphs:create "Hero Banner"',
        description: 'Use the label and prompt for the description and category.',
    )]
    #[CLI\Usage(
        name: 'drush orbit-paragraphs:create "CTA" --machine-name=cta '
            . '--category=cta',
        description: 'Use the label, machine name, and category, then prompt '
            . 'for description.',
    )]
    public function createParagraphType(
        ?string $label = NULL,
        array $options = [
            'machine-name' => InputOption::VALUE_REQUIRED,
            'description' => InputOption::VALUE_OPTIONAL,
            'category' => InputOption::VALUE_OPTIONAL,
        ],
    ): void {
        $label = $label ?: $this->io()->ask(
            'Paragraph type label',
            required: TRUE,
        );
        $machine_name = $options['machine-name']
            ?: $this->machineNameFromLabel($label);
        $description = $options['description'] ?? $this->io()->ask(
            'Paragraph type description',
            default: '',
        );
        $description = $description ?? '';
        $categories = $this->loadParagraphCategories();
        $category_id = $this->resolveParagraphCategory(
            $options['category'] ?? NULL,
            $categories,
        );

        if (!preg_match('/^[a-z0-9_]+$/', $machine_name)) {
            throw new \InvalidArgumentException(
                'The machine name "' . $machine_name . '" must contain only '
                . 'lowercase letters, numbers, and underscores.',
            );
        }

        $storage = $this->entityTypeManager->getStorage('paragraphs_type');

        if ($storage->load($machine_name)) {
            throw new \InvalidArgumentException(
                'The paragraph type "' . $machine_name . '" already exists.',
            );
        }

        $paragraph_type = $storage->create([
            'id' => $machine_name,
            'label' => $label,
            'description' => $description,
            'behavior_plugins' => [],
        ]);

        if (!$paragraph_type instanceof ParagraphsTypeInterface) {
            throw new \LogicException(
                'Expected a paragraph type configuration entity.',
            );
        }

        if ($category_id !== NULL) {
            $paragraph_type->setThirdPartySetting(
                'paragraphs_ee',
                'paragraphs_categories',
                [$category_id],
            );
        }

        $paragraph_type->save();

        $message = 'Created paragraph type "' . $label . '" ('
            . $machine_name . ').';

        if ($category_id !== NULL) {
            $message = 'Created paragraph type "' . $label . '" ('
                . $machine_name . ') in category "'
                . $categories[$category_id]->label() . '".';
        }

        $this->logger()->success($message);
    }

    /**
     * Loads available paragraph categories sorted by weight and label.
     *
     * @return array<string, ParagraphsCategoryInterface>
     *   The categories keyed by machine name.
     */
    protected function loadParagraphCategories(): array {
        if (!$this->entityTypeManager->hasDefinition('paragraphs_category')) {
            return [];
        }

        $categories = $this->entityTypeManager
            ->getStorage('paragraphs_category')
            ->loadMultiple();

        uasort(
            $categories,
            static function (
                ParagraphsCategoryInterface $left,
                ParagraphsCategoryInterface $right,
            ): int {
                $weight = $left->getWeight() <=> $right->getWeight();

                if ($weight !== 0) {
                    return $weight;
                }

                return strcasecmp((string) $left->label(), (string) $right->label());
            },
        );

        return $categories;
    }

    /**
     * Resolves the paragraph category to assign to a new bundle.
     *
     * @param string|null $category_id
     *   The requested category id, if provided.
     * @param array<string, ParagraphsCategoryInterface> $categories
     *   Available categories keyed by machine name.
     *
     * @return string|null
     *   The selected category id, or NULL if no categories are available.
     */
    protected function resolveParagraphCategory(
        ?string $category_id,
        array $categories,
    ): ?string {
        if ($categories === []) {
            $this->logger()->warning(
                'No paragraph categories are available. The paragraph type will '
                . 'be created without a category tag.',
            );
            return NULL;
        }

        if ($category_id !== NULL && $category_id !== '') {
            if (!isset($categories[$category_id])) {
                throw new \InvalidArgumentException(
                    'The paragraph category "' . $category_id . '" does not exist. '
                    . 'Available categories: '
                    . implode(', ', array_keys($categories))
                    . '.',
                );
            }

            return $category_id;
        }

        $choices = [];
        foreach ($categories as $id => $category) {
            $choices[sprintf('%s (%s)', $category->label(), $id)] = $id;
        }

        $selection = $this->io()->choice(
            'Paragraph category',
            array_keys($choices),
            array_key_first($choices),
        );

        return $choices[$selection] ?? NULL;
    }

    /**
     * Generates a paragraph type machine name from a label.
     *
     * @param string $label
     *   The paragraph type label.
     *
     * @return string
     *   The generated machine name.
     */
    protected function machineNameFromLabel(string $label): string {
        $machine_name = mb_strtolower($label);
        $machine_name = preg_replace('/[^a-z0-9]+/', '_', $machine_name);
        $machine_name = trim((string) $machine_name, '_');

        if ($machine_name === '') {
            throw new \InvalidArgumentException(
                'Unable to generate a machine name from the label "' . $label
                . '". Use --machine-name to provide one.',
            );
        }

        return $machine_name;
    }

}
