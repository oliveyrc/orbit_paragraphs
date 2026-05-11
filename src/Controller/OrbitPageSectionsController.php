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
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
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
    ];

    if ($bundles === []) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'text' => ['#plain_text' => (string) $this->t('No page sections were found.')],
      ];
      return $build;
    }

    $build['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['orbit-paragraphs-sections-grid']],
    ];

    foreach ($bundles as $bundle) {
      $bundle_id = $bundle->id();
      $bundle_label = (string) $bundle->label();
      $bundle_description = trim((string) $bundle->getDescription());
      $icon_value = trim((string) $bundle->get('icon_default'));
      $icon_image_source = $this->resolveIconImageSource($bundle, $icon_value);
      $usage_count = count($this->loadNodesForBundle($bundle_id));

      $usage_link = NULL;
      if ($usage_count > 0) {
        $usage_link = Link::fromTextAndUrl(
          $this->t('View latest 10 pages'),
          Url::fromRoute('orbit_paragraphs.section_usage_modal', ['bundle' => $bundle_id]),
        )->toRenderable();
        $usage_link['#attributes'] = [
          'class' => ['use-ajax', 'orbit-paragraphs-sections-card__link'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 900], JSON_THROW_ON_ERROR),
        ];
      }

      $build['grid'][$bundle_id] = [
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
        $build['grid'][$bundle_id]['footer']['unused'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['orbit-paragraphs-sections-card__unused']],
          '#value' => (string) $this->t('Not used yet'),
        ];
        unset($build['grid'][$bundle_id]['footer']['usage_link']);
      }
    }

    return $build;
  }

  /**
   * Builds modal content showing the latest pages using a section bundle.
   */
  public function usageModal(string $bundle): array|AjaxResponse {
    $bundle_entity = $this->entityTypeManagerService->getStorage('paragraphs_type')->load($bundle);
    if (!$bundle_entity instanceof ParagraphsTypeInterface) {
      throw new NotFoundHttpException();
    }

    $nodes = $this->loadNodesForBundle($bundle, 10, 300);

    $build = [
      '#attached' => [
        'library' => ['orbit_paragraphs/sections_overview'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['orbit-paragraphs-sections-modal__intro']],
        '#value' => (string) $this->t('Latest pages where @section is used.', ['@section' => $bundle_entity->label()]),
      ],
    ];

    if ($nodes === []) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => ['#plain_text' => (string) $this->t('No pages currently use this section.')],
      ];
    }
    else {
      $rows = [];
      foreach ($nodes as $node) {
        $rows[] = [
          Link::fromTextAndUrl($node->label(), $node->toUrl())->toRenderable(),
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

    $nodes = [];
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
      if (!isset($nodes[$node_id])) {
        $nodes[$node_id] = $parent;
      }

      if ($node_limit !== NULL && count($nodes) >= $node_limit) {
        break;
      }
    }

    return $nodes;
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
