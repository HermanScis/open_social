<?php

namespace Drupal\social_activity\Plugin\ActivityEntityCondition;

use Drupal\activity_creator\Plugin\ActivityEntityConditionBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\social_group\CrossPostingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function MongoDB\Driver\Monitoring\removeSubscriber;

/**
 * Provides a 'GroupContentFirstActivityEntityCondition' activity condition.
 *
 * @ActivityEntityCondition(
 *  id = "group_content_node_single_group",
 *  label = @Translation("Node exists in single group"),
 *  entities = {"group_content" = {}}
 * )
 */
class GroupContentSingleActivityEntityCondition extends ActivityEntityConditionBase implements ContainerFactoryPluginInterface {

  /**
   * The cross-posting service.
   *
   * @var \Drupal\social_group\CrossPostingService
   */
  protected $crossPostingService;

  /**
   * Constructs a GroupContentMultipleActivityEntityCondition object.
   *
   * @param \Drupal\social_group\CrossPostingService $cross_posting_service
   *   The group content enabler manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CrossPostingService $cross_posting_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->crossPostingService = $cross_posting_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('social_group.cross_posting')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isValidEntityCondition($entity) {
    if ($entity->getEntityTypeId() === 'group_content') {
      // If node is added only to one group then condition is valid.
      if (!$this->crossPostingService->nodeExistsInMultipleGroups($entity)) {
        return TRUE;
      }
    }

    return FALSE;
  }
}
