<?php

namespace Drupal\bbb\Service;

use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\EndMeetingParameters;
use BigBlueButton\Parameters\GetMeetingInfoParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class Meeting.
 *
 * @package Drupal\bbb\Service
 */
class Meeting implements MeetingInterface {

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
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Meetings storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $meetingCollection;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $log;

  /**
   * Drupal API for BigBlueButton service.
   *
   * @var \Drupal\bbb\Service\Api
   */
  protected $api;

  /**
   * Private key service.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * Meetings info cached collection.
   *
   * @var array
   */
  protected static $meetings = [];

  /**
   * Meeting constructor.
   *
   * @param \Drupal\bbb\Service\Api $api
   *   Drupal API for BigBlueButton service.
   * @param \Drupal\Core\PrivateKey $private_key
   *   Private key service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyvalue
   *   Keyvalue service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user instance.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   */
  public function __construct(
    Api $api,
    PrivateKey $private_key,
    ModuleHandlerInterface $module_handler,
    KeyValueFactoryInterface $keyvalue,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->api = $api;
    $this->privateKey = $private_key;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->meetingCollection = $keyvalue->get('bbb_meetings');
    $this->log = $logger_factory->get('bbb');
  }

  /**
   * {@inheritdoc}
   */
  public function get($id, AccountInterface $account = NULL, $cached = TRUE) {
    if (empty($account)) {
      $account = $this->currentUser;
    }

    if (!isset(self::$meetings[$id]) || !$cached) {
      /** @var \BigBlueButton\Parameters\CreateMeetingParameters $meeting_created */
      $meeting_created = $this->meetingCollection->get($id);
      if ($meeting_created) {
        $meeting_info = $this->api->getMeetingInfo(new GetMeetingInfoParameters($meeting_created->getMeetingId(), $meeting_created->getModeratorPassword()));
        if ($meeting_info) {
          $attend = new JoinMeetingParameters(
            $meeting_created->getMeetingId(),
            $account->getDisplayName(),
            $meeting_created->getAttendeePassword()
          );
          $attend->setJoinViaHtml5(TRUE);
          $attend->setRedirect(TRUE);
          $moderate = new JoinMeetingParameters(
            $meeting_created->getMeetingId(),
            $account->getDisplayName(),
            $meeting_created->getModeratorPassword()
          );
          $moderate->setJoinViaHtml5(TRUE);
          $moderate->setRedirect(TRUE);
          $url = [
            'attend' => $this->api->joinMeeting($attend),
            'moderate' => $this->api->joinMeeting($moderate),
          ];
          $meeting = [
            'info' => $meeting_info,
            'created' => $meeting_created,
            'url' => $url,
          ];
          // Allow alteration for e.g. access control
          // Just implement hook_bbb_meeting_alter(&$data) {} in your module.
          $this->moduleHandler->alter('bbb_get_meeting', $meeting);
          // Static cache.
          self::$meetings[$id] = $meeting;
        }
      }
    }
    return isset(self::$meetings[$id]) ? self::$meetings[$id] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function update($key, CreateMeetingParameters $params) {
    $this->meetingCollection->set($key, $params);
    return $key;
  }

  /**
   * {@inheritdoc}
   */
  public function create($key, CreateMeetingParameters $params) {
    if ($data = $this->api->createMeeting($params)) {
      return $data;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function store($key, CreateMeetingParameters $params) {
    return $this->update($key, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    if ($this->meetingCollection->has($key)) {
      $this->meetingCollection->delete($key);
    }
    else {
      $this->log->warning($this->t('Meeting @key not found during removal: It was removed before manually or never exists.', ['@key' => $key]));
    }
    $meeting = $this->get($key);
    $params = new EndMeetingParameters(
      $meeting['created']->getMeetingId(),
      $meeting['created']->getModeratorPassword()
    );
    $request = $this->api->endMeeting($params);
    return $request;
  }

}
