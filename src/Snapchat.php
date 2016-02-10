<?php

namespace Snapchat;

use CasperDevelopersAPI;
use Snapchat\API\Request\AllUpdatesRequest;
use Snapchat\API\Request\AuthStoryBlobRequest;
use Snapchat\API\Request\BlobRequest;
use Snapchat\API\Request\ConversationAuthTokenRequest;
use Snapchat\API\Request\ConversationRequest;
use Snapchat\API\Request\ConversationsRequest;
use Snapchat\API\Request\FriendRequest;
use Snapchat\API\Request\LoginRequest;
use Snapchat\API\Request\Model\SendMediaPayload;
use Snapchat\API\Request\Model\UploadMediaPayload;
use Snapchat\API\Request\SendMediaRequest;
use Snapchat\API\Request\SnapTagRequest;
use Snapchat\API\Request\StoriesRequest;
use Snapchat\API\Request\UpdateSnapsRequest;
use Snapchat\API\Request\UpdateStoriesRequest;
use Snapchat\API\Request\UploadMediaRequest;
use Snapchat\API\Response\FriendsResponse;
use Snapchat\API\Response\Model\Conversation;
use Snapchat\API\Response\Model\Snap;
use Snapchat\API\Response\Model\Story;
use Snapchat\API\Response\StoriesResponse;
use Snapchat\API\Response\UpdatesResponse;
use Snapchat\Model\MediaPath;
use Snapchat\Util\RequestUtil;
use Snapchat\Util\StringUtil;

class Snapchat {

    const EXT_JPG = "jpg";
    const EXT_PNG = "png";
    const EXT_MP4 = "mp4";
    const EXT_ZIP = "zip";

    /**
     *
     * Snapchat Username
     *
     * @var string
     */
    private $username;

    /**
     *
     * Snapchat AuthToken
     *
     * @var string
     */
    private $auth_token;

    /**
     * @var UpdatesResponse
     */
    private $cached_updates_response;

    /**
     * @var FriendsResponse
     */
    private $cached_friends_response;

    /**
     * @var Conversation[]
     */
    private $cached_conversations;

    /**
     * @var StoriesResponse
     */
    private $cached_stories_response;

    /**
     * Casper Developer API instance
     * @var string
     */
    private $casper;

    /**
     * HTTP Proxy to be used for Snapchat API Requests
     * @var string
     */
    private $proxy;

    /**
     * @param $casper CasperDevelopersAPI
     */
    public function __construct($casper){
        $this->setCasper($casper);
    }

    public function initWithAuthToken($username, $auth_token){
        $this->username = $username;
        $this->auth_token = $auth_token;
    }

    public function getUsername(){
        return $this->username;
    }

    public function getAuthToken(){
        return $this->auth_token;
    }

    public function isLoggedIn(){
        return !empty($this->getUsername()) && !empty($this->getAuthToken());
    }

    /**
     * Set the HTTP Proxy to be used for Snapchat API Requests
     * @param $proxy string
     */
    public function setProxy($proxy){
        $this->proxy = $proxy;
    }

    /**
     * Get the HTTP Proxy to be used for Snapchat API Requests
     * @return string
     */
    public function getProxy(){
        return $this->proxy;
    }

    /**
     * Set the CasperDevelopersAPI instance to use
     * @param $casper CasperDevelopersAPI
     */
    public function setCasper($casper){
        $this->casper = $casper;
    }

    /**
     * Get the CasperDevelopersAPI instance to use
     * @return CasperDevelopersAPI
     */
    public function getCasper(){
        return $this->casper;
    }

    /**
     *
     * Login to Snapchat with Credentials
     *
     * @param $username string Snapchat Username
     * @param $password string Snapchat Password
     * @return API\Response\LoginResponse
     * @throws \Exception
     */
    public function login($username, $password){

        $request = new LoginRequest($this->getCasper(), $username, $password);
        $request->setProxy($this->getProxy());
        $response = $request->execute();

        if($response->getStatus() != 0){

            //todo: Support 2Factor Logins.
            if($response->isTwoFaNeeded()){
                throw new \Exception("Snapchat account requires 2Factor Authentication.");
            }

            throw new \Exception(sprintf("[%s] Login Failed: %s", $response->getStatus(), $response->getMessage()));

        }

        $this->cached_updates_response = $response->getUpdatesResponse();
        $this->cached_friends_response = $response->getFriendsResponse();
        $this->cached_stories_response = $response->getStoriesResponse();
        $this->cached_conversations = $response->getConversationsResponse();

        $this->username = $this->cached_updates_response->getUsername();
        $this->auth_token = $this->cached_updates_response->getAuthToken();

        return $response;

    }

    /**
     *
     * Fetch All Updates.
     *
     * @return API\Response\AllUpdatesResponse
     * @throws \Exception
     */
    public function getAllUpdates(){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call getAllUpdates().");
        }

        $request = new AllUpdatesRequest($this);
        $response = $request->execute();

        $this->cached_updates_response = $response->getUpdatesResponse();
        $this->cached_friends_response = $response->getFriendsResponse();
        $this->cached_stories_response = $response->getStoriesResponse();
        $this->cached_conversations = $response->getConversationsResponse();

        $this->username = $this->cached_updates_response->getUsername();
        $this->auth_token = $this->cached_updates_response->getAuthToken();

        return $response;

    }

    /**
     *
     * Fetch Conversations
     *
     * @return Conversation[]
     * @throws \Exception
     */
    public function getConversations(){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call getConversations().");
        }

        $request = new ConversationsRequest($this);
        $response = $request->execute();

        $conversations = $response->getConversationsResponse();

        $this->cached_conversations = $conversations;
        $this->cached_updates_response = $response->getUpdatesResponse();
        $this->cached_friends_response = $response->getFriendsResponse();

        return $conversations;

    }

    /**
     *
     * Fetch Stories
     *
     * @return API\Response\StoriesResponse
     * @throws \Exception
     */
    public function getStories(){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call getStories().");
        }

        $request = new StoriesRequest($this);
        $response = $request->execute();

        $this->cached_stories_response = $response;

        return $response;

    }

    /**
     *
     * Get cached Conversations.
     * If Conversations is not Cached, the API will be queried.
     *
     * @return Conversation[]
     * @throws \Exception
     */
    public function getCachedConversations(){

        if($this->cached_conversations == null){
            $this->getConversations();
        }

        return $this->cached_conversations;

    }

    /**
     *
     * Get cached UpdatesResponse.
     * If UpdatesResponse is not Cached, the API will be queried.
     *
     * @return API\Response\UpdatesResponse
     * @throws \Exception
     */
    public function getCachedUpdatesResponse(){

        if($this->cached_updates_response == null){
            $this->getAllUpdates();
        }

        return $this->cached_updates_response;

    }

    /**
     *
     * Get cached FriendsResponse.
     * If FriendsResponse is not Cached, the API will be queried.
     *
     * @return API\Response\FriendsResponse
     * @throws \Exception
     */
    public function getCachedFriendsResponse(){

        if($this->cached_friends_response == null){
            $this->getAllUpdates();
        }

        return $this->cached_friends_response;

    }

    /**
     *
     * Get cached StoriesResponse.
     * If StoriesResponse is not Cached, the API will be queried.
     *
     * @return API\Response\StoriesResponse
     * @throws \Exception
     */
    public function getCachedStoriesResponse(){

        if($this->cached_stories_response == null){
            $this->getStories();
        }

        return $this->cached_stories_response;

    }

    /**
     * @param $snap Snap The Snap to mark Viewed
     * @param bool|false $screenshot Whether to mark this Snap as Screenshot
     * @param bool|false $replayed Whether to mark this Snap as Replayed
     * @throws \Exception
     */
    public function markSnapViewed($snap, $screenshot = false, $replayed = false){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call markSnapViewed().");
        }

        $request = new UpdateSnapsRequest($this, $snap);
        $request->setScreenshot($screenshot);
        $request->setReplayed($replayed);
        $request->execute();

    }

    /**
     * @param $story Story The Story to mark Viewed
     * @param bool|false $screenshot Whether to mark this Story as Screenshot
     * @throws \Exception
     */
    public function markStoryViewed($story, $screenshot = false){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call markStoryViewed().");
        }

        $request = new UpdateStoriesRequest($this, $story);
        $request->setScreenshot($screenshot);
        $request->execute();

    }

    /**
     * @param $username string Snapchat Username to Add as Friend
     * @return API\Response\FriendResponse
     * @throws \Exception
     */
    public function addFriend($username){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call addFriend().");
        }

        $request = new FriendRequest($this);
        $request->initWithUsername($username);
        $request->add();
        return $request->execute();

    }

    /**
     * @param $username string Snapchat Username to Delete
     * @return API\Response\FriendResponse
     * @throws \Exception
     */
    public function deleteFriend($username){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call deleteFriend().");
        }

        $friend = $this->findCachedFriend($username);

        $request = new FriendRequest($this);

        if($friend != null){
            $request->initWithFriend($friend);
        } else {
            $request->initWithUsername($username);
        }

        $request->delete();
        return $request->execute();

    }

    /**
     * @param $username string Snapchat Username to Update
     * @param $display string The new Display Name to set
     * @return API\Response\FriendResponse
     * @throws \Exception
     */
    public function updateFriendDisplayName($username, $display){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call updateFriendDisplayName().");
        }

        $friend = $this->findCachedFriend($username);

        $request = new FriendRequest($this);

        if($friend != null){
            $request->initWithFriend($friend);
        } else {
            $request->initWithUsername($username);
        }

        $request->updateDisplayName($display);
        return $request->execute();

    }

    /**
     * @param $username string Snapchat Username to Block
     * @return API\Response\FriendResponse
     * @throws \Exception
     */
    public function blockFriend($username){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call blockFriend().");
        }

        $friend = $this->findCachedFriend($username);

        $request = new FriendRequest($this);

        if($friend != null){
            $request->initWithFriend($friend);
        } else {
            $request->initWithUsername($username);
        }

        $request->block();
        return $request->execute();

    }

    /**
     * @param $username string Snapchat Username to Unblock
     * @return API\Response\FriendResponse
     * @throws \Exception
     */
    public function unblockFriend($username){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call unblockFriend().");
        }

        $friend = $this->findCachedFriend($username);

        $request = new FriendRequest($this);

        if($friend != null){
            $request->initWithFriend($friend);
        } else {
            $request->initWithUsername($username);
        }

        $request->unblock();
        return $request->execute();

    }

    /**
     *
     * Download a Snap to a File.
     *
     * @param $snap Snap The Snap to Download
     * @param $file string File Path to save the Snap
     * @param $file_overlay string File Path to save the Snap Overlay
     * @return MediaPath
     * @throws \Exception
     */
    public function downloadSnap($snap, $file, $file_overlay = null){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call downloadSnap().");
        }

        $request = new BlobRequest($this, $snap);
        $response = $request->execute();

        if($file_overlay == null){
            $file_overlay = sprintf("%s_overlay", $file);
        }

        $file_overlay = sprintf("%s.%s", $file_overlay, self::EXT_PNG);

        if($snap->isPhoto()){
            $file = sprintf("%s.%s", $file, self::EXT_JPG);
        } else if($snap->isVideo()){
            $file = sprintf("%s.%s", $file, self::EXT_MP4);
        }

        if($snap->isZipped()){
            $this->unzipBlob($response, $file, $file_overlay);
        } else {
            file_put_contents($file, $response);
        }

        return new MediaPath($file, $file_overlay);

    }

    /**
     *
     * Download a Story to a File.
     *
     * @param $story Story The Story to Download
     * @param $file string File Path to save the Story
     * @param $file_overlay string File Path to save the Story Overlay
     * @return MediaPath
     * @throws \Exception
     */
    public function downloadStory($story, $file, $file_overlay = null){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call downloadStory().");
        }

        $request = new AuthStoryBlobRequest($this, $story);
        $response = $request->execute();

        if($file_overlay == null){
            $file_overlay = sprintf("%s_overlay", $file);
        }

        $file_overlay = sprintf("%s.%s", $file_overlay, self::EXT_PNG);

        if($story->isPhoto()){
            $file = sprintf("%s.%s", $file, self::EXT_JPG);
        } else if($story->isVideo()){
            $file = sprintf("%s.%s", $file, self::EXT_MP4);
        }

        if($story->isZipped()){
            $this->unzipBlob($response, $file, $file_overlay);
        } else {
            file_put_contents($file, $response);
        }

        return new MediaPath($file, $file_overlay);

    }

    /**
     *
     * Download your SnapTag to a File
     *
     * @param $file string File Path to save the SnapTag
     * @return string Where the SnapTag was Saved
     * @throws \Exception
     */
    public function downloadSnapTag($file){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call downloadSnapTag().");
        }

        $updates = $this->getCachedUpdatesResponse();

        $request = new SnapTagRequest($this, $updates->getQrPath());
        $response = $request->execute();

        $file = sprintf("%s.%s", $file, self::EXT_PNG);

        file_put_contents($file, $response);

        return $file;

    }

    private function unzipBlob($data, $file_blob, $file_overlay){

        $file_temp = tempnam(sys_get_temp_dir(), self::EXT_ZIP);
        file_put_contents($file_temp, $data);

        $zip = zip_open($file_temp);
        if(is_resource($zip)){

            while($zip_entry = zip_read($zip)){

                $filename = zip_entry_name($zip_entry);

                if(zip_entry_open($zip, $zip_entry, "r")){

                    if(StringUtil::startsWith($filename, "media")){
                        file_put_contents($file_blob, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));
                    }

                    if(StringUtil::startsWith($filename, "overlay")){
                        file_put_contents($file_overlay, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));
                    }

                    zip_entry_close($zip_entry);

                }

            }

            zip_close($zip);

        }

        unlink($file_temp);

    }

    /**
     * @param $file string File to Upload
     * @return UploadMediaPayload
     * @throws \Exception
     */
    public function uploadPhoto($file){
        return $this->uploadMedia($file, UploadMediaRequest::TYPE_IMAGE);
    }

    /**
     * @param $file string File to Upload
     * @return UploadMediaPayload
     * @throws \Exception
     */
    public function uploadVideo($file){
        return $this->uploadMedia($file, UploadMediaRequest::TYPE_VIDEO);
    }

    /**
     * @param $file string File to Upload
     * @param $type int Media Type
     * @return UploadMediaPayload
     * @throws \Exception
     */
    private function uploadMedia($file, $type){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call uploadMedia().");
        }

        $payload = new UploadMediaPayload();
        $payload->file = $file;
        $payload->type = $type;
        $payload->media_id = RequestUtil::generateMediaID($this->getUsername());

        $request = new UploadMediaRequest($this, $payload);
        return $request->execute();

    }

    /**
     * @param $payload_upload UploadMediaPayload Payload from Upload
     * @param $time int Seconds the Media can be viewed
     * @param $recipients array Usernames to send to
     * @param $story boolean Set this Media as your Story
     * @return object
     * @throws \Exception
     */
    public function sendMedia($payload_upload, $time, $recipients, $story = false){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call uploadMedia().");
        }

        $payload = new SendMediaPayload();

        $payload->time = $time;
        $payload->set_as_story = $story;
        $payload->recipients = $recipients;
        $payload->type = $payload_upload->type;
        $payload->media_id = $payload_upload->media_id;
        $payload->recipient_ids = $this->getUserIDs($recipients);

        $request = new SendMediaRequest($this, $payload);
        return $request->execute();

    }

    /* todo: Fix this...
    public function sendChatMessage($username, $message){

        if(!$this->isLoggedIn()){
            throw new \Exception("You must be logged in to call sendChatMessage().");
        }

        $conversation = $this->getCachedConversation($username);
        if($conversation == null){
            throw new \Exception(sprintf("Failed to get Conversation for '%s'. Add them as a Friend to send a Chat Message.", $username));
        }

        $conversation_auth = $this->getConversationAuth($username);
        if($conversation_auth == null){
            throw new \Exception(sprintf("Failed to get ConversationAuth for '%s'. Add them as a Friend to send a Chat Message.", $username));
        }

        $chat_message = new ChatMessage();

        $body = new ChatMessageBody();
        $body->setType(ChatMessageBody::TYPE_TEXT);
        $body->setText($message);

        $header = new ChatMessageHeader();
        $header->setAuth($conversation_auth);
        $header->setTo(array($username));
        $header->setFrom($this->getUsername());
        $header->setConvId($conversation->getId());

        $chat_message->setBody($body);
        $chat_message->setHeader($header);

        $chat_message->setType("chat_message");
        $chat_message->setSeqNum($conversation->getConversationState()->getUserSequences()[$username] + 1);
        $chat_message->setId(RequestUtil::generateUUID());
        $chat_message->setChatMessageId(RequestUtil::generateUUID());
        $chat_message->setTimestamp(RequestUtil::getCurrentMillis());

        $request = new ConversationPostMessagesRequest($this, array($chat_message));
        $request->execute();

    }
    */

    /**
     * Find a Cached Friend or AddedFriend
     * @param $username string Username of Friend to lookup
     * @return null|API\Response\Model\AddedFriend|API\Response\Model\Friend
     */
    public function findCachedFriend($username){

        if($this->cached_friends_response != null){

            foreach($this->cached_friends_response->getFriends() as $friend){
                if($friend->getName() == $username){
                    return $friend;
                }
            }

            foreach($this->cached_friends_response->getAddedFriends() as $friend){
                if($friend->getName() == $username){
                    return $friend;
                }
            }

        }

        return null;

    }

    /**
     * @param $usernames array Usernames to get IDs for
     * @return array Array of User IDs in same order as input array
     */
    private function getUserIDs($usernames){

        $map = array();

        if($this->cached_friends_response != null){

            foreach($this->cached_friends_response->getFriends() as $friend){
                if(in_array($friend->getName(), $usernames) && !empty($friend->getUserId())){
                    $map[$friend->getName()] = $friend->getUserId();
                }
            }

            foreach($this->cached_friends_response->getAddedFriends() as $friend){
                if(in_array($friend->getName(), $usernames) && !empty($friend->getUserId())){
                    $map[$friend->getName()] = $friend->getUserId();
                }
            }

        }

        $ids = array();

        foreach($usernames as $username){
            $ids[] = isset($map[$username]) ? $map[$username] : "";
        }

        return $ids;

    }

    /**
     * @param $username string Username to get Conversation for
     */
    public function getConversation($username){

        $request = new ConversationRequest($this, $username);
        $response = $request->execute();

        return $response->getConversation();

    }

    /**
     * @param $username string Username to get Conversation for
     */
    public function getCachedConversation($username){

        if($this->cached_conversations != null){
            foreach($this->cached_conversations as $conversation){

                $participants = $conversation->getParticipants();
                unset($participants[$this->getUsername()]);

                if($participants[0] == $username){
                    return $conversation;
                }

            }
        }

        return $this->getConversation($username);

    }

    /**
     * @param $username string Conversation Username to get MessagngAuth for
     * @return API\Response\Model\MessagingAuth
     */
    private function getConversationAuth($username){

        if($this->cached_conversations != null){
            foreach($this->cached_conversations as $conversation){

                $participants = $conversation->getParticipants();
                unset($participants[$this->getUsername()]);

                if($participants[0] == $username){
                    return $conversation->getConversationMessages()->getMessagingAuth();
                }

            }
        }

        $request = new ConversationAuthTokenRequest($this);
        $request->initWithUsername($username);
        $response = $request->execute();

        return $response->getMessagingAuth();

    }

}