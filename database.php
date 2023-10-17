<?php
// config
defined('DB_HOST') or define('DB_HOST', 'localhost');
defined('DB_USER') or define('DB_USER', 'dbusername');
defined('DB_PASSWORD') or define('DB_PASSWORD', 'pass');
defined('DB_NAME') or define('DB_NAME','dbname');

// database tables
defined('DB_TABLE_USERS') or define('DB_TABLE_USERS','users');
defined('DB_TABLE_NOTICES') or define('DB_TABLE_NOTICES','notices');
defined('DB_TABLE_MESSAGES') or define('DB_TABLE_MESSAGES','messages');
defined('DB_TABLE_NOTIFICATIONS') or define('DB_TABLE_NOTIFICATIONS','notifications');

// database table:COMMON fields
defined('DB_ITEM_ID') or define('DB_ITEM_ID','id');
defined('DB_ITEM_NAME') or define('DB_ITEM_NAME','name'); // for both course and teacher tables

// database table user fields:
defined('DB_USER_ID') or define('DB_USER_ID','id');
defined('DB_USER_ACTION') or define('DB_USER_ACTION','action');
defined('DB_USER_MODE') or define('DB_USER_MODE','mode');
defined('DB_USER_ACTION_CACHE') or define('DB_USER_ACTION_CACHE','action_cache');

//database table:messages fields
defined('DB_MESSAGES_SENDER_ID') or define('DB_MESSAGES_SENDER_ID','sender_id');
defined('DB_MESSAGES_ANSWERED') or define('DB_MESSAGES_ANSWERED','answered');

//database table:notices fields:
defined('DB_NOTICES_TEXT') or define('DB_NOTICES_TEXT','text');
defined('DB_NOTICES_APPLIER_ID') or define('DB_NOTICES_APPLIER_ID','applier_id');
defined('DB_NOTICES_APPLIER_USERNAME') or define('DB_NOTICES_APPLIER_USERNAME','username');
defined('DB_NOTICES_FILE_ID') or define('DB_NOTICES_FILE_ID','file_id');
defined('DB_NOTICES_FILE_TYPE') or define('DB_NOTICES_FILE_TYPE','file_type');
defined('DB_NOTICES_STATE') or define('DB_NOTICES_STATE','state'); // closed, open, handled?
defined('DB_NOTICES_DATE') or define('DB_NOTICES_DATE','date');
defined('DB_NOTICES_VERIFIED') or define('DB_NOTICES_VERIFIED','verified');
defined('DB_NOTICES_USER_MESSAGE_ID') or define('DB_NOTICES_USER_MESSAGE_ID','user_msg_id');
defined('DB_NOTICES_CHANNEL_MESSAGE_ID') or define('DB_NOTICES_CHANNEL_MESSAGE_ID','channel_msg_id');

//database table:notices values
defined('NOTICE_VERIFIED') or define('NOTICE_VERIFIED', 1);
defined('NOTICE_REJECTED') or define('NOTICE_REJECTED', 0);
defined('NOTICE_VERIFICATION_PENDING') or define('NOTICE_VERIFICATION_PENDING', -1);

defined('NOTICE_CLOSED') or define('NOTICE_CLOSED', -1);
defined('NOTICE_OPEN') or define('NOTICE_OPEN', 0);
defined('NOTICE_DELEGATED') or define('NOTICE_DELEGATED', 1);

//database table:notifications fields
defined('DB_NOTIFICATIONS_USER_ID') or define('DB_NOTIFICATIONS_USER_ID', 'user_id');
defined('DB_NOTIFICATIONS_MESSAGE_ID') or define('DB_NOTIFICATIONS_MESSAGE_ID', 'message_id');
defined('DB_NOTIFICATIONS_RELATED_NOTICE_ID') or define('DB_NOTIFICATIONS_RELATED_NOTICE_ID', 'notice_id');

//database: god mode
defined('GOD_NAME') or define('GOD_NAME','superuser name');
defined('GOD_SECRET') or define('GOD_SECRET','superuser pass');

defined('MAX_GODS') or define('MAX_GODS', 2);

// user modes:
defined('NORMAL_USER') or define('NORMAL_USER', 0);
defined('ADMIN_USER') or define('ADMIN_USER', 1);
defined('GOD_USER') or define('GOD_USER', 2);

// database engine
class Database {
  private $connection;
  private static $database;

  public static function getInstance($option = null): Database
  {
    if (self::$database == null){
      self::$database = new Database($option);
    }

    return self::$database;
  }

  private function __construct(){
    $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($this->connection->connect_error) {
      echo "Connection failed: " . $this->connection->connect_error;
      exit;
    }
    $this->connection->query("SET NAMES 'utf8'");
  }

  public function update(string $sql_query, array $query_data = array()) {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return $result;
  }

  public function insert($sql_query, array $query_data = array()){
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return mysqli_insert_id($this->connection);;
  }

  private function safeQuery(string $sql_query, ?array $query_data) {
    if($query_data)
      foreach ($query_data as $key=>$value){
        $value = $this->connection->real_escape_string($value);
        $value = "'$value'";

        $sql_query = str_replace(":$key", $value, $sql_query);
      }
    return $this->connection->query($sql_query);
  }

  public function query(string $sql_query, ?array $query_data = null, ?string $specific_column = null): ?array {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }

    $records = array();
    if ($result->num_rows == 0) {
      return $records;
    }
    while($row = $result->fetch_assoc()) {
      $records[] = !$specific_column ? $row : $row[$specific_column];
    }
    return $records;
  }

  public function getConnection(): mysqli
  {
    return $this->connection;
  }

  public function fuckoff() {
    $this->connection->close();
  }

}

// project specific functions:

function getStatistics(): array {
    $db = Database::getInstance();
    $users = $db->query('SELECT * FROM ' . DB_TABLE_USERS);
    $other_tables = array(DB_TABLE_NOTICES => 'تعداد کل آگهی ها', DB_TABLE_MESSAGES => "تعداد پیام های کاربران");
    // TODO: most active users?
    $admins = array_filter($users, function($item) {
        return $item[DB_USER_MODE] == ADMIN_USER;
    });
    $stats = array(DB_TABLE_USERS => array('total' => count($users), 'fa' => 'تعداد کل کاربران ربات'), 'admins' => array('total' => count($admins), 'fa' => 'تعداد ادمین ها'));
    foreach($other_tables as $table=>$table_fa) {
        $result = $db->query("SELECT COUNT(*) AS TOTAL FROM $table");
        $stats[$table] = array('total' => $result[0]['TOTAL'] ?? 0, 'fa' => $table_fa);
    }
    return $stats;
}
