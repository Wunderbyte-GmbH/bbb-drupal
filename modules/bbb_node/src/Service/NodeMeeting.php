<?php

namespace Drupal\bbb_node\Service;

use BigBlueButton\Parameters\CreateMeetingParameters;
use Drupal\bbb\Service\Meeting as BbbMeeting;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Class NodeMeeting.
 *
 * @package Drupal\bbb\Service
 */
class NodeMeeting {

  use StringTranslationTrait;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Meeting API.
   *
   * @var \Drupal\bbb\Service\Meeting
   */
  protected $meetingApi;

  public function __construct(
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    BbbMeeting $meeting_api
  ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->meetingApi = $meeting_api;
  }

  /**
   * Check if user is meeting owner.
   */
  public function isOwner(NodeInterface $node, $account = NULL) {
    if (!$account) {
      $account = $this->currentUser;
    }
    return $account->id() === $node->getOwnerId();
  }

  /**
   * Check if node type is meeting.
   *
   * @param string|\Drupal\node\NodeInterface|\Drupal\node\NodeTypeInterface $typeOrNode
   *   Node or node type.
   *
   * @return bool
   *   Is node has default Meeting settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isTypeOf($typeOrNode) {
    if ($typeOrNode instanceof NodeInterface) {
      $typeOrNode = $typeOrNode->getType();
    }
    if ($typeOrNode instanceof NodeTypeInterface) {
      $typeOrNode = $typeOrNode->id();
    }
    /** @var \Drupal\bbb_node\Entity\BBBNodeTypeInterface $settings */
    $settings = $this->entityTypeManager
      ->getStorage('bbb_node_type')
      ->load($typeOrNode);
    if (!$settings) {
      return FALSE;
    }
    return (bool) $settings->active();
  }

  /**
   * Return a meeting object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node instance.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   User instance.
   * @param bool $cached
   *   Flag of returning cached results.
   *
   * @return array
   *   Meeting info.
   */
  public function get(NodeInterface $node, $account = NULL, $cached = FALSE) {
    return $this->meetingApi->get($node->uuid(), $account, $cached);
  }

  /**
   * Init meeting.
   */
  public function init(NodeInterface $node, CreateMeetingParameters $params = NULL) {
    if (empty($params)) {
      $params = new CreateMeetingParameters($node->uuid(), $node->getTitle());
    }
    /** @var \Drupal\bbb_node\Entity\BBBNodeTypeInterface $bbb_config */
    $bbb_config = $this->entityTypeManager->getStorage('bbb_node_type')->load($node->getType());
    $params->setMeetingName(
      $params->getMeetingName() ?:
        $node->getTitle()
    );
    $params->setWelcomeMessage(
      $params->getWelcomeMessage() ?:
        $bbb_config->get('welcome') ?:
          $this->t('Welcome to @title', ['@title' => $node->getTitle()])
    );
    $params->setDialNumber(
      $params->getDialNumber() ?:
        $bbb_config->get('dialNumber') ?:
          NULL
    );
    $params->setModeratorPassword(
      $params->getModeratorPassword() ?:
        $bbb_config->get('moderatorPW') ?:
          user_password()
    );
    $params->setAttendeePassword(
      $params->getAttendeePassword() ?:
        $bbb_config->get('attendeePW') ?:
          user_password()
    );
    $logout_url = $bbb_config->get('logoutURL');
    $params->setLogoutUrl(
      $params->getLogoutUrl() ?:
        (
        empty($logout_url) ?
          NULL :
          Url::fromUserInput($bbb_config->get('logoutURL'), ['absolute' => TRUE])
            ->toString()
        )
    );
    $params->setRecord(
      $params->isRecorded() ?:
        (bool) $bbb_config->get('record')
    );
    // This is the PIN that a dial-in user must enter to join the conference.
    // 5-digit value.
    $params->setVoiceBridge($params->getVoiceBridge() ?: random_int(10000, 99999));

    // TODO: Add support for the next values:
    /*$params->setLogo(
      $params->getLogo() ?:
        Url::fromUserInput($bbb_config->get('logoURL'), ['absolute' => TRUE])->toString()
    );*/
    // $params->setAllowStartStopRecording();
    // $params->setAutoStartRecording();
    // $params->setCopyright($params->getCopyright());
    // $params->setDuration($params->getDuration());
    // $params->setEndCallbackUrl();
    // $params->setMaxParticipants($params->getMaxParticipants());
    // $params->setModeratorOnlyMessage($params->getModeratorOnlyMessage());
    // $params->setMuteOnStart($params->isMuteOnStart());
    // $params->setParentMeetingId($params->getParentMeetingId());
    // $params->setWebcamsOnlyForModerator($params->isWebcamsOnlyForModerator());

    // $params->setBreakout($params->isBreakout());
    // $params->setFreeJoin($params->isFreeJoin());
    // $params->setSequence($params->getSequence());
    // $params->setWebVoice($params->getWebVoice());
    return $params;
  }

  /**
   * Update meeting.
   *
   * @param \Drupal\node\NodeInterface $node
   * @param array $params
   */
  public function update(NodeInterface $node, CreateMeetingParameters $params = NULL) {
    // This is a new record if params is empty.
    $params = $this->init($node, $params);
    return $this->meetingApi->update($node->uuid(), $params);
  }

  /**
   * Create meeting.
   */
  public function create(NodeInterface $node, CreateMeetingParameters $params) {
    $this->init($node, $params);
    return $this->meetingApi->create($node->uuid(), $params);
  }

  /**
   * Store meeting.
   */
  public function store($node, CreateMeetingParameters $params) {
    return $this->update($node, $params);
  }

  /**
   * End and Delete meeting.
   */
  public function delete(NodeInterface $node) {
    $this->meetingApi->delete($node->uuid());
  }

}
