<?php
// TELEGRAM API GENERAL CONSTANTS
defined('FILE_ID') or define('FILE_ID', 'file_id');
defined('TEXT_TAG') or define('TEXT_TAG', 'text');
defined('REPLY_TO_TAG') or define('REPLY_TO_TAG', 'reply_to_message_id');
defined('MESSAGE_ID_TAG') or define('MESSAGE_ID_TAG', 'message_id');
defined('KEYBOARD') or define('KEYBOARD', 'reply_markup');
defined('INLINE_KEYBOARD') or define('INLINE_KEYBOARD', 'inline_keyboard');
defined('CHAT_ID') or define('CHAT_ID', 'chat_id');

defined('CAPTION_TAG') or define('CAPTION_TAG', 'caption');
defined('CALLBACK_DATA') or define('CALLBACK_DATA', 'callback_data');
defined('INLINE_URL_TAG') or define('INLINE_URL_TAG', 'url');

defined('CALLBACK_QUERY') or define('CALLBACK_QUERY', 'callback_query');

defined('FILE_PHOTO') or define('FILE_PHOTO', 'photo');
defined('FILE_VOICE') or define('FILE_VOICE', 'voice');
defined('FILE_VIDEO') or define('FILE_VIDEO', 'video');
defined('FILE_AUDIO') or define('FILE_AUDIO', 'audio');
defined('FILE_DOCUMENT') or define('FILE_DOCUMENT', 'document');
defined('USER_NOT_A_MEMBER') or define('USER_NOT_A_MEMBER', 'left');

defined('METH_SEND_MESSAGE') or define('METH_SEND_MESSAGE', 'sendMessage');
defined('METH_SEND_PHOTO') or define('METH_SEND_PHOTO', 'sendPhoto');
defined('METH_SEND_VOICE') or define('METH_SEND_VOICE', 'sendVoice');
defined('METH_SEND_AUDIO') or define('METH_SEND_AUDIO', 'sendAudio');
defined('METH_SEND_VIDEO') or define('METH_SEND_VIDEO', 'sendVideo');
defined('METH_SEND_DOCUMENT') or define('METH_SEND_DOCUMENT', 'sendDocument');

defined('METH_SEND_LOCATION') or define('METH_SEND_LOCATION', 'sendLocation');
defined('METH_SEND_CONTACT') or define('METH_SEND_CONTACT', 'sendContact');
defined('METH_SEND_CHAT_ACTION') or define('METH_SEND_CHAT_ACTION', 'sendChatAction'); // typing..., sending video ..., that kind of thing
// lasts for 5 secs

defined('METH_FORWARD_MESSAGE') or define('METH_FORWARD_MESSAGE', 'forwardMessage');
defined('METH_COPY_MESSAGE') or define('METH_COPY_MESSAGE', 'copyMessage');
defined('METH_ANSWER_CALLBACK_QUERY') or define('METH_ANSWER_CALLBACK_QUERY', 'answerCallbackQuery');
defined('METH_EDIT_MESSAGE') or define('METH_EDIT_MESSAGE', 'editMessageText');
defined('METH_EDIT_KEYBOARD') or define('METH_EDIT_KEYBOARD', 'editMessageReplyMarkup');

defined('METH_DELETE_MESSAGE') or define('METH_DELETE_MESSAGE', 'deleteMessage');
defined('METH_GET_CHAT_MEMBER') or define('METH_GET_CHAT_MEMBER', 'getChatMember');

// BOT SPECIFIC CONSTANTS
defined('TOKEN') or define('TOKEN', 'bot token');
defined('URL_BASE') or define('URL_BASE', 'https://api.telegram.org/bot' . TOKEN . '/');

defined('PERSIAN_PROJECT_CHANNEL_URL') or define('PERSIAN_PROJECT_CHANNEL_URL', 'https://t.me/Persian_project');
defined('PERSIAN_PROJECT_CHANNEL_ID') or define('PERSIAN_PROJECT_CHANNEL_ID', -1001648749488);
defined('PERSIAN_PROJECT_BOT_USERNAME') or define('PERSIAN_PROJECT_BOT_USERNAME', 'persian_projectbot');

defined('FIRST_2_JOIN_CHANNEL_URL') or define('FIRST_2_JOIN_CHANNEL_URL', 'https://t.me/persian_collegee');
defined('FIRST_2_JOIN_CHANNEL_ID') or define('FIRST_2_JOIN_CHANNEL_ID', -1001903402454);


// INLINE ACTIONS:
defined('INLINE_ACTION_VERIFY_ACCOUNT') or define('INLINE_ACTION_VERIFY_ACCOUNT', 1);
defined('INLINE_ACTION_REPLY_USER') or define('INLINE_ACTION_REPLY_USER', 2);
defined('INLINE_ACTION_SHOW_MESSAGE') or define('INLINE_ACTION_SHOW_MESSAGE', 3);
defined('INLINE_ACTION_REMOVE_ADMIN') or define('INLINE_ACTION_REMOVE_ADMIN', 4);
defined('INLINE_ACTION_VERIFY_NOTICE') or define('INLINE_ACTION_VERIFY_NOTICE', 5);
defined('INLINE_ACTION_REJECT_NOTICE') or define('INLINE_ACTION_REJECT_NOTICE', 0);
defined('INLINE_ACTION_SELECT_YOUR_NOTICE') or define('INLINE_ACTION_SELECT_YOUR_NOTICE', 6);
defined('INLINE_ACTION_DELEGATE_NOTICE') or define('INLINE_ACTION_DELEGATE_NOTICE', 7);


// TELEGRAM API GENERAL FUNCTIONS
function getUpdate($as_array = true) {
    $content = file_get_contents("php://input");
    return json_decode($content, $as_array);
}

function callMethod($method, ...$params) {
    // callMethod('method', 'key1', value1, 'key2', value2, ...)
    $payload = array("method" => $method);
    $len_params = count($params);
    for($i = 0; $i < $len_params - 1; $i += 2) {
        $payload[$params[$i]] = $params[$i + 1];
    }

    $req_handle = curl_init(URL_BASE);
    curl_setopt($req_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req_handle, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds for server connect timeout
    curl_setopt($req_handle, CURLOPT_TIMEOUT, 60); // response return timeout at 60 secs
    curl_setopt($req_handle, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($req_handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    return curl_exec($req_handle);
}

function getFileFrom($message): ?array
{
    $file_types = [FILE_PHOTO, FILE_VOICE, FILE_VIDEO, FILE_AUDIO, FILE_DOCUMENT];

    foreach($file_types as &$tag) {
        if(isset($message[$tag])) {
            $file_id = $tag != FILE_PHOTO
                ? $message[$tag][FILE_ID]
                : $message[$tag][count($message[FILE_PHOTO]) - 1][FILE_ID];
            return array(FILE_ID => $file_id, 'tag' => $tag, CAPTION_TAG => $message[CAPTION_TAG] ?? '');

        }
    }
    return null;
}

function isUsernameValid(&$username) {
    return preg_match("/^[a-zA-Z]{1}[A-Za-z0-9_]{4,}$/", $username) && $username[-1] != '_';
}

function wrapInlineButtonData($action, ...$params) {
    $callback_data = array("act" => $action);
    $len_params = count($params);
    for($i = 0; $i < $len_params - 1; $i += 2) {
        $callback_data[$params[$i]] = $params[$i + 1];
    }
    return json_encode($callback_data);
}
