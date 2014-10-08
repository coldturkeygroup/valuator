<?php
/**
 * Template file for displaying single home valuation page
 *
 * @package WordPress
 * @subpackage Home Valuator
 * @author Aaron Huisinga
 * @since 1.0.0
 */

global $valuator, $wp_query;

get_header();
the_post();
$id = get_the_ID();
$title = get_the_title();
$content = wp_strip_all_tags(apply_filters('the_content',get_the_content()));

// Get the background image
if( has_post_thumbnail( $id )
	? $img = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'full' )
	: $img = ''
);

?>
<style>
.valuation-page {
	background: url(<?php echo $img[0]; ?>) no-repeat scroll center center;
	background-size: cover;
}
</style>

<div id="content" class="valuation-page">
	<div class="container-fluid">
		<div class="row">
			<div class="col-xs-10 col-xs-offset-1 well well-sm">
				<h4 style="text-align: center;" class="landing-title"><?php echo $title; ?></h4>
				<h3 style="text-align: center;"><?php echo $content; ?></h3>

				<form id="step-one">
					<div class="form-group">
						<input class="form-control" required="required" placeholder="Enter Your Address" name="address" type="text" id="address">
					</div>
					<div class="form-group">
						<input class="form-control" placeholder="Unit #" name="unit" type="text" id="unit">
					</div>
					<input name="action" type="hidden" value="valuator_step_one" />
					<?php wp_nonce_field( 'valuator_step_one', 'valuator_nonce' ); ?>
					<input class="btn btn-primary btn-lg btn-block" type="submit" value="GET THE VALUE">
				</form>
			</div>
		</div>
	</div>
</div>

<?php get_footer(); ?>