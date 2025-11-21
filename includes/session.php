<?php
class Session {

 public $msg;
 private $user_is_logged_in = false;

 function __construct(){
   $this->flash_msg();
   $this->userLoginSetup();
 }

  public function isUserLoggedIn(){
    return $this->user_is_logged_in;
  }
  public function login($user_id){
    $_SESSION['id'] = $user_id;
  }
  private function userLoginSetup()
  {
    if(isset($_SESSION['id']))
    {
      $this->user_is_logged_in = true;
    } else {
      // Check for remember me cookie
      if(isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
        $this->attemptCookieLogin();
      } else {
        $this->user_is_logged_in = false;
      }
    }

  }
  
  private function attemptCookieLogin()
  {
    global $db;
    
    // Check if database is loaded yet
    if (!isset($db) || $db === null) {
      $this->user_is_logged_in = false;
      return;
    }
    
    // Check if remember_token column exists
    $check_column = $db->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
    if ($db->num_rows($check_column) === 0) {
      // Column doesn't exist yet, clear cookies and return
      $this->clearRememberMeCookies();
      $this->user_is_logged_in = false;
      return;
    }
    
    $token = $db->escape($_COOKIE['remember_token']);
    $user_id = (int)$_COOKIE['remember_user'];
    
    // Verify token matches database
    $sql = "SELECT id FROM users WHERE id = '{$user_id}' AND remember_token = '{$token}' LIMIT 1";
    $result = $db->query($sql);
    
    if($result && $db->num_rows($result) === 1) {
      // Valid token - log user in
      $_SESSION['id'] = $user_id;
      $this->user_is_logged_in = true;
      
      // Update last login time
      updateLastLogIn($user_id);
    } else {
      // Invalid token - clear cookies
      $this->clearRememberMeCookies();
      $this->user_is_logged_in = false;
    }
  }
  
  private function clearRememberMeCookies()
  {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    setcookie('remember_user', '', time() - 3600, '/', '', false, true);
    // Note: We don't clear remember_username here - it persists even after logout
    // This allows username to be pre-filled without auto-login
  }
public function logout(){
    // Clear remember me token from database
    if(isset($_SESSION['id'])) {
        global $db;
        $user_id = (int)$_SESSION['id'];
        
        // Check if remember_token column exists before updating
        $check_column = $db->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
        if ($db->num_rows($check_column) > 0) {
            $db->query("UPDATE users SET remember_token = NULL WHERE id = '{$user_id}'");
        }
    }
    
    // Clear remember me cookies
    $this->clearRememberMeCookies();
    
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}


public function msg($type ='', $msg =''){
    if(!empty($msg)){
       if(strlen(trim($type)) == 1){
         $type = str_replace( array('d', 'i', 'w','s'), array('danger', 'info', 'warning','success'), $type );
       }
       $_SESSION['msg'][$type] = $msg;
    } else {
      // Always return the latest message array
      return $this->msg ?? null;
    }
}


  private function flash_msg(){
    if(isset($_SESSION['msg'])) {
      $this->msg = $_SESSION['msg'];
      unset($_SESSION['msg']);
    } else {
      $this->msg;
    }
  }
}

$session = new Session();
$msg = $session->msg();

?>
