<?php

namespace Drupal\sitefarm_core;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Class BlockContentGenerateDescription.
 *
 * Automatically generate a unique block description for Block Content types
 *
 * @package Drupal\sitefarm_core
 */
class BlockContentGenerateDescription {
  // Prevent errors when ajax is used to submit the form
  use DependencySerializationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;


  /**
   * BlockContentAutoGeneratedDescription constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A Database connection to use for reading and writing configuration data.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(Connection $connection, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->database = $connection;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * Create an auto-generated block description from a Title field
   *
   * @param array $form
   */
  public function createFromTitle(array &$form) {
    $config = \Drupal::config('sitefarm_core.settings');
    $autogen_title = $config->get('generate_custom_block_title');

    if ($autogen_title) {
      if (isset($form['field_sf_title'])) {
        $form['info']['widget'][0]['value']['#type'] = 'hidden';

        // Check if the block description is empty
        $default_value = $form['info']['widget'][0]['value']['#default_value'];
        if (empty($default_value)) {
          $form['#validate'][] = [$this, 'createDescription'];
          // Add a placeholder so the title will validate
          $form['info']['widget'][0]['value']['#default_value'] = 'block_title_placeholder';
        }
      }
    }
  }

  /**
   * Create a Block Description based on the Title field
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function createDescription(array &$form, FormStateInterface $form_state) {
    $entity = $form_state->getFormObject()->getEntity();

    // Get the block type bundle label
    $bundle_label = $this->entityTypeBundleInfo->getBundleInfo('block_content')[$entity->bundle()]['label'];
    // Generate a Prefix from the bundle label
    $prefix = $this->createPrefix($bundle_label);
    // Get the title field value
    $title = $form_state->getValue('field_sf_title')[0]['value'];

    // If the block description already exists, then append a suffix
    $block_description = $this->generateUnique($prefix . ': ' . $title);

    $form_state->setValue('info', [['value' => $block_description]]);
  }

  /**
   * Generate a unique description if it is already used in the database
   *
   * @param $block_description
   * @return mixed|string
   */
  public function generateUnique($block_description) {
    // If the block description already exists, then append a suffix
    $query = $this->database->select('block_content_field_data', 'bc');
    $query->addField('bc', 'info');
    $query->condition('bc.info', $block_description);

    $description_exists = $query->countQuery()->execute()->fetchField();

    if ($description_exists) {
      if (preg_match('/ \d+$/', $block_description, $numbers)) {
        $lastnum = $numbers[0];
        $lastnum++; // Add + 1
        // Replace the last number with one more incremented
        $block_description = preg_replace('/\d+$/', $lastnum, $block_description);
      } else {
        $block_description .= ' 1';
      }

      $block_description = $this->generateUnique($block_description);
    }

    return $block_description;
  }

  /**
   * Create the prefixed text which will be used as the block description
   *
   * @param $text
   * @return string
   */
  public function createPrefix($text) {
    $prefix = '';

    // Strip any special characters
    $clean_text = preg_replace('/[^0-9a-zA-Z\s]+/', '', $text);

    // Get the first character of each word
    $words = preg_split('/\s+/', $clean_text);

    foreach ($words as $word) {
      $prefix .= strtoupper($word[0]);
    }

    return $prefix;
  }

}
