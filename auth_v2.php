<?php
include_once('includes/load.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $req_fields = array('username','password');
  validate_fields($req_fields);

  $username = remove_junk($_POST['username']);
  $password = remove_junk($_POST['password']);
  $remember_me = isset($_POST['remember_me']) ? true : false;

  if (empty($errors)) {

    $user = authenticate_v2($username, $password);

    if ($user) {
      $session->login($user['id']);
      updateLastLogIn($user['id']);
      
      // Handle Remember Me
      if ($remember_me) {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        $user_id = $user['id'];
        
        // Store token in database
        global $db;
        $db->query("UPDATE users SET remember_token = '{$token}' WHERE id = '{$user_id}'");
        
        // Set cookie for 30 days
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        setcookie('remember_user', $user_id, time() + (30 * 24 * 60 * 60), '/', '', false, true);
      }

      $session->msg("s", "Hello ".$user['username'].", Welcome to BSU-INV.");

      if ($user['user_level'] === '1') {
        redirect('admin.php', false);
      } elseif ($user['user_level'] === '2') {
        redirect('super_admin.php', false);
      } else {
        redirect('home.php', false);
      }

    } else {
      $session->msg("d", "Sorry Username/Password incorrect.");
      redirect('login.php', false);
    }

  } else {
    $session->msg("d", $errors);
    redirect('login.php', false);
  }

} else {
  redirect('login.php', false);
}
?>


