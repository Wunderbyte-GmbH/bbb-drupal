<?php

namespace Drupal\bbb_node\Access;

use Drupal\bbb_node\Service\NodeMeeting;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Class BBBMeetingAttendeeAccessCheck.
 *
 * @package Drupal\bbb\Access
 */
class BBBMeetingAttendeeAccessCheck implements AccessInterface {

  /**
   * Node based Meeting API.
   *
   * @var \Drupal\bbb_node\Service\NodeMeeting
   */
  protected $nodeMeeting;

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a BBBMeetingModeratorAccessCheck object.
   *
   * @param \Drupal\bbb_node\Service\NodeMeeting $node_meeting
   *   Node based Meeting API.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(NodeMeeting $node_meeting, EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeMeeting = $node_meeting;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    $node = $route_match->getParameter('node');

    if (!$node instanceof NodeInterface) {
      $node = $this->nodeStorage->load($node);
    }
    if (!$this->nodeMeeting->isTypeOf($node)) {
      return AccessResult::forbidden();
    }

    if ($account->hasPermission('bbb_node attend meetings') || $account->hasPermission('administer big blue button')) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

}
