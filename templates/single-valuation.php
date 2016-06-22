<?php
/**
 * Template file for displaying single home valuation page
 *
 * @package    WordPress
 * @subpackage Home Valuator
 * @author     Aaron Huisinga
 * @since      1.0.0
 */

global $pf_valuator, $wp_query;

$id = get_the_ID();
$title = get_the_title();
$frontdesk_campaign = get_post_meta($id, 'frontdesk_campaign', true);
$broker = get_post_meta($id, 'legal_broker', true);
$retargeting = get_post_meta($id, 'retargeting', true);
$conversion = get_post_meta($id, 'conversion', true);
$offer = get_post_meta($id, 'offer', true);
$call_to_action = get_post_meta($id, 'call_to_action', true);
$submit_offer = get_post_meta($id, 'submit_offer', true);
$phone = of_get_option('phone_number');
$hide_phone = get_post_meta($id, 'hide_phone', true);
$img = '';

if ($hide_phone == '' || $hide_phone == null) {
    $hide_phone = false;
}

// Get the background image
if (has_post_thumbnail($id))
    $img = wp_get_attachment_image_src(get_post_thumbnail_id($id), 'full');

// Get the page colors
$color_setting = get_post_meta($id, 'primary_color', true);
$color_theme = of_get_option('primary_color');
$hover_setting = get_post_meta($id, 'hover_color', true);
$hover_theme = of_get_option('secondary_color');

if ($color_setting && strlen($color_setting) > 0 && $color_setting != '') {
    $primary_color = $color_setting;
} elseif ($color_theme && strlen($color_theme) > 0 && $color_theme != '') {
    $primary_color = $color_theme;
}

if ($hover_setting && strlen($hover_setting) > 0 && $hover_setting != '') {
    $hover_color = $hover_setting;
} elseif ($hover_theme && strlen($hover_theme) > 0 && $hover_theme != '') {
    $hover_color = $hover_theme;
}

?>
    <!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="utf-8">
        <title><?php wp_title('&middot;', true, 'right'); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">
        <?php wp_head(); ?>
        <style>
            .single-pf_valuator {
                background: url(<?= $img[0]; ?>) no-repeat scroll center center;
                background-size: cover;
                background-attachment: fixed;
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
                .valuation-page h3.step-two-subtitle strong {
                    color: ' . $primary_color . ' !important; }
                .valuation-page h4.thank-you {
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
        <link rel="alternate" type="application/rss+xml" title="<?= get_bloginfo('name'); ?> Feed" href="<?= esc_url(get_feed_link()); ?>">
        <!--[if lt IE 9]>
        <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <script src="assets/js/respond.min.js"></script>
        <![endif]-->
    </head>

<body <?php body_class(); ?>>
<div class="valuation-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xs-10 col-xs-offset-1 col-sm-12 col-sm-offset-0 col-md-8 col-md-offset-2 well well-sm" id="step-one-well" data-model="stepOne">
                <h4 style="text-align: center;" class="landing-title"><?= $title; ?></h4>

                <form id="step-one" data-remote="true" data-remote-on-success="process">
                    <div class="row">
                        <div class="col-xs-12 col-sm-10">
                            <div class="form-group">
                                <input class="form-control" required="required" placeholder="Enter Your Address" name="address"
                                       type="text" id="address">
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-2">
                            <div class="form-group">
                                <input class="form-control" placeholder="Unit #" name="address_2" type="text" id="address_2">
                            </div>
                        </div>
                    </div>
                    <input name="action" type="hidden" value="pf_valuator_step_one">
                    <?php wp_nonce_field('pf_valuator_step_one', 'pf_valuator_nonce'); ?>
                    <input class="btn btn-primary btn-lg btn-block disabled" type="submit" disabled="disabled" value="Get The Value!">
                </form>
            </div>

            <div class="col-xs-10 col-xs-offset-1 col-sm-12 col-sm-offset-0 col-md-8 col-md-offset-2 well well-sm" id="step-two-well" data-model="stepTwo" style="display:none;">
                <h4 style="text-align: center;" class="landing-title">We've Calculated Your Home's Value!</h4>

                <h3 style="text-align: center;" class="step-two-subtitle">Where can we send your <strong>FREE</strong>
                    report?</h3>

                <div class="row">
                    <div class="col-xs-12 col-sm-10 col-sm-offset-1">
                        <div id="map_canvas"></div>
                    </div>
                </div>

                <form id="step-two" data-remote="true" data-remote-on-success="process">
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
                    <input name="page_id" type="hidden" value="<?= $id; ?>">
                    <input name="frontdesk_campaign" type="hidden" value="<?= $frontdesk_campaign ?>">
                    <input name="property_id" id="property_id" type="hidden" value="">
                    <input name="action" type="hidden" value="pf_valuator_step_two">
                    <?php wp_nonce_field('pf_valuator_step_two', 'pf_valuator_nonce'); ?>
                    <input class="btn btn-primary btn-lg btn-block" type="submit" value="Send Me The Report!">
                </form>
            </div>

            <div class="col-xs-10 col-xs-offset-1 well well-sm" id="step-three-well" style="display:none;">
                <div class="row valuation-result">
                    <div class="col-xs-12 col-sm-4 col-md-2 col-md-offset-2 valuation-value">
                        <h4 class="range">
                            <small class="low"></small>
                        </h4>
                        <p>Low Estimate</p>
                    </div>
                    <div class="col-xs-12 col-sm-4 valuation-value">
                        <h4 class="estimated-value"></h4>

                        <p>Estimated Value</p>
                    </div>
                    <div class="col-xs-12 col-sm-4 col-md-2 valuation-value">
                        <h4 class="range">
                            <small class="high"></small>
                        </h4>
                        <p>High Estimate</p>
                    </div>
                </div>
                <h3 style="text-align: center;margin-bottom: 30px;margin-top: 20px;" class="step-three-subtitle">Valuation for:
                    <span class="valuation-address"></span></h3>

                <div class="row">
                    <div class="col-xs-12 col-sm-6 col-sm-offset-3 page-media">

                    </div>
                </div>

                <div class="row">
                    <div class="col-xs-10 col-xs-offset-1 page-text"></div>
                </div>

                <div class="row">
                    <div style="margin-bottom:100px" class="col-xs-8 col-xs-offset-2">
                        <button class="btn btn-primary btn-lg btn-offer" id="get-offer"><?= $call_to_action; ?></button>
                    </div>
                </div>
            </div>

            <div class="col-xs-10 col-xs-offset-1 well well-sm" id="step-four-well" style="display:none;">
                <h4 style="text-align: center;" class="landing-title thank-you">Thank You!</h4>

                <h3 style="text-align: center;font-size: 30px;" class="step-two-subtitle">I'll be in touch shortly.</h3>
            </div>
        </div>
    </div>

    <?php
    if ($retargeting != null) {
        ?>
        <!-- Facebook Pixel Code -->
        <script>
            !function (f, b, e, v, n, t, s) {
                if (f.fbq)return;
                n = f.fbq = function () {
                    n.callMethod ?
                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq)f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window,
                document, 'script', '//connect.facebook.net/en_US/fbevents.js');

            fbq('init', '<?= $retargeting ?>');
            fbq('track', "PageView");</script>
        <noscript><img height="1" width="1" style="display:none"
                       src="https://www.facebook.com/tr?id=<?= $retargeting ?>&ev=PageView&noscript=1"
            /></noscript>
        <?php
        echo '<input type="hidden" id="retargeting" value="' . $retargeting . '">';
    }

    if ($conversion != null) {
        echo '<input type="hidden" id="conversion" value="' . $conversion . '">';
    }
    ?>

    <div class="footer">
        <?php echo $broker;
        if ($phone != null && $hide_phone != true) {
            echo ' &middot; ' . $phone;
        }
        ?>
    </div>

    <div class="modal fade" id="valuator-offer" tabindex="-1" role="dialog" aria-labelledby="valuator-label" aria-hidden="true" data-model="stepThree">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <h3 style="color: #333;font-size: 20px;"><?= $offer; ?></h3>

                    <form id="step-three" data-remote="true" data-remote-on-success="process">
                        <div class="form-group">
                            <label for="first_name_3" class="control-label sr-only">First Name</label>
                            <input type="text" name="first_name_3" id="first_name_3" class="form-control disabled" disabled="disabled" placeholder="First Name">
                        </div>
                        <div class="form-group">
                            <label for="last_name_3" class="control-label sr-only">Last Name</label>
                            <input type="text" name="last_name_3" id="last_name_3" class="form-control disabled" disabled="disabled" required="required" placeholder="Last Name">
                        </div>
                        <div class="form-group">
                            <label for="email_3" class="control-label sr-only">Email Address</label>
                            <input type="text" name="email_3" id="email_3" class="form-control disabled" disabled="disabled" placeholder="Email Address">
                        </div>
                        <div class="form-group">
                            <label for="phone" class="control-label sr-only">Phone Number</label>
                            <input type="text" name="phone" id="phone" class="form-control" required="required" placeholder="Phone Number">
                        </div>

                        <input name="action" type="hidden" value="pf_valuator_step_three">
                        <input name="property_id" id="property_id_complete" type="hidden" value="">
                        <?php wp_nonce_field('pf_valuator_step_three', 'pf_valuator_nonce'); ?>

                        <input type="submit" class="btn btn-primary btn-lg btn-offer" value="<?= $submit_offer; ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<?php wp_footer(); ?>