<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// The parameters
if (isset($_REQUEST['category'])) {
        $category=strip_tags($_REQUEST['category']);
} else {
        die("The category is missing !");
}

if (isset($_REQUEST['last'])) {
        $_REQUEST['last'] = (int) $_REQUEST['last'];
        if ($_REQUEST['last'] <= 1) { $last=1; }
        elseif ($_REQUEST['last'] >= 100) { $last=100; }
        else $last=$_REQUEST['last'];
} else { $last=20; }

// The debug mode
$debug=0;
if($debug==1) {
        echo "<h1>Debug mode activated.</h1>";
}

// The database connection
$ts_mycnf = parse_ini_file("/data/project/ash-dev/replica.my.cnf");

try {
        $bdd = new PDO('mysql:host=commonswiki.labsdb;dbname=commonswiki_p', $ts_mycnf['user'], $ts_mycnf['password']);
} catch (Exception $e) {
        die('Error: ' . $e->getMessage());
}

// The query
$category = trim(str_replace (" " , "_", $category));
$sql = "SELECT /* SLOW_OK */ 
                page.page_title, 
                image.img_timestamp, 
                oldimage.oi_timestamp, 
                image.img_user_text, 
                image.img_minor_mime, 
                image.img_width, 
                image.img_height,
                image.img_metadata      
        FROM image
        CROSS JOIN page ON image.img_name = page.page_title 
        CROSS JOIN categorylinks ON page.page_id = categorylinks.cl_from
        LEFT JOIN oldimage ON image.img_name = oldimage.oi_name AND oldimage.oi_timestamp = (SELECT MIN(o.oi_timestamp) FROM oldimage o WHERE o.oi_name = image.img_name)   
        WHERE categorylinks.cl_to=\"$category\" 
                AND IF(oldimage.oi_timestamp IS NULL, img_timestamp, oldimage.oi_timestamp) 
        ORDER BY img_timestamp DESC LIMIT {$last};" ;

if ($debug) { echo "<p>Request: ".$sql."</p>"; }

try {
        $result = $bdd->query($sql);

        // The handling
        $pics = array();
        while ($data = $result->fetch()) {
                $thePic                 = array();
                $thePic["filename"]     = $data['page_title'];
                $thePic["author"]       = $data['img_user_text'];
                $thePic["timestamp"]    = $data['img_timestamp'];
                $thePic["filetype"]     = $data['img_minor_mime'];
                $thePic["width"]        = $data['img_width'];
                $thePic["height"]       = $data['img_height'];
                $thePic["metadata"]     = $data['img_metadata'];
                $pics[]                 = $thePic;
        }
} catch (PDOException $e) {
        die($e->getMessage());
}

$returnArray=json_encode($pics);

// The output
$header = "Content-Type: application/json";
header($header);
echo $returnArray;

// The end
$result->closeCursor();
