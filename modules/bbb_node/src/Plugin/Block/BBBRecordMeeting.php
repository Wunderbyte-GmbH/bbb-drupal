<?php

namespace Drupal\bbb_node\Plugin\Block;

use BigBlueButton\Parameters\GetRecordingsParameters;
use Drupal\bbb\Service\Api;
use Drupal\bbb_node\Service\NodeMeeting;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a "BBB Meeting recorde´s" block.
 *
 * @Block(
 *   id = "bbb_node_record_meeting",
 *   admin_label = @Translation("BBB Meeting records")
 * )
 */
class BBBRecordMeeting extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Api wrapper.
   *
   * @var \Drupal\bbb\Service\Api
   */
  protected $api;

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
    $records = $this->get_records($node);
    $count = count($records['#items']);
    if (!($node && $this->nodeMeeting->isTypeOf($node)) || $count == 0) {
      return AccessResult::forbidden();
    }
    return parent::blockAccess($account);
  }

  protected function get_records($node) {
    $meeting = $this->nodeMeeting->get($node);
    if (count($meeting) == 0){
      return ['#items' => []];
    }
    $recording_parameters = new GetRecordingsParameters();
    $recording_parameters->setMeetingId($meeting['created']->getMeetingId());
    $recording = $this->api->getRecordings($recording_parameters);
    $links = [
      '#theme' => 'item_list__rec',
      '#list_type' => 'ul',
      '#items' => [],
      '#attributes' => ['class' => 'bbb_records'],
      '#wrapper_attributes' => ['class' => 'container'],
      '#cache' => ['max-age' => 0,],
    ];
    $timezone = drupal_get_user_timezone();
    foreach ($recording as $key => $record) {
      $start = $recording[$key]->getStartTime();
      $end = $recording[$key]->getEndTime();
      $name = $recording[$key]->getName();
      $playbackurl = $recording[$key]->getPlaybackUrl();
      $recordID = $recording[$key]->getRecordId();
      $url = Url::fromUri($playbackurl);
      $formatted_date = \Drupal::service('date.formatter')->format($start/1000, 'short');
      $min = ($end -$start) / 60000;
      if ($min >= 1) {
        $duration = (int) $min . t( ' min.');
      }
      else {
        $duration = (int) ($min * 60) . t(' sec.');
      }
      $link = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $name,
        '#description' => $formatted_date . " " . $duration
      ];
      $link['#attributes']['target'] = '_blank';
      $links['#items'][] = $link ;//. ": " . $formatted_date . " " . $duration;
    }
    return $links;
  }

  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    dd($node);//exit();
    $records = $this->get_records($node);
    return [
      '#theme' => 'bbb_meeting_record',
      '#records' => $records,
      '#cache' => ['max-age' => 0,],
    ];
  }

}
