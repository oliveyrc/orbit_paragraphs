<?php

declare(strict_types=1);

namespace Drupal\orbit_paragraphs\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\orbit_paragraphs\Form\OrbitPageSectionsFilterForm;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\paragraphs_ee\ParagraphsCategoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for browsing Orbit page sections.
 */
final class OrbitPageSectionsController extends ControllerBase {

  /**
   * Creates a new OrbitPageSectionsController instance.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected DateFormatterInterface $dateFormatter,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('file_url_generator'),
      $container->get('request_stack'),
    );
  }

  /**
   * Builds the page sections overview page.
   */
  public function overview(): array {
    $bundles = $this->entityTypeManagerService->getStorage('paragraphs_type')->loadMultiple();
    $categories = $this->loadParagraphCategories();
    $used_category_ids = [];
    $has_uncategorized = FALSE;

    foreach ($bundles as $bundle) {
      if (!$bundle instanceof ParagraphsTypeInterface) {
        continue;
      }

      $bundle_category_ids = $this->bundleCategoryIds($bundle);
      if ($bundle_category_ids === []) {
        $has_uncategorized = TRUE;
        continue;
      }

      foreach ($bundle_category_ids as $bundle_category_id) {
        if (isset($categories[$bundle_category_id])) {
          $used_category_ids[$bundle_category_id] = TRUE;
        }
        else {
          $has_uncategorized = TRUE;
        }
      }
    }

    $category_options = ['' => (string) $this->t('All categories')];
    foreach ($categories as $category_id => $category) {
      if (!isset($used_category_ids[$category_id])) {
        continue;
      }

      $category_options[$category_id] = (string) $category->label();
    }
    if ($has_uncategorized) {
      $category_options['__none'] = (string) $this->t('Uncategorized');
    }

    $selected_category = (string) $this->requestStack->getCurrentRequest()->query->get('category', '');
    if (!isset($category_options[$selected_category])) {
      $selected_category = '';
    }

    uasort(
      $bundles,
      static fn(ParagraphsTypeInterface $left, ParagraphsTypeInterface $right): int => strcasecmp(
        (string) $left->label(),
        (string) $right->label(),
      ),
    );

    $build = [
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
          'orbit_paragraphs/sections_overview',
        ],
      ],
      'filters' => $this->formBuilder()->getForm(
        OrbitPageSectionsFilterForm::class,
        $category_options,
        $selected_category,
      ),
    ];

    if ($bundles === []) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'text' => ['#plain_text' => (string) $this->t('No page sections were found.')],
      ];
      return $build;
    }

    $bundles_by_category = [];
    foreach (array_keys($category_options) as $category_id) {
      if ($category_id === '') {
        continue;
      }
      $bundles_by_category[$category_id] = [];
    }

    foreach ($bundles as $bundle) {
      $bundle_category_ids = $this->bundleCategoryIds($bundle);
      $bundle_group_ids = $bundle_category_ids !== [] ? $bundle_category_ids : ['__none'];

      foreach ($bundle_group_ids as $group_id) {
        if (!array_key_exists($group_id, $bundles_by_category)) {
          $group_id = '__none';

          if (!isset($bundles_by_category[$group_id])) {
            $bundles_by_category[$group_id] = [];
          }

          if (!isset($category_options[$group_id])) {
            $category_options[$group_id] = (string) $this->t('Uncategorized');
          }
        }

        if ($selected_category !== '' && $group_id !== $selected_category) {
          continue;
        }

        $bundles_by_category[$group_id][] = $bundle;
      }
    }

    $has_results = FALSE;
    $build['groups'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['orbit-paragraphs-sections-groups']],
    ];

    foreach ($bundles_by_category as $category_id => $group_bundles) {
      if ($group_bundles === []) {
        continue;
      }

      $has_results = TRUE;
      $group_label = $category_options[$category_id] ?? (string) $this->t('Uncategorized');
      $group_key = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower($category_id));
      $group_key = trim((string) $group_key, '_');
      $group_key = $group_key !== '' ? $group_key : 'group';

      $build['groups'][$group_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-group']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['orbit-paragraphs-sections-group__title']],
          '#value' => (string) $this->formatPlural(
            count($group_bundles),
            '@category (1 section)',
            '@category (@count sections)',
            ['@category' => $group_label],
          ),
        ],
        'grid' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['orbit-paragraphs-sections-grid']],
        ],
      ];

      foreach ($group_bundles as $bundle) {
        if (!$bundle instanceof ParagraphsTypeInterface) {
          continue;
        }

        $bundle_id = $bundle->id();
        $card_key = $bundle_id . '__' . $group_key;
        $build['groups'][$group_key]['grid'][$card_key] = $this->buildSectionCard($bundle);
      }
    }

    if (!$has_results) {
      $build['empty_filtered'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'text' => ['#plain_text' => (string) $this->t('No page sections found for the selected category.')],
      ];
    }

    return $build;
  }

  /**
   * Builds one section card for the overview page.
   */
  protected function buildSectionCard(ParagraphsTypeInterface $bundle): array {
    $bundle_id = $bundle->id();
    $bundle_label = (string) $bundle->label();
    $bundle_description = trim((string) $bundle->getDescription());
    $icon_value = trim((string) $bundle->get('icon_default'));
    $icon_image_source = $this->resolveIconImageSource($bundle, $icon_value);
    $usage_count = count($this->loadNodesForBundle($bundle_id));

    $usage_link = NULL;
    if ($usage_count > 0) {
      $usage_link = Link::fromTextAndUrl(
        $this->t('View pages'),
        Url::fromRoute('orbit_paragraphs.section_usage_modal', ['bundle' => $bundle_id]),
      )->toRenderable();
      $usage_link['#attributes'] = [
        'class' => ['use-ajax', 'orbit-paragraphs-sections-card__link'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode(['width' => 900], JSON_THROW_ON_ERROR),
      ];
    }

    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['orbit-paragraphs-sections-card']],
      'icon' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'orbit-paragraphs-sections-card__icon',
            $icon_image_source !== NULL
              ? 'orbit-paragraphs-sections-card__icon--image'
              : 'orbit-paragraphs-sections-card__icon--fallback',
          ],
        ],
        'content' => $this->buildIconRender(
          $icon_image_source,
          $icon_value,
          $bundle_label,
        ),
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-card__title']],
        '#value' => $bundle_label,
      ],
      'usage_count' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-card__count']],
        '#value' => (string) $this->formatPlural(
          $usage_count,
          '1 page',
          '@count pages',
        ),
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-card__description']],
        '#value' => $bundle_description !== '' ? $bundle_description : (string) $this->t('No description provided.'),
      ],
      'footer' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-card__footer']],
        'usage_link' => $usage_link,
      ],
    ];

    if ($usage_link === NULL) {
      $card['footer']['unused'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-card__unused']],
        '#value' => (string) $this->t('Not used yet'),
      ];
      unset($card['footer']['usage_link']);
    }

    return $card;
  }

  /**
   * Loads available paragraph categories sorted by weight and label.
   *
   * @return array<string, ParagraphsCategoryInterface>
   *   Paragraph categories keyed by machine name.
   */
  protected function loadParagraphCategories(): array {
    if (!$this->entityTypeManagerService->hasDefinition('paragraphs_category')) {
      return [];
    }

    $categories = $this->entityTypeManagerService
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
   * Gets category IDs configured on a paragraph bundle.
   *
   * @return string[]
   *   Category IDs on the bundle.
   */
  protected function bundleCategoryIds(ParagraphsTypeInterface $bundle): array {
    $category_ids = $bundle->getThirdPartySetting('paragraphs_ee', 'paragraphs_categories', []);
    if (!is_array($category_ids)) {
      return [];
    }

    $category_ids = array_values(
      array_filter(
        array_map('strval', $category_ids),
        static fn(string $category_id): bool => $category_id !== '',
      ),
    );

    return array_values(array_unique($category_ids));
  }

  /**
   * Builds modal content showing the latest pages using a section bundle.
   */
  public function usageModal(string $bundle): array|AjaxResponse {
    $bundle_entity = $this->entityTypeManagerService->getStorage('paragraphs_type')->load($bundle);
    if (!$bundle_entity instanceof ParagraphsTypeInterface) {
      throw new NotFoundHttpException();
    }

    $usage_items = $this->loadNodeUsageForBundle($bundle, 10, 300);

    $build = [
      '#attached' => [
        'library' => ['orbit_paragraphs/sections_overview'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-modal__intro']],
        '#value' => (string) $this->t('Latest pages where <strong>@section</strong> is used.', ['@section' => $bundle_entity->label()]),
      ],
    ];

    if ($usage_items === []) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => ['#plain_text' => (string) $this->t('No pages currently use this section.')],
      ];
    }
    else {
      $rows = [];
      foreach ($usage_items as $usage_item) {
        $node = $usage_item['node'];
        $paragraph_id = (int) $usage_item['paragraph_id'];
        $page_url = $node->toUrl();
        $page_url->setOption('fragment', 'paragraph-' . $paragraph_id);
        $link = Link::fromTextAndUrl($node->label(), $page_url)->toRenderable();
        $link['#attributes']['target'] = '_blank';
        $link['#attributes']['rel'] = 'noopener noreferrer';
        $rows[] = [
          ['data' => $link],
          $node->bundle(),
          $this->dateFormatter->format($node->getChangedTime(), 'short'),
        ];
      }

      $build['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Page'),
          $this->t('Content type'),
          $this->t('Updated'),
        ],
        '#rows' => $rows,
        '#attributes' => ['class' => ['orbit-paragraphs-sections-modal__table']],
      ];
    }

    if ($this->requestStack->getCurrentRequest()->query->get('_wrapper_format') === 'drupal_modal') {
      $response = new AjaxResponse();
      $response->addCommand(new OpenModalDialogCommand(
        $this->usageTitle($bundle),
        $build,
        ['width' => 900],
      ));
      return $response;
    }

    return $build;
  }

  /**
   * Builds the modal title for a bundle usage list.
   */
  public function usageTitle(string $bundle): string {
    $bundle_entity = $this->entityTypeManagerService->getStorage('paragraphs_type')->load($bundle);
    if (!$bundle_entity instanceof ParagraphsTypeInterface) {
      return (string) $this->t('Section usage');
    }

    return (string) $this->t('Used on pages: @section', ['@section' => $bundle_entity->label()]);
  }

  /**
   * Loads latest nodes that reference paragraph items from a section bundle.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Nodes keyed by node ID in descending paragraph change order.
   */
  protected function loadNodesForBundle(
    string $bundle,
    ?int $node_limit = NULL,
    int $paragraph_limit = 0,
  ): array {
    $usage_items = $this->loadNodeUsageForBundle($bundle, $node_limit, $paragraph_limit);

    $nodes = [];
    foreach ($usage_items as $node_id => $usage_item) {
      $nodes[$node_id] = $usage_item['node'];
    }

    return $nodes;
  }

  /**
   * Loads latest node usage details for a section bundle.
   *
   * @return array<int, array{node: \Drupal\node\NodeInterface, paragraph_id: int}>
   *   Usage keyed by node ID in descending paragraph change order.
   */
  protected function loadNodeUsageForBundle(
    string $bundle,
    ?int $node_limit = NULL,
    int $paragraph_limit = 0,
  ): array {
    $paragraph_query = $this->entityTypeManagerService->getStorage('paragraph')->getQuery()
      ->condition('type', $bundle)
      ->sort('id', 'DESC')
      ->accessCheck(TRUE);

    if ($paragraph_limit > 0) {
      $paragraph_query->range(0, $paragraph_limit);
    }

    $paragraph_ids = $paragraph_query->execute();

    if ($paragraph_ids === []) {
      return [];
    }

    $paragraphs = $this->entityTypeManagerService->getStorage('paragraph')->loadMultiple($paragraph_ids);

    $usage_items = [];
    foreach ($paragraphs as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }

      $parent = $paragraph->getParentEntity();
      $depth = 0;
      while ($parent instanceof ParagraphInterface && $depth < 20) {
        $parent = $parent->getParentEntity();
        $depth++;
      }

      if (!$parent instanceof NodeInterface) {
        continue;
      }

      if (!$parent->access('view')) {
        continue;
      }

      $node_id = (int) $parent->id();
      if (!isset($usage_items[$node_id])) {
        $usage_items[$node_id] = [
          'node' => $parent,
          'paragraph_id' => (int) $paragraph->id(),
        ];
      }

      if ($node_limit !== NULL && count($usage_items) >= $node_limit) {
        break;
      }
    }

    return $usage_items;
  }

  /**
   * Builds icon render output for a section card.
   */
  protected function buildIconRender(
    ?string $icon_image_source,
    string $icon,
    string $label,
  ): array {
    if ($icon_image_source !== NULL) {
      $safe_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      $safe_icon = htmlspecialchars($icon_image_source, ENT_QUOTES, 'UTF-8');

      return [
        '#markup' => '<img class="orbit-paragraphs-sections-card__icon-image" src="'
          . $safe_icon . '" alt="' . $safe_label . '">',
      ];
    }

    if ($icon !== '' && preg_match('/^[A-Za-z0-9 _-]+$/', $icon) === 1 && str_contains($icon, ' ')) {
      return [
        '#markup' => '<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>',
      ];
    }

    if ($icon !== '') {
      return ['#plain_text' => $icon];
    }

    $fallback = mb_strtoupper(mb_substr($label, 0, 1));
    return ['#plain_text' => $fallback !== '' ? $fallback : 'S'];
  }

  /**
   * Resolves the best image source for a paragraph section icon.
   */
  protected function resolveIconImageSource(
    ParagraphsTypeInterface $bundle,
    string $icon_default,
  ): ?string {
    $icon_uuid = trim((string) $bundle->get('icon_uuid'));
    if ($icon_uuid !== '') {
      $files = $this->entityTypeManagerService
        ->getStorage('file')
        ->loadByProperties(['uuid' => $icon_uuid]);
      $file = reset($files);

      if ($file instanceof FileInterface) {
        return $this->fileUrlGenerator->generateString($file->getFileUri());
      }
    }

    if ($this->isDataImageUri($icon_default)) {
      return $icon_default;
    }

    if (
      $icon_default !== ''
      && (str_starts_with($icon_default, '/')
      || str_starts_with($icon_default, 'http://')
      || str_starts_with($icon_default, 'https://'))
    ) {
      return $icon_default;
    }

    return NULL;
  }

  /**
   * Checks whether an icon value is a data URI image.
   */
  protected function isDataImageUri(string $icon): bool {
    if ($icon === '') {
      return FALSE;
    }

    return preg_match(
      '/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\r\n]+$/',
      $icon,
    ) === 1;
  }

}
