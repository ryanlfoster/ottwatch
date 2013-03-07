<?php

include("../../lib/include.php");

DevelopmentAppController::scanDevApps();
#DevelopmentAppController::injestApplication('__13234');

class DevelopmentAppController {

  static public function scanDevApps() {

    print "scanDevApps: loading default page of results\n";

    # get dev-apps sorted by status update.
    # results are sorted with oldtest date first, so then jump to last page, and start scanning backwards
    # until no dates on page are "new"
    #$html = file_get_contents('http://app01.ottawa.ca/postingplans/searchResults.jsf?lang=en&newReq=true&action=sort&sortField=objectCurrentStatusDate&keyword=.');
    #file_put_contents("t.html",$html);
    $html = file_get_contents("t.html");

    # parse out all of the pages of results
    $lines = explode("\n",$html);
    $add = 0;
    $span = "";
    foreach ($lines as $l) {
      if (preg_match("/span/",$l)) {
        if ($add) {
          $span = $l;
          break;
        }
      }
      if (preg_match("/searchpaging/",$l)) {
        $add = 1;
      }
    }

    $data = explode("<a",$span);
    $pages = array();
    foreach ($data as $d) {
      if (preg_match('/page=(\d+)"/',$d,$match)) {
        $pages[$match[1]] = 1;
      }
    }
    $pages = array_keys($pages);
    $pages = array_reverse($pages);

    # obtain all search results until a page has no relatively new DevApps
    foreach ($pages as $p) {
      print "scanDevApps: loading results page $p\n";
      $url="http://app01.ottawa.ca/postingplans/searchResults.jsf?lang=en&action=sort&sortField=objectCurrentStatusDate&keyword=.&page=$p";
      #$html = file_get_contents($url);
      #file_put_contents("p.html",$html);
      $html = file_get_contents("p.html");
      $lines = explode("\n",$html); 
      foreach ($lines as $l) {
        # <a href="appDetails.jsf;jsessionid=D49D6B525184BD8711CED3AFDE61A2D2?lang=en&appId=__866MYU" class="app_applicationlink">D01-01-12-0006           </a>
        $matches = array();
        if (preg_match('/appDetails.jsf.*appId=([^"]+)".*>(D[^ <]+)/',$l,$matches)) {
          $appid = $matches[1];
          $devid = $matches[2];
          #self::injestApplication($appid);
#          $url = "http://app01.ottawa.ca/postingplans/appDetails.jsf?lang=en&appId=$appid";
#          $html = file_get_contents($url);
#          injestApplication
#          #file_put_contents("a.html",$html);
#          exit;
        }
        if (preg_match('/<td class="subRowGray15">(.*)</',$l,$matches)) {
          $statusdate = $matches[1];
          $statusdate = strftime("%Y-%m-%d",strtotime($statusdate));
          $app = getDatabase()->one(" select id,date(statusdate) statusdate from devapp where appid = :appid ",array("appid"=>$appid));
          $action = '';
          if ($app['id']) {
            if ($app['statusdate'] != $statusdate) {
              self::injestApplication($appid,'update');
            }
          } else {
            self::injestApplication($appid,'insert');
          }
        }
      }
      exit;
    }

  }

  static function injestApplication ($appid,$action) {
    print "injestApplication($appid,$action)\n";
    return;
    $url = "http://app01.ottawa.ca/postingplans/appDetails.jsf?lang=en&appId=$appid";
    $html = file_get_contents($url);
    #file_put_contents("a.html",$html);
    #$html = file_get_contents("a.html");
    $html = preg_replace("/&nbsp;/"," ",$html);
    $html = preg_replace("/\r/"," ",$html);
    $lines = explode("\n",$html);

		$labels = array();
		$labels['Application #'] = '';
		$labels['Date Received'] = '';
		#$labels['Address'] = '';
		$labels['Ward'] = '';
		$labels['Application'] = '';
		$labels['Review Status'] = '';
		$labels['Status Date'] = '';
		#$labels['Description'] = '';

    $label = '';
    $value = '';
    for ($x = 0; $x < count($lines); $x++) {
      if (preg_match('/div.*class="label"/',$lines[$x])) {
        $x++;
        $label = self::suckToNextDiv($lines,$x);
      }
      if (preg_match('/div.*class="appDetailValue"/',$lines[$x])) {
        $x++;
        $value = self::suckToNextDiv($lines,$x);
        if (array_key_exists($label,$labels)) {
          $labels[$label] = $value;
        }
      }
    }

    $labels['status_date'] = strftime('%Y-%m-%d',strtotime($labels['Status Date']));
    unset($labels['Status Date']);
    $labels['date_received'] = strftime('%Y-%m-%d',strtotime($labels['Date Received']));
    unset($labels['Date Received']);

    getDatabase()->execute(" delete from devapp where appid = :appid ",array("appid"=>$appid));
    getDatabase()->execute(" 
      insert into devapp 
      (appid,devid,ward,apptype,status,statusdate,receiveddate)
      values
      (:appid,:devid,:ward,:apptype,:status,:statusdate,:receiveddate)",array(
        'devid'=> $labels['Application #'],
        'appid'=> $appid,
        'ward' => $labels['Ward'],
        'apptype' => $labels['Application'],
        'status' => $labels['Review Status'],
        'statusdate' => $labels['status_date'],
        'receiveddate' =>$labels['date_received'],
    ));

#  id mediumint not null auto_increment,
#  appid varchar(10),
#  ward varchar(100),
#  apptype varchar(100),
#  status varchar(100),
#  statusdate datetime,
#  receiveddate datetime,
#  created datetime,
#  updated datetime,
#  primary key (id)

  }

  static function suckToNextDiv ($lines,$x) {
        $snippet = '';
        while (!preg_match('/div>/',$lines[$x])) {
          $snippet .= $lines[$x];
          $x++;
        }
        $snippet = preg_replace("/:/","",$snippet);
        $snippet = preg_replace("/ +/"," ",$snippet);
        $snippet = preg_replace("/^\s+/","",$snippet);
        $snippet = preg_replace("/\s$/","",$snippet);
        return $snippet;
  }


  
}

?>
