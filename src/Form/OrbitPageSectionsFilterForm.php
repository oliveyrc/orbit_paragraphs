<?php

declare(strict_types=1);

namespace Drupal\orbit_paragraphs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Filter form for the page sections report.
 */
final class OrbitPageSectionsFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'orbit_paragraphs_page_sections_filter_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, string> $category_options
   *   Available category options keyed by category ID.
   * @param string $selected_category
   *   Current selected category ID.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    array $category_options = [],
    string $selected_category = '',
  ): array {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('orbit_paragraphs.sections_overview')->toString();

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => $selected_category !== '',
    ];

    $form['filters']['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $category_options,
      '#default_value' => $selected_category,
    ];

    $form['filters']['actions'] = ['#type' => 'actions'];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
    ];

    $form['filters']['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('orbit_paragraphs.sections_overview'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
