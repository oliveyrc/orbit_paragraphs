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
        description: 'Paragraph category IDs (comma-separated). Prompts if omitted.',
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
    #[CLI\Usage(
        name: 'drush orbit-paragraphs:create "Feature" --category=text,media',
        description: 'Assign multiple categories using a comma-separated list.',
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
        $category_ids = $this->resolveParagraphCategories(
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

        if ($category_ids !== []) {
            $paragraph_type->setThirdPartySetting(
                'paragraphs_ee',
                'paragraphs_categories',
                $category_ids,
            );
        }

        $paragraph_type->save();
        $this->createParagraphFormDisplayTabs($machine_name);

        $message = 'Created paragraph type "' . $label . '" ('
            . $machine_name . ').';

        if ($category_ids !== []) {
            $category_labels = [];
            foreach ($category_ids as $category_id) {
                $category_labels[] = (string) $categories[$category_id]->label();
            }

            $message = 'Created paragraph type "' . $label . '" ('
                . $machine_name . ') in categories: '
                . implode(', ', $category_labels)
                . '.';
        }

        $this->logger()->success($message);
    }

    /**
     * Creates default Field Group tabs on paragraph form display.
     *
     * @param string $bundle
     *   The paragraph bundle machine name.
     */
    protected function createParagraphFormDisplayTabs(string $bundle): void {
        $form_display_storage = $this->entityTypeManager->getStorage('entity_form_display');
        $form_display_id = 'paragraph.' . $bundle . '.default';
        $form_display = $form_display_storage->load($form_display_id);

        if ($form_display === NULL) {
            $form_display = $form_display_storage->create([
                'id' => $form_display_id,
                'targetEntityType' => 'paragraph',
                'bundle' => $bundle,
                'mode' => 'default',
                'status' => TRUE,
            ]);
        }

        $third_party_settings = (array) $form_display->get('third_party_settings');
        $field_group_settings = (array) ($third_party_settings['field_group'] ?? []);
        $changed = FALSE;

        foreach ($this->defaultFieldGroups() as $group_name => $group_definition) {
            if (isset($field_group_settings[$group_name])) {
                continue;
            }

            $field_group_settings[$group_name] = $group_definition;
            $changed = TRUE;
        }

        if ($changed) {
            $third_party_settings['field_group'] = $field_group_settings;
            $form_display->set('third_party_settings', $third_party_settings);
        }

        $form_display->save();
    }

    /**
     * Returns default field group definitions for paragraph form display.
     *
     * @return array<string, array<string, mixed>>
     *   Field group definitions keyed by group machine name.
     */
    protected function defaultFieldGroups(): array {
        return [
            'group_tabs' => [
                'children' => [
                    'group_content',
                    'group_settings',
                ],
                'label' => 'Tabs',
                'region' => 'content',
                'parent_name' => '',
                'weight' => 0,
                'format_type' => 'tabs',
                'format_settings' => [
                    'id' => '',
                    'classes' => '',
                    'direction' => 'horizontal',
                    'width_breakpoint' => 640,
                    'formatter' => 'closed',
                    'description' => '',
                    'required_fields' => TRUE,
                ],
            ],
            'group_content' => [
                'children' => [],
                'label' => 'Content',
                'region' => 'content',
                'parent_name' => 'group_tabs',
                'weight' => 0,
                'format_type' => 'tab',
                'format_settings' => [
                    'id' => '',
                    'classes' => '',
                    'formatter' => 'open',
                    'description' => '',
                    'required_fields' => TRUE,
                ],
            ],
            'group_settings' => [
                'children' => [],
                'label' => 'Settings',
                'region' => 'content',
                'parent_name' => 'group_tabs',
                'weight' => 1,
                'format_type' => 'tab',
                'format_settings' => [
                    'id' => '',
                    'classes' => '',
                    'formatter' => 'closed',
                    'description' => '',
                    'required_fields' => TRUE,
                ],
            ],
        ];
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
     * Resolves paragraph categories to assign to a new bundle.
     *
     * @param string|null $category_id
     *   The requested category IDs as a comma-separated string, if provided.
     * @param array<string, ParagraphsCategoryInterface> $categories
     *   Available categories keyed by machine name.
     *
     * @return string[]
     *   Selected category IDs, or an empty array when none are available.
     */
    protected function resolveParagraphCategories(
        ?string $category_id,
        array $categories,
    ): array {
        if ($categories === []) {
            $this->logger()->warning(
                'No paragraph categories are available. The paragraph type will '
                . 'be created without a category tag.',
            );
            return [];
        }

        if ($category_id !== NULL && $category_id !== '') {
            $requested_categories = array_values(
                array_filter(
                    array_map('trim', explode(',', $category_id)),
                    static fn(string $value): bool => $value !== '',
                ),
            );

            if ($requested_categories === []) {
                return [];
            }

            $requested_categories = array_values(array_unique($requested_categories));

            $missing_categories = [];
            foreach ($requested_categories as $requested_category) {
                if (!isset($categories[$requested_category])) {
                    $missing_categories[] = $requested_category;
                }
            }

            if ($missing_categories !== []) {
                throw new \InvalidArgumentException(
                    'The paragraph categories "' . implode(', ', $missing_categories)
                    . '" do not exist. '
                    . 'Available categories: '
                    . implode(', ', array_keys($categories))
                    . '.',
                );
            }

            return $requested_categories;
        }

        $choices = [];
        foreach ($categories as $id => $category) {
            $choices[sprintf('%s (%s)', $category->label(), $id)] = $id;
        }

        $selection = $this->io()->choice(
            'Paragraph categories (use space to select multiple)',
            array_keys($choices),
            array_key_first($choices) !== NULL ? [array_key_first($choices)] : [],
            TRUE,
        );

        if (!is_array($selection)) {
            return [];
        }

        $selected_categories = [];
        foreach ($selection as $selected_label) {
            if (isset($choices[$selected_label])) {
                $selected_categories[] = $choices[$selected_label];
            }
        }

        return array_values(array_unique($selected_categories));
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
