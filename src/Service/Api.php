<?php

namespace Drupal\bbb\Service;

use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\EndMeetingParameters;
use BigBlueButton\Parameters\GetMeetingInfoParameters;
use BigBlueButton\Parameters\IsMeetingRunningParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use BigBlueButton\Parameters\GetRecordingsParameters;
/**
 * Class Api.
 *
 * Drupal API for BigBlueButton service.
 *
 * @package Drupal\bbb\Service
 */
class Api {

  /**
     * The value of the status, which means a correctly passed request.
   */
  const SUCCESS = 'SUCCESS';

  /**
   * Drupal wrapper for BigBlueButton\BigBlueButton.
   *
   * @var \Drupal\bbb\Service\BigBlueButton
   */
  protected $bbb;

  /**
   * Api constructor.
   *
   * @param \Drupal\bbb\Service\BigBlueButton $bbb
   *   Drupal wrapper for BigBlueButton\BigBlueButton.
   */
  public function __construct(BigBlueButton $bbb) {
    $this->bbb = $bbb;
  }

  /**
   * Create Meeting (create).
   *
   * @param \BigBlueButton\Parameters\CreateMeetingParameters $params
   *   Associative array of additional url parameters. Components:
   *   - name: STRING (REQUIRED) A name for the meeting.
   *   - meetingID: STRING A meeting ID that can be used to identify this
   *     meeting by the third party application. This is optional, and if not
   *     supplied, BBB will generate a meeting token that can be used to
   *     identify the meeting.
   *   - attendeePW: STRING The password that will be required for attendees to
   *     join the meeting. This is optional, and if not supplied, BBB will
   *     assign a random password.
   *   - moderatorPW: STRING The password that will be required for moderators
   *     to join the meeting or for certain administrative actions (i.e. ending
   *     a meeting). This is optional, and if not supplied, BBB will assign a
   *     random password.
   *   - welcome: STRING A welcome message that gets displayed on the chat
   *     window when the participant joins. You can include keywords
   *     (%%CONFNAME%%, %%DIALNUM%%, %%CONFNUM%%) which will be substituted
   *     automatically. You can set a default welcome message on
   *     bigbluebutton.properties
   *   - dialNumber: STRING The dial access number that participants can call in
   *     using regular phone. You can set a default dial number on
   *     bigbluebutton.properties
   *   - logoutURL: STRING The URL that the BigBlueButton client will go to
   *     after users click the OK button on the 'You have been logged out
   *     message'. This overrides, the value for bigbluebutton.web.loggedOutUrl
   *     if defined in bigbluebutton.properties.
   *
   * @return \BigBlueButton\Responses\CreateMeetingResponse|false
   *   - meetingToken: STRING The internal meeting token assigned by the API for
   *     this meeting. It can be used by subsequent calls for joining or
   *     otherwise modifying a meeting's status.
   *   - meetingID: STRING The meeting ID supplied by the third party app, or
   *     null if none was supplied. If can be used in conjunction with a
   *     password in subsequent calls for joining or otherwise modifying a
   *     meeting's status.
   *   - attendeePW: STRING The password that will be required for attendees to
   *     join the meeting. If you did not supply one, BBB will assign a random
   *     password.
   *   - moderatorPW: STRING The password that will be required for moderators
   *     to join the meeting or for certain administrative actions (i.e. ending
   *     a meeting). If you did not supply one, BBB will assign a random
   *     password.
   */
  public function createMeeting(CreateMeetingParameters $params) {
    $response = $this->bbb->createMeeting($params);
    if ($response->getReturnCode() === self::SUCCESS) {
      return $response;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Join Meeting (join).
   *
   * @param \BigBlueButton\Parameters\JoinMeetingParameters $params
   *   Associative array of additional url parameters. Components:
   *   - fullName: STRING (REQUIRED) The full name that is to be used to
   *     identify this user to other conference attendees.
   *   - meetingToken: The internal meeting token assigned by the API for this
   *     meeting when it was created. Note that either the meetingToken or the
   *     meetingID along with one of the passwords must be passed into this call
   *     in order to determine which meeting to find.
   *   - meetingID: STRING If you specified a meeting ID when calling create,
   *     then you can use either the generated meeting token or your specified
   *     meeting ID to find meetings. This parameter takes your meeting ID.
   *   - password: STRING The password that this attendee is using. If the
   *     moderator password is supplied, he will be given moderator status (and
   *     the same for attendee password, etc)
   *   - redirectImmediately: BOOLEAN If this is passed as true, then BBB will
   *     not return a URL for you to redirect the user to, but will instead
   *     treat this as an entry URL and will immediately set up the client
   *     session and redirect the user into the conference.
   *     Values can be either a 1 (one) or a 0 (zero), indicating true or false
   *     respectively. Defaults to false.
   *
   * @return STRING
   *   The URL that the user can be sent to in order to join the meeting. When
   *   they go to this URL, BBB will setup their client session and redirect
   *   them into the conference.
   */
  public function joinMeeting(JoinMeetingParameters $params) {
    return $this->bbb->getJoinMeetingURL($params);
  }

  /**
   * Is meeting running (isMeetingRunning).
   *
   * This call enables you to simply check on whether or not a meeting is
   * running by looking it up with either the token or your ID.
   *
   * @param \BigBlueButton\Parameters\IsMeetingRunningParameters $params
   *   Associative array of additional url parameters. Components:
   *   - meetingToken: STRING The internal meeting token assigned by the API for
   *     this meeting when it was created.
   *   - meetingID: STRING If you specified a meeting ID when calling create,
   *     then you can use either the generated meeting token or your specified
   *     meeting ID to find meetings. This parameter takes your meeting ID.
   *
   * @return bool
   *   A string of either “true” or “false” that signals whether a meeting with
   *   this ID or token is currently running.
   */
  public function isMeetingRunning(IsMeetingRunningParameters $params) {
    $response = $this->bbb->isMeetingRunning($params);
    if ($response->getReturnCode() === self::SUCCESS) {
      return $response->isRunning();
    }
    else {
      \Drupal::logger('bigbluebutton')->error('%message', ['%message' => $response->getMessage()]);
      return FALSE;
    }
  }

  /**
   * End Meeting (endMeeting).
   *
   * Use this to forcibly end a meeting and kick all participants out of the
   * meeting.
   *
   * @param \BigBlueButton\Parameters\EndMeetingParameters $params
   *   Associative array of additional url parameters. Components:
   *   - meetingToken: STRING The internal meeting token assigned by the API for
   *     this meeting when it was created. Note that either the meetingToken or
   *     the meetingID along with one of the passwords must be passed into this
   *     call in order to determine which meeting to find.
   *   - meetingID: STRING If you specified a meeting ID when calling create,
   *     then you can use either the generated meeting token or your specified
   *     meeting ID to find meetings. This parameter takes your meeting ID.
   *   - password: STRING The moderator password for this meeting. You can not
   *     end a meeting using the attendee password.
   *
   * @return \BigBlueButton\Responses\EndMeetingResponse|false
   *   A string of either “true” or “false” that signals whether the meeting
   *   was successfully ended.
   */
  public function endMeeting(EndMeetingParameters $params) {
    $response = $this->bbb->endMeeting($params);
    if ($response->getReturnCode() === self::SUCCESS) {
      return $response;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get Meeting Info (getMeetingInfo).
   *
   * This call will return all of a meeting's information, including the list of
   * attendees as well as start and end times.
   *
   * @param \BigBlueButton\Parameters\GetMeetingInfoParameters $params
   *   Associative array of additional url parameters. Components:
   *   - meetingToken: STRING The internal meeting token assigned by the API for
   *     this meeting when it was created. Note that either the meetingToken or
   *     the meetingID along with one of the passwords must be passed into this
   *     call in order to determine which meeting to find.
   *   - meetingID: STRING If you specified a meeting ID when calling create,
   *     then you can use either the generated meeting token or your specified
   *     meeting ID to find meetings. This parameter takes your meeting ID.
   *   - password: STRING (REQUIRED) The moderator password for this meeting.
   *     You can not get the meeting information using the attendee password.
   *
   * @return \BigBlueButton\Core\Meeting|false
   *   Meeting instance.
   */
  public function getMeetingInfo(GetMeetingInfoParameters $params) {
    $response = $this->bbb->getMeetingInfo($params);
    if ($response->getReturnCode() === self::SUCCESS) {
      return $response->getMeeting();
    }
    else {
      return FALSE;
    }
  }

  /**
   * End Meeting (endMeeting)
   *
   *  Use this to forcibly end a meeting and kick all participants out of the
   *  meeting.
   *
   * @param \BigBlueButton\Parameters\EndMeetingParameters $params
   *   Associative array of additional url parameters. Components:
   *   - meetingToken: STRING The internal meeting token assigned by the API for
   *     this meeting when it was created. Note that either the meetingToken or
   *     the meetingID along with one of the passwords must be passed into this
   *     call in order to determine which meeting to find.
   *   - meetingID: STRING If you specified a meeting ID when calling create,
   *     then you can use either the generated meeting token or your specified
   *     meeting ID to find meetings. This parameter takes your meeting ID.
   *   - password: STRING The moderator password for this meeting. You can not
   *      end a meeting using the attendee password.
   *
   * @return \BigBlueButton\Responses\EndMeetingResponse|BOOLEAN
   *     A string of either “true” or “false” that signals whether the meeting was
   *     successfully ended.
   */
  public function end($key) {

  }
  public function getRecordings(GetRecordingsParameters $params) {
    $response = $this->bbb->getRecordings($params);
    if ($response->getReturnCode() === self::SUCCESS) {
      return $response->getRecords();
    }
    else {
      return FALSE;
    }
  }
}
