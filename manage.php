<?php
require_once './telegram_api.php';
require_once './database.php';
require_once './user.php';
require_once  './menu.php';
require_once  './notice.php';

function isGodEnough(): bool
{
    // just trying to be funny:|
    return count(
            Database::getInstance()->query(
                'SELECT * FROM ' . DB_TABLE_USERS . ' WHERE ' . DB_USER_MODE . '=' . GOD_USER
        )) >= MAX_GODS;
}

function handleGospel(&$user, $whisper): ?string
{
    // handle god login requests
    $answer = null;
    switch($user[DB_USER_ACTION]) {
        case ACTION_WHISPER_GODS_NAME:
            if($whisper === GOD_NAME) {
                $answer = 'God\'s Secret:';
                if(!updateAction($user[DB_USER_ID], ACTION_WHISPER_GODS_SECRET)) {
                    $answer = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                    resetAction($user[DB_USER_ID]);
                }
            }
            break;
        case ACTION_WHISPER_GODS_SECRET:
            if($whisper === GOD_SECRET && !isGodEnough()) {
                if(!updateUserMode($user[DB_USER_ID], GOD_USER))
                    $answer = 'خطایی حین ثبت اطلاعات پیش اومد. دوباره تلاش کن!';
                $user[DB_USER_MODE] = GOD_USER; // update the old user object
                resetAction($user[DB_USER_ID]);
                $answer = 'Now you\'re God Almighty :)!';
            }
            break;
    }
    return $answer;
}

function addFootnote($text, $footnote = '@Persian_project') {
    return $text . "\n- - - - - - - - - - - - - - - - - - - -\n" . $footnote;
}

function handleCasualMessage(&$update) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $username = $update['message']['from']['username'] ?? null;

    $user = getUser($user_id);

    $message = $update['message'];
    $message_id = $update['message'][MESSAGE_ID_TAG];

    $data = $message[TEXT_TAG] ?? null;
    $response = handleGospel($user, $data);
    $keyboard = getMainMenu($user[DB_USER_MODE]);

    if(!$response) {
        switch($data) {
            case '/start':
                $response = 'خب! چه کاری میتونم برات انجام بدم؟';
                resetAction($user_id);
                break;
            case '/cancel':
                resetAction($user_id);
                $response = 'لغو شد!';
                break;
            case CMD_GOD_ACCESS:
                if(!isGodEnough()) {
                    $response = 'God\'s Name:';
                    if(!updateAction($user_id, ACTION_WHISPER_GODS_NAME)) {
                        $response = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                }
                break;
            case CMD_MAIN_MENU:
                // TODO: write sth?
                $response = 'خب! چی بکنیم؟';
                resetAction($user_id);
                break;
            case CMD_SUBMIT_NOTICE:
                $response = "⚠️⚖️ قوانین ثبت آگهی:

1️⃣ آگهی باید برای یک خواسته و نیازمندی باشه یعنی نمیتونی چندتا موضوع مختلف رو توی یه آگهی ثبت کنی!

2️⃣ توی متن آگهی نباید از لینک و موارد تبلیغاتی استفاده کنی!

3️⃣ متن آگهی باید منطبق بر عرف و بدون توهین باشه.

4️⃣ آگهی برای امتحان، پایان‌نامه و پرپوزال ممنوعه و تیم پشتیبانی این آگهی‌ها رو رد میکنه.";
                if(!updateAction($user_id, ACTION_SET_APPLIER_USERNAME)) {
                    $response = 'حین ورود به حالت ارسال آگهی مشکلی پیش اومد. لطفا دوباره تلاش کن!';
                    resetAction($user_id);
                } else
                    $keyboard = backToMainMenuKeyboard(CMD_ACCEPT_AGREEMENTS);
                break;
            case CMD_ACCEPT_AGREEMENTS:
                if($user[DB_USER_ACTION] == ACTION_SET_APPLIER_USERNAME) {
                    $response = "📝 یوزرنیم صاحب آگهی را وارد کنید:";
                    $keyboard = backToMainMenuKeyboard(CMD_USE_MY_USERNAME);
                } else
                    $response = 'متوجه نشدم. لطفا دوباره تلاش کن!';
                break;
            case CMD_YOUR_NOTICES:
                $keyboard = createInlineMenu(DB_TABLE_NOTICES, INLINE_ACTION_SELECT_YOUR_NOTICE, DB_NOTICES_DATE, DB_NOTICES_APPLIER_ID . "=$user_id");
                $response = $keyboard ? 'تمامی آگهی های شما بر حسب تاریخ ثبت، لیست شده اند. آگهی مورد نظر خود را انتخاب کنید:'
                                     : 'شما تاکنون هیچ آگهی ای ثبت نکرده اید!';
                break;
            default:
                $response = null;
                break;
        }
    }

    // check if user is submitting any notice
    if(!$response) {
        switch($user[DB_USER_ACTION]) {
            case ACTION_SET_APPLIER_USERNAME:
                $applier_username = null;
                if($data == CMD_USE_MY_USERNAME) {
                    if(!$username) {
                        $response = 'متاسفانه شما برای اکانت خود username تعریف نکرده اید. برای اینکه کاربران بتوانند به آگهی شما پاسخ دهند لطفا اول یوزرنیم خود را تنظیم کرده و سپس دوباره متن آگهی رو بفرست.';
                        $keyboard = backToMainMenuKeyboard(CMD_USE_MY_USERNAME);
                        break;
                    }
                    $applier_username = &$username;
                } else $applier_username = $data[0] != '@' ? $data : substr($data, 1);
                //check username
                if(isUsernameValid($applier_username)) {
                    $response = "🔸 لطفا متن آگهی خود را بصورت صحیح و بدون غلط املایی وارد کنید. \n\nمثال:\nبه فردی مسلط به ریاضی عمومی یک ، برای رفع اشکال نیازمندم.";
                    if(!updateAction($user_id, ACTION_SUBMIT_NOTICE) || !updateActionCache($user_id, $applier_username)) {
                        $response = 'حین ورود به حالت ارسال آگهی مشکلی پیش اومد. لطفا دوباره تلاش کن!';
                        resetAction($user_id);
                    } else
                        $keyboard = backToMainMenuKeyboard();

                } else {
                    $response = 'یوزرنیم وارد شده مطابق با الگوی تلگرام نیست. لطفا یوزرنیم معتبر وارد کنید:';
                    $keyboard = backToMainMenuKeyboard(CMD_USE_MY_USERNAME);
                }
                break;
            case ACTION_SUBMIT_NOTICE:
                $notice_id = submitNotice($user_id, $user[DB_USER_ACTION_CACHE], $data, $message_id);
                if($notice_id) {
                    $response = addFootnote(
                        addFootnote('✅ آگهی شما بصورت زیر با موفقیت برای مدیر ارسال شد و پس از تایید، داخل کانال قرار خواهد گرفت', $data));
                    foreach(getSuperiors() as &$admin) {
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $admin[DB_USER_ID],
                            TEXT_TAG, $data,
                            KEYBOARD, array(
                                INLINE_KEYBOARD => array(
                                    array(
                                        // row 1
                                        array(TEXT_TAG => 'تایید آگهی', CALLBACK_DATA => wrapInlineButtonData(
                                            INLINE_ACTION_VERIFY_NOTICE, DB_ITEM_ID, $notice_id
                                        )),
                                        array(TEXT_TAG => 'رد آگهی', CALLBACK_DATA => wrapInlineButtonData(
                                            INLINE_ACTION_REJECT_NOTICE, DB_ITEM_ID, $notice_id
                                        ))
                                    ),
                                    array(
                                        array(TEXT_TAG => "اکانت ثبت کننده آگهی", INLINE_URL_TAG => accountLink($username))
                                    )
                                )
                            )
                        );
                    }

                } else
                    $response = 'مشکلی حین ثبت آگهی شما پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید.';
                resetAction($user_id);
                break;
        }
    }

    if(!$response) {
        switch($user[DB_USER_MODE]) {
            case NORMAL_USER:
                if($user[DB_USER_ACTION] == ACTION_NONE) {
                    switch($data) {
                        case CMD_SUPPORT:
                            $response = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                            $keyboard = backToMainMenuKeyboard();
                            if(!updateAction($user_id, ACTION_WRITE_MESSAGE_TO_ADMIN)) {
                                $response = 'حین ورود به حالت ارسال پیام مشکلی پیش اومد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;

                        default:
                            $response = 'دستور مورد نظر صحیح نیست!';
                            break;
                    }
                } else if($user[DB_USER_ACTION] == ACTION_WRITE_MESSAGE_TO_ADMIN){
                    saveMessage($user_id, $message_id);
                    foreach(getSuperiors() as &$admin) {
                        callMethod(
                            METH_FORWARD_MESSAGE,
                            CHAT_ID, $admin[DB_USER_ID],
                            'from_chat_id', $chat_id,
                            MESSAGE_ID_TAG, $message_id
                        );
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $admin[DB_USER_ID],
                            TEXT_TAG, 'برای پاسخ به پیام بالا میتونی از گزینه زیر استفاده کنی',
                            KEYBOARD, array(
                                INLINE_KEYBOARD => array(
                                    array(array(TEXT_TAG => 'پاسخ', CALLBACK_DATA => wrapInlineButtonData(INLINE_ACTION_REPLY_USER,
                                        MESSAGE_ID_TAG, $message_id
                                    )))
                                )
                            )
                        );
                    }
                    $response = "پیام شما با موفقیت ارسال شد✅ \n در صورت لزوم، تیم پشتیبانی پاسخ را از طریق همین بات به شما اعلام خواهد کرد.";
                    resetAction($user_id);

                }
                break;
            case GOD_USER:
                if($data === CMD_ADD_ADMIN) {
                    $response = 'یک پیام از اکانت موردنظرت فوروارد کن:';
                    if(!updateAction($user_id, ACTION_ADD_ADMIN)) {
                        $response = 'مشکلی حین ورود به حالت اضافه کردن ادمین پیش اومده. لطفا دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                    break;
                } else if($user[DB_USER_ACTION] == ACTION_ADD_ADMIN) {
                    if(isset($message['forward_from'])) {

                        $target_id = $message['forward_from']['id'];
                        if(!updateUserMode($target_id, ADMIN_USER)) {
                            $response = 'متاسفانه مشکلی حین ثبت اکانت بعنوان ادمین پیش اومده. لطفا دوباره تلاش کن!';
                            resetAction($user_id);
                        } else {
                            $response = 'اکانت موردنظر بعنوان ادمین ثبت شد!';
                            // notify the target user
                            callMethod(METH_SEND_MESSAGE,
                                CHAT_ID, $target_id,
                                TEXT_TAG, 'تبریک! اکانتت به دسترسی ادمین ارتقا پیدا کرد.',
                                KEYBOARD, getMainMenu(ADMIN_USER)
                            );
                            if(!updateAction($user_id, ACTION_ASSIGN_USER_NAME) || !updateActionCache($user_id, $target_id)) {
                                $response .= ' اما حین ورود به حالت تعیین اسم مشکلی پیش اومد!';
                                resetAction($user_id);
                            } else {
                                $response .= ' حالا یک اسم براش تعیین کن:';
                            }
                        }
                    } else {
                        $response = 'اکانت موردنظر حالت مخفی رو فعال کرده. برای ارتقا یافتن به ادمین باید موقتا این حالت رو غیرفعال کنه!';
                        resetAction($user_id);
                    }

                    break;
                } else if($user[DB_USER_ACTION] == ACTION_ASSIGN_USER_NAME) {
                    // set message text as the name for the admin
                    // cache is the target user id
                    $response = assignUserName($user[DB_USER_ACTION_CACHE], $data) ? 'اسم این کاربر با موفقیت ثبت شد.'
                        : 'مشکلی در ثبت اسم این کاربر پیش اومد!';
                    resetAction($user_id);
                    break;
                } else if($data === CMD_REMOVE_ADMIN) {
                    $keyboard = createInlineMenu(DB_TABLE_USERS, INLINE_ACTION_REMOVE_ADMIN, DB_ITEM_NAME, DB_USER_MODE . '=' . ADMIN_USER);
                    $response = $keyboard ? 'روی شخص موردنظرت کلیک کن تا از حالت ادمین خارج بشه:'
                                          : 'هیج ادمینی تعریف نشده است!';
                    break;
                }
            case ADMIN_USER:
                // get admin's action value
                if(!$user[DB_USER_ACTION]) {
                    // if action value is none
                    switch($data) {
                        case CMD_STATISTICS:
                            $response = "آمار ربات:" . "\n";
                            foreach(getStatistics() as $field=>$stat) {
                                $response .= "{$stat['fa']}: {$stat['total']} \n";
                            }
                            break;
                        default:
                            $response = 'دستور مورد نظر صحیح نیست!';
                            break;
                    }
                }
                else {
                    switch($user[DB_USER_ACTION]) {
                        case ACTION_WRITE_REPLY_TO_USER:
                            $msg = getMessage($user[DB_USER_ACTION_CACHE]);
                            if($msg) {
                                callMethod(METH_SEND_MESSAGE,
                                    CHAT_ID, $msg[DB_MESSAGES_SENDER_ID],
                                    TEXT_TAG, 'ادمین پیام شما را پاسخ داد.',
                                    REPLY_TO_TAG, $msg[DB_ITEM_ID],
                                     KEYBOARD, array(
                                        INLINE_KEYBOARD => array(
                                            array(
                                                array(TEXT_TAG => 'مشاهده',
                                                    CALLBACK_DATA => wrapInlineButtonData(INLINE_ACTION_SHOW_MESSAGE,
                                                        'rid', $message_id,
                                                        'by', $chat_id,
                                                        'to', (int)$msg[DB_ITEM_ID]
                                                    )
                                                )
                                            )
                                        )
                                     )
                                );
                                markMessageAsAnswered($user[DB_USER_ACTION_CACHE]);
                                $response = 'پاسخ شما با موفقیت ارسال شد.';
                            } else {
                                $response = 'چنین پیامی اصلا وجود نداره که بخوای جوابش رو بدی!';
                            }
                            resetAction($user_id);
                            break;
                        default:
                            $response = 'عملیات موردنظر تعریف نشده است!';
                            resetAction($user_id);
                            break;
                    }
                }
                break;
        }
    }
    if($keyboard)
        callMethod(
            METH_SEND_MESSAGE,
            CHAT_ID, $chat_id,
            TEXT_TAG, $response,
            REPLY_TO_TAG, $message_id,
            KEYBOARD, $keyboard

        );
    else
        callMethod(
            METH_SEND_MESSAGE,
            CHAT_ID, $chat_id,
            TEXT_TAG, $response,
            REPLY_TO_TAG, $message_id
        );
}

function handleCallbackQuery(&$update) {
    $callback_id = $update[CALLBACK_QUERY]['id'];
    $chat_id = $update[CALLBACK_QUERY]['message']['chat']['id'];
    $message_id = $update[CALLBACK_QUERY]['message'][MESSAGE_ID_TAG];
    $user_id = $update[CALLBACK_QUERY]['from']['id'];
    $data = json_decode($update[CALLBACK_QUERY]['data'], true);
    $text = $update[CALLBACK_QUERY]['message']['text'];
    $answer = null;
    $keyboard = null;
    $user = getUser($user_id);
    switch($data['act']) {
        case INLINE_ACTION_VERIFY_ACCOUNT:
            // check membership is ok
            // because if it wasn't ok, this function couldn't be called
            $answer = 'مرسی که عضو کانال های ما شدی :)';
            callMethod(METH_SEND_MESSAGE,
                CHAT_ID, $chat_id,
                TEXT_TAG, 'چه کاری میتونم برات انجام بدم؟',
                KEYBOARD,  getMainMenu($user[DB_USER_MODE])
            );
            break;
        case INLINE_ACTION_SHOW_MESSAGE:
            if(isset($data['rid']) && isset($data['by'])) {
                callMethod(
                    METH_COPY_MESSAGE,
                    MESSAGE_ID_TAG, $data['rid'],
                    CHAT_ID, $chat_id,
                    'from_chat_id', $data['by'],
                    REPLY_TO_TAG, $data['to'] ?? 0
                );
                callMethod(METH_DELETE_MESSAGE,
                    MESSAGE_ID_TAG, $message_id,
                    CHAT_ID, $chat_id
                ); // remove the show message box
            } else
                $answer = 'خطای غیرمنتظره حین باز کردن پیام اتفاق افتاد!';
            break;

        case INLINE_ACTION_VERIFY_NOTICE:
            if(isset($data[DB_ITEM_ID])) {
                $result = setNoticeVerificationState($data[DB_ITEM_ID]);
                if(!isset($result['err']) && isset($result['notice'])) {
                    // send the notice to the channel
                    $channel_response = callMethod(METH_SEND_MESSAGE,
                        TEXT_TAG, addFootnote($result['notice'][DB_NOTICES_TEXT] . "\n@" . $result['notice'][DB_NOTICES_APPLIER_USERNAME]),
                        CHAT_ID, PERSIAN_PROJECT_CHANNEL_ID,
                        KEYBOARD, array(
                            INLINE_KEYBOARD => array(
                                array(
                                    array(TEXT_TAG => 'برای ثبت رایگان آگهی خود کلیک کنید',
                                    INLINE_URL_TAG => accountLink(PERSIAN_PROJECT_BOT_USERNAME))
                                )
                            )
                        )
                    );
                    $channel_response = json_decode($channel_response, true);
                    $channel_msg_id = $channel_response['result'][MESSAGE_ID_TAG] ?? null;
                    $warning = $channel_msg_id && linkNoticeToChannelMessage($result['notice'][DB_ITEM_ID], $channel_msg_id)
                        ? '' : "\n\n هشدار: متاسفانه حین لینک آگهی به پیام مربوطه در کانال مشکلی پیش آمد. این باعث میشود برخی عملیات های مربوط به این آگهی به درستی عمل نکنند!" .
                            "\n آیدی آگهی: " . $result['notice'][DB_ITEM_ID];

                    callMethod(METH_SEND_MESSAGE,
                        TEXT_TAG, addFootnote('✅ آگهی شما توسط مدیر تایید و با موفقیت داخل کانال ثبت گردید.'),
                        CHAT_ID, $result['notice'][DB_NOTICES_APPLIER_ID],
                        REPLY_TO_TAG, $result['notice'][DB_NOTICES_USER_MESSAGE_ID],
                        KEYBOARD, array(
                            INLINE_KEYBOARD => array(
                                array(
                                    array(TEXT_TAG => 'کانال',
                                        INLINE_URL_TAG => PERSIAN_PROJECT_CHANNEL_URL
                                    ),
                                    array(TEXT_TAG => 'واگذاری',
                                        CALLBACK_DATA => wrapInlineButtonData(INLINE_ACTION_DELEGATE_NOTICE,
                                            DB_ITEM_ID, $result['notice'][DB_ITEM_ID]
                                        )
                                    )
                                )
                            )
                        )
                    );
                    $answer = addFootnote($text, 'آگهی با موفقیت تایید شد و در کانال قرار گرفت.' . $warning);
                    break;
                } else $answer = addFootnote($text, $result['err']);
            }
            else
                $answer = $text . '\n----------------\n آیدی آگهی مورد نظر اشتباه است!';
            break;
        case INLINE_ACTION_REJECT_NOTICE:
            if(isset($data[DB_ITEM_ID])) {
                $result = setNoticeVerificationState($data[DB_ITEM_ID], NOTICE_REJECTED);
                if(!isset($result['err']) && isset($result['notice'])) {
                    callMethod(METH_SEND_MESSAGE,
                        TEXT_TAG, '❌ کاربر گرامی آگهی شما از طرف مدیریت تایید نشد. برای اطلاع از علت این امر از گزینه پشتیبانی استفاده کنید.',
                        CHAT_ID, $result['notice'][DB_NOTICES_APPLIER_ID],
                        REPLY_TO_TAG, $result['notice'][DB_NOTICES_USER_MESSAGE_ID]
                    );
                    $answer = addFootnote($text, " آگهی ریجکت شد.");
                } else $answer = addFootnote($text, $result['err']);
                break;
            }
            else
                $answer = 'آیدی آگهی مورد نظر اشتباه است!';
            break;
        case INLINE_ACTION_DELEGATE_NOTICE:
            if(isset($data[DB_ITEM_ID])) {
                if(setNoticeState($data[DB_ITEM_ID])) {
                    $notice = getNotice($data[DB_ITEM_ID]);
                    callMethod(
                        METH_EDIT_MESSAGE,
                        MESSAGE_ID_TAG, $notice[DB_NOTICES_CHANNEL_MESSAGE_ID],
                        TEXT_TAG, addFootnote(addFootnote($notice[DB_NOTICES_TEXT], " آگهی واگذار شد.")),
                        CHAT_ID, PERSIAN_PROJECT_CHANNEL_ID,
                        KEYBOARD, array(
                            INLINE_KEYBOARD => array(
                                array(
                                    array(TEXT_TAG => 'برای ثبت رایگان آگهی خود کلیک کنید',
                                    INLINE_URL_TAG => accountLink(PERSIAN_PROJECT_BOT_USERNAME))
                                )
                            )
                        )
                    );
                    $answer = addFootnote($text, " آگهی موردنظر واگذار شد.");
                } else
                    $answer =  addFootnote($text, " متاسفانه واگذاری این آگهی با مشکل مواجه شد. لطفا لحظاتی دیگر تلاش کنید.");

            } else $answer =  addFootnote($text, " چنین آگهی ای یافت نشد.");
            break;
        case INLINE_ACTION_SELECT_YOUR_NOTICE:
            if(isset($data[DB_ITEM_ID]) && ($notice = getNotice($data[DB_ITEM_ID])) != null) {
                $answer = "متن آگهی: \n" . $notice[DB_NOTICES_TEXT] . "\n\n وضعیت: \n" . ($notice[DB_NOTICES_VERIFIED] == 1 ? "تایید شده و در کانال قرار گرفته است."
                                                                                           : (!$notice[DB_NOTICES_VERIFIED] ?
                                                                                                "آگهی مطابق با قوانین نبوده و رد شده است." : "در انتظار بررسی"))
                                        . "\n\n تاریخ ثبت: \n" . $notice[DB_NOTICES_DATE];
                if($notice[DB_NOTICES_STATE] != NOTICE_OPEN)
                    $answer .= $notice[DB_NOTICES_STATE] == NOTICE_DELEGATED ? "\n\n آگهی واگذار شده است." : "\n\n آگهی بسته شده است.";

                $keyboard = array(
                    INLINE_KEYBOARD => array(
                        array(
                            array(TEXT_TAG => 'واگذاری',
                                CALLBACK_DATA => wrapInlineButtonData(INLINE_ACTION_DELEGATE_NOTICE,
                                    DB_ITEM_ID, $notice[DB_ITEM_ID]
                                )
                            )
                        )
                    )
                );
            } else $answer = 'آگهی مورد نظر پیدا نشد!';
            break;
        case INLINE_ACTION_REPLY_USER:
            // admin is attempting to answer a message
            updateAction($user_id, ACTION_WRITE_REPLY_TO_USER);
            updateActionCache($user_id, $data[MESSAGE_ID_TAG]);
            $answer = 'پاسخ خودتو بنویس: (لغو /cancel)';
            if(isMessageAnswered($data[MESSAGE_ID_TAG]))
                callMethod('answerCallbackQuery',
                    'callback_query_id', $callback_id,
                    TEXT_TAG, 'این پیام قبلا پاسخ داده شده است!',
                    'show_alert', true
                );
            callMethod(
                METH_SEND_MESSAGE,
                CHAT_ID, $chat_id,
                TEXT_TAG, $answer,
                REPLY_TO_TAG, $message_id,
                KEYBOARD, backToMainMenuKeyboard()
            );
            exit();

        case INLINE_ACTION_REMOVE_ADMIN:
            if(!updateUserMode($data[DB_ITEM_ID], NORMAL_USER)) {
                $answer = 'مشکلی حین تغییر کاربری پیش اومد. لطفا دوباره تلاش کن!';
            } else $answer = 'کاربر موردنظر به دسترسی عادی بازگشت!';
            break;

        default:
            // TODO: sth is wrong!
            $answer = 'گزینه انتخاب شده اشتباه است!';
            resetAction($user_id);
            break;
    }
    if($keyboard)
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            MESSAGE_ID_TAG, $message_id,
            TEXT_TAG, $answer,
            KEYBOARD, $keyboard
        );
    else
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            MESSAGE_ID_TAG, $message_id,
            TEXT_TAG, $answer
        );
}
