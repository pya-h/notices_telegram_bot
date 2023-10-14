<?php
require_once './database.php';
require_once './telegram_api.php';
require_once './user.php';

function submitNotice($applier_id, $applier_username, $text, $message_id = null, $file=null) {
    $fields = implode(',', array(DB_NOTICES_APPLIER_ID, DB_NOTICES_APPLIER_USERNAME, DB_NOTICES_TEXT,
        DB_NOTICES_DATE, DB_NOTICES_USER_MESSAGE_ID, DB_NOTICES_FILE_ID, DB_NOTICES_FILE_TYPE));
    $text = !hasEmojis($text) ? $text : urlencode($text);
    return Database::getInstance()->insert(
        'INSERT INTO ' . DB_TABLE_NOTICES . " ($fields)" . ' VALUES (:applier_id, :applier_username, :text, :date, :message_id, :file_id, :type)',
            array('applier_id' => $applier_id,
                'applier_username' => $applier_username, 'text' => $text,
                'date' => date('Y-m-d', time()), MESSAGE_ID_TAG => $message_id, 'file_id' => $file[FILE_ID] ?? null, 'type' => $file['tag'] ?? null)
    );

}

function getNotice($notice_id): ?array {
    $notices = Database::getInstance()->query('SELECT * FROM ' . DB_TABLE_NOTICES . ' WHERE ' . DB_ITEM_ID . '=:notice_id',
        array('notice_id' => $notice_id)
    );
    if(!count($notices))
        return null;
    $notices[0][DB_NOTICES_TEXT] = urldecode($notices[0][DB_NOTICES_TEXT]);
    return $notices[0];
}

function setNoticeVerificationState($notice_id, $verification_state=NOTICE_VERIFIED): array {
    $notice = getNotice($notice_id);
    $err = null;
    if(!$notice)
        $err = 'چنین آگهی ای در دیتابیس وجود ندارد!';
    else if($notice[DB_NOTICES_VERIFIED] == NOTICE_VERIFIED)
        $err = 'این آگهی قبلا توسط تیم ادمین تایید شده است و در کانال قرار گرافته است!';
    else if($notice[DB_NOTICES_VERIFIED] == NOTICE_REJECTED)
        $err = 'این آگهی قبلا توسط تیم ادمین رد شده است!';
    else {
        if(!Database::getInstance()->update('UPDATE ' . DB_TABLE_NOTICES . ' SET ' . DB_NOTICES_VERIFIED . "=$verification_state WHERE " . DB_ITEM_ID . '=:notice_id',
                array('notice_id' => $notice_id)))
            $err = 'مشکلی حین تایید اعتبار آگهی پیش آمد. لطفا لحظاتی بعد دوباره تلاش کن!';
    }
    return array('notice' => $notice, 'err' => $err);
}

function hasEmojis( $string ): bool {
    return preg_match( '([*#0-9](?>\\xEF\\xB8\\x8F)?\\xE2\\x83\\xA3|\\xC2[\\xA9\\xAE]|\\xE2..(\\xF0\\x9F\\x8F[\\xBB-\\xBF])?(?>\\xEF\\xB8\\x8F)?|\\xE3(?>\\x80[\\xB0\\xBD]|\\x8A[\\x97\\x99])(?>\\xEF\\xB8\\x8F)?|\\xF0\\x9F(?>[\\x80-\\x86].(?>\\xEF\\xB8\\x8F)?|\\x87.\\xF0\\x9F\\x87.|..(\\xF0\\x9F\\x8F[\\xBB-\\xBF])?|(((?<zwj>\\xE2\\x80\\x8D)\\xE2\\x9D\\xA4\\xEF\\xB8\\x8F\k<zwj>\\xF0\\x9F..(\k<zwj>\\xF0\\x9F\\x91.)?|(\\xE2\\x80\\x8D\\xF0\\x9F\\x91.){2,3}))?))', $string );
}

function linkNoticeToChannelMessage($notice_id, $channel_message_id) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_NOTICES . ' SET ' . DB_NOTICES_CHANNEL_MESSAGE_ID
         . "=:channel_message_id WHERE " . DB_ITEM_ID . '=:notice_id',
            array('notice_id' => $notice_id, 'channel_message_id' => $channel_message_id));
}

function setNoticeState($notice_id, $state=NOTICE_DELEGATED) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_NOTICES . ' SET ' . DB_NOTICES_STATE
         . "=:state WHERE " . DB_ITEM_ID . '=:notice_id',
            array('notice_id' => $notice_id, 'state' => $state));
}