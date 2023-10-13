<?php
require_once './database.php';
require_once './user.php';
require_once './telegram_api.php';

defined('MAX_COLUMN_LENGTH') or define('MAX_COLUMN_LENGTH', 30);
// UI constants
defined('CMD_STATISTICS') or define('CMD_STATISTICS', 'آمار ربات');
defined('CMD_ADD_ADMIN') or define('CMD_ADD_ADMIN', 'افزودن ادمین');
defined('CMD_REMOVE_ADMIN') or define('CMD_REMOVE_ADMIN', 'حذف ادمین');

defined('CMD_SUBMIT_NOTICE') or define('CMD_SUBMIT_NOTICE', 'ثبت آگهی 📣');
defined('CMD_ACCEPT_AGREEMENTS') or define('CMD_ACCEPT_AGREEMENTS', 'قوانین را می پذیرم ✅');
defined('CMD_USE_MY_USERNAME') or define('CMD_USE_MY_USERNAME', 'استفاده از یوزرنیم خودم');
defined('CMD_SUPPORT') or define('CMD_SUPPORT', 'پشتیبانی 💬');
defined('CMD_YOUR_NOTICES') or define('CMD_YOUR_NOTICES', 'آگهی های شما 📑');

defined('CMD_MAIN_MENU') or define('CMD_MAIN_MENU', 'بازگشت به منو ↪️');

defined('CMD_GOD_ACCESS') or define('CMD_GOD_ACCESS', '/godAccess');

function alignButtons($items, $action_value, $callback_value_column, $item_caption = null): array
{
    $buttons = array(array()); // an inline keyboard
    $current_row = 0;
    $column_length = 0;
    foreach($items as $item) {
        $caption = $item[$item_caption] ?? $item[$callback_value_column];
        array_unshift($buttons[$current_row], array(
            TEXT_TAG => $caption,
            CALLBACK_DATA => wrapInlineButtonData($action_value,
                                $callback_value_column, $item[$callback_value_column] ?? 0
                            )
        ));
        // buttons callback_data is as: type/id, type determines whether it's a course or a teacher;
        $column_length += strlen($caption);
        if($column_length > MAX_COLUMN_LENGTH) {
            $column_length = 0;
            $current_row++;
            $buttons[] = array();
        }
    }
    return $buttons;
}

function createInlineMenu($table_name, $menu_action, $item_caption, $filter_query = null): ?array
{
    $query = 'SELECT * FROM ' . $table_name;
    if($filter_query)
        $query .= ' WHERE ' . $filter_query;
    $items = Database::getInstance()->query($query);

    return $items && count($items) ? array(INLINE_KEYBOARD => alignButtons($items, $menu_action, DB_ITEM_ID, $item_caption)) : null;
    // return array(INLINE_KEYBOARD => alignButtons($items, $menu_action, DB_ITEM_ID, $item_caption));
}

function getMainMenu($user_mode): array
{
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
            'keyboard' => array(array(CMD_YOUR_NOTICES, CMD_SUBMIT_NOTICE))
    );
    if($user_mode == NORMAL_USER) {
        $keyboard['keyboard'][] = array(CMD_SUPPORT);
    }
    else {
        $keyboard['keyboard'][] = array(CMD_STATISTICS);
        if($user_mode == GOD_USER)
            $keyboard['keyboard'][] = array(CMD_REMOVE_ADMIN, CMD_ADD_ADMIN);
    }
    return $keyboard;
}

function backToMainMenuKeyboard($second_option=null): array
{
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
        'keyboard' => array(
            array(CMD_MAIN_MENU)
        )
    );
    if($second_option)
        $keyboard['keyboard'][] = array($second_option);

    return $keyboard;
}