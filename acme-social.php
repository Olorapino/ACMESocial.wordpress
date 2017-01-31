<?php
/*
Plugin Name: ACME - SOCIAL
Description: This plugin allows you to integrate ACME-Social features to your blog.<strong> DISCLAIMER: </strong>You shall warn your users that this plugin may collect data from their navigation such their email, or what do they read. This data may be processed by third parties for stadistical or commertial purposes.
Version: 0.0.0
Author: Miguel A Olivero
Text Domain: ACME-Social
Domain Path: /languages/

*/
$host = "http://acmesocial.herokuapp.com";

// Añade la funcionalidad de los filtros y acciones al WP
add_action( 'wp_ajax_nopriv_commentsvote_ajaxhandler', 'commentsvote_ajaxhandler' );
add_action( 'wp_ajax_commentsvote_ajaxhandler', 'commentsvote_ajaxhandler' );
add_filter('comment_text', commentsvote_comment_text);
//add_filter('get_comment_author', addAuthorScore);
add_action('admin_menu', 'commentvote_create_menu');
add_action('wp_enqueue_scripts', voteme_enqueuescripts);
add_filter( 'pre_comment_approved' , 'filter_handler' , '99', 2 );
add_action( 'plugins_loaded', load_plugins );
add_filter('get_comment_author', 'comment_author_display_name');

// Añade los JS para que puedan ser utilizados en el plugin.
define('VOTECOMMENTSURL', WP_PLUGIN_URL."/".dirname( plugin_basename( __FILE__ ) ) );
define('VOTECOMMENTPATH', WP_PLUGIN_DIR."/".dirname( plugin_basename( __FILE__ ) ) );

function load_plugins() {
	load_plugin_textdomain( 'ACME-Social', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

function voteme_enqueuescripts() {
    wp_enqueue_script('votecomment', VOTECOMMENTSURL.'/js/commentsvote.js', array('jquery'));
	wp_localize_script( 'votecomment', 'votecommentajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

function hasASAccount($email){
	$app_id = get_option('app_id');
	$url = $GLOBALS["host"]."/security/".$app_id."/user/".$current_user;
	$response = wp_remote_get( $url,  array( 'timeout' => 15 ) );
	//die(print_r($url,true));
	$body = wp_remote_retrieve_body( $response );
	return json_decode($body)->active;
}

function say($this,$print=null){
	
	$translation = __( $this, 'ACME-Social' );
	
	if($print!=null){
		print_r($translation);
	}
	return $translation;
}

function hasBeenVoted($current_commentID){
	$app_id = get_option('app_id');
	$email_of_logged_user = wp_get_current_user()->user_email;
	$url = $GLOBALS["host"]."/rating/".$app_id."/from/".$email_of_logged_user."/reason/".$current_commentID;
	$query = wp_remote_get( $url,  array( 'timeout' => 15 ) );
	$body = wp_remote_retrieve_body( $query );
	//die(print_r(json_decode($body)->rated."AQIO",true));
	return json_decode($body)->rated=="true";
}

function getVotes($current_commentID){
	$app_id = get_option('app_id');
	$url = $GLOBALS["host"]."/rating/".$app_id."/reason/".$current_commentID;
	$response = wp_remote_get( $url,  array( 'timeout' => 15 ) );
	//die(print_r($url,true));
	$body = wp_remote_retrieve_body( $response );
	return json_decode($body)->count;
}

function getUserScore($current_user = ""){
	if($current_user == ""){
		$current_user = wp_get_current_user()->user_email;
	}
	$app_id = get_option('app_id');
	$url = $GLOBALS["host"]."/rating/".$app_id."/user/".$current_user;
	$response = wp_remote_get( $url,  array( 'timeout' => 15 ) );
	//die(print_r($url,true));
	$body = wp_remote_retrieve_body( $response );
	return json_decode($body)->score;
	
}

function postAction($content,$email="_" ){
		$app_id = get_option('app_id');
		if($email=="_"){
			$email=wp_get_current_user()->user_email;
		}
		$url = $GLOBALS["host"]."/user/".$email."/from/".$app_id."";
	
		wp_remote_post( $url, array('body' => array( 'mainData' => $content ) ));
}

function rateComment($current_commentID,$alignment,$comment_author_email){
	$app_id = get_option('app_id');
	$email_of_logged_user = wp_get_current_user()->user_email;
	
	wp_remote_post( $GLOBALS["host"]."/rating", array(
						'body' => array( 'action' => $alignment, 'starValue' => '1', 'target' =>$comment_author_email, 'nonce'=>$current_commentID, 'app'=>$app_id, 'origin'=>$email_of_logged_user )));
}

// Modify apparience of comment in order to add Rate buttons
function commentsvote_comment_text($content) {
	update_option("require_name_email",1,true);
    return $content.commentsvote_showlink();
}

function commentsvote_showlink() {
    $nonce = wp_create_nonce("commentsvote_nonce");
    $current_commentID =  get_comment_ID();
	
	if(is_user_logged_in()){
		$app_id = get_option('app_id');
		postAction("VIEWED ".$current_commentID." @ ".$app_id);
	}
	
	if(hasBeenVoted($current_commentID)){	
        $completelink = '<div class="commentlink" >'.getVotes($current_commentID).' '.say("Votes").' <a href="#"></a></div>';
	}elseif(( is_user_logged_in())) {
	
        $arguments_up = $current_commentID.",'".$nonce."','up'";
		$upButton='<img src="'.VOTECOMMENTSURL.'/images/up-arrow-circle-hi.png" >';
        $upVote = ' <a onclick="commentsvote_add('.$arguments_up.');">'.$upButton.'</a>';
		
		$downButton='<img src="'.VOTECOMMENTSURL.'/images/up-arrow-circle-lo.png" >';
		$arguments_down = $current_commentID.",'".$nonce."','down'";
		$downVote = ' <a onclick="commentsvote_add('.$arguments_down.');">'.$downButton.'</a>';
		
        $completelink = '<div id="commentsvote-'.$current_commentID.'">';
        $completelink .= '<span>'.getVotes($current_commentID).' '.say("Votes").' </span><br><span>'.$upVote.'  '.$downVote.'</span>';
        $completelink .= '</div>';
	}else {
		$register_link = site_url('wp-login.php', 'login') ;
		$completelink = '<div class="commentlink" >'." <a href=".$register_link.">".getVotes($current_commentID)." ".say("Votes")."</a>".'</div>';
	}
    return $completelink;
}


function comment_author_display_name($author) {
    global $comment;
    if (!empty($comment->user_id)){

		$user=get_userdata($comment->user_id);
		$author='['.getUserScore($user->user_email).'] '.$user->display_name;    

	}else if(!empty($comment->comment_author_email)){
		$author='['.getUserScore($comment->comment_author_email).'] '.$comment->comment_author_email;    
	}

    return $author;
}
/*
function addAuthorScore(){
	if (strlen(comment_author())>0){
		return "g!".;
	}else{
		return "ANNO";
	}
}*/

// This functions is called through AJAX to post comment data.
function commentsvote_ajaxhandler() {
    if ( !wp_verify_nonce( $_POST['nonce'], "commentsvote_nonce")) {
        exit("Something Wrong");
    }
 
    $results = '';
    global $wpdb;
	global $user_email;
    if( get_option('commentvotelogin') != 'yes' || is_user_logged_in() ) {
  
        $commentid = $_POST['commentid'];
		$alignment = $_POST['alignment'];
		$current_comment_email = get_comment_author_email($commentid);
	
		rateComment($commentid,$alignment,$current_comment_email);

        $results .= '<div class="votescore">'.say("Thanks for voting!").'</div>';
    }

    die($results);
}

// A esta función se le llama cuando se va a insertar un comentario nuevo.
function filter_handler( $approved , $commentdata ){

	$span_rate = "0";
	$publish_rate = "0";
	if(get_option('span_rate')!=""){
		$span_rate = get_option('span_rate');
	};
	if(get_option('publish_rate')!=""){
		$publish_rate = get_option('publish_rate');
	};
	
	postAction("COMMENTED: ".$commentdata["comment_content"],$commentdata["comment_author_email"]);
	
	if(hasASAccount($commentdata["comment_author_email"])!="true"){
		return 0;
	}

	//postAction($commentdata["comment_content"]);
	if( get_option('autopublish') == 'yes' ){
		
		
		if(getUserScore()<$span_rate){
			//die(print_r("A",true));
			return "spam";
		}else if(getUserScore()>=$publish_rate){
			//die(print_r("B".$span_rate." ".$publish_rate." ".getUserScore(),true));
			return $approved;
		}else{
			//die(print_r("C".$span_rate." ".$publish_rate." ".getUserScore(),true));
			return 0;
		}
	}else{
		//die(print_r("D",true));
		return $approved;
	}

}


// Settings

function commentvote_create_menu() {
    add_submenu_page('options-general.php',say('Comments\' Vote'),say('Comments\' Vote'),'manage_options', __FILE__.'comments_settings_page','comments_settings_page');
}
function comments_settings_page() {
?>
    <div class="wrap">
    <?php
	
    if( isset( $_POST['commentvotesubmit'] ) ) {
        update_option( 'commentvotelogin' , $_POST[ 'commentvotelogin' ] );
		update_option( 'app_id' , $_POST[ 'app_id' ] );
		update_option( 'autopublish' , $_POST[ 'autopublish' ] );
		update_option( 'publish_rate' , $_POST[ 'publish_rate' ] );
		update_option( 'span_rate' , $_POST[ 'span_rate' ] );
    }
    ?>
        <div id="commentvotesetting">
            <form id='commentvotesettingform' method="post" action="">
                <h1><?php echo say('Settings'); ?></h1>
				<?php say("Place here your ACME-Social identifier:",1);?> <input type='text' size="38" maxlength="32" name='app_id' value='<?php if( get_option('app_id') != '' ) echo get_option('app_id');?>'><br>
				<br/>
				<h1><?php echo say('Privacy'); ?></h1>
				<p><?php echo say("text1"); ?></p>
				<p><?php echo say("text2"); ?></p>

				<br>
				<h1><input type = 'checkbox' Name ='autopublish' value= 'yes' <?php if( get_option('autopublish') == 'yes' ) echo 'checked';?> ><?php echo say('Comments filter'); ?></h1>
				<?php say("If enabled, comments will be filtered by using user score.",1);?>
                <br/>
				<br/>
				<?php say("Spam comment if user score is LOWER THAN:",1); ?> <input size="6" maxlength="4" type="number" step="1" name='span_rate' value='<?php if( get_option('span_rate') != '' ){ echo get_option('span_rate');}else{echo '0';}?>'> <?php say("(Default: 0)(This rule is checked first)",1); ?><br>
				<?php say("Autopublish comment if user has a score GREATER OR EQUALS TO:",1); ?> <input type="number" size="6" step="1" maxlength="4" name='publish_rate' value='<?php if( get_option('publish_rate') != '' ) {echo get_option('publish_rate');}else{echo '0';}?>'><?php say("(Default: 0)",1); ?> <br>
				
				<p class="submit">
                <input type="submit" id="commentvotesubmit" name="commentvotesubmit" class="button-primary" value="<?php say("Save",1); ?>" />
                </p>
            </form>
        </div>
    </div>
<?php }


// Add fields after default fields above the comment box, always visible

add_action( 'comment_form_logged_in_after', 'additional_fields' );
add_action( 'comment_form_after_fields', 'additional_fields' );

function additional_fields () {

$string = '<p>'.say("Powered by").'<a href="http://acmesocial.herokuapp.com/"> ACME-Social*</a></p>';
$disclaimer = '<p><a href="http://acmesocial.herokuapp.com/legal">'.say("Submitting this comment you accept the Terms and Conditions").'</a></p>';
	echo $disclaimer.$string;

}

?>