<?php

namespace Drupal\bbb_node\Plugin\Block;

use BigBlueButton\Parameters\GetMeetingInfoParameters;
use Drupal\bbb\Service\Api;
use Drupal\bbb_node\Service\NodeMeeting;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a "BBB Meeting details" block.
 *
 * @Block(
 *   id = "bbb_node_login_meeting",
 *   admin_label = @Translation("BBB Meeting Details")
 * )
 */
class BBBLoginMeeting extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Node based Meeting API.
   *
   * @var \Drupal\bbb_node\Service\NodeMeeting
   */
  protected $nodeMeeting;

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('bbb_node.meeting'),
      $container->get('bbb.api')
    );
  }

  /**
   * BBBLoginMeeting constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $route_match
   *   Current route match service.
   * @param \Drupal\bbb_node\Service\NodeMeeting $node_meeting
   *   Node based Meetings API.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ResettableStackedRouteMatchInterface $route_match, NodeMeeting $node_meeting, Api $api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->nodeMeeting = $node_meeting;
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $node = $this->routeMatch->getParameter('node');
    if ($node == null) {
      return AccessResult::forbidden();
    }
    $meeting = $this->nodeMeeting->get($node);
    if (!($node && $this->nodeMeeting->isTypeOf($node)) || count($meeting) == 0) {
      return AccessResult::forbidden();
    }
    return parent::blockAccess($account);
  }

  protected function status($node) {
    $meeting = $this->nodeMeeting->get($node);
    if (count($meeting) == 0){
      return null;
    }
    $status = $this->api->getMeetingInfo(new GetMeetingInfoParameters($meeting['created']->getMeetingId(), $meeting['created']->getModeratorPassword()));
    if ($status && property_exists($status, 'isRunning') && $status->isRunning()) {
      return 'open';
    }
    else {
      return 'closed';
    }
  }
  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    $meeting = $this->nodeMeeting->get($node);

    $record = $meeting->record ? true : false;
    return [
      '#theme' => 'bbb_meeting_status',
      '#meeting' => _bbb_node_get_links($node, $record),
      '#status' => $this->status($node),
      '#cache' => ['max-age' => 0,],
      '#attributes' => ['id' => 'meeting_status', 'class' => 'meeting_status'],
      '#allowed_tags' => ['span'],
    ];
  }

}
