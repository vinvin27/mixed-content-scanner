<?php

/**
 * Plugin Name: Scan Mixed Content
 * Version: 1.0
 * Plugin URI: https://mixed-content.info/plugin-wordpress
 * Description: Add mixed content scanner to your website using [SCAN_MIXED_CONTENT] shortcode
 * Author: Vincent Guesne
 * Author URI: https://vincent-guesne.fr
 * Text Domain: scan-mixed-content
 * Domain Path: /languages/
 * License: GPL v3
 */


add_action( 'wp_ajax_mon_action', 'mon_action' );
add_action( 'wp_ajax_nopriv_mon_action', 'mon_action' );

function mon_action() {

  /**
   * mixed-content-scan - A CLI Script to crawl+scan HTTPS-enabled websites for Mixed Content.
   * @author Bramus! <bramus@bram.us>
   */
  // Error settings
  error_reporting(E_ERROR);
  ini_set('display_errors', 'on');
  // Require autoloader
  if (file_exists(__DIR__ . '/mixed-content-scan-master/vendor/autoload.php')) { // Installed Locally
      require __DIR__ . '/mixed-content-scan-master/vendor/autoload.php';
  } elseif (file_exists(__DIR__ . '/../../mixed-content-scan-master/autoload.php')) { // Installed Globally
      require __DIR__ . '/../../mixed-content-scan-master/autoload.php';
  } else {
      exit('Make sure you run `composer install` first, before running this scanner');
  }


  $argv = array(
    'rootUrl' =>'https://vincent-guesne.fr'
  );
  // Define CLI Options/Arguments
  $cli = new \Garden\Cli\Cli();
  /*$cli->description('Scan your HTTPS-enabled website for Mixed Content.')
      ->opt('loglevel', 'The Monolog loglevel to log at. Defaults to 200.', false)
      ->opt('output', 'Stream to write to. Defaults to `php://stdout`', false)
      ->opt('format', 'Output format to use. Allowed values: `ansi`, `no-ansi`, or `json`. Defaults to `ansi`', false)
      ->opt('no-crawl', 'Don\'t crawl scanned pages for new pages.', false)
      ->opt('no-check-certificate', 'Don\'t check the certificate for validity.', false)
      ->opt('timeout', 'How long to wait for each request to complete. Defaults to 10000ms.', false, 'integer')
      ->opt('input', 'Specify a file containing a list of links as the source, instead of parsing the passed in URL. Automatically enables `--no-crawl`', false)
      ->opt('ignore', 'File containing URL patterns to ignore. See readme shipping with release on how to build this file.', false)
      ->opt('user-agent', 'Set the user agent to be used when crawling', false)
      ->arg('rootUrl', 'The URL to start scanning at', false);*/
  // Parse and return cli options
  //$opts = $cli->parse($argv, true)->getOpts();
  //$args = $cli->parse($argv, true)->getArgs();

  $args = $opts = array(
    'rootUrl' =>'https://vincent-guesne.fr',
    'input' => false,
    'format' => 'json'
  );
  // Determine numerical log level
  if (isset($opts['loglevel']) && !is_int($opts['loglevel'])) {
      $levels = \Monolog\Logger::getLevels();
      if (array_key_exists(strtoupper($opts['loglevel']), $levels)) {
          $opts['loglevel'] = $levels[ strtoupper($opts['loglevel']) ];
      }
  }
  $loglevel = isset($opts['loglevel']) ? (int) $opts['loglevel'] : 200;
  // Create logger writing to the specified output
  $logger = new \Monolog\Logger('MCS');
  $handler = new \Monolog\Handler\StreamHandler((isset($opts['output']) ? $opts['output'] : 'php://stdout'), $loglevel);
  // Define formatter to use
  if (!isset($opts['format'])) $opts['format'] = 'ansi';
  switch($opts['format']) {
      case 'no-ansi':
          $formatter = new \Monolog\Formatter\LineFormatter(null, null, false, true);
          break;
      case 'json':
          $formatter = new \Monolog\Formatter\JsonFormatter();
          break;
      case 'ansi':
      default:
          $formatter = new \Bramus\Monolog\Formatter\ColoredLineFormatter(null, null, null, false, true);
          break;
  }
  // Link formatter to logger
  $handler->setFormatter($formatter);
  $logger->pushHandler($handler);
  // Define the rootURL and/or the list of links to scan
  $urlsToQueue = [];
  /*
  if (isset($opts['input'])) {
      // Set the rootUrl to the wildcard
      $rootUrl = '*';
      // Open the file and make sure it's readable
      try {
          $fi = new \SplFileObject($opts['input']);
      } catch(\Exception $e) {
        echo 'foo33';
          $logger->addError('Please make sure the file containing the list of links passed in via `--input` exists and is readable.');
          exit();
      }
      if (!$fi->isFile() || !$fi->isReadable()) {
        echo 'foo22';
          $logger->addError('Please make sure the file containing the list of links passed in via `--input` exists and is readable.');
          exit();
      }
      // Loop the contents and queue all URLs
      foreach ($fi as $link) {
          if (parse_url(trim($link)) && (trim($link) != '')) $urlsToQueue[] = trim($link);
      }
      // Make sure `--no-crawl` is set when working with `--input-file`
      $opts['no-crawl'] = true;
      // Give a notice if we have ignored any passed in rootUrl
      if (isset($args['rootUrl'])) $logger->addNotice('Using an input-file as source. Ignoring the passed in $rootUrl');
  } else {
      if (!isset($args['rootUrl']) || !parse_url($args['rootUrl'])) {
        echo 'foo';
          $cli->writeHelp();
          // $logger->addError('Please pass the URL to scan (rootUrl) as the 1st argument to this script. E.g. `mixed-content-scan $url`');
          exit();
      }
      $rootUrl = $args['rootUrl'];
  }
  */
  // Define the ignore patterns
  $ignorePatterns = [];
  if (isset($opts['ignore'])) {
      // Open the file and make sure it's readable
      try {
          $fi = new \SplFileObject($opts['ignore']);
      } catch(\Exception $e) {
        echo 'foo';
          $logger->addError('Please make sure the file containing the ignore patterns passed in via `--ignore` exists and is readable.');
          exit();
      }
      if (!$fi->isFile() || !$fi->isReadable()) {
        echo 'foo2';
          $logger->addError('Please make sure the file containing the ignore patterns passed in via `--ignore` exists and is readable.');
          exit();
      }
      // Loop the contents and extract all patterns
      foreach ($fi as $pattern) {
          if ((strlen(trim($pattern)) > 0) && (substr($pattern, 0, 1) != '#')) $ignorePatterns[] = trim($pattern);
      }
  }
  // Do we need to crawl or not?
  if (isset($opts['no-crawl'])) {
      $crawl = false;
  } else {
      $crawl = true;
  }
  // Do we need to check the certificate or not?
  if (isset($opts['no-check-certificate'])) {
      $checkCertificate = false;
  } else {
      $checkCertificate = true;
  }
  // Set the timeout value for each request
  if (isset($opts['timeout'])) {
      $timeout = $opts['timeout'];
      if (!(is_numeric($timeout) && $timeout > 0 && $timeout == round($timeout, 0))) {
          $timeout = 10000;
          $logger->addNotice('Invalid timeout value specified. Using default value of 10000ms.');
      }
  } else {
      $timeout = 10000;
  }
  // Set the user agent to use when crawling
  if (isset($opts['user-agent'])) {
      $userAgent = $opts['user-agent'] .' mixed-content-scan';
  } else {
      $userAgent = 'mixed-content-scan';
  }


  //get_header();

  if( isset($_POST['sbtMixedContent']) &&  isset($_POST['urlWebsite']) && !empty($_POST['urlWebsite']) ){

  ?>
  <style>
  .resultatsScan {
      padding: 5px;
      border: 1px solid #eee;
      background: #f5f5f5;
      border-radius: 5px;
      line-height: 30px;
  }
  span.detectedMixedContent {
      color: #d44747;
  }
  </style>
  <?php

    // Go for it!
    try {
        $args = $opts = array(
          'rootUrl' => $_POST['urlWebsite'],
          'input' => false,
          'format' => 'json',
          'userAgent' => 'mixed-content-scan',
        );
      //  echo '<div class="resultatsScan">';
      //  echo '<span class="urlScanned"> Scan du site : ' .$opts['rootUrl'] .'</span>';
        $scanner = new \Bramus\MCS\Scanner($opts['rootUrl'], $logger, (array) $ignorePatterns);
        $scanner->setCrawl($crawl);
        $scanner->setTimeout($timeout);
        $scanner->setCheckCertificate($checkCertificate);
        $scanner->setUserAgent($userAgent);
        if (sizeof($urlsToQueue) > 0) $scanner->queueUrls($urlsToQueue);
        $res = '';
        passthru( $scanner->scan() , $res);
  //      echo '</div>';

    } catch(\Exception $e) {
        echo 'error ' . $e;
        exit(1);
    }


  }
  else{
    // display form :

    ?>
    <style>
    form#formCheckWebsite input[type="submit"] {
      width: 50%;
      margin: 40px auto;
      display: block;
      text-transform: uppercase;
  }

  form#formCheckWebsite input {
      width: 100%;
      margin: 5px auto;
      padding: 15px;
  font-size: 22px;
  }
    </style>
    <script>
    function check_url(){
    //Get input value
    var elem = document.getElementById("urlWebsite");
    var input_value = elem.value;
  //Set input value to lower case so HTTP or HtTp become http
  input_value = input_value.toLowerCase();

    //Check if string starts with http:// or https://
    var regExr = /^(http:|https:)\/\/.*$/m;

    //Test expression
    var result = regExr.test(input_value);

  //If http:// or https:// is not present add http:// before user input
    if (!result){
    var new_value = "https://"+input_value;
    elem.value=new_value;
    }


    }
    function getResult(){
      jQuery.post(
        ajaxurl,
        {
            'action': 'mon_action',
            'param': 'coucou'
        },
        function(response){
                console.log(response);
            }
        );


        return false;
    }


    </script>
    <form method="POST" action="" id="formCheckWebsite" onsubmit=" return getResult(); ">

      <input type="url" value="" name="urlWebsite" id="urlWebsite" placeholder="Url de votre site avec https://" />
      <input type="submit" value="Checker votre site" name="sbtMixedContent" onclick="check_url()"/>


    <?php

  }
}
function add_js_scripts_2() {
	wp_enqueue_script( 'script', plugins_url( '/scanCheck.js', __FILE__ ), array('jquery'), '1.0', true );


  // pass Ajax Url to script.js
	wp_localize_script('script', 'ajaxurl', admin_url( 'admin-ajax.php' ) );

}
add_action('wp_enqueue_scripts', 'add_js_scripts_2' , 2);


add_shortcode( 'SCAN_MIXED_CONTENT', 'SCAN_MIXED_CONTENT_FN' );


function SCAN_MIXED_CONTENT_FN(){
  /**
   * mixed-content-scan - A CLI Script to crawl+scan HTTPS-enabled websites for Mixed Content.
   * @author Bramus! <bramus@bram.us>
   */
  // Error settings
  error_reporting(E_ERROR);
  ini_set('display_errors', 'on');
  // Require autoloader
  if (file_exists(__DIR__ . '/mixed-content-scan-master/vendor/autoload.php')) { // Installed Locally
      require __DIR__ . '/mixed-content-scan-master/vendor/autoload.php';
  } elseif (file_exists(__DIR__ . '/../../mixed-content-scan-master/autoload.php')) { // Installed Globally
      require __DIR__ . '/../../mixed-content-scan-master/autoload.php';
  } else {
      exit('Make sure you run `composer install` first, before running this scanner');
  }


  $argv = array(
    'rootUrl' =>'https://vincent-guesne.fr'
  );
  // Define CLI Options/Arguments
  $cli = new \Garden\Cli\Cli();
  /*$cli->description('Scan your HTTPS-enabled website for Mixed Content.')
      ->opt('loglevel', 'The Monolog loglevel to log at. Defaults to 200.', false)
      ->opt('output', 'Stream to write to. Defaults to `php://stdout`', false)
      ->opt('format', 'Output format to use. Allowed values: `ansi`, `no-ansi`, or `json`. Defaults to `ansi`', false)
      ->opt('no-crawl', 'Don\'t crawl scanned pages for new pages.', false)
      ->opt('no-check-certificate', 'Don\'t check the certificate for validity.', false)
      ->opt('timeout', 'How long to wait for each request to complete. Defaults to 10000ms.', false, 'integer')
      ->opt('input', 'Specify a file containing a list of links as the source, instead of parsing the passed in URL. Automatically enables `--no-crawl`', false)
      ->opt('ignore', 'File containing URL patterns to ignore. See readme shipping with release on how to build this file.', false)
      ->opt('user-agent', 'Set the user agent to be used when crawling', false)
      ->arg('rootUrl', 'The URL to start scanning at', false);*/
  // Parse and return cli options
  //$opts = $cli->parse($argv, true)->getOpts();
  //$args = $cli->parse($argv, true)->getArgs();

  $args = $opts = array(
    'rootUrl' =>'https://vincent-guesne.fr',
    'input' => false,
    'format' => 'json'
  );
  // Determine numerical log level
  if (isset($opts['loglevel']) && !is_int($opts['loglevel'])) {
      $levels = \Monolog\Logger::getLevels();
      if (array_key_exists(strtoupper($opts['loglevel']), $levels)) {
          $opts['loglevel'] = $levels[ strtoupper($opts['loglevel']) ];
      }
  }
  $loglevel = isset($opts['loglevel']) ? (int) $opts['loglevel'] : 200;
  // Create logger writing to the specified output
  $logger = new \Monolog\Logger('MCS');
  $handler = new \Monolog\Handler\StreamHandler((isset($opts['output']) ? $opts['output'] : 'php://stdout'), $loglevel);
  // Define formatter to use
  if (!isset($opts['format'])) $opts['format'] = 'ansi';
  switch($opts['format']) {
      case 'no-ansi':
          $formatter = new \Monolog\Formatter\LineFormatter(null, null, false, true);
          break;
      case 'json':
          $formatter = new \Monolog\Formatter\JsonFormatter();
          break;
      case 'ansi':
      default:
          $formatter = new \Bramus\Monolog\Formatter\ColoredLineFormatter(null, null, null, false, true);
          break;
  }
  // Link formatter to logger
  $handler->setFormatter($formatter);
  $logger->pushHandler($handler);
  // Define the rootURL and/or the list of links to scan
  $urlsToQueue = [];
  /*
  if (isset($opts['input'])) {
      // Set the rootUrl to the wildcard
      $rootUrl = '*';
      // Open the file and make sure it's readable
      try {
          $fi = new \SplFileObject($opts['input']);
      } catch(\Exception $e) {
        echo 'foo33';
          $logger->addError('Please make sure the file containing the list of links passed in via `--input` exists and is readable.');
          exit();
      }
      if (!$fi->isFile() || !$fi->isReadable()) {
        echo 'foo22';
          $logger->addError('Please make sure the file containing the list of links passed in via `--input` exists and is readable.');
          exit();
      }
      // Loop the contents and queue all URLs
      foreach ($fi as $link) {
          if (parse_url(trim($link)) && (trim($link) != '')) $urlsToQueue[] = trim($link);
      }
      // Make sure `--no-crawl` is set when working with `--input-file`
      $opts['no-crawl'] = true;
      // Give a notice if we have ignored any passed in rootUrl
      if (isset($args['rootUrl'])) $logger->addNotice('Using an input-file as source. Ignoring the passed in $rootUrl');
  } else {
      if (!isset($args['rootUrl']) || !parse_url($args['rootUrl'])) {
        echo 'foo';
          $cli->writeHelp();
          // $logger->addError('Please pass the URL to scan (rootUrl) as the 1st argument to this script. E.g. `mixed-content-scan $url`');
          exit();
      }
      $rootUrl = $args['rootUrl'];
  }
  */
  // Define the ignore patterns
  $ignorePatterns = [];
  if (isset($opts['ignore'])) {
      // Open the file and make sure it's readable
      try {
          $fi = new \SplFileObject($opts['ignore']);
      } catch(\Exception $e) {
        echo 'foo';
          $logger->addError('Please make sure the file containing the ignore patterns passed in via `--ignore` exists and is readable.');
          exit();
      }
      if (!$fi->isFile() || !$fi->isReadable()) {
        echo 'foo2';
          $logger->addError('Please make sure the file containing the ignore patterns passed in via `--ignore` exists and is readable.');
          exit();
      }
      // Loop the contents and extract all patterns
      foreach ($fi as $pattern) {
          if ((strlen(trim($pattern)) > 0) && (substr($pattern, 0, 1) != '#')) $ignorePatterns[] = trim($pattern);
      }
  }
  // Do we need to crawl or not?
  if (isset($opts['no-crawl'])) {
      $crawl = false;
  } else {
      $crawl = true;
  }
  // Do we need to check the certificate or not?
  if (isset($opts['no-check-certificate'])) {
      $checkCertificate = false;
  } else {
      $checkCertificate = true;
  }
  // Set the timeout value for each request
  if (isset($opts['timeout'])) {
      $timeout = $opts['timeout'];
      if (!(is_numeric($timeout) && $timeout > 0 && $timeout == round($timeout, 0))) {
          $timeout = 10000;
          $logger->addNotice('Invalid timeout value specified. Using default value of 10000ms.');
      }
  } else {
      $timeout = 10000;
  }
  // Set the user agent to use when crawling
  if (isset($opts['user-agent'])) {
      $userAgent = $opts['user-agent'] .' mixed-content-scan';
  } else {
      $userAgent = 'mixed-content-scan';
  }


  get_header();

  if( isset($_POST['sbtMixedContent']) &&  isset($_POST['urlWebsite']) && !empty($_POST['urlWebsite']) ){

  ?>
  <style>
  .resultatsScan {
      padding: 5px;
      border: 1px solid #eee;
      background: #f5f5f5;
      border-radius: 5px;
      line-height: 30px;
  }
  span.detectedMixedContent {
      color: #d44747;
  }
  </style>
  <?php

    // Go for it!
    try {
        $args = $opts = array(
          'rootUrl' => $_POST['urlWebsite'],
          'input' => false,
          'format' => 'json',
          'userAgent' => 'mixed-content-scan',
        );
        echo '<div class="resultatsScan">';
        echo '<span class="urlScanned"> Scan du site : ' .$opts['rootUrl'] .'</span>';
        $scanner = new \Bramus\MCS\Scanner($opts['rootUrl'], $logger, (array) $ignorePatterns);
        $scanner->setCrawl($crawl);
        $scanner->setTimeout($timeout);
        $scanner->setCheckCertificate($checkCertificate);
        $scanner->setUserAgent($userAgent);
        if (sizeof($urlsToQueue) > 0) $scanner->queueUrls($urlsToQueue);
        $res = '';
        passthru( $scanner->scan() , $res);
        echo '</div>';

    } catch(\Exception $e) {
        echo 'error ' . $e;
        exit(1);
    }


  }
  else{
    // display form :

    ?>
    <style>
    form#formCheckWebsite input[type="submit"] {
      width: 50%;
      margin: 40px auto;
      display: block;
      text-transform: uppercase;
  }

  form#formCheckWebsite input {
      width: 100%;
      margin: 5px auto;
      padding: 15px;
  font-size: 22px;
  }
    </style>
    <script>
    function check_url(){
    //Get input value
    var elem = document.getElementById("urlWebsite");
    var input_value = elem.value;
  //Set input value to lower case so HTTP or HtTp become http
  input_value = input_value.toLowerCase();

    //Check if string starts with http:// or https://
    var regExr = /^(http:|https:)\/\/.*$/m;

    //Test expression
    var result = regExr.test(input_value);

  //If http:// or https:// is not present add http:// before user input
    if (!result){
    var new_value = "https://"+input_value;
    elem.value=new_value;
    }


    }
    function getResult(){
      jQuery.post(
        ajaxurl,
        {
            'action': 'mon_action',
            'param': 'coucou'
        },
        function(response){
                console.log(response);
            }
        );


        return false;
    }


    </script>
    <form method="POST" action="" id="formCheckWebsite" onsubmit=" return getResult(); ">

      <input type="url" value="" name="urlWebsite" id="urlWebsite" placeholder="Url de votre site avec https://" />
      <input type="submit" value="Checker votre site" name="sbtMixedContent" onclick="check_url()"/>

    </form>

    <div id="results">

    </div>
    <?php

  }
}
