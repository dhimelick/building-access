<?php
  ini_set('display_errors', 'On');

  $show = isset($_REQUEST["s"]) ? $_REQUEST["s"] : "";

  require_once "db.php";
  require_once "common.php";
  require_once "config.php";
  require_once "post_config.php";
  require_once "people_ldap.php";
  require_once "policy.php";
  require_once "registration.php";
  require_once "approval.php";
  require_once "show_data.php";
  if( SAFETY_MONITOR_SIGNUP ) {
    require_once "safety_monitor.php";
  }

  foreach( $download_handlers as $handler ) {
    if( $show == $handler->tag ) {
      call_user_func($handler->handler_fn);
      exit();
    }
  }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
  <link href="<?php echo WEB_APP_TOP ?>style.css" rel="stylesheet" type="text/css"/>
  <title><?php echo WEB_APP_TITLE ?></title>
</head>
<body>

<?php

if( !REMOTE_USER_NETID ) {
  echo "<p>Unauthenticated access denied.</p>\n";
} else {

  showNavbar($show);

  if( isset($_POST["form"]) ) {
    $form = $_POST["form"];

    foreach( $submit_handlers as $handler ) {
      if( $form == $handler->tag ) {
        call_user_func($handler->handler_fn,array(&$show));
      }
    }
  }

  foreach( $page_handlers as $handler ) {
    if( $show == $handler->tag ) {
      echo "<main role='main' class='",$handler->page_class,"'>\n";
      call_user_func($handler->handler_fn);
      echo "</main>\n";
    }
  }
}

?>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
<script src="tablesort.js"></script>
</body>
</html>

<?php

function showNavbar($show) {
  global $user_menu;
  global $admin_menu;

?>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
      <span class="navbar-brand" href="#"><img src="<?php echo WEB_APP_TOP ?>uwcrest_web_sm.png" height="30" class="d-inline-block align-top" alt="UW"> <?php echo WEB_APP_TITLE ?></span>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav mr-auto">
          <?php
	    if(count($user_menu)>1 || count($admin_menu) && isDeptAdmin()) {
	      foreach( $user_menu as $menu ) {
	        $active = $show == $menu->tag ? "active" : "";
		echo "<li class='nav-item $active'><a class='nav-link' href='",htmlescape($menu->url),"'>",htmlescape($menu->label),"</a></li>\n";
	      }
	    }
	    if(count($admin_menu) && isDeptAdmin()) {
	      echo "<li class='navbar-text admin-only'>&nbsp;&nbsp;<small>Admin:</small></li>\n";
	      foreach( $admin_menu as $menu ) {
	        $active = $show == $menu->tag ? "active" : "";
		echo "<li class='nav-item admin-only $active'><a class='nav-link' href='",htmlescape($menu->url),"'>",htmlescape($menu->label),"</a></li>\n";
	      }
	    }
	  ?>
        </ul>
        <a class='btn btn-secondary' href='https://<?php echo $_SERVER["SERVER_NAME"] ?>/Shibboleth.sso/Logout?return=https://login.wisc.edu/logout'>Log Out</a>&nbsp;&nbsp;
        <span class="navbar-text" style='color: rgb(255,0,255)'><?php echo htmlescape(getWebUserName()) ?></span>&nbsp;
      </div>
    </nav>
<?php
}
