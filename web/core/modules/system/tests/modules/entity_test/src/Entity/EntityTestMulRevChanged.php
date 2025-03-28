<?php

declare(strict_types=1);

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\EntityTestAccessControlHandler;
use Drupal\entity_test\EntityTestDeleteForm;
use Drupal\entity_test\EntityTestForm;
use Drupal\entity_test\EntityTestViewBuilder as TestViewBuilder;
use Drupal\views\EntityViewsData;

/**
 * Defines the test entity class.
 */
#[ContentEntityType(
  id: 'entity_test_mulrev_changed',
  label: new TranslatableMarkup('Test entity - mul changed revisions and data table'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'bundle' => 'type',
    'revision' => 'revision_id',
    'label' => 'name',
    'langcode' => 'langcode',
  ],
  handlers: [
    'view_builder' => TestViewBuilder::class,
    'access' => EntityTestAccessControlHandler::class,
    'form' => [
      'default' => EntityTestForm::class,
      'delete' => EntityTestDeleteForm::class,
    ],
    'route_provider' => [
      'html' => DefaultHtmlRouteProvider::class,
    ],
    'views_data' => EntityViewsData::class,
  ],
  links: [
    'add-form' => '/entity_test_mulrev_changed/add',
    'canonical' => '/entity_test_mulrev_changed/manage/{entity_test_mulrev_changed}',
    'delete-form' => '/entity_test/delete/entity_test_mulrev_changed/{entity_test_mulrev_changed}',
    'edit-form' => '/entity_test_mulrev_changed/manage/{entity_test_mulrev_changed}/edit',
    'revision' => '/entity_test_mulrev_changed/{entity_test_mulrev_changed}/revision/{entity_test_mulrev_changed_revision}/view',
  ],
  base_table: 'entity_test_mulrev_changed',
  data_table: 'entity_test_mulrev_changed_property',
  revision_table: 'entity_test_mulrev_changed_revision',
  revision_data_table: 'entity_test_mulrev_changed_property_revision',
  translatable: TRUE,
)]
class EntityTestMulRevChanged extends EntityTestMulChanged {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The version id of the test entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['langcode']->setRevisionable(TRUE);
    $fields['name']->setRevisionable(TRUE);
    $fields['user_id']->setRevisionable(TRUE);
    $fields['changed']->setRevisionable(TRUE);
    $fields['not_translatable']->setRevisionable(TRUE);

    return $fields;
  }

}
