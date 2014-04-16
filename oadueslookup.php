<?php
/*
 * Plugin Name: OA Dues Lookup
 * Plugin URI: https://github.com/justdave/oadueslookup
 * Description: Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues
 * Version: 1.0
 * Author: Dave Miller
 * Author URI: http://twitter.com/justdavemiller
 * Author Email: github@justdave.net
 * */

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 David D. Miller
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

add_action( 'admin_menu', 'oadueslookup_plugin_menu' );
add_action( 'parse_request', 'oadueslookup_url_handler' );
register_activation_hook( __FILE__, 'oadueslookup_install' );

global $oadueslookup_db_version;
$oadueslookup_db_version = 1;

function oadueslookup_create_table($ddl) {
    global $wpdb;
    $table = "";
    if (preg_match("/create table\s+(\w+)\s/i", $ddl, $match)) {
        $table = $match[1];
    } else {
        return false;
    }
    foreach ($wpdb->get_col("SHOW TABLES",0) as $tbl ) {
        if ($tbl == $table) {
            return true;
        }
    }
    // if we get here it doesn't exist yet, so create it
    $wpdb->query($ddl);
    // check if it worked
    foreach ($wpdb->get_col("SHOW TABLES",0) as $tbl ) {
        if ($tbl == $table) {
            return true;
        }
    }
    return false;
}

function oadueslookup_install() {
    /* Reference: http://codex.wordpress.org/Creating_Tables_with_Plugins */

    global $wpdb;
    global $oadueslookup_db_version;

    $dbprefix = $wpdb->prefix . "oalm_";

    //
    // CREATE THE TABLES IF THEY DON'T EXIST
    //

    // This code checks if each table exists, and creates it if it doesn't.
    // No checks are made that the DDL for the table actually matches,
    // only if it doesn't exist yet. If the columns or indexes need to
    // change it'll need update code (see below).

    $sql = "CREATE TABLE ${dbprefix}dues_data (
  bsaid            MEDIUMINT(10) NOT NULL,
  max_dues_year    VARCHAR(4),
  dues_paid_date   DATE,
  level            VARCHAR(12),
  reg_audit_result VARCHAR(15),
  PRIMARY KEY (bsaid)
);";
    oadueslookup_create_table( $sql );

    //
    // DATABSE UPDATE CODE
    //

    // Check the stored database schema version and compare it to the version
    // required for this version of the plugin.  Run any SQL updates required
    // to bring the DB schema into compliance with the current version.
    // If new tables are created, you don't need to do anything about that
    // here, since the table code above takes care of that.  All that needs
    // to be done here is to make any required changes to existing tables.
    // Don't forget that any changes made here also need to be made to the DDL
    // for the tables above.

    $installed_version = get_option("oadueslookup_db_version");
    if (empty($installed_version)) {
        // if we get here, it's a new install, and the schema will be correct
        // from the initialization of the tables above, so make it the
        // current version so we don't run any update code.
        $installed_version = $oadueslookup_db_version;
        add_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }

    // insert next database revision update code immediately above this line.
    // don't forget to increment $oadueslookup_db_version at the top of the file.

    if ($installed_version < $oadueslookup_db_version ) {
        // updates are done, update the schema version to say we did them
        update_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }
}

function oadueslookup_user_page( &$wp ) {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    ob_start();
?>
<style type="text/css"><!--
.oalm_dues_table {
  border: 1px solid black;
  border-collapse: collapse;
}
.oalm_dues_table tr td {
  border: 1px solid black;
  padding: 5px;
}
.oalm_dues_table tr td.oalm_value {
  text-align: center;
  vertical-align: middle;
  padding: 5px;
}
.oalm_dues_table tr td.oalm_desc {
  text-align: center;
  vertical-align: top;
  padding: 5px;
}
.oalm_dues_table tr th {
  border: 1px solid black;
  text-align: right;
  padding: 5px;
}
.oalm_dues_good {
  color: green;
}
.oalm_dues_bad {
  color: red;
}
--></style>
<?php
    if ( isset($_POST['bsaid']) ) {
        $bsaid = $_POST['bsaid'];
        if (preg_match('/^\d+$/', $bsaid)) {
            $results = $wpdb->get_row($wpdb->prepare("SELECT max_dues_year, dues_paid_date, level, reg_audit_result FROM ${dbprefix}dues_data WHERE bsaid = %d", array($bsaid)));
            if (!isset($results)) {
?>
<div class="oalm_dues_bad"><p>Your BSA Member ID <?php echo htmlspecialchars($bsaid) ?> was not found.</p></div>
<p>This can mean any of the following:</p>
<ul>
<li>You mistyped your ID</li>
<li>You are not a member of the lodge.</li>
<li>You have never paid dues.</li>
<li>(most likely) We don't have your BSA Member ID on your record or have the incorrect ID on your record.</li>
</ul>
<br><br><br>
<?php
            } else {
                $max_dues_year = $results->max_dues_year;
                $dues_paid_date = $results->dues_paid_date;
                $level = $results->level;
                $reg_audit_result = $results->reg_audit_result;
?>
<table class="oalm_dues_table">
<tr><th>BSA Member ID</th><td class="oalm_value"><?php echo htmlspecialchars($bsaid) ?></td><td class="oalm_desc"></td></tr>
<tr><th>Dues Paid Thru</th><td class="oalm_value">12/31/<?php echo htmlspecialchars($max_dues_year) ?></td><td class="oalm_desc"><?php
                $thedate = getdate();
                if ($max_dues_year >= $thedate['year']) {
?><span class="oalm_dues_good">Your dues are current.</span><?php
                } else {
?><span class="oalm_dues_bad">Your dues are not current.</span><br><a href="http://www.michiganscouting.org/Event.aspx?id=11755">Pay your dues online.</a><?php
                }
?></td></tr>
<tr><th>Your current honor/level</th><td class="oalm_value"><?php echo htmlspecialchars($level) ?></td><td class="oalm_desc"></td></tr>
<tr><th>BSA Membership Status</th><td class="oalm_value"><?php echo htmlspecialchars($reg_audit_result) ?></td><td class="oalm_desc"></td></tr>
</table>
<?php
            }
        } else {
?>
<div class="oalm_dues_bad"><p>Invalid BSA Member ID entered, please try again.</p></div>
<?php
        }
?>
<?php
    }
?>
<p>Enter your BSA Member ID to check your current dues status.</p>
<form method="POST" action="">
<label for="bsaid">BSA Member ID:</label> <input id="bsaid" name="bsaid" type="text" size="9">
<input type="submit" value="Go">
</form>
<?php
    return ob_get_clean();
}

function oadueslookup_url_handler( &$wp ) {
    global $oadueslookup_body;
    if($wp->request == 'dues') {
        # http://stackoverflow.com/questions/17960649/wordpress-plugin-generating-virtual-pages-and-using-theme-template
        add_action('template_redirect', 'oadueslookup_template_redir');
        $oadueslookup_body = oadueslookup_user_page($wp);
        add_filter('the_posts', 'oadueslookup_dummypost');
        remove_filter('the_content', 'wpautop');
    }
}

function oadueslookup_dummypost($posts) {
    // have to create a dummy post as otherwise many templates
    // don't call the_content filter
    global $wp, $wp_query, $oadueslookup_body;

    //create a fake post intance
    $p = new stdClass;
    // fill $p with everything a page in the database would have
    $p->ID = -1;
    $p->post_author = 1;
    $p->post_date = current_time('mysql');
    $p->post_date_gmt =  current_time('mysql', $gmt = 1);
    $p->post_content = $oadueslookup_body;
    $p->post_title = 'Dues';
    $p->post_excerpt = '';
    $p->post_status = 'publish';
    $p->ping_status = 'closed';
    $p->post_password = '';
    $p->post_name = 'dues_page'; // slug
    $p->to_ping = '';
    $p->pinged = '';
    $p->modified = $p->post_date;
    $p->modified_gmt = $p->post_date_gmt;
    $p->post_content_filtered = '';
    $p->post_parent = 0;
    $p->guid = get_home_url('/' . $p->post_name); // use url instead?
    $p->menu_order = 0;
    $p->post_type = 'page';
    $p->post_mime_type = '';
    $p->comment_status = 'closed';
    $p->comment_count = 0;
    $p->filter = 'raw';
    $p->ancestors = array(); // 3.6

    // reset wp_query properties to simulate a found page
    $wp_query->is_page = TRUE;
    $wp_query->is_singular = TRUE;
    $wp_query->is_home = FALSE;
    $wp_query->is_archive = FALSE;
    $wp_query->is_category = FALSE;
    unset($wp_query->query['error']);
    $wp->query = array();
    $wp_query->query_vars['error'] = '';
    $wp_query->is_404 = FALSE;

    $wp_query->current_post = $p->ID;
    $wp_query->found_posts = 1;
    $wp_query->post_count = 1;
    $wp_query->comment_count = 0;
    // -1 for current_comment displays comment if not logged in!
    $wp_query->current_comment = null;
    $wp_query->is_singular = 1;

    $wp_query->post = $p;
    $wp_query->posts = array($p);
    $wp_query->queried_object = $p;
    $wp_query->queried_object_id = $p->ID;
    $wp_query->current_post = $p->ID;
    $wp_query->post_count = 1;

    return array($p);
}

function oadueslookup_template_redir() {
    # By including the "dues" subtag here, if someone wanted to re-theme this
    # page, they could, by creating a "page-dues.php" templates file. Otherwise
    # it just uses page.php.
    get_template_part('page', 'dues');
    # we're done, because the above should have displayed the entire page, so
    # quit instead of letting WP try to display it again.
    exit;
}

function oadueslookup_plugin_menu() {
    add_options_page( 'OA Dues Lookup', 'OA Dues Lookup', 'manage_options', 'oadueslookup', 'oadueslookup_options' );
}

function oadueslookup_options() {

    global $wpdb;

    $dbprefix = $wpdb->prefix . "oalm_";
    $hidden_field_name = 'oalm_submit_hidden';

    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    // =========================
    // form processing code here
    // =========================


    // ============================
    // screens and forms start here
    // ============================

    //
    // MAIN SETTINGS SCREEN
    //

    echo '<div class="wrap">';

    // header

    echo "<h2>" . __( 'OA Dues Lookup Settings', 'oadueslookup' ) . "</h2>";

    // settings form

    echo "<p>This is a settings page placeholder.</p>";

    echo "</div>";
} // END OF SETTINGS SCREEN

