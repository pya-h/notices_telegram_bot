<?php
require_once './database.php';

// admin actions:
defined('ACTION_NONE') or define('ACTION_NONE', 0);
/* define specific actions */
defined('ACTION_SUBMIT_NOTICE') or define('ACTION_SUBMIT_NOTICE', 1);
defined('ACTION_SET_APPLIER_USERNAME') or define('ACTION_SET_APPLIER_USERNAME', 2);

defined('ACTION_WHISPER_GODS_NAME') or define('ACTION_WHISPER_GODS_NAME', 6); 
defined('ACTION_WHISPER_GODS_SECRET') or define('ACTION_WHISPER_GODS_SECRET', 7);
defined('ACTION_WRITE_MESSAGE_TO_ADMIN') or define('ACTION_WRITE_MESSAGE_TO_ADMIN', 9);
defined('ACTION_WRITE_REPLY_TO_USER') or define('ACTION_WRITE_REPLY_TO_USER', 10);
defined('ACTION_ASSIGN_USER_NAME') or define('ACTION_ASSIGN_USER_NAME', 12);

defined('ACTION_ADD_ADMIN') or define('ACTION_ADD_ADMIN', 11);

function getSuperiors(): ?array {
    // get admin and gods
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS 
        .' WHERE ' . DB_USER_MODE . '=' . GOD_USER . ' OR ' . DB_USER_MODE . '=' . ADMIN_USER);
}

function getCertainUsers(int $user_mode) {
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS 
        .' WHERE ' . DB_USER_MODE . '=:mode', array('mode' => $user_mode));
}

function getUser($id) {
    $db = Database::getInstance();
    $user = $db->query('SELECT * FROM '. DB_TABLE_USERS .' WHERE ' . DB_USER_ID . '=:id LIMIT 1', 
        array('id' => $id));

    if(count($user) == 1)
        return $user[0];

    $db->insert('INSERT INTO '. DB_TABLE_USERS .' (' . DB_USER_ID . ') VALUES (:id)', array(
        'id' => $id
    ));
    // TODO: error check?
    return array(DB_USER_ID => $id, DB_USER_MODE => NORMAL_USER, DB_USER_ACTION => ACTION_NONE, DB_USER_ACTION_CACHE => null);
}

function updateAction($id, int $action, bool $reset_cache = false) {
    $query = 'UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION . '=:action';
    if($reset_cache)
        $query .= ', ' . DB_USER_ACTION_CACHE . '=NULL';
    return Database::getInstance()->update("$query WHERE " . DB_USER_ID . '=:id',
        array('id' => $id, 'action' => $action));
}


function updateUserMode($id, int $mode) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_MODE . '=:mode WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'mode' => $mode));
}

function updateActionCache($id, $cache) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION_CACHE . '=:cache WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'cache' => $cache));
}

function setActionAndCache($id, int $action, $cache) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION . '=:action,'
            . DB_USER_ACTION_CACHE . '=:cache WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'action' => $action, 'cache' => $cache));
}

function resetAction($id): bool {
    return updateAction($id, ACTION_NONE, true);
}

function saveMessage($sender_id, $message_id) {
    return Database::getInstance()->insert('INSERT INTO '. DB_TABLE_MESSAGES
        . ' (' . DB_ITEM_ID . ', ' . DB_MESSAGES_SENDER_ID . ') VALUES (:message_id, :sender_id)', array(
            MESSAGE_ID_TAG => $message_id, 'sender_id' => $sender_id
    ));
}

function saveNotification($user_id, $notice_id, $message_id) {
    $fields = implode(',', [DB_NOTIFICATIONS_USER_ID, DB_NOTIFICATIONS_MESSAGE_ID, DB_NOTIFICATIONS_RELATED_NOTICE_ID]);
    return Database::getInstance()->insert('INSERT INTO '. DB_TABLE_NOTIFICATIONS
        . " ($fields) VALUES (:user_id, :message_id, :notice_id)", array(
            "user_id" => $user_id, "message_id" => $message_id, 'notice_id' => $notice_id
        )
    );
}

function markMessageAsAnswered($message_id) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_MESSAGES . ' SET ' . DB_MESSAGES_ANSWERED . '=1 WHERE ' . DB_ITEM_ID . '=:id',
        array('id' => $message_id));
}

function isMessageAnswered($message_id): bool
{
    $msg = Database::getInstance()->query('SELECT (' . DB_MESSAGES_ANSWERED . ') FROM '. DB_TABLE_MESSAGES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $message_id), DB_MESSAGES_ANSWERED);
    return count($msg) > 0 && $msg[0];
}

function getMessage($message_id) {
    $msg = Database::getInstance()->query('SELECT * FROM '. DB_TABLE_MESSAGES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $message_id));
    return count($msg) ? $msg[0] : null;
}

function assignUserName($id, string &$name) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_ITEM_NAME . '=:name WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'name' => $name));
}

function accountLink(string $username): string {
    return 'https://t.me/' . $username;
}

function getNotifications($notice_id): ?array {
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_NOTIFICATIONS
        .' WHERE ' . DB_NOTIFICATIONS_RELATED_NOTICE_ID . '=:notice_id', array('notice_id' => $notice_id));
}

function deleteNotifications($notice_id): ?array {
    return Database::getInstance()->query('DELETE FROM '. DB_TABLE_NOTIFICATIONS
        .' WHERE ' . DB_NOTIFICATIONS_RELATED_NOTICE_ID . '=:notice_id', array('notice_id' => $notice_id));
}