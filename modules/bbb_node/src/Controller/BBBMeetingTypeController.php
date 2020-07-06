<?php

namespace Drupal\bbb_node\Controller;

use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\GetMeetingInfoParameters;
use Drupal\bbb\Service\Api;
use Drupal\bbb_node\Service\NodeMeeting;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BBBMeetingTypeController.
 *
 * @package Drupal\bbb\Controller
 */
class BBBMeetingTypeController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Node based Meeting api.
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
   * Module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('bbb.api'),
      $container->get('bbb_node.meeting'),
      $container->get('config.factory')

    );
  }

  /**
   * BBBMeetingTypeController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\bbb\Service\Api $api
   * @param \Drupal\bbb_node\Service\NodeMeeting $node_meeting
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    Api $api,
    NodeMeeting $node_meeting,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->api = $api;
    $this->nodeMeeting = $node_meeting;
    $this->config = $config_factory->get('bbb_node.settings');

    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Redirect to big blue button instance; Menu callback
   *
   * @param \Drupal\node\NodeInterface|int $node
   *   A Drupal node Interface
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   Render array.
   */
  public function attend(NodeInterface $node) {
    $node_type = $node->getType();
    $meeting_settings = $this->entityTypeManager->getStorage('bbb_node_type')
      ->load($node_type);
    $mode = 'attend';
    $meeting = $this->nodeMeeting->get($node, \Drupal::currentUser());
    if (empty($meeting['info'])) {
      $params = new CreateMeetingParameters($node->uuid(), $node->getTitle());
      $this->nodeMeeting->create($node, $params);
      $this->nodeMeeting->update($node, $params);
      $meeting = $this->nodeMeeting->get($node, $this->currentUser());
    }
    $status = $this->api->getMeetingInfo(new GetMeetingInfoParameters($meeting['created']->getMeetingId(), $meeting['created']->getModeratorPassword()));
    if ($status && property_exists($status, 'hasBeenForciblyEnded') && $status->hasBeenForciblyEnded() == 'true') {
      $this->messenger->addWarning('The meeting has been terminated and is not available for attending.');
      return new RedirectResponse(Url::fromRoute('bbb_node.meeting.closed', ['node' => $node->id()], ['absolute' => TRUE]));
    }

//    drupal_set_title($node->getTitle());
    if ($status && property_exists($status, 'isRunning') && $status->isRunning()) {
      if ($this->getDisplayMode() === 'blank') {
        $this->attendRedirect($node, $mode);
      }
    }
    else {
      if ($meeting_settings->get('moderatorRequired')) {
        $this->messenger->addStatus($this->t('You signed up for this meeting. Please stay on this page, you will be redirected immediately after the meeting has started.'));
        $render = $this->entityTypeManager->getViewBuilder('node')->view($node);
        $render['#attached']['library'] = 'bbb_node/check_status';
        $url = Url::fromRoute('bbb_node.meeting.end_status', ['node' => $node->id()]);
        $render['#attached']['drupalSettings']['bbb']['check_status']['url'] = $url->toString();
        return $render;
      }
      else {
        if (empty($meeting->initialized)) {
          if ($data = $this->nodeMeeting->create($node, $meeting['created'])) {
            // Update local data.
            $this->nodeMeeting->update($node, $meeting['created']);
          }
        }
        if ($this->getDisplayMode() == 'blank') {
          return $this->attendRedirect($node, $mode);
        }
      }
    }
    return [
      '#theme' => 'bbb_meeting',
      '#meeting' => $meeting['url'][$mode],
      '#mode' => $mode,
      '#height' => $this->getDisplayHeight(),
      '#width' => $this->getDisplayWidth(),
      '#cache' => ['max-age' => 0,],
    ];

  }

  /**
   * Redirect to big blue button instance.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A Drupal node Interface.
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   Drupal render array.
   */
  public function moderate(NodeInterface $node, $record = NULL) {
    $mode = 'moderate';
    $meeting = $this->nodeMeeting->get($node, \Drupal::currentUser(), FALSE);

    $status = $this->api->getMeetingInfo(new GetMeetingInfoParameters($meeting['created']->getMeetingId(), $meeting['created']->getModeratorPassword()));
    if ($status && property_exists($status, 'hasBeenForciblyEnded') && $status->hasBeenForciblyEnded() == 'true') {
      $this->messenger->addStatus('The meeting has been terminated and is not available for reopening.');
      return new RedirectResponse(Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE]));
    }

//    drupal_set_title($node->getTitle());
    // Implicitly create meeting.
    $display_mode = $this->config('bbb_node.settings')->get('display_mode');
    if (empty($meeting->initialized)) {
      if ($meeting['created']->isRecorded() == TRUE) {
        $meeting['created']->setAllowStartStopRecording(true);
        if($record === 'norecord') {
          $meeting['created']->setAutoStartRecording(false);
        }
      }
      if ($display_mode === 'inline') {
        $meeting['created']->setLogoutUrl(Url::fromRoute('bbb_node.meeting.closed', ['node' => $node->id()], ['absolute' => 'true'])
          ->toString());
      }
      if ($data = $this->nodeMeeting->create($node, $meeting['created'])) {
        // Update local data.
        $this->nodeMeeting->update($node, $meeting['created']);
      }
    }
    else {
      if ($display_mode === 'inline') {
        $meeting['created']->setLogoutUrl(Url::fromRoute('bbb_node.meeting.closed', ['node' => $node->id()], ['absolute' => 'true'])
          ->toString());
        $this->nodeMeeting->update($node, $meeting['created']);
      }

    }
    if ($this->getDisplayMode() === 'blank') {
      return $this->attendRedirect($node, $mode);
    }
    return [
      '#theme' => 'bbb_meeting',
      '#meeting' => $meeting['url'][$mode],
      '#mode' => $mode,
      '#height' => $this->getDisplayHeight(),
      '#width' => $this->getDisplayWidth(),
      '#cache' => ['max-age' => 0,],
    ];

  }

  /**
   * Redirect to meeting.
   *
   * @param \Drupal\node\NodeInterface $node
   * @param string $mode
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   */
  public function attendRedirect(NodeInterface $node, $mode = 'attend') {
    $meeting = $this->nodeMeeting->get($node, \Drupal::currentUser(), false);
    if (empty($meeting['url'][$mode])) {
      // Redirect not found.
      throw new NotFoundHttpException();
    }
    // Get redirect URL.
    $url = parse_url($meeting['url'][$mode]);
    $fullurl = $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '') . $url['path'] . '?' . $url['query'];
    return new TrustedRedirectResponse($fullurl, 301);
  }

  /**
   * Return meeting status; Menu callback
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A Drupal node Interface.
   *
   * @return JsonResponse with boolean 'running'
   */
  public function status(NodeInterface $node) {
    $meeting = $this->nodeMeeting->get($node);
    $status = $this->api->getMeetingInfo(new GetMeetingInfoParameters($meeting['created']->getMeetingId(), $meeting['created']->getModeratorPassword()));
    if ($status && property_exists($status, 'isRunning') && $status->isRunning()) {
      return new JsonResponse(['running' => TRUE]);
    }
    else {
      return new JsonResponse(['running' => FALSE]);
    }
  }

  /**
   * Redirect to node after lewviong conference on display mode = inline.
   */
  public function closed(NodeInterface $node) {
    $options = ['absolute' => TRUE];
    $url_object = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options);
    $build = [
      '#markup' => t('Please wait a second ...'),
      '#attached' => [
        'library' => 'bbb_node/reload_node',
        'drupalSettings' => [
          'bbb' => [
            'reload' => [
              'url' => $url_object->toString(),
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0,],
    ];
    return $build;
  }

  public function getTitle(NodeInterface $node) {
    return $node->getTitle();
  }

  public function getDisplayMode() {
    return $this->config->get('display_mode');
  }

  public function getDisplayHeight() {
    return $this->config->get('display_height');
  }

  public function getDisplayWidth() {
    return $this->config->get('display_width');
  }

}
