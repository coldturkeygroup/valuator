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
$permalink = get_permalink();
$content = wp_strip_all_tags(apply_filters('the_content',get_the_content()));

// Get the background image
if( has_post_thumbnail( $id )
	? $img = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'full' )
	: $img = ''
);

// Get the page colors
$color_setting = get_post_meta( $id , 'primary_color' , true );
$color_theme = of_get_option('primary_color');
$hover_setting = get_post_meta( $id , 'hover_color' , true);
$hover_theme = of_get_option('secondary_color');

if( $color_setting && strlen( $color_setting ) > 0 && $color_setting != '' ) {
	$primary_color = $color_setting;
} elseif( $color_theme && strlen( $color_theme ) > 0 && $color_theme != '' ) {
	$primary_color = $color_theme;
}

if( $hover_setting && strlen( $hover_setting ) > 0 && $hover_setting != '' ) {
	$hover_color = $hover_setting;
} elseif( $hover_theme && strlen( $hover_theme ) > 0 && $hover_theme != '' ) {
	$hover_color = $hover_theme;
}

?>
<style>
.valuation-page {
	background: url(<?php echo $img[0]; ?>) no-repeat scroll center center;
	background-size: cover;
}
<?php
if( $primary_color != null ) {
	echo '
	.valuation-page .btn-primary {
		background-color: ' . $primary_color . ' !important;
		border-color: ' . $primary_color . ' !important; }
	.valuation-page .valuation-value h4 {
		color: ' . $primary_color . ' !important; }
	.valuation-page .valuation-value h4 small {
		color: ' . $primary_color . ' !important; }
	';
}
if( $hover_color != null ) {
	echo '
	.valuation-page .btn-primary:hover,
	.valuation-page .btn-primary:active {
		background-color: ' . $hover_color . ' !important;
		border-color: ' . $hover_color . ' !important; }
	';
}
?>
</style>

<div id="content" class="valuation-page">
	<div class="container-fluid">
		<div class="row">
			<div class="col-xs-10 col-xs-offset-1 well well-sm" id="step-one-well">
				<h4 style="text-align: center;" class="landing-title"><?php echo $title; ?></h4>
				<h3 style="text-align: center;"><?php echo $content; ?></h3>

				<form id="step-one">
					<div class="row">
						<div class="col-xs-12 col-sm-10">
							<div class="form-group">
								<input class="form-control" required="required" placeholder="Enter Your Address" name="address" type="text" id="address">
							</div>
						</div>
						<div class="col-xs-12 col-sm-2">
							<div class="form-group">
								<input class="form-control" placeholder="Unit #" name="address_2" type="text" id="address_2">
							</div>
						</div>
					</div>
					<input name="action" type="hidden" value="valuator_step_one">
					<?php wp_nonce_field( 'valuator_step_one', 'valuator_nonce' ); ?>
					<input class="btn btn-primary btn-lg btn-block" type="submit" value="GET THE VALUE">
				</form>
			</div>
			
			<div class="col-xs-10 col-xs-offset-1 well well-sm" id="step-two-well" style="display:none;">
				<h4 style="text-align: center;" class="landing-title">We Found a Valuation for Your Home!</h4>
				<h3 style="text-align: center;">Where can we send you your <strong>FREE</strong> report?</h3>
				
				<div class="row">
					<div class="col-xs-10 col-xs-offset-1">
						<div id="map_canvas"></div>
					</div>
				</div>
				
				<form id="step-two">
					<div class="row">
						<div class="col-xs-12 col-sm-6">
							<div class="form-group">
								<input class="form-control" required="required" placeholder="Your First Name" name="first_name" type="text" id="first_name">
							</div>
						</div>
						<div class="col-xs-12 col-sm-6">
							<div class="form-group">
								<input class="form-control" required="required" placeholder="Your Last Name" name="last_name" type="text" id="last_name">
							</div>
						</div>
					</div>
					<div class="form-group">
						<input class="form-control" required="required" placeholder="Your Email Address" name="email" type="text" id="email">
					</div>
					<input name="page_id" type="hidden" value="<?php echo $id; ?>">
					<input name="permalink" type="hidden" value="<?php echo $permalink; ?>">
					<input name="property_id" id="property_id" type="hidden" value="">
					<input name="action" type="hidden" value="valuator_step_two">
					<?php wp_nonce_field( 'valuator_step_two', 'valuator_nonce' ); ?>
					<input class="btn btn-primary btn-lg btn-block" type="submit" value="GET THE FREE REPORT">
				</form>
			</div>
			
			<div class="col-xs-10 col-xs-offset-1 well well-sm" id="step-three-well" style="display:none;">
				<div class="row">
					<div class="col-xs-12 col-sm-3 col-sm-offset-1 col-md-2 col-md-offset-3 valuation-value">
						<h4 class="range"><small class="low"></small></h4>
						<p>Low Estimate</p>
					</div>
					<div class="col-xs-12 col-sm-3 col-md-2 valuation-value">
						<h4 class="estimated-value"></h4>
						<p>Estimated Value</p>
					</div>
					<div class="col-xs-12 col-sm-3 col-md-2 valuation-value">
						<h4 class="range"><small class="high"></small></h4>
						<p>High Estimate</p>
					</div>
				</div>
				<h3 style="text-align: center;">Valuation for: <span class="valuation-address"></span></h3>
				
				<div class="row">
					<div class="col-xs-10 col-xs-offset-1 col-sm-6 col-sm-offset-3 page-media">
						
					</div>
				</div>
				
				<form id="step-three">
					<div class="form-group">
						<input class="form-control" required="required" placeholder="Enter Your Address" name="address" type="text" id="address">
					</div>
					<div class="form-group">
						<input class="form-control" placeholder="Unit #" name="unit" type="text" id="unit">
					</div>
					<input name="action" type="hidden" value="valuator_step_three" />
					<?php wp_nonce_field( 'valuator_step_three', 'valuator_nonce' ); ?>
					<input class="btn btn-primary btn-lg btn-block" type="submit" value="GET THE VALUE">
				</form>
			</div>
		</div>
	</div>
</div>

<?php get_footer(); ?>