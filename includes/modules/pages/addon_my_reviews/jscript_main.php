<?php
/**
 * My Reviews Page Template JavaScript
 *
 * Loaded automatically by index.php?main_page=my_reviews.<br />
 * Displays a list of products the customer has bought from the website
 * as well as any of the customer's ratings and reviews on each of those products.
 * @package addon_review_reminder
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: addon_my_reviews.php 2017-07-12 08:00 v.1.0 $
 * @author Will Vasconcelos willvasconcelos@outlook.com $
 */
?>
<script type="text/javascript"><!--
	/* MAKE SURE JQUERY LOADS */
	document.addEventListener("DOMContentLoaded", function(event) { 
		if (!window.jQuery) {
			var script = document.createElement("SCRIPT");
			script.src = 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js';
			script.type = 'text/javascript';
			document.getElementsByTagName("head")[0].appendChild(script);
		}
	});
	
	function show_rating(pid, rating){
		/* UPDATE STARS */
		update_stars(pid, rating);
		/* UPDATE LABEL */
		switch(rating){
			case 1:
				$("#" + pid + '-lblRating').html("I hate it");
				break;
			case 2:
				$("#" + pid + '-lblRating').html("I don't like it");
				break;
			case 3:
				$("#" + pid + '-lblRating').html("It's okay");
				break;
			case 4:
				$("#" + pid + '-lblRating').html("I like it");
				break;
			case 5:
				$("#" + pid + '-lblRating').html("I love it");
				break;
		}
	}
	
	function hide_rating( pid ){
		/* GET CURRENT RATING (IF ANY) */
		var rating = 0;
		if( $("#" + pid + "-rating").val() != '' ){
			rating = $("#" + pid + "-rating").val();
		}
		/* LOAD STARS */
		update_stars(pid, rating);
		$("#" + pid + '-lblRating').html("");
	}
	
	function update_rating( pid, rating=0 ){
		var stars = '';
		var feedback = '';
		var review = '';
		
		/* LOAD CUSTOMER NAME */
		name = $("#customer_name").val();
		
		/* LOAD RATING */
		if( rating > 0 && rating <= 5 ){
			$("#" + pid + "-rating").val(rating); //update hidden field
		}else{
			rating = $("#" + pid + "-rating").val(); //retrieve value
		}
		
		/* LOAD REVIEW */
		review = $("#" + pid + "-review").val(); //GET TEXT AREA'S TEXT
		
		/* LOAD STARS */
		stars = get_stars(rating);
		
		/* LOAD FEEDBACK */
		if( review != '' ){
			feedback  = '<span class="feedback">';
			feedback += '<span class="feedback-pending">Pending review.</span> ' + name + ' | ';
		}else{
			feedback  = '<span class="feedback">';
			feedback += '<span class="feedback-posted">&#10004; posted publicly as</span> ' + name + ' | ';
		}
		
		feedback += '<span onclick="change_rating(' + pid + ');" class="clear-rating" id="change-' + pid + '">Change</span>';
		feedback += '</span>';
		
		/* ENFORCE MINIMUM CHARACTERS IN REVIEW */
<?php
	$min_length = 0;
	$short_review_message = '';
	$sql = "SELECT `configuration_value`
			FROM `" . TABLE_CONFIGURATION . "`
			WHERE `configuration_key` = 'REVIEW_TEXT_MIN_LENGTH'";
	$rec = $db->Execute($sql);
	if(!$rec->EOF){
		$min_length = $rec->fields['configuration_value'];
		$short_review_message = sprintf(SHORT_REVIEW_MESSAGE, $min_length);
	}
?>
		var short_review_message = '<?php echo $short_review_message; ?>';
		if( review.length > 0 ){
			if( review.length < <?php echo $min_length; ?> && short_review_message != '' ){
				alert( short_review_message );
				return;
			}
		}
		
		/* SHOW RESULTS */
		$('#' + pid + '-stars').html(stars + feedback);
		/* show add review textbox */
		$("#" + pid + '-review').css("display","block");
		
		/* UPDATE THE DATABASE */
		var myReview = {
			"customer_name": name,
			"product_id": pid,
			"rating": rating,
			"review": review,
			"action": 'add'
		};
		var myJsonReview = JSON.stringify(myReview);
		$.ajax({
			type: "POST",
			url: "addon_my_reviews.php", 
			data: myJsonReview,
			success: function( result ){
				if( result.indexOf("expired") > 0 ){ //IF SESSION EXPIRED
					window.location='index.php?main_page=time_out';
				}
				$("#ajax-feedback").html(result); //SHOW ANY ERROR MESSAGE HERE
				if( review != '' ){
					$("#" + pid + "-review").css("display", "none"); //HIDE TEXT AREA
					$("#btnSave-" + pid).css("display", "none"); //HIDE SAVE BUTTON
					$("#" + pid + "-stars").append('<div class="txt-review" id="txt-review-' + pid + '">' + review + '</div>');
				}else{
					$("#btnSave-" + pid).css("display", "none"); //HIDE SAVE BUTTON
				}
			}
		});
	}
	
	function review_status(cmp, pid){
		if( cmp.value != '' ){
			$("#btnSave-" + pid).css("display","block");
		}
	}
	
	function change_rating( pid ){
		var rating = $("#" + pid + "-rating").val();
		var stars = '';
		for( var i=1; i <= rating; i++ ){
			stars += '<img src="<?php echo DIR_WS_TEMPLATE; ?>images/rating_star_on.png" id="' + pid + '-' + i + '" onmouseover="show_rating(' + pid + ', ' + i + ');" onmouseout="hide_rating(\'' + pid + '\');" onclick="update_rating(\'' + pid + '\', ' + i + ');" /> ';
		}
		for( i; i <= 5; i++ ){
			stars += '<img src="<?php echo DIR_WS_TEMPLATE; ?>images/rating_star.png" id="' + pid + '-' + i + '" onmouseover="show_rating(' + pid + ', ' + i + ');" onmouseout="hide_rating(\'' + pid + '\');" onclick="update_rating(\'' + pid + '\', ' + i + ');" /> ';
		}
		stars += '<span id="' + pid + '-lblRating" class="lblRating"></span>';
		stars = '<div id="' + pid + '-stars">' + stars + '</div>';
		
		/* UPDATE UI */
		$("#" + pid + "-stars").html( stars );
		$("#" + pid + "-review").css("display","block");
		$("#txt-review-" + pid).html('');
	}
	
	function get_empty_rating( pid ){
		var stars = '';
		for( var i=1; i <= 5; i++ ){
			stars += '<img src="<?php echo DIR_WS_TEMPLATE; ?>images/rating_star.png" id="' + pid + '-' + i + '" onmouseover="show_rating(' + pid + ', ' + i + ');" onmouseout="hide_rating(\'' + pid + '\');" onclick="update_rating(\'' + pid + '\', ' + i + ');" /> ';
		}
		stars += '<span id="' + pid + '-lblRating" class="lblRating"></span>';
		return '<div id="' + pid + '-stars">' + stars + '</div>';
	}
	
	function update_stars(pid, rating){
		for(var i=1; i <= rating; i++){
			$('#' + pid + '-' + i).attr("src","<?php echo DIR_WS_TEMPLATE; ?>images/rating_star_on.png");
		}
		for(i; i <= 5; i++){
			$('#' + pid + '-' + i).attr("src","<?php echo DIR_WS_TEMPLATE; ?>images/rating_star.png");
		}
	}
	
	function get_stars(rating){
		var stars = '';
		for(var i=1; i <= rating; i++){
			stars += '<img src="<?php echo DIR_WS_TEMPLATE; ?>images/rating_star_on.png" /> ';
		}
		for(i=rating; i < 5; i++){
			stars += '<img src="<?php echo DIR_WS_TEMPLATE; ?>images/rating_star.png" /> ';
		}
		return stars;
	}
	
	function reminder_signup(){
		var action = '';
		
		if( $("#cbxOptIn").is(":checked") ){
			action = 'signup';
		}else{
			action = 'optout';
		}
		
		/* UPDATE THE DATABASE */
		var reminder = {
			"action": action
		};
		var myJsonReview = JSON.stringify( reminder );
		$.ajax({
			type: "POST",
			url: "addon_my_reviews.php", 
			data: myJsonReview,
			success: function( result ){
				if( result.indexOf("expired") > 0 ){ //IF SESSION EXPIRED
					window.location='index.php?main_page=time_out';
				}
				$("#ajax-feedback").html(result); //SHOW ANY ERROR MESSAGE HERE
			}
		});
	}
	
	function switch_hide_review(btn){
		if( btn.value == '<?php echo BTN_SHOW_REVIEWED; ?>' ){
			btn.value = '<?php echo BTN_HIDE_REVIEWED; ?>';
			$(".reviewed-item").css("display","table-row");
		}else{
			btn.value = '<?php echo BTN_SHOW_REVIEWED; ?>';
			$(".reviewed-item").css("display","none");
		}
	}
//--></script>